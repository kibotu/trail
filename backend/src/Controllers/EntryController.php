<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Config\Config;
use Trail\Services\TextSanitizer;
use Trail\Services\UrlEmbedService;
use Trail\Services\IframelyUsageTracker;
use Trail\Services\HashIdService;
use Trail\Services\TwitterDateParser;
use Trail\Services\ImageService;

class EntryController
{
    public static function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        $data = json_decode((string) $request->getBody(), true);
        
        $text = $data['text'] ?? '';
        $imageIds = $data['image_ids'] ?? null;
        $media = $data['media'] ?? null;
        $rawUpload = $data['raw_upload'] ?? false;
        $createdAt = $data['created_at'] ?? null;
        $initialClaps = $data['initial_claps'] ?? null;
        $initialViews = $data['initial_views'] ?? null;

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $maxTextLength = Config::getMaxTextLength($config);
        $maxImagesPerEntry = Config::getMaxImagesPerEntry($config);
        
        // Check if raw_upload requires admin privileges
        if ($rawUpload && !$isAdmin) {
            $response->getBody()->write(json_encode([
                'error' => 'raw_upload requires admin privileges',
                'code' => 'ADMIN_REQUIRED'
            ]));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Validate total image count before processing
        $existingImageCount = is_array($imageIds) ? count($imageIds) : 0;
        $incomingMediaCount = is_array($media) ? count($media) : 0;
        if (($existingImageCount + $incomingMediaCount) > $maxImagesPerEntry) {
            $response->getBody()->write(json_encode([
                'error' => "Maximum {$maxImagesPerEntry} images per entry allowed",
                'max_images' => $maxImagesPerEntry
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Process inline media uploads if provided
        $uploadedImageIds = [];
        if (!empty($media) && is_array($media)) {
            try {
                $uploadBasePath = __DIR__ . '/../../public/uploads/images';
                $tempBasePath = __DIR__ . '/../../storage/temp';
                $imageService = new ImageService($uploadBasePath, $tempBasePath);
                
                foreach ($media as $mediaItem) {
                    if (empty($mediaItem['data']) || empty($mediaItem['filename'])) {
                        continue;
                    }
                    
                    // Decode base64 data
                    $imageData = base64_decode($mediaItem['data'], true);
                    if ($imageData === false) {
                        error_log("EntryController::create: Failed to decode base64 image data");
                        continue;
                    }
                    
                    // Save to temp file
                    $tempFile = tempnam($tempBasePath, 'upload_');
                    file_put_contents($tempFile, $imageData);
                    
                    // Generate secure filename
                    $secureFilename = $imageService->generateSecureFilename($userId, $mediaItem['filename']);
                    $targetPath = $imageService->getImagePath($userId, $secureFilename);
                    
                    // Process or save raw
                    if ($rawUpload) {
                        $result = $imageService->saveRawImage($tempFile, $targetPath);
                        $mimeType = $result['mime_type'];
                        $width = $result['width'];
                        $height = $result['height'];
                        $fileSize = $result['file_size'];
                    } else {
                        // Validate first
                        $validation = $imageService->validateImage($tempFile);
                        $mimeType = $validation['mime_type'];
                        
                        // Optimize and convert
                        $imageType = $mediaItem['image_type'] ?? 'post';
                        $optimized = $imageService->optimizeAndConvert($tempFile, $targetPath, $imageType);
                        $width = $optimized['width'];
                        $height = $optimized['height'];
                        $fileSize = $optimized['file_size'];
                    }
                    
                    // Secure the file
                    $imageService->secureUploadedFile($targetPath);
                    
                    // Generate ETag
                    $etag = $imageService->generateETag($targetPath);
                    
                    // Save to database
                    $stmt = $db->prepare("
                        INSERT INTO trail_images (
                            user_id, filename, original_filename, image_type, 
                            mime_type, file_size, width, height, etag
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $secureFilename,
                        $mediaItem['filename'],
                        $mediaItem['image_type'] ?? 'post',
                        $mimeType,
                        $fileSize,
                        $width,
                        $height,
                        $etag
                    ]);
                    
                    $uploadedImageIds[] = (int) $db->lastInsertId();
                    
                    // Clean up temp file
                    @unlink($tempFile);
                }
            } catch (\Throwable $e) {
                error_log("EntryController::create: Media upload failed: " . $e->getMessage());
                $response->getBody()->write(json_encode(['error' => 'Media upload failed: ' . $e->getMessage()]));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        }
        
        // Merge uploaded image IDs with provided image IDs
        if (!empty($uploadedImageIds)) {
            $imageIds = array_merge($imageIds ?? [], $uploadedImageIds);
        }

        // Final validation: enforce image limit after merge
        if (is_array($imageIds) && count($imageIds) > $maxImagesPerEntry) {
            $response->getBody()->write(json_encode([
                'error' => "Maximum {$maxImagesPerEntry} images per entry allowed",
                'max_images' => $maxImagesPerEntry
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validation: Either text or images must be provided
        if (empty($text) && (empty($imageIds) || !is_array($imageIds) || count($imageIds) === 0)) {
            $response->getBody()->write(json_encode(['error' => 'Either text or images are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $sanitizedText = '';
        
        // Only validate and sanitize text if provided
        if (!empty($text)) {
            // Validation: Check UTF-8 encoding (for emoji support)
            if (!TextSanitizer::isValidUtf8($text)) {
                $response->getBody()->write(json_encode(['error' => 'Invalid text encoding']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validation: Check length before sanitization
            if (mb_strlen($text) > $maxTextLength) {
                $response->getBody()->write(json_encode(['error' => "Text must be {$maxTextLength} characters or less"]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Security: Check for dangerous patterns before sanitization
            if (!TextSanitizer::isSafe($text)) {
                $response->getBody()->write(json_encode(['error' => 'Text contains potentially dangerous content']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Security: Sanitize text to remove scripts while preserving URLs and emojis
            $sanitizedText = TextSanitizer::sanitize($text);

            // Double-check length after sanitization
            if (mb_strlen($sanitizedText) > $maxTextLength) {
                $response->getBody()->write(json_encode(['error' => "Text must be {$maxTextLength} characters or less"]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        // Parse created_at date if provided
        $parsedDate = null;
        if (!empty($createdAt)) {
            $parsedDate = TwitterDateParser::parse($createdAt);
            if ($parsedDate === null) {
                $response->getBody()->write(json_encode(['error' => 'Invalid date format. Expected Twitter format: "Fri Nov 28 10:54:34 +0000 2025"']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $entryModel = new Entry($db);

        // Extract and fetch URL preview if text contains a URL (with caching)
        $urlPreviewId = null;
        try {
            // Create usage tracker for iframe.ly API
            $adminEmail = $config['production']['admin_email'] ?? 'admin@example.com';
            $usageTracker = new IframelyUsageTracker($db, $adminEmail);
            
            $embedService = new UrlEmbedService($config, $usageTracker, $db);
            if ($embedService->hasUrl($sanitizedText)) {
                $urlPreviewId = $embedService->extractAndGetPreviewId($sanitizedText);
            }
        } catch (\Throwable $e) {
            // Log error but continue - preview is optional
            error_log("EntryController::create: Preview fetch failed: " . $e->getMessage());
            $urlPreviewId = null;
        }

        $entryId = $entryModel->create($userId, $sanitizedText, $urlPreviewId, $imageIds, $parsedDate);
        $entry = $entryModel->findById($entryId, $userId);

        // Add initial claps if provided (requires admin for raw_upload mode)
        if ($initialClaps !== null) {
            $clapCount = (int) $initialClaps;
            // Allow higher max claps in raw_upload mode (for API imports)
            $maxClaps = $rawUpload ? 100000 : 50;
            if ($clapCount < 1 || $clapCount > $maxClaps) {
                error_log("EntryController::create: Invalid initial_claps value: {$clapCount}");
            } else {
                try {
                    $clapModel = new \Trail\Models\Clap($db);
                    $clapModel->addClap($entryId, $userId, $clapCount, $maxClaps);
                } catch (\Throwable $e) {
                    error_log("EntryController::create: Failed to add initial claps: " . $e->getMessage());
                }
            }
        }

        // Add initial views if provided (requires admin for raw_upload mode)
        if ($initialViews !== null && $rawUpload) {
            $viewCount = (int) $initialViews;
            if ($viewCount < 0) {
                error_log("EntryController::create: Invalid initial_views value: {$viewCount}");
            } else {
                try {
                    $viewModel = new \Trail\Models\View($db);
                    $viewModel->setViewCount('entry', $entryId, $viewCount);
                } catch (\Throwable $e) {
                    error_log("EntryController::create: Failed to set initial views: " . $e->getMessage());
                }
            }
        }

        // Create notifications for mentioned users
        if (!empty($sanitizedText)) {
            try {
                $userModel = new \Trail\Models\User($db);
                $mentionService = new \Trail\Services\MentionService($userModel);
                $notificationModel = new \Trail\Models\Notification($db);
                $notificationPrefs = new \Trail\Models\NotificationPreference($db);
                $emailService = new \Trail\Services\EmailService(
                    $config['production']['admin_email'] ?? 'admin@example.com',
                    $config['app']['base_url'] ?? 'http://localhost'
                );
                
                // Generate hash ID for the entry
                $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
                $hashIdService = new \Trail\Services\HashIdService($hashSalt);
                $hashId = $hashIdService->encode($entryId);
                
                $mentionedUserIds = $mentionService->extractMentions($sanitizedText);
                foreach ($mentionedUserIds as $mentionedUserId) {
                    if ($mentionedUserId !== $userId) { // Don't notify self
                        // Create notification
                        $notificationModel->create(
                            $mentionedUserId,
                            'mention_entry',
                            $userId,
                            $entryId,
                            null
                        );
                        
                        // Send email if user has email notifications enabled
                        if ($notificationPrefs->shouldSendEmail($mentionedUserId, 'mention_entry')) {
                            $recipient = $userModel->findById($mentionedUserId);
                            $actor = $userModel->findById($userId);
                            if ($recipient && $actor) {
                                $emailService->sendMentionNotification($recipient, $actor, $entry, $hashId);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Log error but don't fail the entry creation
                error_log("EntryController::create: Notification creation failed: " . $e->getMessage());
            }
        }

        // Fetch entry with images for response
        $entryWithImages = $entryModel->findByIdWithImages($entryId, $userId);

        $response->getBody()->write(json_encode([
            'id' => $entryId,
            'created_at' => $entry['created_at'],
            'images' => $entryWithImages['images'] ?? [],
            'clap_count' => $entry['clap_count'] ?? 0,
            'user_clap_count' => $entry['user_clap_count'] ?? 0,
        ]));
        
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public static function listPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        
        $limit = min(100, max(1, (int) ($queryParams['limit'] ?? 20)));
        $before = $queryParams['before'] ?? null;
        $searchQuery = $queryParams['q'] ?? null;

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Try to detect authenticated user (optional - doesn't require auth for listing)
        $userId = self::getOptionalUserId($request, $config);
        $excludeUserId = null;
        $excludeEntryIds = [];
        
        if ($userId) {
            // User is logged in - apply mute and hide filters
            $reportModel = new \Trail\Models\Report($db);
            $excludeUserId = $userId;
            $excludeEntryIds = $reportModel->getHiddenEntryIds($userId);
        }

        // Handle search if query provided
        $isValidSearch = false;
        if ($searchQuery !== null && trim($searchQuery) !== '') {
            // Sanitize and validate search query
            $searchQuery = \Trail\Services\SearchService::sanitize($searchQuery);
            
            if (!\Trail\Services\SearchService::isEmpty($searchQuery) && \Trail\Services\SearchService::isSafe($searchQuery)) {
                $entries = $entryModel->searchAllWithImages($searchQuery, $limit, $before, $excludeUserId, $excludeEntryIds, $userId);
                $isValidSearch = true;
            } else {
                // Invalid or unsafe query - return empty results
                $entries = [];
            }
        } else {
            // No search query - use regular listing
            $entries = $entryModel->getAllWithImages($limit, $before, null, $excludeUserId, $excludeEntryIds, $userId);
        }
        
        $hasMore = count($entries) === $limit;

        // Initialize HashIdService
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);

        // Add avatar URLs, ensure nicknames, and add hash IDs
        $userModel = new \Trail\Models\User($db);
        $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
        
        foreach ($entries as &$entry) {
            $entry['avatar_url'] = self::getAvatarUrl($entry);
            
            // Generate nickname if not set
            if (empty($entry['user_nickname']) && !empty($entry['google_id'])) {
                $entry['user_nickname'] = $userModel->getOrGenerateNickname(
                    (int) $entry['user_id'],
                    $entry['google_id'],
                    $salt
                );
            }
            
            // Add hash_id for secure permalinks
            try {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            } catch (\Throwable $e) {
                error_log("Failed to encode entry ID {$entry['id']}: " . $e->getMessage());
                // Fallback to numeric ID if encoding fails
                $entry['hash_id'] = (string) $entry['id'];
            }
        }

        // Get the cursor for the next page (created_at of the last entry)
        $nextCursor = null;
        if ($hasMore && !empty($entries)) {
            $lastEntry = end($entries);
            $nextCursor = $lastEntry['created_at'];
        }

        $responseData = [
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'limit' => $limit,
        ];
        
        // Include search query and total count in response if searching
        if ($isValidSearch && isset($searchQuery) && $searchQuery !== null && trim($searchQuery) !== '') {
            $responseData['search_query'] = $searchQuery;
            
            // Only get total count on first page (no cursor) for performance
            if ($before === null) {
                try {
                    $responseData['total_count'] = $entryModel->countSearchAll($searchQuery, $excludeUserId, $excludeEntryIds);
                } catch (\Throwable $e) {
                    error_log("EntryController::listPublic: Failed to count search results: " . $e->getMessage());
                    // Don't include total_count on error - frontend will fall back to entries.length
                }
            }
        }

        $response->getBody()->write(json_encode($responseData));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        
        $limit = min(50, max(1, (int) ($queryParams['limit'] ?? 20)));
        $before = $queryParams['before'] ?? null;

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getByUserWithImages($userId, $limit, $before, $userId);
        $hasMore = count($entries) === $limit;

        // Initialize HashIdService
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);

        // Add avatar URLs and hash IDs
        foreach ($entries as &$entry) {
            $entry['avatar_url'] = self::getAvatarUrl($entry);
            try {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            } catch (\Throwable $e) {
                error_log("Failed to encode entry ID {$entry['id']}: " . $e->getMessage());
                $entry['hash_id'] = (string) $entry['id'];
            }
        }

        // Get the cursor for the next page (created_at of the last entry)
        $nextCursor = null;
        if ($hasMore && !empty($entries)) {
            $lastEntry = end($entries);
            $nextCursor = $lastEntry['created_at'];
        }

        $response->getBody()->write(json_encode([
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'limit' => $limit,
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        $entryId = (int) $args['id'];
        $data = json_decode((string) $request->getBody(), true);

        $text = $data['text'] ?? '';
        $imageIds = $data['image_ids'] ?? null;

        // Validation: Check if text is provided
        if (empty($text)) {
            $response->getBody()->write(json_encode(['error' => 'Text is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validation: Check UTF-8 encoding (for emoji support)
        if (!TextSanitizer::isValidUtf8($text)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid text encoding']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $maxTextLength = Config::getMaxTextLength($config);
        $maxImagesPerEntry = Config::getMaxImagesPerEntry($config);

        // Validation: Check image count limit
        if (is_array($imageIds) && count($imageIds) > $maxImagesPerEntry) {
            $response->getBody()->write(json_encode([
                'error' => "Maximum {$maxImagesPerEntry} images per entry allowed",
                'max_images' => $maxImagesPerEntry
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validation: Check length before sanitization
        if (mb_strlen($text) > $maxTextLength) {
            $response->getBody()->write(json_encode(['error' => "Text must be {$maxTextLength} characters or less"]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Security: Check for dangerous patterns before sanitization
        if (!TextSanitizer::isSafe($text)) {
            $response->getBody()->write(json_encode(['error' => 'Text contains potentially dangerous content']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Security: Sanitize text to remove scripts while preserving URLs and emojis
        $sanitizedText = TextSanitizer::sanitize($text);

        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Authorization: Check if user can modify this entry
        if (!$entryModel->canModify($entryId, $userId, $isAdmin)) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized to modify this entry']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Extract and fetch URL preview if text contains a URL (with caching)
        $urlPreviewId = null;
        try {
            // Create usage tracker for iframe.ly API
            $adminEmail = $config['production']['admin_email'] ?? 'admin@example.com';
            $usageTracker = new IframelyUsageTracker($db, $adminEmail);
            
            $embedService = new UrlEmbedService($config, $usageTracker, $db);
            if ($embedService->hasUrl($sanitizedText)) {
                $urlPreviewId = $embedService->extractAndGetPreviewId($sanitizedText);
            }
        } catch (\Throwable $e) {
            // Log error but continue - preview is optional
            error_log("EntryController::update: Preview fetch failed: " . $e->getMessage());
            $urlPreviewId = null;
        }

        $success = $entryModel->update($entryId, $sanitizedText, $urlPreviewId, $imageIds);

        if ($success) {
            $entry = $entryModel->findById($entryId);
            $response->getBody()->write(json_encode([
                'success' => true,
                'updated_at' => $entry['updated_at']
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'Failed to update entry']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    public static function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        $entryId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Authorization: Check if user can modify this entry
        if (!$entryModel->canModify($entryId, $userId, $isAdmin)) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized to delete this entry']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $success = $entryModel->delete($entryId);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get a single entry by hash ID
     */
    public static function getById(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $hashId = $args['id'] ?? '';

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        
        // Initialize HashIdService with salt from config
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        
        // Decode hash to get real entry ID
        $entryId = $hashIdService->decode($hashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        // Get optional user ID for clap counts
        $userId = self::getOptionalUserId($request, $config);
        
        $entry = $entryModel->findByIdWithImages($entryId, $userId);

        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Add avatar URL
        $entry['avatar_url'] = self::getAvatarUrl($entry);

        // Generate nickname if not set
        if (empty($entry['user_nickname']) && !empty($entry['google_id'])) {
            $userModel = new \Trail\Models\User($db);
            $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
            $entry['user_nickname'] = $userModel->getOrGenerateNickname(
                (int) $entry['user_id'],
                $entry['google_id'],
                $salt
            );
        }

        // Add hash_id to response for frontend use
        $entry['hash_id'] = $hashId;

        $response->getBody()->write(json_encode($entry));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get entries by user nickname
     */
    public static function listByNickname(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $nickname = $args['nickname'] ?? null;

        if (empty($nickname)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        // Find user by nickname
        $userModel = new \Trail\Models\User($db);
        $user = $userModel->findByNickname($nickname);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Get entries for this user
        $queryParams = $request->getQueryParams();
        $limit = min(100, max(1, (int) ($queryParams['limit'] ?? 20)));
        $before = $queryParams['before'] ?? null;
        $searchQuery = $queryParams['q'] ?? null;

        // Get optional user ID for clap counts
        $userId = self::getOptionalUserId($request, $config);
        
        $entryModel = new Entry($db);
        
        // Handle search if query provided
        $isValidSearch = false;
        if ($searchQuery !== null && trim($searchQuery) !== '') {
            // Sanitize and validate search query
            $searchQuery = \Trail\Services\SearchService::sanitize($searchQuery);
            
            if (!\Trail\Services\SearchService::isEmpty($searchQuery) && \Trail\Services\SearchService::isSafe($searchQuery)) {
                $entries = $entryModel->searchByUserWithImages($user['id'], $searchQuery, $limit, $before, $userId);
                $isValidSearch = true;
            } else {
                // Invalid or unsafe query - return empty results
                $entries = [];
            }
        } else {
            // No search query - use regular listing
            $entries = $entryModel->getByUserWithImages($user['id'], $limit, $before, $userId);
        }
        
        $hasMore = count($entries) === $limit;

        // Initialize HashIdService
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);

        // Add avatar URLs and hash IDs
        foreach ($entries as &$entry) {
            $entry['avatar_url'] = self::getAvatarUrl($entry);
            try {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            } catch (\Throwable $e) {
                error_log("Failed to encode entry ID {$entry['id']}: " . $e->getMessage());
                $entry['hash_id'] = (string) $entry['id'];
            }
        }

        // Get the cursor for the next page
        $nextCursor = null;
        if ($hasMore && !empty($entries)) {
            $lastEntry = end($entries);
            $nextCursor = $lastEntry['created_at'];
        }

        $responseData = [
            'entries' => $entries,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'limit' => $limit,
            'user' => [
                'id' => $user['id'],
                'nickname' => $user['nickname'],
                'photo_url' => $user['photo_url'],
                'gravatar_hash' => $user['gravatar_hash']
            ]
        ];
        
        // Include search query and total count in response if searching
        if ($isValidSearch && isset($searchQuery) && $searchQuery !== null && trim($searchQuery) !== '') {
            $responseData['search_query'] = $searchQuery;
            
            // Only get total count on first page (no cursor) for performance
            if ($before === null) {
                try {
                    $responseData['total_count'] = $entryModel->countSearchByUser($user['id'], $searchQuery);
                } catch (\Throwable $e) {
                    error_log("EntryController::listByNickname: Failed to count search results: " . $e->getMessage());
                    // Don't include total_count on error - frontend will fall back to entries.length
                }
            }
        }

        $response->getBody()->write(json_encode($responseData));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get user avatar URL with Google photo fallback to Gravatar.
     * 
     * @param array $user User data with photo_url, gravatar_hash, and email
     * @param int $size Avatar size in pixels
     * @return string Avatar URL (already HTML-escaped for JSON output)
     */
    private static function getAvatarUrl(array $user, int $size = 96): string
    {
        // Use Google photo if available
        if (!empty($user['photo_url'])) {
            return $user['photo_url'];
        }

        // Fallback to Gravatar using hash if available
        if (!empty($user['gravatar_hash'])) {
            return "https://www.gravatar.com/avatar/{$user['gravatar_hash']}?s={$size}&d=mp";
        }

        // Fallback to Gravatar using email
        if (!empty($user['email']) || !empty($user['user_email'])) {
            $email = $user['email'] ?? $user['user_email'];
            $gravatarHash = md5(strtolower(trim($email)));
            return "https://www.gravatar.com/avatar/{$gravatarHash}?s={$size}&d=mp";
        }

        // Ultimate fallback
        return "https://www.gravatar.com/avatar/00000000000000000000000000000000?s={$size}&d=mp";
    }

    /**
     * Optionally get user ID from request without requiring authentication
     * Used for public endpoints that want to apply user-specific filters if logged in
     * 
     * @param ServerRequestInterface $request
     * @param array $config
     * @return int|null User ID if authenticated, null otherwise
     */
    private static function getOptionalUserId(ServerRequestInterface $request, array $config): ?int
    {
        // Try to get JWT token from cookie
        $cookies = $request->getCookieParams();
        $token = $cookies['trail_jwt'] ?? null;

        // If no JWT cookie, try session
        if (!$token) {
            try {
                require_once __DIR__ . '/../../public/helpers/session.php';
                $session = getAuthenticatedUser($db ?? \Trail\Database\Database::getInstance($config));
                if ($session && !empty($session['jwt_token'])) {
                    $token = $session['jwt_token'];
                }
            } catch (\Throwable $e) {
                // Session check failed - user not logged in
                return null;
            }
        }

        if (!$token) {
            return null;
        }

        // Verify JWT token
        try {
            $jwtService = new \Trail\Services\JwtService($config);
            $payload = $jwtService->verify($token);
            
            if ($payload && isset($payload['user_id'])) {
                return (int) $payload['user_id'];
            }
        } catch (\Throwable $e) {
            // Token invalid or expired - treat as not logged in
            return null;
        }

        return null;
    }
}
