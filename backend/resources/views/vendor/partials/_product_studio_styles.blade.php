    <!-- Facebook Marketplace Listing Studio CSS Design System -->
    <style>
        .fb-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(8px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            overflow: hidden;
            animation: fbFadeIn 0.2s ease-out;
        }

        @keyframes fbFadeIn {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }

        .fb-studio-container {
            background: #F0F2F5;
            width: 100%;
            max-width: 1260px;
            height: 94vh;
            max-height: 94vh;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.15);
            overflow: hidden;
        }

        .fb-studio-topbar {
            background: #FFFFFF;
            border-bottom: 1px solid #E4E6EB;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 64px;
            flex-shrink: 0;
        }

        .fb-topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fb-market-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #E7F3FF;
            color: #1877F2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fb-topbar-title {
            font-size: 17px;
            font-weight: 800;
            color: #050505;
            letter-spacing: -0.3px;
        }

        .fb-topbar-sub {
            font-size: 12px;
            color: #65676B;
        }

        .fb-mobile-tabs {
            display: none;
            background: #E4E6EB;
            padding: 4px;
            border-radius: 10px;
            gap: 4px;
        }

        .fb-tab-btn {
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 700;
            color: #65676B;
            padding: 7px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .fb-tab-btn.active {
            background: #FFFFFF;
            color: #1877F2;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .fb-badge-dot {
            width: 8px;
            height: 8px;
            background: #10B981;
            border-radius: 50%;
            display: inline-block;
        }

        .fb-close-btn {
            border: none;
            background: #E4E6EB;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            color: #050505;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }

        .fb-close-btn:hover {
            background: #D8DADF;
        }

        /* Studio Split Screen Grid */
        .fb-studio-body {
            display: grid;
            grid-template-columns: 560px 1fr;
            height: calc(100% - 64px);
            overflow: hidden;
        }

        /* Left Column: Form Studio */
        .fb-col-form {
            background: #FFFFFF;
            height: 100%;
            overflow-y: auto;
            padding: 24px 28px;
            border-right: 1px solid #E4E6EB;
        }

        .fb-merchant-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: #F0F2F5;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .fb-merchant-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1877F2, #0A58CA);
            color: white;
            font-weight: 800;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(24, 119, 242, 0.35);
        }

        .fb-merchant-name {
            font-size: 14px;
            font-weight: 800;
            color: #050505;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .fb-verified-check {
            width: 16px;
            height: 16px;
            background: #1877F2;
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 900;
        }

        .fb-merchant-scope {
            font-size: 12px;
            color: #65676B;
            margin-top: 2px;
        }

        .fb-section-card {
            background: #FFFFFF;
            border: 1px solid #E4E6EB;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .fb-section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .fb-section-title {
            font-size: 15px;
            font-weight: 800;
            color: #050505;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fb-badge-compulsory {
            font-size: 10px;
            font-weight: 900;
            color: #DC2626;
            background: #FEE2E2;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }

        .fb-section-hint {
            font-size: 12px;
            color: #65676B;
            margin: 4px 0 0;
        }

        .fb-photo-count {
            font-size: 12px;
            font-weight: 800;
            color: #1877F2;
            background: #E7F3FF;
            padding: 3px 10px;
            border-radius: 12px;
        }

        /* Dropzone */
        .fb-dropzone {
            border: 2px dashed #CBD5E1;
            border-radius: 12px;
            background: #F8FAFC;
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .fb-dropzone:hover, .fb-dropzone.dragover {
            border-color: #1877F2;
            background: #EBF5FF;
        }

        .fb-dropzone-icon {
            margin-bottom: 8px;
        }

        .fb-dropzone-text {
            font-size: 14.5px;
            font-weight: 800;
            color: #1877F2;
        }

        .fb-dropzone-sub {
            font-size: 12px;
            color: #65676B;
            margin-top: 3px;
        }

        .fb-dropzone-limits {
            font-size: 11px;
            color: #94A3B8;
            margin-top: 4px;
        }

        .fb-inline-error {
            background: #FEF2F2;
            border-left: 3.5px solid #EF4444;
            padding: 9px 12px;
            border-radius: 6px;
            color: #B91C1C;
            font-size: 12px;
            margin-top: 10px;
        }

        /* Dynamic Photo Gallery Strip */
        .fb-thumbnail-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .fb-thumb-card {
            position: relative;
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #E4E6EB;
            cursor: pointer;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }

        .fb-thumb-card.active {
            border-color: #1877F2;
            box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.35);
        }

        .fb-thumb-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .fb-thumb-badge-cover {
            position: absolute;
            bottom: 4px;
            left: 4px;
            background: rgba(0, 0, 0, 0.75);
            color: #FBBF24;
            font-size: 9.5px;
            font-weight: 800;
            padding: 2px 5px;
            border-radius: 4px;
            backdrop-filter: blur(2px);
        }

        .fb-thumb-btn-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(0, 0, 0, 0.65);
            color: white;
            border: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .fb-thumb-btn-remove:hover {
            background: #EF4444;
        }

        .fb-thumb-add-tile {
            aspect-ratio: 1;
            border: 2px dashed #CBD5E1;
            border-radius: 8px;
            background: #F8FAFC;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #1877F2;
            font-weight: 700;
            font-size: 11px;
            gap: 4px;
            transition: all 0.15s ease;
        }

        .fb-thumb-add-tile:hover {
            border-color: #1877F2;
            background: #EBF5FF;
        }

        /* Form Inputs */
        .fb-field-group {
            margin-bottom: 14px;
        }

        .fb-field-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #050505;
            margin-bottom: 5px;
        }

        .fb-req {
            color: #EF4444;
            font-weight: 900;
        }

        .fb-opt {
            font-size: 11.5px;
            font-weight: 400;
            color: #65676B;
        }

        .fb-input, .fb-select, .fb-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #CED0D4;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            color: #050505;
            background: #FFFFFF;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            box-sizing: border-box;
        }

        .fb-input:focus, .fb-select:focus, .fb-textarea:focus {
            outline: none;
            border-color: #1877F2;
            box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.15);
        }

        .fb-input.error, .fb-select.error {
            border-color: #EF4444 !important;
            background-color: #FEF2F2;
        }

        .fb-input.valid {
            border-color: #10B981 !important;
        }

        .fb-field-hint {
            font-size: 11.5px;
            color: #65676B;
            margin-top: 4px;
        }

        .fb-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Price Slash Alert */
        .fb-slash-alert {
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .fb-slash-alert.error {
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
        }

        .fb-slash-alert.success {
            background: #F0FDF4;
            border: 1px solid #86EFAC;
            color: #065F46;
        }

        /* Radio Choice Cards */
        .fb-radio-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .fb-radio-card {
            border: 1.5px solid #CED0D4;
            background: #FFFFFF;
            border-radius: 10px;
            padding: 10px 12px;
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            transition: all 0.15s ease;
        }

        .fb-radio-card input {
            margin-top: 3px;
        }

        .fb-radio-card.active {
            border-color: #1877F2;
            background: #F0F7FF;
        }

        .fb-radio-title {
            font-size: 13px;
            font-weight: 800;
        }

        .fb-radio-desc {
            font-size: 11px;
            color: #65676B;
            margin-top: 2px;
        }

        .fb-cargo-notice {
            margin-top: 8px;
            font-size: 11.5px;
            color: #92400E;
            background: #FEF3C7;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 600;
            border: 1px solid #FDE68A;
        }

        /* Form Footer / Submit */
        .fb-form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid #E4E6EB;
            margin-top: 18px;
        }

        .fb-btn-cancel {
            border: none;
            background: #E4E6EB;
            color: #050505;
            padding: 11px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .fb-btn-cancel:hover {
            background: #D8DADF;
        }

        .fb-btn-publish {
            border: none;
            background: #1877F2;
            color: #FFFFFF;
            padding: 11px 26px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(24, 119, 242, 0.35);
            transition: background 0.15s ease, transform 0.1s ease;
        }

        .fb-btn-publish:hover:not(:disabled) {
            background: #166FE5;
            transform: translateY(-1px);
        }

        .fb-btn-publish:disabled {
            background: #94A3B8 !important;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* RIGHT COLUMN: Live Facebook Preview */
        .fb-col-preview {
            background: #F0F2F5;
            height: 100%;
            overflow-y: auto;
            padding: 24px 28px;
            display: flex;
            justify-content: center;
        }

        .fb-preview-sticky-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .fb-mock-top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 12px;
            color: #65676B;
        }

        .fb-mock-crumb {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }

        .fb-mock-badge-live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 800;
            color: #047857;
            background: #D1FAE5;
            padding: 3px 10px;
            border-radius: 12px;
        }

        .fb-pulse-dot {
            width: 7px;
            height: 7px;
            background: #10B981;
            border-radius: 50%;
            animation: fbPulse 1.5s infinite;
        }

        @keyframes fbPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }

        /* Facebook Listing Card */
        .fb-listing-card {
            background: #FFFFFF;
            border-radius: 14px;
            border: 1px solid #CED0D4;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .fb-card-hero {
            position: relative;
            aspect-ratio: 4/3;
            background: #E4E6EB;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fb-card-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .fb-hero-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
        }

        .fb-hero-badges-top {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            display: flex;
            justify-content: space-between;
            pointer-events: none;
        }

        .fb-pill-condition {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
        }

        .fb-pill-condition.new {
            background: rgba(16, 185, 129, 0.9);
            color: #FFFFFF;
        }

        .fb-pill-condition.used {
            background: rgba(79, 70, 229, 0.9);
            color: #FFFFFF;
        }

        .fb-pill-tier {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
        }

        .fb-pill-tier.bike {
            background: rgba(2, 132, 199, 0.9);
            color: #FFFFFF;
        }

        .fb-pill-tier.keke {
            background: rgba(217, 119, 6, 0.9);
            color: #FFFFFF;
        }

        .fb-hero-dots {
            position: absolute;
            bottom: 8px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 6px;
        }

        .fb-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transition: all 0.15s ease;
        }

        .fb-dot.active {
            background: #FFFFFF;
            transform: scale(1.3);
        }

        /* Card Content */
        .fb-card-content {
            padding: 16px 18px 20px;
        }

        .fb-prev-price-row {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 4px;
        }

        .fb-prev-main-price {
            font-size: 24px;
            font-weight: 800;
            color: #050505;
            letter-spacing: -0.5px;
        }

        .fb-prev-slash-price {
            font-size: 15px;
            color: #65676B;
            text-decoration: line-through;
            font-weight: 600;
        }

        .fb-prev-discount-pill {
            font-size: 11.5px;
            font-weight: 800;
            color: #047857;
            background: #D1FAE5;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .fb-prev-title {
            font-size: 18px;
            font-weight: 700;
            color: #050505;
            line-height: 1.35;
            margin-bottom: 4px;
            word-break: break-word;
        }

        .fb-prev-meta {
            font-size: 12.5px;
            color: #65676B;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 14px;
        }

        /* Action Buttons */
        .fb-prev-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
        }

        .fb-btn-fb-message {
            flex: 1;
            background: #1877F2;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .fb-action-icon-btns {
            display: flex;
            gap: 6px;
        }

        .fb-icon-btn {
            border: none;
            background: #E4E6EB;
            color: #050505;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Seller Info */
        .fb-prev-seller-block {
            border-top: 1px solid #E4E6EB;
            padding-top: 14px;
            margin-bottom: 14px;
        }

        .fb-prev-seller-header {
            font-size: 13px;
            font-weight: 800;
            color: #050505;
            margin-bottom: 8px;
        }

        .fb-prev-seller-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fb-prev-seller-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #1877F2;
            color: white;
            font-weight: 800;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fb-prev-seller-name {
            font-size: 13.5px;
            font-weight: 800;
            color: #050505;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .fb-prev-seller-sub {
            font-size: 11.5px;
            color: #65676B;
        }

        .fb-prev-seller-rating {
            font-size: 11px;
            font-weight: 700;
            color: #B45309;
            margin-top: 2px;
        }

        /* Details */
        .fb-prev-details-block {
            border-top: 1px solid #E4E6EB;
            padding-top: 14px;
            margin-bottom: 14px;
        }

        .fb-prev-details-header {
            font-size: 13px;
            font-weight: 800;
            color: #050505;
            margin-bottom: 8px;
        }

        .fb-prev-detail-item {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            padding: 4px 0;
        }

        .fb-detail-label {
            color: #65676B;
            font-weight: 600;
        }

        .fb-detail-value {
            color: #050505;
            font-weight: 700;
            text-align: right;
        }

        /* Description */
        .fb-prev-desc-block {
            border-top: 1px solid #E4E6EB;
            padding-top: 14px;
            margin-bottom: 14px;
        }

        .fb-prev-desc-header {
            font-size: 13px;
            font-weight: 800;
            color: #050505;
            margin-bottom: 6px;
        }

        .fb-prev-desc-text {
            font-size: 12.5px;
            color: #374151;
            line-height: 1.5;
            white-space: pre-line;
            word-break: break-word;
        }

        /* Escrow Shield */
        .fb-prev-escrow-badge {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 8px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11.5px;
            color: #065F46;
        }

        /* RESPONSIVENESS ACROSS ALL POSSIBLE SCREENS */
        @media (max-width: 991px) {
            .fb-modal-backdrop {
                padding: 0;
            }

            .fb-studio-container {
                max-width: 100vw;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
            }

            .fb-mobile-tabs {
                display: flex;
            }

            .fb-studio-body {
                display: block;
                height: calc(100% - 64px);
                position: relative;
            }

            .fb-col-form {
                width: 100%;
                height: 100%;
                display: block;
                box-sizing: border-box;
                padding: 20px 16px 80px;
            }

            .fb-col-preview {
                width: 100%;
                height: 100%;
                display: none;
                box-sizing: border-box;
                padding: 20px 16px 80px;
            }

            .fb-col-preview.active-tab {
                display: flex;
            }

            .fb-col-form.hide-tab {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .fb-topbar-sub {
                display: none;
            }

            .fb-grid-2 {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .fb-radio-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .fb-merchant-pill {
                padding: 10px;
            }
        }
    </style>

