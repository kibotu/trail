/**
 * ImageCropModal - Crop images before upload using Cropper.js
 *
 * Usage:
 *   ImageCropModal.show(file, {
 *       aspectRatio: 1,       // 1=square, 4.8=header, NaN=free
 *       outputWidth: 512,     // target pixel width
 *       onCrop: (croppedFile) => { ... },
 *       onCancel: () => { ... }
 *   });
 */
class ImageCropModal {
    /**
     * @param {File} file
     * @param {Object} opts
     * @param {number} opts.aspectRatio - 1 (square), 4.8 (header), NaN (free)
     * @param {number} opts.outputWidth - target pixel width
     * @param {Function} opts.onCrop - receives cropped File
     * @param {Function} opts.onCancel
     */
    static show(file, opts) {
        // Skip crop for SVG and animated GIFs — no reliable pixel crop
        if (file.type === 'image/svg+xml' || file.type === 'image/gif') {
            opts.onCrop(file);
            return;
        }

        if (typeof Cropper === 'undefined') {
            console.error('Cropper.js not loaded');
            opts.onCrop(file);
            return;
        }

        // Use FileReader for data URL — blob: URLs are blocked by CSP
        const reader = new FileReader();
        reader.onload = () => {
            const overlay = document.createElement('div');
            overlay.className = 'crop-modal-overlay';

            const container = document.createElement('div');
            container.className = 'crop-modal-container';

            const header = document.createElement('div');
            header.className = 'crop-modal-header';
            header.innerHTML = '<span>Crop Image</span>';

            const body = document.createElement('div');
            body.className = 'crop-modal-body';

            const img = document.createElement('img');
            img.style.maxWidth = '100%';
            img.src = reader.result;
            body.appendChild(img);

            const footer = document.createElement('div');
            footer.className = 'crop-modal-footer';

            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'button secondary';
            cancelBtn.textContent = 'Cancel';

            const cropBtn = document.createElement('button');
            cropBtn.className = 'button primary';
            cropBtn.textContent = 'Crop';

            footer.appendChild(cancelBtn);
            footer.appendChild(cropBtn);

            container.appendChild(header);
            container.appendChild(body);
            container.appendChild(footer);
            overlay.appendChild(container);
            document.body.appendChild(overlay);

            const cropper = new Cropper(img, {
                aspectRatio: opts.aspectRatio,
                viewMode: 1,
                autoCropArea: 1,
                responsive: true,
                checkOrientation: false
            });

            function destroy() {
                cropper.destroy();
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }

            cancelBtn.addEventListener('click', () => {
                destroy();
                if (opts.onCancel) opts.onCancel();
            });

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    destroy();
                    if (opts.onCancel) opts.onCancel();
                }
            });

            cropBtn.addEventListener('click', () => {
                cropBtn.disabled = true;
                cropBtn.textContent = 'Cropping...';

                // PNG (lossless) — server converts to WebP in one pass
                cropper.getCroppedCanvas({
                    width: opts.outputWidth,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high'
                }).toBlob((blob) => {
                    const croppedFile = new File([blob], file.name.replace(/\.[^.]+$/, '.png'), {
                        type: 'image/png',
                        lastModified: Date.now()
                    });
                    destroy();
                    opts.onCrop(croppedFile);
                }, 'image/png');
            });
        };
        reader.readAsDataURL(file);
    }
}
