/**
 * Victorious MARKET — Persistent Client Cart Engine
 * Handles multi-item cart in localStorage with reactivity,
 * cart drawer rendering, quantity adjustment, subtotal computation,
 * and 5% cashback estimation.
 */
(function (window) {
    'use strict';

    const STORAGE_KEY = 'vm_cart_items_v1';

    const vmCart = {
        items: [],

        init: function () {
            this.load();
            this.updateBadge();
            this.renderDrawer();
            this.bindEvents();
        },

        load: function () {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                this.items = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(this.items)) this.items = [];
            } catch (e) {
                console.error('Error loading cart from storage', e);
                this.items = [];
            }
        },

        save: function () {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(this.items));
            } catch (e) {
                console.error('Error saving cart to storage', e);
            }
            this.updateBadge();
            this.renderDrawer();
            window.dispatchEvent(new CustomEvent('vm-cart-updated', { detail: { items: this.items } }));
        },

        addItem: function (product, quantity = 1) {
            quantity = parseInt(quantity, 10) || 1;

            // Single-Shop Order Integrity Check
            if (this.items.length > 0 && product.shop_name) {
                const currentShop = this.items[0].shop_name;
                if (currentShop && currentShop !== product.shop_name) {
                    if (confirm(`Your cart already contains items from "${currentShop}". Victorious MARKET orders are fulfilled directly from individual merchant branches. Would you like to clear your cart and start an order from "${product.shop_name}" instead?`)) {
                        this.items = [];
                    } else {
                        return;
                    }
                }
            }

            const existingIndex = this.items.findIndex(item => item.id === product.id);

            if (existingIndex > -1) {
                this.items[existingIndex].quantity += quantity;
            } else {
                this.items.push({
                    id: product.id,
                    slug: product.slug || '',
                    name: product.name || 'Victorious Product',
                    unit_price: parseFloat(product.unit_price) || 0,
                    image: product.image || '/assets/img/placeholder/placeholder-1-1.png',
                    shop_name: product.shop_name || 'Verified Merchant',
                    shop_lga: product.shop_lga || 'Uyo',
                    quantity: quantity
                });
            }

            this.save();
            this.openDrawer();
            this.showToast(`Added "${product.name}" to cart`);
        },

        updateQty: function (productId, quantity) {
            quantity = parseInt(quantity, 10);
            const index = this.items.findIndex(item => item.id === productId);
            if (index > -1) {
                if (quantity <= 0) {
                    this.items.splice(index, 1);
                } else {
                    this.items[index].quantity = quantity;
                }
                this.save();
            }
        },

        removeItem: function (productId) {
            this.items = this.items.filter(item => item.id !== productId);
            this.save();
            this.showToast('Item removed from cart');
        },

        clear: function () {
            this.items = [];
            this.save();
        },

        getCount: function () {
            return this.items.reduce((sum, item) => sum + (item.quantity || 1), 0);
        },

        getSubtotal: function () {
            return this.items.reduce((sum, item) => sum + (item.unit_price * (item.quantity || 1)), 0);
        },

        getCashback: function () {
            return this.getSubtotal() * 0.05;
        },

        formatMoney: function (num) {
            return '₦' + (Number(num) || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        updateBadge: function () {
            const count = this.getCount();
            document.querySelectorAll('#vmHeaderCartBadge, #vmCartBadgeCount').forEach(badge => {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline-block' : (badge.id === 'vmCartBadgeCount' ? 'inline-block' : 'none');
            });
        },

        renderDrawer: function () {
            const container = document.getElementById('vmCartItemsContainer');
            const subtotalEl = document.getElementById('vmCartSubtotal');
            const cashbackEl = document.getElementById('vmCartCashbackEstimate');
            const checkoutBtn = document.getElementById('vmCartCheckoutBtn');
            if (!container) return;

            if (this.items.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 48px 16px; color: #94A3B8;">
                        <span style="font-size: 48px; display: block; margin-bottom: 12px;">🛍️</span>
                        <strong style="color: #1E293B; font-size: 16px; display: block; margin-bottom: 6px;">Your cart is empty</strong>
                        <p style="font-size: 13px; margin: 0 0 18px; color: #64748B;">Explore verified items with guaranteed in-shop inspection and 5% cashback rewards.</p>
                        <a href="/#catalog" onclick="vmCart.closeDrawer()" style="display: inline-block; background: #5E17EB; color: #FFFFFF; font-weight: 700; font-size: 13px; padding: 10px 20px; border-radius: 8px; text-decoration: none;">
                            Start Shopping
                        </a>
                    </div>
                `;
                if (subtotalEl) subtotalEl.textContent = '₦0.00';
                if (cashbackEl) cashbackEl.textContent = '₦0.00';
                if (checkoutBtn) {
                    checkoutBtn.style.opacity = '0.5';
                    checkoutBtn.style.pointerEvents = 'none';
                }
                return;
            }

            let html = '<div style="display: flex; flex-direction: column; gap: 14px;">';

            this.items.forEach(item => {
                const itemTotal = item.unit_price * item.quantity;
                html += `
                    <div style="display: flex; gap: 12px; padding: 12px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; align-items: center;">
                        <div style="width: 58px; height: 58px; border-radius: 8px; background: #FFFFFF; border: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                            <img src="${item.image}" alt="${item.name}" style="max-width: 90%; max-height: 90%; object-fit: contain;">
                        </div>
                        <div style="flex-grow: 1; min-width: 0;">
                            <h4 style="font-size: 13px; font-weight: 700; color: #0F172A; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                ${item.name}
                            </h4>
                            <div style="font-size: 11px; color: #64748B; margin-bottom: 6px;">
                                <span>🏬 ${item.shop_name}</span> &bull; <span>${item.shop_lga}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="font-size: 13.5px; color: #5E17EB;">${this.formatMoney(itemTotal)}</strong>
                                
                                <!-- Quantity Stepper -->
                                <div style="display: inline-flex; align-items: center; border: 1.5px solid #CBD5E1; border-radius: 6px; background: #FFFFFF; overflow: hidden;">
                                    <button type="button" onclick="vmCart.updateQty(${item.id}, ${item.quantity - 1})" style="width: 26px; height: 26px; border: none; background: #F1F5F9; font-weight: 700; cursor: pointer; color: #334155;">-</button>
                                    <span style="min-width: 28px; text-align: center; font-size: 12px; font-weight: 700; color: #0F172A;">${item.quantity}</span>
                                    <button type="button" onclick="vmCart.updateQty(${item.id}, ${item.quantity + 1})" style="width: 26px; height: 26px; border: none; background: #F1F5F9; font-weight: 700; cursor: pointer; color: #334155;">+</button>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="vmCart.removeItem(${item.id})" aria-label="Remove item" style="background: none; border: none; color: #EF4444; font-size: 15px; cursor: pointer; padding: 4px; border-radius: 4px; display: flex; align-items: center; justify-content: center;" title="Remove item">
                            🗑️
                        </button>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;

            if (subtotalEl) subtotalEl.textContent = this.formatMoney(this.getSubtotal());
            if (cashbackEl) cashbackEl.textContent = this.formatMoney(this.getCashback());
            if (checkoutBtn) {
                checkoutBtn.style.opacity = '1';
                checkoutBtn.style.pointerEvents = 'auto';
                checkoutBtn.href = '/checkout?product_id=' + this.items[0].id + '&quantity=' + this.items[0].quantity;
            }
        },

        openDrawer: function () {
            const drawer = document.getElementById('vmCartDrawer');
            const overlay = document.getElementById('vmCartOverlay');
            if (drawer) drawer.classList.add('active');
            if (overlay) overlay.classList.add('active');
        },

        closeDrawer: function () {
            const drawer = document.getElementById('vmCartDrawer');
            const overlay = document.getElementById('vmCartOverlay');
            if (drawer) drawer.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
        },

        showToast: function (msg) {
            let toast = document.getElementById('vmToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'vmToast';
                toast.style.cssText = 'position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(100px); background: #0F172A; color: #FFFFFF; padding: 12px 22px; border-radius: 999px; font-size: 13.5px; font-weight: 600; box-shadow: 0 10px 25px rgba(0,0,0,0.25); z-index: 2000; transition: transform 0.25s cubic-bezier(0.16,1,0.3,1); display: flex; align-items: center; gap: 8px; border: 1.5px solid #FFD700;';
                document.body.appendChild(toast);
            }
            toast.innerHTML = `<span>✨</span><span>${msg}</span>`;
            toast.style.transform = 'translateX(-50%) translateY(0)';
            clearTimeout(this._toastTimeout);
            this._toastTimeout = setTimeout(() => {
                toast.style.transform = 'translateX(-50%) translateY(100px)';
            }, 2500);
        },

        bindEvents: function () {
            const openBtn = document.getElementById('vmHeaderCartBtn');
            const closeBtn = document.getElementById('vmCartCloseBtn');
            const overlay = document.getElementById('vmCartOverlay');
            const checkoutBtn = document.getElementById('vmCartCheckoutBtn');

            if (openBtn) openBtn.addEventListener('click', () => this.openDrawer());
            if (closeBtn) closeBtn.addEventListener('click', () => this.closeDrawer());
            if (overlay) overlay.addEventListener('click', () => this.closeDrawer());

            // Intercept checkout for unauthenticated visitors
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', (e) => {
                    if (!window.vmIsCustomerAuth) {
                        e.preventDefault();
                        const targetUrl = checkoutBtn.getAttribute('href') || '/checkout';
                        if (window.vmOpenAuthModal) {
                            window.vmOpenAuthModal({
                                action: 'checkout',
                                redirectUrl: targetUrl,
                                title: 'Sign In to Complete Checkout',
                                message: 'Your items are saved in your cart! Only customers with accounts can place orders, verify delivery OTPs, and earn 5% cashback.'
                            });
                        } else {
                            window.location.href = '/login?return_to=' + encodeURIComponent(targetUrl);
                        }
                        return false;
                    }
                });
            }

            // Delegate "Add to Cart" clicks from any button with .vm-btn-add-cart
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.vm-btn-add-cart');
                if (btn) {
                    e.preventDefault();
                    const product = {
                        id: parseInt(btn.dataset.id, 10),
                        name: btn.dataset.name,
                        unit_price: parseFloat(btn.dataset.price),
                        image: btn.dataset.image,
                        shop_name: btn.dataset.shop,
                        shop_lga: btn.dataset.lga,
                        slug: btn.dataset.slug
                    };
                    const qtyInput = document.getElementById('vmProductQtyInput');
                    const qty = qtyInput ? parseInt(qtyInput.value, 10) : 1;
                    this.addItem(product, qty);
                }
            });
        }
    };

    window.vmCart = vmCart;
    document.addEventListener('DOMContentLoaded', () => vmCart.init());
})(window);
