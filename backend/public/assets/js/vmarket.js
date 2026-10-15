/**
 * Victorious MARKET (VMarket) — Lightweight Vanilla Storefront JS
 * Zero external dependencies. Fast, accessible, mobile-first.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Menu / Drawer Toggle
    const mobileMenuBtn = document.getElementById('vmMobileMenuBtn');
    const mobileDrawer = document.getElementById('vmMobileDrawer');
    const mobileDrawerClose = document.getElementById('vmDrawerClose');
    const mobileDrawerOverlay = document.getElementById('vmDrawerOverlay');

    function openDrawer() {
        if (mobileDrawer) mobileDrawer.classList.add('open');
        if (mobileDrawerOverlay) mobileDrawerOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        if (mobileDrawer) mobileDrawer.classList.remove('open');
        if (mobileDrawerOverlay) mobileDrawerOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openDrawer);
    if (mobileDrawerClose) mobileDrawerClose.addEventListener('click', closeDrawer);
    if (mobileDrawerOverlay) mobileDrawerOverlay.addEventListener('click', closeDrawer);

    // 2. Product Detail Image Gallery Switcher
    const mainDetailImg = document.getElementById('vmMainDetailImg');
    const detailThumbs = document.querySelectorAll('.vm-detail-thumb');

    detailThumbs.forEach(thumb => {
        thumb.addEventListener('click', function() {
            detailThumbs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const targetSrc = this.getAttribute('data-full-src');
            if (mainDetailImg && targetSrc) {
                mainDetailImg.src = targetSrc;
            }
        });
    });

    // 3. Reusable Responsive Touch Slider Engine (Hero Slider & Footer Slider)
    function initVmSlider(sliderId, options = {}) {
        const container = document.getElementById(sliderId);
        if (!container) return;

        const slides = container.querySelectorAll('.vm-hero-slide, .vm-footer-slide');
        if (!slides || slides.length <= 1) return;

        const autoInterval = options.interval || 5000;
        const prevBtn = document.getElementById(options.prevBtnId);
        const nextBtn = document.getElementById(options.nextBtnId);
        const dotsContainer = document.getElementById(options.dotsId);
        const dots = dotsContainer ? dotsContainer.querySelectorAll('.vm-slider-dot') : [];

        let current = 0;
        let timer = null;

        function goToSlide(index) {
            slides[current].classList.remove('active');
            if (dots[current]) dots[current].classList.remove('active');

            current = (index + slides.length) % slides.length;

            slides[current].classList.add('active');
            if (dots[current]) dots[current].classList.add('active');
        }

        function startAutoPlay() {
            if (timer) clearInterval(timer);
            timer = setInterval(() => {
                goToSlide(current + 1);
            }, autoInterval);
        }

        function resetTimer() {
            startAutoPlay();
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                goToSlide(current + 1);
                resetTimer();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                goToSlide(current - 1);
                resetTimer();
            });
        }

        dots.forEach((dot, idx) => {
            dot.addEventListener('click', (e) => {
                e.preventDefault();
                goToSlide(idx);
                resetTimer();
            });
        });

        // Touch Swipe Navigation for Mobile
        let startX = 0;
        let endX = 0;

        container.addEventListener('touchstart', (e) => {
            if (e.touches && e.touches[0]) {
                startX = e.touches[0].clientX;
            }
        }, { passive: true });

        container.addEventListener('touchend', (e) => {
            if (e.changedTouches && e.changedTouches[0]) {
                endX = e.changedTouches[0].clientX;
                const diff = endX - startX;
                if (Math.abs(diff) > 40) {
                    if (diff < 0) {
                        goToSlide(current + 1);
                    } else {
                        goToSlide(current - 1);
                    }
                    resetTimer();
                }
            }
        }, { passive: true });

        // Pause on mouse hover (Desktop)
        container.addEventListener('mouseenter', () => {
            if (timer) clearInterval(timer);
        });

        container.addEventListener('mouseleave', () => {
            startAutoPlay();
        });

        // Show pagination & arrows on click / touch for 3.5s, then fade out
        const wrapper = container.closest('.vm-hero-slider-container, .vm-footer-slider-container') || container;
        let touchTimeout = null;
        function triggerActiveControls() {
            wrapper.classList.add('active-hover');
            if (touchTimeout) clearTimeout(touchTimeout);
            touchTimeout = setTimeout(() => {
                wrapper.classList.remove('active-hover');
            }, 3500);
        }

        wrapper.addEventListener('click', triggerActiveControls);
        wrapper.addEventListener('touchstart', triggerActiveControls, { passive: true });

        startAutoPlay();
    }

    // Initialize Hero Slider (5.5s auto-rotate)
    initVmSlider('vmHeroSlider', {
        prevBtnId: 'vmHeroPrev',
        nextBtnId: 'vmHeroNext',
        dotsId: 'vmHeroDots',
        interval: 5500,
    });

    // Initialize Footer Slider (6.5s auto-rotate)
    initVmSlider('vmFooterSlider', {
        prevBtnId: 'vmFooterPrev',
        nextBtnId: 'vmFooterNext',
        dotsId: 'vmFooterDots',
        interval: 6500,
    });

    // 4. Strategic Touchpoint 3: Promotional Popup Modal (Polite Bottom-Right Card)
    const popupCard = document.getElementById('vmPromoPopupCard');
    const popupCloseBtn = document.getElementById('vmPopupCloseBtn');

    if (popupCard) {
        const hasSeen = sessionStorage.getItem('vm_promo_popup_seen');
        if (!hasSeen) {
            setTimeout(() => {
                popupCard.style.display = 'block';
            }, 2500);
        }

        function dismissPopup() {
            popupCard.style.display = 'none';
            sessionStorage.setItem('vm_promo_popup_seen', 'true');
        }

        if (popupCloseBtn) {
            popupCloseBtn.addEventListener('click', dismissPopup);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && popupCard.style.display === 'block') {
                dismissPopup();
            }
        });
    }

    // 5. Quantity Increment / Decrement
    // [AI] VMarket V1: skip vanilla handler when authoritative clamped
    // cartQuantityInitialize (.btn-number) is present to avoid double-fire.
    const qtyInput = document.getElementById('vmQtyInput');
    const qtyPlus = document.getElementById('vmQtyPlus');
    const qtyMinus = document.getElementById('vmQtyMinus');

    if (qtyInput && qtyPlus && qtyMinus) {
        qtyPlus.addEventListener('click', () => {
            let current = parseInt(qtyInput.value) || 1;
            const max = parseInt(qtyInput.getAttribute('max')) || 9999;
            if (current < max) {
                qtyInput.value = current + 1;
            }
        });

        qtyMinus.addEventListener('click', () => {
            let current = parseInt(qtyInput.value) || 1;
            const min = parseInt(qtyInput.getAttribute('min')) || 1;
            if (current > min) {
                qtyInput.value = current - 1;
            }
        });
    }

    // 6. Omnichannel Location & Fulfillment Modal Engine (Amazon-Inspired)
    const locModal = document.getElementById('vmLocationModalBackdrop');
    const locCard = document.getElementById('vmLocationModalCard');
    const locTriggers = [
        document.getElementById('vmHeaderLocationBtn'),
        document.getElementById('vmMobileLocationBtn'),
        document.getElementById('vmProximityTrigger')
    ].filter(Boolean);
    const locCloseBtn = document.getElementById('vmLocationModalClose');
    const locCancelBtn = document.getElementById('vmLocationModalCancel');

    function openLocationModal() {
        if (locModal) {
            locModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeLocationModal() {
        if (locModal) {
            locModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    locTriggers.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openLocationModal();
        });
    });

    if (locCloseBtn) locCloseBtn.addEventListener('click', closeLocationModal);
    if (locCancelBtn) locCancelBtn.addEventListener('click', closeLocationModal);

    if (locModal) {
        locModal.addEventListener('click', (e) => {
            if (e.target === locModal) {
                closeLocationModal();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && locModal && locModal.style.display === 'flex') {
            closeLocationModal();
        }
    });

    // LGA Dataset & Autocomplete Engine
    let lgaDataset = [];
    const lgaDataScript = document.getElementById('vmLgaData');
    if (lgaDataScript) {
        try {
            lgaDataset = JSON.parse(lgaDataScript.textContent) || [];
        } catch (e) {
            console.error('Error parsing LGA dataset:', e);
        }
    }

    const lgaSearchInput = document.getElementById('vmLgaSearchInput');
    const lgaDropdownList = document.getElementById('vmLgaDropdownList');
    const lgaClearBtn = document.getElementById('vmLgaClearBtn');
    const hiddenCity = document.getElementById('vmHiddenCity');
    const hiddenState = document.getElementById('vmHiddenState');
    const hiddenLgaId = document.getElementById('vmHiddenLgaId');
    const hiddenLgaName = document.getElementById('vmHiddenLgaName');
    const hiddenStateId = document.getElementById('vmHiddenStateId');
    const previewCity = document.getElementById('vmPreviewCity');
    const cityChips = document.querySelectorAll('.vm-city-chip');

    function selectLgaItem(id, name, stateName, stateId) {
        if (hiddenLgaId) hiddenLgaId.value = id || '';
        if (hiddenLgaName) hiddenLgaName.value = name || '';
        if (hiddenCity) hiddenCity.value = name || '';
        if (hiddenState) hiddenState.value = stateName || '';
        if (hiddenStateId) hiddenStateId.value = stateId || '';
        if (lgaSearchInput) lgaSearchInput.value = name || '';
        if (lgaClearBtn) lgaClearBtn.style.display = name ? 'block' : 'none';
        if (previewCity) previewCity.textContent = `${name}, ${stateName || 'Nigeria'}`;

        // Sync chips
        cityChips.forEach(c => {
            if (c.getAttribute('data-lga-id') == id || c.getAttribute('data-city') == name) {
                c.classList.add('active');
            } else {
                c.classList.remove('active');
            }
        });

        // Hide dropdown
        if (lgaDropdownList) lgaDropdownList.style.display = 'none';
    }

    if (lgaSearchInput && lgaDropdownList) {
        lgaSearchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim().toLowerCase();
            if (lgaClearBtn) lgaClearBtn.style.display = query ? 'block' : 'none';

            if (!query || query.length < 1) {
                lgaDropdownList.style.display = 'none';
                return;
            }

            // Filter LGAs matching query
            const matches = lgaDataset.filter(item => {
                const nameMatch = item.name.toLowerCase().includes(query);
                const stateMatch = item.state_name && item.state_name.toLowerCase().includes(query);
                return nameMatch || stateMatch;
            }).slice(0, 15);

            if (matches.length === 0) {
                lgaDropdownList.innerHTML = `
                    <div style="padding: 12px; text-align: center; color: #64748B; font-size: 12.5px;">
                        No matching LGA found for "${e.target.value}"
                    </div>
                `;
                lgaDropdownList.style.display = 'block';
                return;
            }

            let html = '';
            matches.forEach(item => {
                html += `
                    <div class="vm-lga-dropdown-item" 
                         data-id="${item.id}" 
                         data-name="${item.name}" 
                         data-state="${item.state_name}" 
                         data-state-id="${item.state_id}">
                        <div>
                            <strong>${item.name}</strong>
                            <span class="vm-lga-dropdown-state">• ${item.state_name}</span>
                        </div>
                        <span class="vm-lga-coverage-badge">Select</span>
                    </div>
                `;
            });

            lgaDropdownList.innerHTML = html;
            lgaDropdownList.style.display = 'block';

            // Add click listeners to items
            lgaDropdownList.querySelectorAll('.vm-lga-dropdown-item').forEach(el => {
                el.addEventListener('click', () => {
                    const id = el.getAttribute('data-id');
                    const name = el.getAttribute('data-name');
                    const state = el.getAttribute('data-state');
                    const stateId = el.getAttribute('data-state-id');
                    selectLgaItem(id, name, state, stateId);
                });
            });
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!lgaSearchInput.contains(e.target) && !lgaDropdownList.contains(e.target)) {
                lgaDropdownList.style.display = 'none';
            }
        });
    }

    if (lgaClearBtn) {
        lgaClearBtn.addEventListener('click', () => {
            if (lgaSearchInput) {
                lgaSearchInput.value = '';
                lgaSearchInput.focus();
            }
            if (lgaClearBtn) lgaClearBtn.style.display = 'none';
            if (lgaDropdownList) lgaDropdownList.style.display = 'none';
        });
    }

    // Quick Chips click handling
    cityChips.forEach(chip => {
        chip.addEventListener('click', (e) => {
            e.preventDefault();
            const id = chip.getAttribute('data-lga-id');
            const city = chip.getAttribute('data-city');
            const state = chip.getAttribute('data-state');
            selectLgaItem(id, city, state, '');
        });
    });

    // AJAX Submission on "Done"
    const locForm = document.getElementById('vmLocationForm');
    if (locForm) {
        locForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('vmLocationModalSubmit');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Saving...</span>';
            }

            const formData = new FormData(locForm);

            fetch(locForm.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update all active city labels in DOM immediately
                    const cityLabels = document.querySelectorAll('.vm-active-city-label');
                    cityLabels.forEach(el => el.textContent = data.city);

                    closeLocationModal();
                    // Smooth page reload so proximity recommendations, nearby shops, and all products filter
                    window.location.reload();
                } else {
                    alert(data.message || 'Unable to update location');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span>Done</span>';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Done</span>';
                }
                window.location.reload();
            });
        });
    }

    // 7. Smooth Auth Modal Switching (Login <-> Register)
    document.querySelectorAll('[data-bs-target="#registerModal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const loginEl = document.getElementById('loginModal');
            if (loginEl && typeof bootstrap !== 'undefined') {
                const modalInstance = bootstrap.Modal.getInstance(loginEl);
                if (modalInstance) modalInstance.hide();
            }
        });
    });
    document.querySelectorAll('[data-bs-target="#loginModal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const regEl = document.getElementById('registerModal');
            if (regEl && typeof bootstrap !== 'undefined') {
                const modalInstance = bootstrap.Modal.getInstance(regEl);
                if (modalInstance) modalInstance.hide();
            }
        });
    });

    // 8. Instant feel: idle-preload hidden recommended-tab images + hover-prefetch links
    // Presentation only: warms the browser cache so the first tab toggle and
    // next-page clicks feel instant. No pricing, routing, or logic changes.
    function vmIdle(cb) {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(cb, { timeout: 2500 });
        } else {
            setTimeout(cb, 1200);
        }
    }

    // Preload images inside inactive tab panes once the browser is idle.
    vmIdle(() => {
        try {
            const hiddenImgs = document.querySelectorAll('#nav-tabContent .tab-pane:not(.active) img[loading="lazy"]');
            hiddenImgs.forEach(img => {
                if (img.dataset.vmPreloaded) return;
                img.dataset.vmPreloaded = '1';
                const pre = new Image();
                pre.decoding = 'async';
                pre.src = img.currentSrc || img.src;
            });
        } catch (e) { /* never break storefront */ }
    });

    // Prefetch same-origin GET links on hover (>120ms) or touchstart, once per URL.
    const vmPrefetched = new Set();
    function vmPrefetch(url) {
        try {
            if (!url || vmPrefetched.has(url)) return;
            const u = new URL(url, window.location.origin);
            if (u.origin !== window.location.origin) return;
            vmPrefetched.add(url);
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = u.href;
            document.head.appendChild(link);
        } catch (e) { /* ignore invalid URLs */ }
    }

    let vmHoverTimer = null;
    document.addEventListener('mouseover', (e) => {
        const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.dataset.vmNoPrefetch !== undefined) return;
        clearTimeout(vmHoverTimer);
        vmHoverTimer = setTimeout(() => vmPrefetch(a.href), 120);
    }, { passive: true });

    document.addEventListener('touchstart', (e) => {
        const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (a) vmPrefetch(a.href);
    }, { passive: true });
});

