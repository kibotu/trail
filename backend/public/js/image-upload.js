/**
 * ImageUploader - Chunked image upload with progress tracking
 * 
 * Usage:
 *   const uploader = new ImageUploader('profile', 
 *     (progress) => console.log(progress + '%'),
 *     (result) => console.log('Done!', result)
 *   );
 *   uploader.upload(fileInputElement.files[0]);
 */
class ImageUploader {
    constructor(imageType, onProgress, onComplete, onError) {
        this.imageType = imageType;
        this.onProgress = onProgress || (() => {});
        this.onComplete = onComplete || (() => {});
        this.onError = onError || ((error) => console.error(error));
        this.chunkSize = 512 * 1024; // 512KB chunks
        this.uploadId = null;
        // JWT token is now stored in httpOnly cookie - no need to pass it explicitly
    }
    
    /**
     * Validate file before upload
     */
    validateFile(file) {
        const maxSize = 20 * 1024 * 1024; // 20MB
        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/avif'
        ];
        
        if (!file) {
            throw new Error('No file selected');
        }
        
        if (file.size > maxSize) {
            throw new Error('File size exceeds 20MB limit');
        }
        
        if (!allowedTypes.includes(file.type)) {
            throw new Error('Invalid file type. Allowed: JPEG, PNG, GIF, WebP, SVG, AVIF');
        }
        
