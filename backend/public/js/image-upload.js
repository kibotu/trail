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
        this.jwtToken = null;
    }
    
    /**
     * Set JWT token for authentication
     */
    setToken(token) {
        this.jwtToken = token;
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
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.jwtToken}`
            },
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
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.jwtToken}`
            },
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
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.jwtToken}`
            },
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
        <div class="image-upload-container">
            <input type="file" id="${containerId}-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif" style="display: none;">
            <button type="button" id="${containerId}-button" class="upload-button">
                Choose Image
            </button>
            <div id="${containerId}-preview" class="upload-preview" style="display: none;">
                <img id="${containerId}-preview-img" src="" alt="Preview" style="max-width: 200px; max-height: 200px;">
            </div>
            <div id="${containerId}-progress" class="upload-progress" style="display: none;">
                <div class="progress-bar-container">
                    <div id="${containerId}-progress-bar" class="progress-bar" style="width: 0%; height: 4px; background: var(--accent, #3b82f6); transition: width 0.3s;"></div>
                </div>
                <span id="${containerId}-progress-text" class="progress-text">0%</span>
            </div>
            <div id="${containerId}-error" class="upload-error" style="display: none; color: #ef4444; margin-top: 8px;"></div>
        </div>
    `;
    
    container.innerHTML = html;
    
    // Get elements
    const input = document.getElementById(`${containerId}-input`);
    const button = document.getElementById(`${containerId}-button`);
    const preview = document.getElementById(`${containerId}-preview`);
    const previewImg = document.getElementById(`${containerId}-preview-img`);
    const progressContainer = document.getElementById(`${containerId}-progress`);
    const progressBar = document.getElementById(`${containerId}-progress-bar`);
    const progressText = document.getElementById(`${containerId}-progress-text`);
    const errorContainer = document.getElementById(`${containerId}-error`);
    
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
            button.textContent = 'Choose Image';
            
            // Show preview of uploaded image
            previewImg.src = result.url;
            preview.style.display = 'block';
            
            if (onUploadComplete) {
                onUploadComplete(result);
            }
        },
        (error) => {
            progressContainer.style.display = 'none';
            button.disabled = false;
            button.textContent = 'Choose Image';
            errorContainer.textContent = error;
            errorContainer.style.display = 'block';
        }
    );
    
    // Set JWT token from global variable (set by PHP template)
    if (typeof jwtToken !== 'undefined') {
        uploader.setToken(jwtToken);
    }
    
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
        button.textContent = 'Uploading...';
        
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
        elements: {
            input,
            button,
            preview,
            previewImg,
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
