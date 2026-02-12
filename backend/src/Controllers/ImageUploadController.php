<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Services\ImageService;
use Trail\Config\Config;
use InvalidArgumentException;
use RuntimeException;

/**
 * Image upload controller with chunked upload support
 */
class ImageUploadController
{
    /**
     * Initialize chunked upload session
     * POST /api/images/upload/init
     */
    public static function initUpload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = json_decode((string) $request->getBody(), true);
        
        // Validate input
        $imageType = $data['image_type'] ?? '';
        $filename = $data['filename'] ?? '';
        $fileSize = (int) ($data['file_size'] ?? 0);
        $totalChunks = (int) ($data['total_chunks'] ?? 0);
        
        if (!in_array($imageType, ['profile', 'header', 'post'], true)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid image type']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        if (empty($filename)) {
            $response->getBody()->write(json_encode(['error' => 'Filename is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        // Check file extension to determine if it's a video (for size limit)
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isVideo = in_array($extension, ['mp4', 'mov', 'webm'], true);
        $maxFileSize = $isVideo ? 50 * 1024 * 1024 : 20 * 1024 * 1024; // 50MB for videos, 20MB for images
        
        if ($fileSize <= 0 || $fileSize > $maxFileSize) {
            $limitMB = $maxFileSize / (1024 * 1024);
            $response->getBody()->write(json_encode(['error' => "File size must be between 1 byte and {$limitMB}MB"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        if ($totalChunks <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Total chunks must be greater than 0']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        // Generate upload session ID
        $uploadId = bin2hex(random_bytes(16));
        
        // Create temp directory and metadata file
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $uploadBasePath = __DIR__ . '/../../public/uploads/images';
        $tempBasePath = __DIR__ . '/../../storage/temp';
        
        $imageService = new ImageService($uploadBasePath, $tempBasePath);
        $tempPath = $imageService->getTempPath($uploadId);
        
        // Store metadata
        $metadata = [
            'upload_id' => $uploadId,
            'user_id' => $userId,
            'image_type' => $imageType,
            'filename' => $imageService->sanitizeFilename($filename),
            'file_size' => $fileSize,
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => [],
            'created_at' => time()
        ];
        
        file_put_contents($tempPath . '/metadata.json', json_encode($metadata));
        
        $response->getBody()->write(json_encode([
            'upload_id' => $uploadId,
            'chunk_size' => 512 * 1024 // 512KB
        ]));
        
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Upload a single chunk
     * POST /api/images/upload/chunk
     */
    public static function uploadChunk(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = json_decode((string) $request->getBody(), true);
        
        $uploadId = $data['upload_id'] ?? '';
        $chunkIndex = (int) ($data['chunk_index'] ?? -1);
        $chunkData = $data['chunk_data'] ?? '';
        
        if (empty($uploadId)) {
            $response->getBody()->write(json_encode(['error' => 'Upload ID is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        if ($chunkIndex < 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid chunk index']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        if (empty($chunkData)) {
            $response->getBody()->write(json_encode(['error' => 'Chunk data is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        // Load metadata
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $uploadBasePath = __DIR__ . '/../../public/uploads/images';
        $tempBasePath = __DIR__ . '/../../storage/temp';
        
        $imageService = new ImageService($uploadBasePath, $tempBasePath);
        $tempPath = $tempBasePath . '/' . $uploadId;
        $metadataPath = $tempPath . '/metadata.json';
        
        if (!file_exists($metadataPath)) {
            $response->getBody()->write(json_encode(['error' => 'Upload session not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        $metadata = json_decode(file_get_contents($metadataPath), true);
        
        // Verify ownership
        if ($metadata['user_id'] !== $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
        
        // Decode and save chunk
        $chunkBytes = base64_decode($chunkData, true);
        if ($chunkBytes === false) {
            $response->getBody()->write(json_encode(['error' => 'Invalid chunk data encoding']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $chunkPath = $tempPath . '/chunk_' . $chunkIndex;
        file_put_contents($chunkPath, $chunkBytes);
        
        // Update metadata
        if (!in_array($chunkIndex, $metadata['uploaded_chunks'], true)) {
            $metadata['uploaded_chunks'][] = $chunkIndex;
            sort($metadata['uploaded_chunks']);
            file_put_contents($metadataPath, json_encode($metadata));
        }
        
        $uploadedCount = count($metadata['uploaded_chunks']);
        $progress = (int) round(($uploadedCount / $metadata['total_chunks']) * 100);
        
        $response->getBody()->write(json_encode([
            'uploaded_chunks' => $uploadedCount,
            'total_chunks' => $metadata['total_chunks'],
            'progress' => $progress
        ]));
        
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Complete upload and process image
     * POST /api/images/upload/complete
     */
    public static function completeUpload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = json_decode((string) $request->getBody(), true);
        
        $uploadId = $data['upload_id'] ?? '';
        
        if (empty($uploadId)) {
            $response->getBody()->write(json_encode(['error' => 'Upload ID is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $uploadBasePath = __DIR__ . '/../../public/uploads/images';
        $tempBasePath = __DIR__ . '/../../storage/temp';
        
        $imageService = new ImageService($uploadBasePath, $tempBasePath);
        $tempPath = $tempBasePath . '/' . $uploadId;
        $metadataPath = $tempPath . '/metadata.json';
        
        if (!file_exists($metadataPath)) {
            $response->getBody()->write(json_encode(['error' => 'Upload session not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        $metadata = json_decode(file_get_contents($metadataPath), true);
        
        // Verify ownership
        if ($metadata['user_id'] !== $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
        
        // Verify all chunks uploaded
        if (count($metadata['uploaded_chunks']) !== $metadata['total_chunks']) {
            $response->getBody()->write(json_encode([
                'error' => 'Not all chunks uploaded',
                'uploaded' => count($metadata['uploaded_chunks']),
                'expected' => $metadata['total_chunks']
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        try {
            // Reassemble file
            $assembledPath = $tempPath . '/assembled';
            $assembledFile = fopen($assembledPath, 'wb');
            
            for ($i = 0; $i < $metadata['total_chunks']; $i++) {
                $chunkPath = $tempPath . '/chunk_' . $i;
                if (!file_exists($chunkPath)) {
                    fclose($assembledFile);
                    throw new RuntimeException('Missing chunk: ' . $i);
                }
                $chunkData = file_get_contents($chunkPath);
                fwrite($assembledFile, $chunkData);
            }
            
            fclose($assembledFile);
            
            // Validate media (image or video)
            $validation = $imageService->validateImage($assembledPath);
            
            // Determine media type and processing strategy
            $isVideo = $imageService->isVideoMimeType($validation['mime_type']);
            $isAnimatedGif = !$isVideo && $validation['mime_type'] === 'image/gif' 
                && $imageService->isAnimatedGif($assembledPath);
            
            // Generate secure filename based on media type
            $secureFilename = $imageService->generateSecureFilename(
                $userId, 
                $metadata['filename'],
                $isAnimatedGif,
                $isVideo ? $validation['mime_type'] : null
            );
            
            // Get target path
            $targetPath = $imageService->getImagePath($userId, $secureFilename);
            
            // Process media based on type
            if ($isVideo) {
                // Videos: convert MOV to MP4, copy MP4/WebM as-is
                $optimized = $imageService->processVideo(
                    $assembledPath,
                    $targetPath,
                    $validation['mime_type']
                );
                $storedMimeType = $optimized['mime_type']; // Use the output MIME type from processing
            } elseif ($isAnimatedGif) {
                // Animated GIFs are preserved as-is
                $optimized = $imageService->preserveAnimatedGif(
                    $assembledPath,
                    $targetPath,
                    $metadata['image_type']
                );
                $storedMimeType = 'image/gif';
            } else {
                // Static images are optimized and converted to WebP
                $optimized = $imageService->optimizeAndConvert(
                    $assembledPath,
                    $targetPath,
                    $metadata['image_type']
                );
                // For non-animated images, the output is WebP (except SVG)
                $storedMimeType = $validation['mime_type'] === 'image/svg+xml' 
                    ? 'image/svg+xml' 
                    : 'image/webp';
            }
            
            // Secure the uploaded file (remove execute permissions)
            $imageService->secureUploadedFile($targetPath);
            
            // Generate ETag
            $etag = $imageService->generateETag($targetPath);
            
            // Save to database
            $db = Database::getInstance($config);
            $stmt = $db->prepare("
                INSERT INTO trail_images (
                    user_id, filename, original_filename, image_type, 
                    mime_type, file_size, width, height, etag
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $secureFilename,
                $metadata['filename'],
                $metadata['image_type'],
                $storedMimeType,
                $optimized['file_size'],
                $optimized['width'],
                $optimized['height'],
                $etag
            ]);
            
            $imageId = (int) $db->lastInsertId();
            
            // Clean up temp files
            $imageService->cleanupTempPath($uploadId);
            
            $response->getBody()->write(json_encode([
                'image_id' => $imageId,
                'url' => '/uploads/images/' . $userId . '/' . $secureFilename,
                'width' => $optimized['width'],
                'height' => $optimized['height'],
                'file_size' => $optimized['file_size']
            ]));
            
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
            
        } catch (\Throwable $e) {
            // Clean up on error
            $imageService->cleanupTempPath($uploadId);
            
            error_log("Image upload completion failed: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'error' => 'Failed to process image: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Serve image with caching
     * GET /api/images/{id}
     */
    public static function serveImage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $imageId = (int) $args['id'];
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        // Get image metadata
        $stmt = $db->prepare("SELECT * FROM trail_images WHERE id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();
        
        if (!$image) {
            $response->getBody()->write(json_encode(['error' => 'Image not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        $uploadBasePath = __DIR__ . '/../../public/uploads/images';
        $tempBasePath = __DIR__ . '/../../storage/temp';
        $imageService = new ImageService($uploadBasePath, $tempBasePath);
        
        $filePath = $imageService->getImagePath((int) $image['user_id'], $image['filename']);
        
        if (!file_exists($filePath)) {
            $response->getBody()->write(json_encode(['error' => 'Image file not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        // Check ETag for 304 Not Modified
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if (!empty($ifNoneMatch) && $ifNoneMatch === $image['etag']) {
            return $response->withStatus(304);
        }
        
        // Serve image with caching headers
        $imageData = file_get_contents($filePath);
        $response->getBody()->write($imageData);
        
        // Cache for 7 days but revalidate after 24 hours using must-revalidate
        // max-age=86400 (24h) for initial cache, stale-while-revalidate=518400 (6 days) for background refresh
        return $response
            ->withHeader('Content-Type', $image['mime_type'])
            ->withHeader('Content-Length', (string) $image['file_size'])
            ->withHeader('Cache-Control', 'public, max-age=86400, stale-while-revalidate=518400') // 24h cache, 7 days total
            ->withHeader('ETag', $image['etag'])
            ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', strtotime($image['created_at'])) . ' GMT');
    }
    
    /**
     * Delete image
     * DELETE /api/images/{id}
     */
    public static function deleteImage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $imageId = (int) $args['id'];
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        // Get image metadata
        $stmt = $db->prepare("SELECT * FROM trail_images WHERE id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();
        
        if (!$image) {
            $response->getBody()->write(json_encode(['error' => 'Image not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        // Verify ownership
        if ((int) $image['user_id'] !== $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
        
        // Check if image is referenced by any entries
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM trail_entries 
            WHERE image_ids IS NOT NULL 
            AND JSON_CONTAINS(image_ids, ?)
        ");
        $stmt->execute([json_encode($imageId)]);
        $result = $stmt->fetch();
        
        if ((int) $result['count'] > 0) {
            $response->getBody()->write(json_encode([
                'error' => 'Cannot delete image: it is referenced by ' . $result['count'] . ' entry/entries',
                'referenced_count' => (int) $result['count']
            ]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
        
        // Check if image is used as profile or header image
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM trail_users 
            WHERE profile_image_id = ? OR header_image_id = ?
        ");
        $stmt->execute([$imageId, $imageId]);
        $result = $stmt->fetch();
        
        if ((int) $result['count'] > 0) {
            $response->getBody()->write(json_encode([
                'error' => 'Cannot delete image: it is used as a profile or header image',
                'in_use' => true
            ]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
        
        // Delete file
        $uploadBasePath = __DIR__ . '/../../public/uploads/images';
        $tempBasePath = __DIR__ . '/../../storage/temp';
        $imageService = new ImageService($uploadBasePath, $tempBasePath);
        $imageService->deleteImage((int) $image['user_id'], $image['filename']);
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM trail_images WHERE id = ?");
        $stmt->execute([$imageId]);
        
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
