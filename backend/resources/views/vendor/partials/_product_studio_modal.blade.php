    <!-- Facebook Marketplace-Style Listing Studio Modal -->
    <div id="vmNewProductModal" class="fb-modal-backdrop" style="display: none;">
        <div class="fb-studio-container">
            <!-- Studio Top Bar -->
            <div class="fb-studio-topbar">
                <div class="fb-topbar-left">
                    <div class="fb-market-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="fb-topbar-title">Marketplace Listing Studio</div>
                        <div class="fb-topbar-sub">Facebook Marketplace Experience • 7-Day Certified Freshness</div>
                    </div>
                </div>

                <!-- Mobile / Tablet Segmented Controller -->
                <div class="fb-mobile-tabs" id="fbMobileTabs">
                    <button type="button" class="fb-tab-btn active" id="tabBtnForm" onclick="switchStudioTab('form')">
                        ✏️ Details
                    </button>
                    <button type="button" class="fb-tab-btn" id="tabBtnPreview" onclick="switchStudioTab('preview')">
                        👁️ Live Preview <span id="fbMobilePreviewBadge" class="fb-badge-dot"></span>
                    </button>
                </div>

                <div class="fb-topbar-right">
                    <button type="button" class="fb-close-btn" onclick="closeNewProductModal();" title="Close Studio (Esc)">
                        &times;
                    </button>
                </div>
            </div>

            <!-- Studio Body: Split Screen on Desktop, Tabbed on Mobile -->
            <div class="fb-studio-body">
                <!-- LEFT COLUMN: Form Studio -->
                <div class="fb-col-form" id="fbColForm">
                    <!-- Merchant Profile Pill -->
                    <div class="fb-merchant-pill">
                        <div class="fb-merchant-avatar">
                            {{ substr($vendor->business_name ?? Auth::user()->name ?? 'V', 0, 1) }}
                        </div>
                        <div class="fb-merchant-meta">
                            <div class="fb-merchant-name">
                                {{ $vendor->business_name ?? Auth::user()->name ?? 'Merchant Store' }}
                                <span class="fb-verified-check" title="Verified Merchant">✓</span>
                            </div>
                            <div class="fb-merchant-scope">
                                <span>🌐 Public Listing</span> • <span>Marketplace &amp; Shop Feed</span>
                            </div>
                        </div>
                    </div>

                    <form id="fbProductForm" method="POST" action="{{ route('vendor.products.store') }}" enctype="multipart/form-data" novalidate>
                        @csrf

                        <!-- PHOTO UPLOAD STUDIO (COMPULSORY + PREVIEW) -->
                        <div class="fb-section-card">
                            <div class="fb-section-header">
                                <div>
                                    <h4 class="fb-section-title">
                                        Photos
                                        <span class="fb-badge-compulsory">* COMPULSORY</span>
                                    </h4>
                                    <p class="fb-section-hint">
                                        Add at least 1 real photo. The 1st photo will be your Marketplace cover photo. (Up to 10 photos)
                                    </p>
                                </div>
                                <div class="fb-photo-count" id="fbPhotoCount">0 / 10</div>
                            </div>

                            <!-- Photo Dropzone -->
                            <div class="fb-dropzone" id="fbDropzone" onclick="triggerPhotoSelect();">
                                <input type="file" id="fbFileInput" name="images[]" multiple accept="image/jpeg,image/png,image/jpg,image/webp" style="display: none;" onchange="handlePhotosSelected(this.files);">
                                <input type="file" id="fbFileInputFallback" name="image" accept="image/jpeg,image/png,image/jpg,image/webp" style="display: none;">
                                
                                <div class="fb-dropzone-icon">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1877F2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                </div>
                                <div class="fb-dropzone-text">+ Add Photos</div>
                                <div class="fb-dropzone-sub">Click to browse or drag and drop photos here</div>
                                <div class="fb-dropzone-limits">JPEG, PNG, JPG, WEBP • Max 5MB each</div>
                            </div>

                            <!-- Inline Compulsory Warning -->
                            <div id="fbPhotoError" class="fb-inline-error" style="display: none;">
                                ⚠️ <strong>Photo is required:</strong> Please select at least 1 real photo of your item to publish.
                            </div>

                            <!-- Dynamic Photo Gallery Thumbnails -->
                            <div id="fbThumbnailGallery" class="fb-thumbnail-gallery" style="display: none;"></div>
                        </div>

                        <!-- ITEM DETAILS -->
                        <div class="fb-section-card">
                            <h4 class="fb-section-title" style="margin-bottom: 14px;">Item Details</h4>

                            <!-- Product Name / Title -->
                            <div class="fb-field-group">
                                <label class="fb-field-label" for="inProdName">
                                    Title <span class="fb-req">*</span>
                                </label>
                                <input type="text" name="name" id="inProdName" required maxlength="255" class="fb-input" placeholder="e.g. iPhone 13 128GB Midnight Blue or LG 2HP Inverter AC" oninput="syncLivePreview();">
                                <div class="fb-field-hint">Include brand, model, key specifications, and color for buyers.</div>
                            </div>

                            <!-- PRICING & PRICE SLASH ARCHITECTURE -->
                            <div class="fb-grid-2">
                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inUnitPrice">
                                        Selling Price (₦) <span class="fb-req">*</span>
                                    </label>
                                    <input type="number" step="0.01" min="0.01" max="100000000" name="unit_price" id="inUnitPrice" required class="fb-input" placeholder="e.g. 45000" oninput="validatePricing(); syncLivePreview();">
                                    <div class="fb-field-hint">What the buyer pays today.</div>
                                </div>

                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inSlashPrice">
                                        Original Price (₦)
                                        <span class="fb-opt">(Was-Price)</span>
                                    </label>
                                    <input type="number" step="0.01" min="0.01" max="100000000" name="slash_price" id="inSlashPrice" class="fb-input" placeholder="e.g. 60000" oninput="validatePricing(); syncLivePreview();">
                                    <div class="fb-field-hint">Regular price before discount.</div>
                                </div>
                            </div>

                            <!-- Real-time Price Slash Guard Feedback -->
                            <div id="fbSlashAlert" class="fb-slash-alert" style="display: none;"></div>

                            <!-- Category & Condition -->
                            <div class="fb-grid-2">
                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inCategory">
                                        Category <span class="fb-req">*</span>
                                    </label>
                                    <select name="category_id" id="inCategory" required class="fb-select" onchange="syncLivePreview();">
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="fb-field-group">
                                    <label class="fb-field-label">
                                        Plaza Outlet / Branch
                                    </label>
                                    <select name="shop_id" id="inShop" class="fb-select" onchange="syncLivePreview();">
                                        @if(isset($shops) && count($shops) > 0)
                                            @foreach($shops as $shop)
                                                <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                            @endforeach
                                        @else
                                            <option value="">Primary Shop Branch</option>
                                        @endif
                                    </select>
                                </div>
                            </div>

                            <!-- Condition Selector (Brand New vs UK Used) -->
                            <div class="fb-field-group">
                                <label class="fb-field-label">
                                    Condition <span class="fb-req">*</span>
                                </label>
                                <div class="fb-radio-grid">
                                    <label id="cardConditionNew" class="fb-radio-card active">
                                        <input type="radio" name="condition" value="brand_new" checked onchange="setCondition('brand_new');">
                                        <div>
                                            <div class="fb-radio-title" style="color: #065F46;">✨ Brand New</div>
                                            <div class="fb-radio-desc">Sealed / Factory Box • 0 Hours</div>
                                        </div>
                                    </label>
                                    <label id="cardConditionUsed" class="fb-radio-card">
                                        <input type="radio" name="condition" value="uk_used" onchange="setCondition('uk_used');">
                                        <div>
                                            <div class="fb-radio-title" style="color: #374151;">🇬🇧 UK Used</div>
                                            <div class="fb-radio-desc">Clean Grade A • Pre-Owned Tested</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Delivery Fleet & Package Size (Bike vs Keke Cargo) -->
                            <div class="fb-field-group">
                                <label class="fb-field-label">
                                    Delivery Vehicle &amp; Package Tier <span class="fb-req">*</span>
                                </label>
                                <div class="fb-radio-grid">
                                    <label id="cardLogisticsBike" class="fb-radio-card active">
                                        <input type="radio" name="package_tier" value="bike" checked onchange="setLogistics('bike');">
                                        <div>
                                            <div class="fb-radio-title" style="color: #0369A1;">🏍️ Bike Dispatch</div>
                                            <div class="fb-radio-desc">Normal Fee (Phone, Shoes, Clothes)</div>
                                        </div>
                                    </label>
                                    <label id="cardLogisticsKeke" class="fb-radio-card">
                                        <input type="radio" name="package_tier" value="keke" onchange="setLogistics('keke');">
                                        <div>
                                            <div class="fb-radio-title" style="color: #B45309;">🛺 Keke Cargo</div>
                                            <div class="fb-radio-desc">Bulky/Heavy (Inverter, TV, AC)</div>
                                        </div>
                                    </label>
                                </div>
                                <div id="fbKekeNotice" class="fb-cargo-notice" style="display: none;">
                                    ⚠️ Keke orders automatically trigger the hub bulky cargo surcharge (₦1,000 baseline) to pay the tricycle cargo rider.
                                </div>
                            </div>

                            <!-- Stock Quantity & Warranty Window -->
                            <div class="fb-grid-2">
                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inInventory">
                                        Stock Quantity <span class="fb-req">*</span>
                                    </label>
                                    <input type="number" name="inventory_quantity" id="inInventory" required min="0" max="1000000" class="fb-input" value="1" oninput="syncLivePreview();">
                                </div>

                                <div class="fb-field-group">
                                    <label class="fb-field-label" for="inWarranty">
                                        Warranty / Return Policy
                                    </label>
                                    <select name="warranty_type" id="inWarranty" class="fb-select" onchange="syncLivePreview();">
                                        <option value="in_store_testing" selected>Doorstep / In-Store Testing on Arrival</option>
                                        <option value="7_days_replacement">7-Day Return / Replacement Warranty</option>
                                        <option value="brand_warranty">Official Manufacturer Warranty</option>
                                        <option value="as_is">Sold As-Is (No Warranty After Handshake)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Description / Key Specs -->
                            <div class="fb-field-group">
                                <label class="fb-field-label" for="inDescription">
                                    Description / Key Specs
                                </label>
                                <textarea name="description" id="inDescription" rows="3" class="fb-textarea" placeholder="Tell buyers about your item (storage, color, included accessories, battery health, condition notes)..." oninput="syncLivePreview();"></textarea>
                            </div>

                            <!-- YouTube Unboxing Video URL -->
                            <div class="fb-field-group">
                                <label class="fb-field-label" for="inVideoUrl">
                                    YouTube Video Demo <span class="fb-opt">(Optional)</span>
                                </label>
                                <input type="url" name="video_url" id="inVideoUrl" class="fb-input" placeholder="https://youtube.com/watch?v=... or https://youtu.be/...">
                            </div>
                        </div>

                        <!-- Sticky Submit Bar in Form -->
                        <div class="fb-form-footer">
                            <button type="button" class="fb-btn-cancel" onclick="closeNewProductModal();">
                                Cancel
                            </button>
                            <button type="submit" id="btnFbSubmit" class="fb-btn-publish">
                                🚀 Publish Listing Now
                            </button>
                        </div>
                    </form>
                </div>

                <!-- RIGHT COLUMN: Sticky Live Facebook Marketplace Listing Preview Card -->
                <div class="fb-col-preview" id="fbColPreview">
                    <div class="fb-preview-sticky-wrapper">
                        <!-- Simulated Facebook Marketplace Banner -->
                        <div class="fb-mock-top-header">
                            <div class="fb-mock-crumb">
                                <span>Marketplace</span>
                                <span>&rsaquo;</span>
                                <span id="fbPrevCrumbCategory">Electronics</span>
                                <span>&rsaquo;</span>
                                <span id="fbPrevCrumbCondition">Brand New</span>
                            </div>
                            <div class="fb-mock-badge-live">
                                <span class="fb-pulse-dot"></span> Live Facebook Preview
                            </div>
                        </div>

                        <!-- The Facebook Listing Card -->
                        <div class="fb-listing-card">
                            <!-- Hero Image Viewport with Floating Badges -->
                            <div class="fb-card-hero" id="fbHeroContainer">
                                <img id="fbPrevHeroImg" src="" alt="Listing Preview" style="display: none;">
                                <div id="fbHeroPlaceholder" class="fb-hero-placeholder">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    <span style="font-weight: 700; color: #64748B; margin-top: 8px;">Product Photo Preview</span>
                                    <span style="font-size: 11.5px; color: #94A3B8;">Add photos on the left to see live preview</span>
                                </div>

                                <!-- Floating Badges on Hero -->
                                <div class="fb-hero-badges-top">
                                    <span id="fbPrevConditionPill" class="fb-pill-condition new">✨ Brand New</span>
                                    <span id="fbPrevTierPill" class="fb-pill-tier bike">🏍️ Bike Dispatch</span>
                                </div>

                                <!-- Carousel Dot Indicators -->
                                <div id="fbHeroDots" class="fb-hero-dots" style="display: none;"></div>
                            </div>

                            <!-- Listing Card Body -->
                            <div class="fb-card-content">
                                <!-- Price & Discount Row -->
                                <div class="fb-prev-price-row">
                                    <div class="fb-prev-main-price" id="fbPrevPrice">₦0.00</div>
                                    <div class="fb-prev-slash-price" id="fbPrevSlashPrice" style="display: none;"></div>
                                    <div class="fb-prev-discount-pill" id="fbPrevDiscountPill" style="display: none;"></div>
                                </div>

                                <!-- Listing Title -->
                                <div class="fb-prev-title" id="fbPrevTitle">Your Product Title Here</div>

                                <!-- Location & Timestamp -->
                                <div class="fb-prev-meta">
                                    <span id="fbPrevLocation">Listed just now in {{ $shops->first()->name ?? 'Main Outlet' }}</span>
                                    <span>•</span>
                                    <span style="color: #10B981; font-weight: 700;">In Stock (<span id="fbPrevStockCount">1</span>)</span>
                                </div>

                                <!-- Simulated Facebook Marketplace Action Buttons -->
                                <div class="fb-prev-actions">
                                    <button type="button" class="fb-btn-fb-message" tabindex="-1">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.477 2 2 6.145 2 11.258c0 2.908 1.455 5.503 3.736 7.155v3.587l3.418-1.876c.905.251 1.865.387 2.846.387 5.523 0 10-4.145 10-9.253C22 6.145 17.523 2 12 2zm1.053 12.463l-2.684-2.864-5.237 2.864 5.76-6.115 2.753 2.864 5.168-2.864-5.76 6.115z"/>
                                        </svg>
                                        Message Seller
                                    </button>
                                    <div class="fb-action-icon-btns">
                                        <button type="button" class="fb-icon-btn" title="Save to Wishlist" tabindex="-1">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                        </button>
                                        <button type="button" class="fb-icon-btn" title="Share Listing" tabindex="-1">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="18" cy="5" r="3"></circle>
                                                <circle cx="6" cy="12" r="3"></circle>
                                                <circle cx="18" cy="19" r="3"></circle>
                                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Seller Profile Block -->
                                <div class="fb-prev-seller-block">
                                    <div class="fb-prev-seller-header">Seller Information</div>
                                    <div class="fb-prev-seller-row">
                                        <div class="fb-prev-seller-avatar">
                                            {{ substr($vendor->business_name ?? Auth::user()->name ?? 'V', 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="fb-prev-seller-name">
                                                {{ $vendor->business_name ?? Auth::user()->name ?? 'Merchant Store' }}
                                                <span class="fb-verified-check">✓</span>
                                            </div>
                                            <div class="fb-prev-seller-sub">Verified Merchant • Highly responsive to inquiries</div>
                                            <div class="fb-prev-seller-rating">⭐⭐⭐⭐⭐ 4.9 Marketplace Rating</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Details Breakdown -->
                                <div class="fb-prev-details-block">
                                    <div class="fb-prev-details-header">Details</div>
                                    <div class="fb-prev-detail-item">
                                        <span class="fb-detail-label">Condition:</span>
                                        <span class="fb-detail-value" id="fbPrevDetailCondition">Brand New</span>
                                    </div>
                                    <div class="fb-prev-detail-item">
                                        <span class="fb-detail-label">Category:</span>
                                        <span class="fb-detail-value" id="fbPrevDetailCategory">Electronics</span>
                                    </div>
                                    <div class="fb-prev-detail-item">
                                        <span class="fb-detail-label">Logistics:</span>
                                        <span class="fb-detail-value" id="fbPrevDetailTier">Bike Dispatch (Standard Fare)</span>
                                    </div>
                                    <div class="fb-prev-detail-item">
                                        <span class="fb-detail-label">Warranty:</span>
                                        <span class="fb-detail-value" id="fbPrevDetailWarranty">Doorstep / In-Store Testing</span>
                                    </div>
                                </div>

                                <!-- Description Block -->
                                <div class="fb-prev-desc-block">
                                    <div class="fb-prev-desc-header">Seller's Description</div>
                                    <div class="fb-prev-desc-text" id="fbPrevDesc">
                                        Authentic certified product backed by Victorious MARKET escrow protection.
                                    </div>
                                </div>

                                <!-- Escrow Trust Shield -->
                                <div class="fb-prev-escrow-badge">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    </svg>
                                    <div>
                                        <strong>Victorious Escrow Handshake:</strong>
                                        <span>Payment safely held until doorstep testing is signed off.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

