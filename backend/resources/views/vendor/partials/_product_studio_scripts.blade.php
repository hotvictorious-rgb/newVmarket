    <script src="{{ asset('assets/js/client-image-optimizer.js') }}"></script>
    <!-- Facebook Marketplace Listing Interactive JavaScript Engine -->
    <script>
        // State
        let uploadedFiles = [];
        let activeHeroIndex = 0;
        let isPriceValid = true;
        let totalOriginalBytes = 0;
        let totalOptimizedBytes = 0;

        function openNewProductModal() {
            const modal = document.getElementById('vmNewProductModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            syncLivePreview();
            validatePricing();
        }

        function closeNewProductModal() {
            const modal = document.getElementById('vmNewProductModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        // Mobile Tab Switcher
        function switchStudioTab(tab) {
            const colForm = document.getElementById('fbColForm');
            const colPreview = document.getElementById('fbColPreview');
            const btnForm = document.getElementById('tabBtnForm');
            const btnPreview = document.getElementById('tabBtnPreview');

            if (tab === 'preview') {
                colForm.classList.add('hide-tab');
                colPreview.classList.add('active-tab');
                btnPreview.classList.add('active');
                btnForm.classList.remove('active');
            } else {
                colForm.classList.remove('hide-tab');
                colPreview.classList.remove('active-tab');
                btnForm.classList.add('active');
                btnPreview.classList.remove('active');
            }
        }

        // Trigger file input
        function triggerPhotoSelect() {
            document.getElementById('fbFileInput').click();
        }

        // Handle File Selection with On-Device Compression & Privacy Strip
        async function handlePhotosSelected(fileList) {
            const newFiles = Array.from(fileList || []);
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            const spinner = document.getElementById('fbOptimizingSpinner');
            const statsCard = document.getElementById('fbOptimizationStats');

            const candidateFiles = [];
            for (let file of newFiles) {
                if (!validTypes.includes(file.type)) {
                    alert(`"${file.name}" is not a supported format. Please select JPG, PNG, JPEG or WEBP.`);
                    continue;
                }
                if (uploadedFiles.length + candidateFiles.length >= 10) {
                    alert('Maximum 10 photos allowed per listing.');
                    break;
                }
                candidateFiles.push(file);
            }

            if (candidateFiles.length === 0) return;

            if (spinner) spinner.style.display = 'block';

            try {
                for (let rawFile of candidateFiles) {
                    let fileToAdd = rawFile;
                    let origSize = rawFile.size;
                    let optSize = rawFile.size;

                    if (window.ClientImageOptimizer && typeof window.ClientImageOptimizer.compressFile === 'function') {
                        const result = await window.ClientImageOptimizer.compressFile(rawFile, {
                            maxDimension: 1600,
                            quality: 0.82
                        });
                        fileToAdd = result.file;
                        origSize = result.originalSize;
                        optSize = result.optimizedSize;
                    }

                    uploadedFiles.push(fileToAdd);
                    totalOriginalBytes += origSize;
                    totalOptimizedBytes += optSize;
                }

                // Update Optimization Statistics Feedback
                if (statsCard && totalOriginalBytes > 0 && window.ClientImageOptimizer) {
                    const savingsPercent = Math.max(0, Math.round(((totalOriginalBytes - totalOptimizedBytes) / totalOriginalBytes) * 100));
                    document.getElementById('fbOptimizationSummary').innerHTML = 
                        `<strong>${uploadedFiles.length} photo(s) optimized on-device:</strong> ${window.ClientImageOptimizer.formatBytes(totalOriginalBytes)} &rarr; ${window.ClientImageOptimizer.formatBytes(totalOptimizedBytes)}`;
                    const savingsBadge = document.getElementById('fbOptimizationSavings');
                    savingsBadge.textContent = `${savingsPercent}% data saved ⚡`;
                    statsCard.style.display = 'flex';
                }
            } catch (err) {
                console.error('Client compression error:', err);
            } finally {
                if (spinner) spinner.style.display = 'none';
            }

            updateGalleryUI();
            updateFileInputDataTransfer();
            validatePhotoRequirement();
        }

        // Synchronize files to the actual form input using DataTransfer
        function updateFileInputDataTransfer() {
            const dataTransfer = new DataTransfer();
            uploadedFiles.forEach(file => dataTransfer.items.add(file));
            
            const fileInput = document.getElementById('fbFileInput');
            fileInput.files = dataTransfer.files;

            // Also synchronize primary photo to single fallback input
            if (uploadedFiles.length > 0) {
                const singleTransfer = new DataTransfer();
                singleTransfer.items.add(uploadedFiles[0]);
                document.getElementById('fbFileInputFallback').files = singleTransfer.files;
            }
        }

        // Remove photo by index
        function removePhoto(index, event) {
            if (event) event.stopPropagation();
            uploadedFiles.splice(index, 1);
            if (activeHeroIndex >= uploadedFiles.length) {
                activeHeroIndex = Math.max(0, uploadedFiles.length - 1);
            }

            if (uploadedFiles.length === 0) {
                totalOriginalBytes = 0;
                totalOptimizedBytes = 0;
                const statsCard = document.getElementById('fbOptimizationStats');
                if (statsCard) statsCard.style.display = 'none';
            }

            updateGalleryUI();
            updateFileInputDataTransfer();
            validatePhotoRequirement();
        }

        // Set active preview thumbnail
        function selectHeroPhoto(index) {
            activeHeroIndex = index;
            renderHeroImage();
            renderGalleryThumbnails();
        }

        // Update thumbnail gallery UI
        function updateGalleryUI() {
            const countBadge = document.getElementById('fbPhotoCount');
            const gallery = document.getElementById('fbThumbnailGallery');
            const dropzone = document.getElementById('fbDropzone');

            countBadge.textContent = `${uploadedFiles.length} / 10`;

            if (uploadedFiles.length === 0) {
                gallery.style.display = 'none';
                dropzone.style.display = 'block';
                activeHeroIndex = 0;
            } else {
                gallery.style.display = 'grid';
                renderGalleryThumbnails();
            }

            renderHeroImage();
        }

        function renderGalleryThumbnails() {
            const gallery = document.getElementById('fbThumbnailGallery');
            gallery.innerHTML = '';

            uploadedFiles.forEach((file, idx) => {
                const card = document.createElement('div');
                card.className = `fb-thumb-card ${idx === activeHeroIndex ? 'active' : ''}`;
                card.onclick = () => selectHeroPhoto(idx);

                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = `Photo ${idx + 1}`;
                card.appendChild(img);

                // Cover badge for 1st photo
                if (idx === 0) {
                    const coverBadge = document.createElement('div');
                    coverBadge.className = 'fb-thumb-badge-cover';
                    coverBadge.textContent = '⭐ Cover';
                    card.appendChild(coverBadge);
                }

                // Delete button
                const btnRemove = document.createElement('button');
                btnRemove.type = 'button';
                btnRemove.className = 'fb-thumb-btn-remove';
                btnRemove.innerHTML = '&times;';
                btnRemove.title = 'Remove photo';
                btnRemove.onclick = (e) => removePhoto(idx, e);
                card.appendChild(btnRemove);

                gallery.appendChild(card);
            });

            // If less than 10 photos, show "+ Add More" tile
            if (uploadedFiles.length < 10) {
                const addTile = document.createElement('div');
                addTile.className = 'fb-thumb-add-tile';
                addTile.onclick = triggerPhotoSelect;
                addTile.innerHTML = `
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add Photo</span>
                `;
                gallery.appendChild(addTile);
            }
        }

        function renderHeroImage() {
            const heroImg = document.getElementById('fbPrevHeroImg');
            const placeholder = document.getElementById('fbHeroPlaceholder');
            const dotsContainer = document.getElementById('fbHeroDots');

            if (uploadedFiles.length > 0 && uploadedFiles[activeHeroIndex]) {
                heroImg.src = URL.createObjectURL(uploadedFiles[activeHeroIndex]);
                heroImg.style.display = 'block';
                placeholder.style.display = 'none';

                // Carousel Dots
                if (uploadedFiles.length > 1) {
                    dotsContainer.style.display = 'flex';
                    dotsContainer.innerHTML = '';
                    uploadedFiles.forEach((_, i) => {
                        const dot = document.createElement('div');
                        dot.className = `fb-dot ${i === activeHeroIndex ? 'active' : ''}`;
                        dotsContainer.appendChild(dot);
                    });
                } else {
                    dotsContainer.style.display = 'none';
                }
            } else {
                heroImg.style.display = 'none';
                placeholder.style.display = 'flex';
                dotsContainer.style.display = 'none';
            }
        }

        // Validate compulsory photo rule
        function validatePhotoRequirement() {
            const errorBanner = document.getElementById('fbPhotoError');
            const dropzone = document.getElementById('fbDropzone');

            if (uploadedFiles.length === 0) {
                dropzone.style.borderColor = '#EF4444';
                return false;
            } else {
                dropzone.style.borderColor = '#CBD5E1';
                errorBanner.style.display = 'none';
                return true;
            }
        }

        // PRICE SLASH VALIDATION (Strictly forbids selling price >= original price)
        function validatePricing() {
            const inUnitPrice = document.getElementById('inUnitPrice');
            const inSlashPrice = document.getElementById('inSlashPrice');
            const alertBox = document.getElementById('fbSlashAlert');
            const btnSubmit = document.getElementById('btnFbSubmit');

            const unitPrice = parseFloat(inUnitPrice.value) || 0;
            const slashPrice = inSlashPrice.value ? parseFloat(inSlashPrice.value) : null;

            isPriceValid = true;
            alertBox.style.display = 'none';
            inUnitPrice.classList.remove('error', 'valid');
            inSlashPrice.classList.remove('error', 'valid');
            btnSubmit.disabled = false;

            if (slashPrice !== null && !isNaN(slashPrice)) {
                // If slash price is entered, selling price MUST be strictly less than slash price
                if (unitPrice >= slashPrice) {
                    isPriceValid = false;
                    alertBox.style.display = 'block';
                    alertBox.className = 'fb-slash-alert error';
                    alertBox.innerHTML = `
                        ❌ <strong>Invalid Price Slash:</strong> The selling price (<strong>₦${unitPrice.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong>)
                        cannot be higher than or equal to the original regular price (<strong>₦${slashPrice.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong>).
                        A discount slash must lower the price. Please correct the selling price or original price.
                    `;
                    inUnitPrice.classList.add('error');
                    inSlashPrice.classList.add('error');
                    btnSubmit.disabled = true;
                } else if (unitPrice > 0 && slashPrice > unitPrice) {
                    const savings = slashPrice - unitPrice;
                    const discountPercent = Math.round((savings / slashPrice) * 100);
                    alertBox.style.display = 'block';
                    alertBox.className = 'fb-slash-alert success';
                    alertBox.innerHTML = `
                        🏷️ <strong>Valid Deal Slash:</strong> Buyer saves <strong>₦${savings.toLocaleString('en-NG', {minimumFractionDigits: 2})}</strong> (${discountPercent}% OFF) on Marketplace!
                    `;
                    inUnitPrice.classList.add('valid');
                    inSlashPrice.classList.add('valid');
                }
            }
        }

        // Condition toggle
        function setCondition(val) {
            const cardNew = document.getElementById('cardConditionNew');
            const cardUsed = document.getElementById('cardConditionUsed');
            if (val === 'brand_new') {
                cardNew.classList.add('active');
                cardUsed.classList.remove('active');
            } else {
                cardUsed.classList.add('active');
                cardNew.classList.remove('active');
            }
            syncLivePreview();
        }

        // Logistics toggle
        function setLogistics(val) {
            const cardBike = document.getElementById('cardLogisticsBike');
            const cardKeke = document.getElementById('cardLogisticsKeke');
            const kekeNotice = document.getElementById('fbKekeNotice');
            if (val === 'bike') {
                cardBike.classList.add('active');
                cardKeke.classList.remove('active');
                kekeNotice.style.display = 'none';
            } else {
                cardKeke.classList.add('active');
                cardBike.classList.remove('active');
                kekeNotice.style.display = 'block';
            }
            syncLivePreview();
        }

        // Sync form values into the sticky Facebook Marketplace preview card (Safe XSS-free)
        function syncLivePreview() {
            const titleInput = document.getElementById('inProdName');
            const unitPriceInput = document.getElementById('inUnitPrice');
            const slashPriceInput = document.getElementById('inSlashPrice');
            const categorySelect = document.getElementById('inCategory');
            const shopSelect = document.getElementById('inShop');
            const inventoryInput = document.getElementById('inInventory');
            const warrantySelect = document.getElementById('inWarranty');
            const descInput = document.getElementById('inDescription');

            // Elements in Preview Card
            const prevTitle = document.getElementById('fbPrevTitle');
            const prevPrice = document.getElementById('fbPrevPrice');
            const prevSlash = document.getElementById('fbPrevSlashPrice');
            const prevDiscount = document.getElementById('fbPrevDiscountPill');
            const prevLocation = document.getElementById('fbPrevLocation');
            const prevStock = document.getElementById('fbPrevStockCount');
            const prevDesc = document.getElementById('fbPrevDesc');

            const prevCrumbCat = document.getElementById('fbPrevCrumbCategory');
            const prevCrumbCond = document.getElementById('fbPrevCrumbCondition');
            const prevConditionPill = document.getElementById('fbPrevConditionPill');
            const prevTierPill = document.getElementById('fbPrevTierPill');

            const prevDetailCond = document.getElementById('fbPrevDetailCondition');
            const prevDetailCat = document.getElementById('fbPrevDetailCategory');
            const prevDetailTier = document.getElementById('fbPrevDetailTier');
            const prevDetailWarranty = document.getElementById('fbPrevDetailWarranty');

            // Title
            prevTitle.textContent = titleInput.value.trim() ? titleInput.value.trim() : 'Your Product Title Here';

            // Prices
            const unitPrice = parseFloat(unitPriceInput.value) || 0;
            const slashPrice = slashPriceInput.value ? parseFloat(slashPriceInput.value) : null;

            prevPrice.textContent = `₦${unitPrice.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            if (slashPrice && slashPrice > unitPrice && unitPrice > 0) {
                prevSlash.style.display = 'inline-block';
                prevSlash.textContent = `₦${slashPrice.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                const savings = slashPrice - unitPrice;
                const percent = Math.round((savings / slashPrice) * 100);
                prevDiscount.style.display = 'inline-block';
                prevDiscount.textContent = `${percent}% OFF`;
            } else {
                prevSlash.style.display = 'none';
                prevDiscount.style.display = 'none';
            }

            // Category
            const selectedCatText = categorySelect.options[categorySelect.selectedIndex]?.text || 'General';
            prevCrumbCat.textContent = selectedCatText;
            prevDetailCat.textContent = selectedCatText;

            // Shop / Location
            const selectedShopText = shopSelect.options[shopSelect.selectedIndex]?.text || 'Plaza Outlet';
            prevLocation.textContent = `Listed just now in ${selectedShopText}`;

            // Inventory
            prevStock.textContent = inventoryInput.value || '1';

            // Condition
            const conditionVal = document.querySelector('input[name="condition"]:checked')?.value || 'brand_new';
            if (conditionVal === 'brand_new') {
                prevCrumbCond.textContent = 'Brand New';
                prevConditionPill.textContent = '✨ Brand New';
                prevConditionPill.className = 'fb-pill-condition new';
                prevDetailCond.textContent = 'Brand New (Factory Sealed)';
            } else {
                prevCrumbCond.textContent = 'UK Used';
                prevConditionPill.textContent = '🇬🇧 UK Used';
                prevConditionPill.className = 'fb-pill-condition used';
                prevDetailCond.textContent = 'UK Used (Clean Grade A)';
            }

            // Logistics Tier
            const tierVal = document.querySelector('input[name="package_tier"]:checked')?.value || 'bike';
            if (tierVal === 'bike') {
                prevTierPill.textContent = '🏍️ Bike Dispatch';
                prevTierPill.className = 'fb-pill-tier bike';
                prevDetailTier.textContent = 'Bike Dispatch (Standard Fare)';
            } else {
                prevTierPill.textContent = '🛺 Keke Cargo';
                prevTierPill.className = 'fb-pill-tier keke';
                prevDetailTier.textContent = 'Keke Cargo (+Bulky Surcharge applies)';
            }

            // Warranty
            const warrantyText = warrantySelect.options[warrantySelect.selectedIndex]?.text || 'Doorstep Testing';
            prevDetailWarranty.textContent = warrantyText;

            // Description
            const desc = descInput.value.trim();
            prevDesc.textContent = desc ? desc : 'Authentic certified product backed by Victorious MARKET escrow protection.';
        }

        // Form Submit Validation Handler
        document.getElementById('fbProductForm').addEventListener('submit', function (e) {
            // Check compulsory photo
            if (uploadedFiles.length === 0) {
                e.preventDefault();
                const errorBanner = document.getElementById('fbPhotoError');
                errorBanner.style.display = 'block';
                document.getElementById('fbDropzone').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            // Check price validity
            validatePricing();
            if (!isPriceValid) {
                e.preventDefault();
                alert('Please resolve the price slash error. Slashed selling price cannot exceed the original price.');
                document.getElementById('inUnitPrice').focus();
                return false;
            }

            // Submit button loading state
            const btnSubmit = document.getElementById('btnFbSubmit');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '⏳ Publishing to Marketplace...';
        });

        // Drag and Drop support
        const dropzone = document.getElementById('fbDropzone');
        ['dragenter', 'dragover'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                handlePhotosSelected(dt.files);
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNewProductModal();
            }
        });
    </script>