        return true;
    }
    
    /**
     * Initialize upload session
     */
    async initUpload(file, totalChunks) {
        const response = await fetch('/api/images/upload/init', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // Include httpOnly cookie with JWT
            body: JSON.stringify({
                image_type: this.imageType,
                filename: file.name,
                file_size: file.size,
                total_chunks: totalChunks
            })
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to initialize upload');
        }
        
        return await response.json();
    }
    
    /**
     * Upload a single chunk
     */
    async uploadChunk(uploadId, chunkIndex, chunkData) {
        // Convert chunk to base64
        const base64Data = await this.arrayBufferToBase64(chunkData);
        
        const response = await fetch('/api/images/upload/chunk', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // Include httpOnly cookie with JWT
            body: JSON.stringify({
                upload_id: uploadId,
                chunk_index: chunkIndex,
                chunk_data: base64Data
            })
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to upload chunk');
        }
        
        return await response.json();
    }
    
    /**
     * Complete upload and process image
     */
    async completeUpload(uploadId) {
        const response = await fetch('/api/images/upload/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin', // Include httpOnly cookie with JWT
            body: JSON.stringify({
                upload_id: uploadId
            })
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to complete upload');
        }
        
        return await response.json();
    }
    
    /**
     * Convert ArrayBuffer to base64
     */
    async arrayBufferToBase64(buffer) {
        return new Promise((resolve, reject) => {
            const blob = new Blob([buffer]);
            const reader = new FileReader();
            reader.onload = () => {
                const base64 = reader.result.split(',')[1];
                resolve(base64);
            };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }
    
    /**
     * Main upload method
     */
    async upload(file) {
        try {
            // Validate file
            this.validateFile(file);
            
            // Calculate chunks
            const totalChunks = Math.ceil(file.size / this.chunkSize);
            
            // Initialize upload
            this.onProgress(0);
            const initResponse = await this.initUpload(file, totalChunks);
            this.uploadId = initResponse.upload_id;
            
            // Upload chunks
            for (let i = 0; i < totalChunks; i++) {
                const start = i * this.chunkSize;
                const end = Math.min(start + this.chunkSize, file.size);
                const chunk = file.slice(start, end);
                
                // Read chunk as ArrayBuffer
                const chunkData = await chunk.arrayBuffer();
                
                // Upload chunk
                await this.uploadChunk(this.uploadId, i, chunkData);
                
                // Update progress
                const progress = Math.round(((i + 1) / totalChunks) * 100);
                this.onProgress(progress);
            }
            
            // Complete upload
            const result = await this.completeUpload(this.uploadId);
            this.onComplete(result);
            
            return result;
            
        } catch (error) {
            this.onError(error.message || 'Upload failed');
            throw error;
        }
    }
    
    /**
     * Cancel upload (cleanup temp files)
     */
    async cancel() {
        if (this.uploadId) {
            // Note: Server will auto-cleanup after 1 hour
            this.uploadId = null;
        }
    }
}

/**
 * Create image upload UI component
 */
function createImageUploadUI(imageType, containerId, onUploadComplete) {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return null;
    }
    
    // Create UI elements
    const html = `
        <style>
            .image-upload-container {
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            
            .upload-button {
                background: var(--bg-tertiary, #334155);
                color: var(--text-secondary, #cbd5e1);
                border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
                font-size: 1.125rem;
                flex-shrink: 0;
            }
            
            .upload-button:hover:not(:disabled) {
                background: var(--accent, #3b82f6);
                color: white;
                transform: scale(1.1);
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
            }
            
            .upload-button:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
            }
            
            .upload-preview {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            
            .upload-preview-item {
                position: relative;
                display: inline-block;
            }
            
            .upload-preview img {
                max-width: 200px;
                max-height: 200px;
                border-radius: 8px;
                border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
                display: block;
            }
            
            .upload-preview-remove {
                position: absolute;
                top: 4px;
                right: 4px;
                background: rgba(0, 0, 0, 0.7);
                color: white;
                border: none;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 0.75rem;
                transition: all 0.2s;
            }
            
            .upload-preview-remove:hover {
                background: #ef4444;
                transform: scale(1.1);
            }
            
            .upload-progress {
                flex: 1;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            
            .progress-bar-container {
                flex: 1;
                height: 4px;
                background: var(--bg-tertiary, #334155);
                border-radius: 2px;
                overflow: hidden;
            }
            
            .progress-bar {
                height: 100%;
                background: var(--accent, #3b82f6);
                transition: width 0.3s;
            }
            
            .progress-text {
                font-size: 0.875rem;
                color: var(--text-muted, #94a3b8);
                min-width: 40px;
            }
            
            .upload-error {
                color: #ef4444;
                font-size: 0.875rem;
            }
        </style>
        <div class="image-upload-container">
            <input type="file" id="${containerId}-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif" style="display: none;">
            <button type="button" id="${containerId}-button" class="upload-button" title="Upload image">
                <i class="fa-solid fa-image"></i>
            </button>
            <div id="${containerId}-preview" class="upload-preview" style="display: none;"></div>
            <div id="${containerId}-progress" class="upload-progress" style="display: none;">
                <div class="progress-bar-container">
                    <div id="${containerId}-progress-bar" class="progress-bar"></div>
                </div>
                <span id="${containerId}-progress-text" class="progress-text">0%</span>
            </div>
            <div id="${containerId}-error" class="upload-error" style="display: none;"></div>
        </div>
    `;
    
    container.innerHTML = html;
    
    // Get elements
    const input = document.getElementById(`${containerId}-input`);
    const button = document.getElementById(`${containerId}-button`);
    const preview = document.getElementById(`${containerId}-preview`);
    const progressContainer = document.getElementById(`${containerId}-progress`);
    const progressBar = document.getElementById(`${containerId}-progress-bar`);
    const progressText = document.getElementById(`${containerId}-progress-text`);
    const errorContainer = document.getElementById(`${containerId}-error`);
    
    // Store uploaded images
    const uploadedImages = [];
    
    // Function to add image preview
    function addImagePreview(imageUrl, imageId) {
        const previewItem = document.createElement('div');
        previewItem.className = 'upload-preview-item';
        previewItem.dataset.imageId = imageId;
        
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = 'Preview';
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'upload-preview-remove';
        removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        removeBtn.title = 'Remove image';
        removeBtn.onclick = () => {
            previewItem.remove();
            const index = uploadedImages.findIndex(img => img.id === imageId);
            if (index > -1) {
                uploadedImages.splice(index, 1);
            }
            if (preview.children.length === 0) {
                preview.style.display = 'none';
            }
            // Notify about removal
            if (onUploadComplete) {
                onUploadComplete({ removed: true, image_id: imageId, all_images: uploadedImages });
            }
        };
        
        previewItem.appendChild(img);
        previewItem.appendChild(removeBtn);
        preview.appendChild(previewItem);
        preview.style.display = 'flex';
    }
    
    // Create uploader instance
    const uploader = new ImageUploader(
        imageType,
        (progress) => {
            progressBar.style.width = progress + '%';
            progressText.textContent = progress + '%';
        },
        (result) => {
            progressContainer.style.display = 'none';
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-image"></i>';
            
            // Add to uploaded images array
            uploadedImages.push({ id: result.image_id, url: result.url });
            
            // Add preview of uploaded image
            addImagePreview(result.url, result.image_id);
            
            if (onUploadComplete) {
                onUploadComplete(result);
            }
        },
        (error) => {
            progressContainer.style.display = 'none';
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-image"></i>';
            errorContainer.textContent = error;
            errorContainer.style.display = 'block';
        }
    );
    
    // JWT token is now in httpOnly cookie - no need to set it manually
    
    // Button click opens file picker
    button.addEventListener('click', () => {
        input.click();
    });
    
    // File selected
    input.addEventListener('change', async () => {
        const file = input.files[0];
        if (!file) return;
        
        // Hide error
        errorContainer.style.display = 'none';
        
        // Show progress
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = '0%';
        
        // Disable button during upload
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        
        // Start upload
        try {
            await uploader.upload(file);
        } catch (error) {
            console.error('Upload failed:', error);
        }
        
        // Reset input
        input.value = '';
    });
    
    return {
        uploader,
        uploadedImages,
        clearPreviews: () => {
            preview.innerHTML = '';
            preview.style.display = 'none';
            uploadedImages.length = 0;
        },
        elements: {
            input,
            button,
            preview,
            progressContainer,
            progressBar,
            progressText,
            errorContainer
        }
    };
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ImageUploader, createImageUploadUI };
}
