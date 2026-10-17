/**
 * Victorious MARKET — Client-Side Zero-Latency Image Optimizer
 * 
 * Compresses and downscales high-resolution camera images on-device BEFORE
 * network transmission to ensure ultra-fast, secure uploads that consume minimal
 * data bundles and strip sensitive EXIF GPS metadata.
 */
(function (global) {
    'use strict';

    const ClientImageOptimizer = {
        DEFAULT_MAX_DIMENSION: 1600,
        DEFAULT_PROOF_DIMENSION: 1200,
        DEFAULT_QUALITY: 0.82,

        /**
         * Check if browser canvas supports WebP export
         */
        isWebpSupported: function () {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 1;
                canvas.height = 1;
                return canvas.toDataURL('image/webp').startsWith('data:image/webp');
            } catch (e) {
                return false;
            }
        },

        /**
         * Compress a single File or Blob on-device before upload.
         * 
         * @param {File|Blob} file 
         * @param {Object} options { maxDimension: 1600, quality: 0.82, forceJpeg: false }
         * @returns {Promise<Object>} { file, previewUrl, originalSize, optimizedSize, reductionPercent, width, height }
         */
        compressFile: function (file, options) {
            options = options || {};
            const maxDimension = options.maxDimension || this.DEFAULT_MAX_DIMENSION;
            const quality = options.quality !== undefined ? options.quality : this.DEFAULT_QUALITY;
            const originalSize = file.size || 0;

            // Non-image files (like PDFs) pass through uncompressed
            if (!file.type || !file.type.startsWith('image/')) {
                return Promise.resolve({
                    file: file,
                    previewUrl: null,
                    originalSize: originalSize,
                    optimizedSize: originalSize,
                    reductionPercent: 0,
                    width: null,
                    height: null,
                    isImage: false,
                });
            }

            const self = this;
            return new Promise(function (resolve) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = new Image();
                    img.onload = function () {
                        let width = img.naturalWidth || img.width;
                        let height = img.naturalHeight || img.height;

                        // Maintain aspect ratio while bounding to maxDimension
                        if (width > maxDimension || height > maxDimension) {
                            if (width >= height) {
                                height = Math.round((height * maxDimension) / width);
                                width = maxDimension;
                            } else {
                                width = Math.round((width * maxDimension) / height);
                                height = maxDimension;
                            }
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;

                        const ctx = canvas.getContext('2d');
                        ctx.imageSmoothingEnabled = true;
                        ctx.imageSmoothingQuality = 'high';

                        // Fill white background for transparent PNG/WebP conversions
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, width, height);
                        ctx.drawImage(img, 0, 0, width, height);

                        // Determine optimal export format
                        const useWebp = !options.forceJpeg && self.isWebpSupported();
                        const mimeType = useWebp ? 'image/webp' : 'image/jpeg';
                        const extension = useWebp ? '.webp' : '.jpg';

                        // Strip extension from original file name
                        const originalName = file.name || 'image_' + Date.now();
                        const baseName = originalName.lastIndexOf('.') !== -1
                            ? originalName.substring(0, originalName.lastIndexOf('.'))
                            : originalName;
                        const finalName = baseName + extension;

                        canvas.toBlob(function (blob) {
                            if (!blob) {
                                // Fallback to original if canvas export fails
                                resolve({
                                    file: file,
                                    previewUrl: URL.createObjectURL(file),
                                    originalSize: originalSize,
                                    optimizedSize: originalSize,
                                    reductionPercent: 0,
                                    width: img.width,
                                    height: img.height,
                                    isImage: true,
                                });
                                return;
                            }

                            const optimizedFile = new File([blob], finalName, {
                                type: mimeType,
                                lastModified: Date.now(),
                            });

                            const optimizedSize = optimizedFile.size;
                            const reduction = originalSize > 0
                                ? Math.max(0, Math.round(((originalSize - optimizedSize) / originalSize) * 100))
                                : 0;

                            const previewUrl = URL.createObjectURL(blob);

                            resolve({
                                file: optimizedFile,
                                previewUrl: previewUrl,
                                originalSize: originalSize,
                                optimizedSize: optimizedSize,
                                reductionPercent: reduction,
                                width: width,
                                height: height,
                                isImage: true,
                            });
                        }, mimeType, quality);
                    };

                    img.onerror = function () {
                        // Return original file on image load failure
                        resolve({
                            file: file,
                            previewUrl: null,
                            originalSize: originalSize,
                            optimizedSize: originalSize,
                            reductionPercent: 0,
                            isImage: true,
                        });
                    };

                    img.src = e.target.result;
                };

                reader.onerror = function () {
                    resolve({
                        file: file,
                        previewUrl: null,
                        originalSize: originalSize,
                        optimizedSize: originalSize,
                        reductionPercent: 0,
                        isImage: true,
                    });
                };

                reader.readAsDataURL(file);
            });
        },

        /**
         * Compress a batch of files in parallel
         * 
         * @param {FileList|Array<File>} fileList 
         * @param {Object} options 
         * @returns {Promise<Array<Object>>}
         */
        compressFiles: function (fileList, options) {
            const files = Array.from(fileList || []);
            const self = this;
            return Promise.all(files.map(function (file) {
                return self.compressFile(file, options);
            }));
        },

        /**
         * Create a DataTransfer object to sync compressed files back into input.files
         * 
         * @param {Array<File>} files 
         * @returns {FileList}
         */
        createFileList: function (files) {
            try {
                const dt = new DataTransfer();
                (files || []).forEach(function (f) {
                    dt.items.add(f);
                });
                return dt.files;
            } catch (e) {
                console.warn('DataTransfer not supported in this client environment:', e);
                return files;
            }
        },

        /**
         * Format byte sizes to human-readable strings (e.g. "4.8 MB", "180 KB")
         */
        formatBytes: function (bytes) {
            if (bytes === 0 || !bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    };

    global.ClientImageOptimizer = ClientImageOptimizer;
})(typeof window !== 'undefined' ? window : this);
