@extends('layouts.dashboard')

@section('title', 'Notification Orchestration & Gateway Control Center')
@section('role_title', 'Super Admin')
@section('page_title', 'Notifications & Firebase')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🔔</span>
                <span>Multi-Channel Notification &amp; Firebase Control Tower</span>
            </h1>
            <p class="page-header-subtitle">
                Centrally manage Firebase Cloud Messaging (FCM HTTP v1), Termii SMS failover, real-time in-app alerts, registered mobile terminals, and custom message wording.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <a href="#test-push-card" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 6px 14px;">
                Test Push 🚀
            </a>
            <a href="#test-sms-card" class="btn-dash btn-dash-blue" style="font-size: 12px; padding: 6px 14px;">
                Test SMS 💬
            </a>
            <a href="#devices-card" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 14px;">
                Device Terminals 📱
            </a>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-bottom: 20px; padding: 14px 18px; background: rgba(16, 185, 129, 0.1); border: 1.5px solid #10B981; border-radius: 10px; color: #065F46; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">✅</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div style="margin-bottom: 20px; padding: 14px 18px; background: rgba(239, 68, 68, 0.1); border: 1.5px solid #EF4444; border-radius: 10px; color: #991B1B; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 18px;">⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Metrics Cards Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="dash-card" style="padding: 18px; border-left: 4px solid #3B82F6;">
            <div style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Active Registered Terminals</div>
            <div style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 4px 0;">{{ number_format($activeTokensCount) }}</div>
            <div style="font-size: 11.5px; color: #3B82F6; font-weight: 600;">📱 Live multi-device push links</div>
        </div>

        <div class="dash-card" style="padding: 18px; border-left: 4px solid #10B981;">
            <div style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Couriers on Push Radar</div>
            <div style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 4px 0;">{{ number_format($ridersWithTokensCount) }}</div>
            <div style="font-size: 11.5px; color: #10B981; font-weight: 600;">🏍️ Active dispatch terminals</div>
        </div>

        <div class="dash-card" style="padding: 18px; border-left: 4px solid #8B5CF6;">
            <div style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Merchants Connected</div>
            <div style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 4px 0;">{{ number_format($vendorsWithTokensCount) }}</div>
            <div style="font-size: 11.5px; color: #8B5CF6; font-weight: 600;">🏬 Verified store terminals</div>
        </div>

        <div class="dash-card" style="padding: 18px; border-left: 4px solid #06B6D4;">
            <div style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Customers Linked</div>
            <div style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 4px 0;">{{ number_format($customersWithTokensCount) }}</div>
            <div style="font-size: 11.5px; color: #06B6D4; font-weight: 600;">🛍️ Mobile app shoppers</div>
        </div>
    </div>

    <!-- Master Channels & Gateway Credentials Form -->
    <form action="{{ route('admin.notifications.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="dash-card" style="margin-bottom: 24px; border: 1.5px solid rgba(59, 130, 246, 0.25);">
            <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(239, 246, 255, 0.5) 100%);">
                <div class="dash-card-title">
                    <span style="font-size: 20px;">⚙️</span>
                    <div>
                        <span style="font-weight: 800; color: #0F172A;">Master Channels &amp; Gateway Credentials</span>
                        <div class="dash-card-subtitle">Centrally configure Firebase FCM and Termii SMS directly from the database without touching server files.</div>
                    </div>
                </div>
                <span class="dash-badge dash-badge-blue">Database Configured</span>
            </div>

            <div style="padding: 24px;">
                <!-- Master Switches Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #E2E8F0;">
                    <label style="display: flex; align-items: flex-start; gap: 12px; background: #F8FAFC; padding: 14px; border-radius: 10px; border: 1px solid #E2E8F0; cursor: pointer;">
                        <input type="checkbox" name="fcm_enabled" value="1" {{ $settings['fcm_enabled'] ? 'checked' : '' }} style="margin-top: 3px; transform: scale(1.2);">
                        <div>
                            <div style="font-weight: 700; color: #0F172A; font-size: 13.5px;">🔥 Firebase Push (FCM)</div>
                            <div style="font-size: 11.5px; color: #64748B;">Instant push alerts to rider, vendor &amp; customer devices.</div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 12px; background: #F8FAFC; padding: 14px; border-radius: 10px; border: 1px solid #E2E8F0; cursor: pointer;">
                        <input type="checkbox" name="sms_enabled" value="1" {{ $settings['sms_enabled'] ? 'checked' : '' }} style="margin-top: 3px; transform: scale(1.2);">
                        <div>
                            <div style="font-weight: 700; color: #0F172A; font-size: 13.5px;">💬 SMS Gateway (Termii)</div>
                            <div style="font-size: 11.5px; color: #64748B;">Mission-critical OTPs and delivery doorstep text alerts.</div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 12px; background: #F8FAFC; padding: 14px; border-radius: 10px; border: 1px solid #E2E8F0; cursor: pointer;">
                        <input type="checkbox" name="inapp_enabled" value="1" {{ $settings['inapp_enabled'] ? 'checked' : '' }} style="margin-top: 3px; transform: scale(1.2);">
                        <div>
                            <div style="font-weight: 700; color: #0F172A; font-size: 13.5px;">🔔 In-App Bell &amp; Drawer</div>
                            <div style="font-size: 11.5px; color: #64748B;">Real-time web portal notifications with unread badge.</div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 12px; background: #F8FAFC; padding: 14px; border-radius: 10px; border: 1px solid #E2E8F0; cursor: pointer;">
                        <input type="checkbox" name="whatsapp_enabled" value="1" {{ $settings['whatsapp_enabled'] ? 'checked' : '' }} style="margin-top: 3px; transform: scale(1.2);">
                        <div>
                            <div style="font-weight: 700; color: #0F172A; font-size: 13.5px;">🟢 WhatsApp Business</div>
                            <div style="font-size: 11.5px; color: #64748B;">High-priority dispatches for merchants &amp; corporate fleet desks.</div>
                        </div>
                    </label>
                </div>

                <!-- Section A: Termii SMS Gateway Configuration -->
                <div style="margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid #E2E8F0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">💬</span>
                            <span style="font-weight: 800; font-size: 15px; color: #0F172A;">Termii Nigeria SMS Gateway Configuration</span>
                        </div>
                        <span style="font-size: 11.5px; color: #059669; font-weight: 700; background: #D1FAE5; padding: 3px 8px; border-radius: 6px;">
                            Auto-loads from Admin Portal
                        </span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Termii API Key (Live Key)
                            </label>
                            <input type="password" name="termii_api_key" value="{{ old('termii_api_key', $settings['termii_api_key']) }}"
                                   placeholder="SEC_... or your Termii Live API Key"
                                   style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                            <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Configured securely in the database. Overrides .env on production.</div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Registered Sender ID
                            </label>
                            <input type="text" name="termii_sender_id" value="{{ old('termii_sender_id', $settings['termii_sender_id']) }}"
                                   placeholder="e.g. VMARKET"
                                   style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                            <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Approved 11-character alphanumeric sender name on Termii dashboard.</div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Delivery Route Channel
                            </label>
                            <select name="termii_channel" style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                                <option value="dnd" {{ $settings['termii_channel'] === 'dnd' ? 'selected' : '' }}>DND Route (Guaranteed Handshake PINs)</option>
                                <option value="generic" {{ $settings['termii_channel'] === 'generic' ? 'selected' : '' }}>Generic Route (Standard SMS)</option>
                                <option value="whatsapp" {{ $settings['termii_channel'] === 'whatsapp' ? 'selected' : '' }}>WhatsApp Messaging Route</option>
                            </select>
                            <div style="font-size: 11px; color: #64748B; margin-top: 4px;">DND route delivers OTPs across all Nigerian telecom networks.</div>
                        </div>
                    </div>
                </div>

                <!-- Section B: Google Firebase Service Account Credentials -->
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 18px;">🔥</span>
                            <span style="font-weight: 800; font-size: 15px; color: #0F172A;">Google Firebase Service Account Credentials</span>
                        </div>
                        <span style="font-size: 11.5px; color: #D97706; font-weight: 700; background: #FEF3C7; padding: 3px 8px; border-radius: 6px;">
                            FCM HTTP v1 (OAuth2 RSA SHA-256)
                        </span>
                    </div>

                    <!-- Direct JSON File Upload Option -->
                    <div style="background: rgba(245, 158, 11, 0.08); border: 1.5px dashed #F59E0B; border-radius: 10px; padding: 16px; margin-bottom: 18px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <div style="font-weight: 700; color: #92400E; font-size: 13px;">
                                    📁 Direct Upload: Firebase Service Account JSON
                                </div>
                                <div style="font-size: 11.5px; color: #B45309;">
                                    Upload your downloaded service-account-file.json to automatically parse and configure Project ID, Client Email, and Private Key safely without copy-paste formatting errors.
                                </div>
                            </div>
                            <input type="file" name="firebase_service_account_json" accept=".json" style="font-size: 12px; color: #334155;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Firebase Project ID
                            </label>
                            <input type="text" name="firebase_project_id" value="{{ old('firebase_project_id', $settings['firebase_project_id']) }}"
                                   placeholder="e.g. victorious-market-fcm"
                                   style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Client Email (IAM Service Account)
                            </label>
                            <input type="email" name="firebase_client_email" value="{{ old('firebase_client_email', $settings['firebase_client_email']) }}"
                                   placeholder="firebase-adminsdk-xxxxx@project.iam.gserviceaccount.com"
                                   style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                                Private Key (PEM RSA 2048-bit)
                            </label>
                            <textarea name="firebase_private_key" rows="4" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"
                                      style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-family: monospace; font-size: 11.5px; resize: vertical;">{{ old('firebase_private_key', $settings['firebase_private_key']) }}</textarea>
                            <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Encrypted and stored in system settings.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event-by-Event Multi-Channel Routing Matrix Card -->
        <div class="dash-card" style="margin-bottom: 24px; border: 1.5px solid rgba(139, 92, 246, 0.25);">
            <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(245, 243, 255, 0.5) 100%);">
                <div class="dash-card-title">
                    <span style="font-size: 20px;">🎛️</span>
                    <div>
                        <span style="font-weight: 800; color: #0F172A;">Comprehensive Event-by-Event Routing Matrix</span>
                        <div class="dash-card-subtitle">Granularly control which communication channels trigger across all 15 business lifecycle events.</div>
                    </div>
                </div>
                <span class="dash-badge dash-badge-purple">Cost &amp; Channel Rules</span>
            </div>

            <div style="padding: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #E2E8F0; text-align: left; background: #F8FAFC;">
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569;">Business Life-Cycle Event</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: center;">Target Persona</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: center;">🔥 FCM Push</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: center;">💬 SMS (Termii)</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: center;">🔔 In-App Bell</th>
                            <th style="padding: 12px 16px; font-weight: 700; color: #475569; text-align: center;">🟢 WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $eventsConfig = [
                                'order_placed' => ['name' => '1. New Order Placed', 'desc' => 'Customer purchase receipt & Merchant prep alert', 'persona' => 'Customer, Vendor'],
                                'vendor_handover_ready' => ['name' => '2. Merchant Ready for Handover', 'desc' => 'Courier dispatched to collect sealed package', 'persona' => 'Assigned Courier'],
                                'out_for_delivery' => ['name' => '3. Package Out For Delivery', 'desc' => 'Courier custody confirmed; package in transit', 'persona' => 'Customer, Vendor'],
                                'doorstep_arrived' => ['name' => '4. Courier Arrived at Doorstep', 'desc' => 'Urgent arrival alert with 6-digit Delivery PIN', 'persona' => 'Customer'],
                                'delivery_completed' => ['name' => '5. Doorstep Handover Sealed', 'desc' => 'Instant Cashback credited; Fleet & Vendor escrow', 'persona' => 'Customer, Fleet, Vendor'],
                                'doorstep_return_dispute' => ['name' => '6. Doorstep Return Initiated', 'desc' => 'Customer refused item; courier returning parcel', 'persona' => 'Merchant, Super Admin'],
                                'vendor_return_otp' => ['name' => '7. Return Handshake Counter PIN', 'desc' => 'Courier at merchant shop; return OTP required', 'persona' => 'Merchant Counter'],
                                'return_accepted' => ['name' => '8. Return Accepted & Refunded', 'desc' => 'Merchant accepted return; customer wallet credited', 'persona' => 'Customer, Fleet'],
                                'dispute_escalated' => ['name' => '9. Return Dispute Escalated', 'desc' => 'Merchant rejected return; sent to arbitration', 'persona' => 'Customer, Super Admin'],
                                'dispute_arbitration' => ['name' => '10. Arbitration Ruling Issued', 'desc' => 'Super admin resolved return dispute verdict', 'persona' => 'Merchant, Customer'],
                                'payout_requested' => ['name' => '11. Payout Withdrawal Requested', 'desc' => 'Merchant or Fleet filed payout withdrawal', 'persona' => 'Super Admin, Requester'],
                                'payout_disbursed' => ['name' => '12. Payout Transfer Disbursed', 'desc' => 'Bank transfer sent via Paystack NUBAN gateway', 'persona' => 'Merchant, Fleet'],
                                'payout_rejected' => ['name' => '13. Payout Request Rejected', 'desc' => 'Super admin rejected payout; funds restored', 'persona' => 'Merchant, Fleet'],
                                'wallet_credit' => ['name' => '14. Sovereign Wallet Credited', 'desc' => 'Instant customer refund or cashback credited', 'persona' => 'Customer'],
                                'security_bank_cooldown' => ['name' => '15. Bank Update 48h Cooldown', 'desc' => 'Security freeze notice on bank details change', 'persona' => 'Merchant, Fleet'],
                            ];
                        @endphp

                        @foreach($eventsConfig as $eventKey => $meta)
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 14px 16px;">
                                    <div style="font-weight: 700; color: #0F172A;">{{ $meta['name'] }}</div>
                                    <div style="font-size: 11.5px; color: #64748B;">{{ $meta['desc'] }}</div>
                                </td>
                                <td style="padding: 14px 16px; text-align: center;">
                                    <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: #E2E8F0; color: #334155;">{{ $meta['persona'] }}</span>
                                </td>
                                <td style="padding: 14px 16px; text-align: center;">
                                    <input type="checkbox" name="matrix[{{ $eventKey }}][fcm]" value="1"
                                           {{ !empty($settings['matrix'][$eventKey]['fcm']) ? 'checked' : '' }} style="transform: scale(1.2);">
                                </td>
                                <td style="padding: 14px 16px; text-align: center;">
                                    <input type="checkbox" name="matrix[{{ $eventKey }}][sms]" value="1"
                                           {{ !empty($settings['matrix'][$eventKey]['sms']) ? 'checked' : '' }} style="transform: scale(1.2);">
                                </td>
                                <td style="padding: 14px 16px; text-align: center;">
                                    <input type="checkbox" name="matrix[{{ $eventKey }}][in_app]" value="1"
                                           {{ !empty($settings['matrix'][$eventKey]['in_app']) ? 'checked' : '' }} style="transform: scale(1.2);">
                                </td>
                                <td style="padding: 14px 16px; text-align: center;">
                                    <input type="checkbox" name="matrix[{{ $eventKey }}][whatsapp]" value="1"
                                           {{ !empty($settings['matrix'][$eventKey]['whatsapp']) ? 'checked' : '' }} style="transform: scale(1.2);">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Customizable Message Templates & Wording Console (Inspired by Reference v1) -->
        <div class="dash-card" style="margin-bottom: 24px; border: 1.5px solid rgba(16, 185, 129, 0.25);">
            <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(236, 253, 245, 0.5) 100%);">
                <div class="dash-card-title">
                    <span style="font-size: 20px;">📝</span>
                    <div>
                        <span style="font-weight: 800; color: #0F172A;">Notification Templates &amp; Custom Message Wording</span>
                        <div class="dash-card-subtitle">Customize titles and message bodies with dynamic tags: <code style="background: #E2E8F0; padding: 2px 4px; border-radius: 4px;">{order_number}</code>, <code style="background: #E2E8F0; padding: 2px 4px; border-radius: 4px;">{amount}</code>, <code style="background: #E2E8F0; padding: 2px 4px; border-radius: 4px;">{otp}</code>, <code style="background: #E2E8F0; padding: 2px 4px; border-radius: 4px;">{shop_name}</code>, <code style="background: #E2E8F0; padding: 2px 4px; border-radius: 4px;">{rider_name}</code>.</div>
                    </div>
                </div>
                <span class="dash-badge dash-badge-green">Reference v1 Aligned</span>
            </div>

            <div style="padding: 24px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
                    <!-- Customer: Order Confirmed -->
                    <div style="background: #F8FAFC; padding: 16px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; margin-bottom: 8px;">🛍️ Customer: Order Confirmed</div>
                        <input type="text" name="templates[order_placed][customer][title]"
                               value="{{ $settings['templates']['order_placed']['customer']['title'] ?? 'Order Confirmed! #{order_number}' }}"
                               placeholder="Title Template"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; margin-bottom: 6px;">
                        <textarea name="templates[order_placed][customer][body]" rows="2"
                                  placeholder="Body Template"
                                  style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px;">{{ $settings['templates']['order_placed']['customer']['body'] ?? 'Total: ₦{amount}. Your order is being prepared.' }}</textarea>
                    </div>

                    <!-- Courier: Package Ready for Pickup -->
                    <div style="background: #F8FAFC; padding: 16px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; margin-bottom: 8px;">🏍️ Courier: Package Ready for Pickup</div>
                        <input type="text" name="templates[vendor_handover_ready][courier][title]"
                               value="{{ $settings['templates']['vendor_handover_ready']['courier']['title'] ?? 'Package Ready for Pickup!' }}"
                               placeholder="Title Template"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; margin-bottom: 6px;">
                        <textarea name="templates[vendor_handover_ready][courier][body]" rows="2"
                                  placeholder="Body Template"
                                  style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px;">{{ $settings['templates']['vendor_handover_ready']['courier']['body'] ?? 'Order #{order_number} is ready at {shop_name}. Tap to view manifest.' }}</textarea>
                    </div>

                    <!-- Customer: Doorstep Arrival PIN -->
                    <div style="background: #F8FAFC; padding: 16px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; margin-bottom: 8px;">🚪 Customer: Doorstep Courier Arrival</div>
                        <input type="text" name="templates[doorstep_arrived][customer][title]"
                               value="{{ $settings['templates']['doorstep_arrived']['customer']['title'] ?? 'Courier at Your Doorstep!' }}"
                               placeholder="Title Template"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; margin-bottom: 6px;">
                        <textarea name="templates[doorstep_arrived][customer][body]" rows="2"
                                  placeholder="Body Template"
                                  style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px;">{{ $settings['templates']['doorstep_arrived']['customer']['body'] ?? 'Your courier has arrived with Order #{order_number}. PIN: {otp}' }}</textarea>
                    </div>

                    <!-- Vendor: Payout Disbursed -->
                    <div style="background: #F8FAFC; padding: 16px; border-radius: 10px; border: 1px solid #E2E8F0;">
                        <div style="font-weight: 700; color: #0F172A; font-size: 13px; margin-bottom: 8px;">💰 Merchant: Earnings Disbursed</div>
                        <input type="text" name="templates[payout_disbursed][vendor][title]"
                               value="{{ $settings['templates']['payout_disbursed']['vendor']['title'] ?? 'Earnings Disbursed! ₦{amount}' }}"
                               placeholder="Title Template"
                               style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; margin-bottom: 6px;">
                        <textarea name="templates[payout_disbursed][vendor][body]" rows="2"
                                  placeholder="Body Template"
                                  style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px;">{{ $settings['templates']['payout_disbursed']['vendor']['body'] ?? 'Bank transfer of ₦{amount} to {bank_name} confirmed.' }}</textarea>
                    </div>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 12px 32px; font-size: 14px; font-weight: 700;">
                        💾 Save All Notification Settings &amp; Rules
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Push & SMS Live Diagnostic Tools Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <!-- Push Diagnostic Tester Card -->
        <div class="dash-card" id="test-push-card" style="border: 1.5px solid rgba(16, 185, 129, 0.25);">
            <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(236, 253, 245, 0.5) 100%);">
                <div class="dash-card-title">
                    <span style="font-size: 20px;">🚀</span>
                    <div>
                        <span style="font-weight: 800; color: #0F172A;">FCM Push Diagnostic &amp; Live Terminal Tester</span>
                        <div class="dash-card-subtitle">Dispatch a live test notification to any registered courier or raw token.</div>
                    </div>
                </div>
                <span class="dash-badge dash-badge-green">Live Push</span>
            </div>

            <form action="{{ route('admin.notifications.test_push') }}" method="POST" style="padding: 20px;">
                @csrf
                <div style="display: grid; gap: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Target Mode</label>
                        <select name="target_type" onchange="toggleTestTargetMode(this.value)" style="width: 100%; padding: 8px 12px; border: 1.5px solid #CBD5E1; border-radius: 6px; font-size: 12.5px;">
                            <option value="user">Registered Platform User (by ID)</option>
                            <option value="token">Raw FCM Registration Token</option>
                        </select>
                    </div>

                    <div id="target_user_field">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Target User ID</label>
                        <input type="number" name="target_id" value="{{ auth()->id() }}" style="width: 100%; padding: 8px 12px; border: 1.5px solid #CBD5E1; border-radius: 6px; font-size: 12.5px;">
                    </div>

                    <div id="target_token_field" style="display: none;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Raw FCM Device Token</label>
                        <input type="text" name="device_token" placeholder="Paste FCM registration token..." style="width: 100%; padding: 8px 12px; border: 1.5px solid #CBD5E1; border-radius: 6px; font-size: 12px; font-family: monospace;">
                    </div>

                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Title</label>
                        <input type="text" name="title" value="Test Courier Alert 🚀" required style="width: 100%; padding: 8px 12px; border: 1.5px solid #CBD5E1; border-radius: 6px; font-size: 12.5px;">
                    </div>

                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Message Body</label>
                        <input type="text" name="body" value="Victorious Dispatch Terminal connectivity check. Audio and heads-up channel confirmed." required style="width: 100%; padding: 8px 12px; border: 1.5px solid #CBD5E1; border-radius: 6px; font-size: 12.5px;">
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 6px;">
                        <button type="submit" class="btn-dash btn-dash-success" style="padding: 9px 20px; font-size: 12.5px; font-weight: 700;">
                            🚀 Fire Test Push
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Termii SMS Diagnostic Tester Card -->
        <div class="dash-card" id="test-sms-card" style="border: 1.5px solid rgba(59, 130, 246, 0.25);">
            <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(239, 246, 255, 0.5) 100%);">
                <div class="dash-card-title">
                    <span style="font-size: 20px;">💬</span>
                    <div>
                        <span style="font-weight: 800; color: #0F172A;">Termii SMS Gateway Tester</span>
                        <div class="dash-card-subtitle">Dispatch a live test SMS through the configured Termii gateway credentials.</div>
                    </div>
                </div>
                <span class="dash-badge dash-badge-blue">Live SMS</span>
            </div>

            <form action="{{ route('admin.notifications.test_sms') }}" method="POST" style="padding: 20px;">
                @csrf
                <div style="display: grid; gap: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Recipient Phone Number</label>
                        <input type="text" name="recipient" value="{{ auth()->user()->phone ?? '+2348000000000' }}" placeholder="+2348012345678" required style="width: 100%; padding: 8px 12px; border: 1.5px solid #CBD5E1; border-radius: 6px; font-size: 12.5px;">
                    </div>

                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Test SMS Content</label>
                        <input type="text" name="message" value="Victorious Market Gateway Test: Handover OTP is [88921]. Systems operational." required style="width: 100%; padding: 8px 12px; border: 1.5px solid #CBD5E1; border-radius: 6px; font-size: 12.5px;">
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 6px;">
                        <button type="submit" class="btn-dash btn-dash-primary" style="padding: 9px 20px; font-size: 12.5px; font-weight: 700;">
                            💬 Fire Test SMS
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Registered Device Tokens Management Console -->
    <div class="dash-card" id="devices-card" style="margin-bottom: 24px;">
        <div class="dash-card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <div class="dash-card-title">
                <span style="font-size: 18px;">📱</span>
                <div>
                    <span style="font-weight: 800; color: #0F172A;">Registered Courier &amp; Mobile Device Terminals</span>
                    <div class="dash-card-subtitle">Inspect active push tokens linked to couriers, vendors, and customers. Deactivate or prune old tokens.</div>
                </div>
            </div>

            <form action="{{ route('admin.notifications.prune_tokens') }}" method="POST" onsubmit="return confirm('Prune all device tokens inactive for over 30 days?')">
                @csrf
                <button type="submit" class="btn-dash btn-dash-outline" style="font-size: 11.5px; color: #EF4444; border-color: #EF4444; padding: 5px 12px;">
                    🧹 Prune Inactive (>30 Days)
                </button>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1.5px solid #E2E8F0; text-align: left; background: #F8FAFC;">
                        <th style="padding: 10px 14px; color: #475569;">User</th>
                        <th style="padding: 10px 14px; color: #475569;">Role / App</th>
                        <th style="padding: 10px 14px; color: #475569;">Platform / Device</th>
                        <th style="padding: 10px 14px; color: #475569;">Token (Masked)</th>
                        <th style="padding: 10px 14px; color: #475569;">Last Active</th>
                        <th style="padding: 10px 14px; color: #475569; text-align: center;">Status / Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registeredDevices as $dev)
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 10px 14px;">
                                <div style="font-weight: 700; color: #0F172A;">{{ $dev->user->name ?? 'User #' . $dev->user_id }}</div>
                                <div style="font-size: 11px; color: #64748B;">{{ $dev->user->email ?? '--' }}</div>
                            </td>
                            <td style="padding: 10px 14px;">
                                @if($dev->app_type === 'rider')
                                    <span style="font-weight: 700; color: #059669; background: #D1FAE5; padding: 2px 7px; border-radius: 4px; font-size: 11px;">🏍️ Courier</span>
                                @elseif($dev->app_type === 'vendor')
                                    <span style="font-weight: 700; color: #7C3AED; background: #EDE9FE; padding: 2px 7px; border-radius: 4px; font-size: 11px;">🏬 Vendor</span>
                                @else
                                    <span style="font-weight: 700; color: #2563EB; background: #DBEAFE; padding: 2px 7px; border-radius: 4px; font-size: 11px;">🛍️ Customer</span>
                                @endif
                            </td>
                            <td style="padding: 10px 14px; color: #334155;">
                                <div style="font-weight: 600;">{{ ucfirst($dev->device_type) }}</div>
                                <div style="font-size: 11px; color: #64748B;">{{ $dev->device_name ?: 'Mobile Device' }}</div>
                            </td>
                            <td style="padding: 10px 14px; font-family: monospace; font-size: 11px; color: #64748B;">
                                {{ Str::limit($dev->device_token, 24, '...') }}
                            </td>
                            <td style="padding: 10px 14px; color: #64748B; white-space: nowrap;">
                                {{ $dev->last_seen_at ? $dev->last_seen_at->diffForHumans() : 'Recently' }}
                            </td>
                            <td style="padding: 10px 14px; text-align: center;">
                                @if($dev->is_active)
                                    <form action="{{ route('admin.notifications.revoke_token', $dev->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Revoke this device token?')">
                                        @csrf
                                        <button type="submit" class="btn-dash btn-dash-outline" style="font-size: 10.5px; padding: 3px 8px; color: #EF4444; border-color: #EF4444;">
                                            Revoke
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size: 11px; font-weight: 700; color: #94A3B8; background: #F1F5F9; padding: 2px 8px; border-radius: 999px;">Revoked</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 24px; text-align: center; color: #94A3B8;">
                                No registered device tokens found in the sovereign registry.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Delivery Audit Log Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span style="font-size: 18px;">📊</span>
                <div>
                    <span style="font-weight: 800; color: #0F172A;">Recent Gateway Delivery Receipts</span>
                    <div class="dash-card-subtitle">Real-time audit receipts across Push, SMS, In-App, and WhatsApp gateways.</div>
                </div>
            </div>
            <span class="dash-badge dash-badge-gray">Audit Stream</span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1.5px solid #E2E8F0; text-align: left; background: #F8FAFC;">
                        <th style="padding: 10px 14px; color: #475569;">Timestamp</th>
                        <th style="padding: 10px 14px; color: #475569;">Channel</th>
                        <th style="padding: 10px 14px; color: #475569;">Event</th>
                        <th style="padding: 10px 14px; color: #475569;">Recipient / Token</th>
                        <th style="padding: 10px 14px; color: #475569;">Title</th>
                        <th style="padding: 10px 14px; color: #475569; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 10px 14px; color: #64748B; white-space: nowrap;">
                                {{ $log->created_at->format('M d, H:i:s') }}
                            </td>
                            <td style="padding: 10px 14px;">
                                @if($log->channel === 'fcm')
                                    <span style="font-weight: 700; color: #D97706; background: #FEF3C7; padding: 2px 7px; border-radius: 4px; font-size: 11px;">🔥 FCM</span>
                                @elseif($log->channel === 'sms')
                                    <span style="font-weight: 700; color: #2563EB; background: #DBEAFE; padding: 2px 7px; border-radius: 4px; font-size: 11px;">💬 SMS</span>
                                @elseif($log->channel === 'in_app')
                                    <span style="font-weight: 700; color: #7C3AED; background: #EDE9FE; padding: 2px 7px; border-radius: 4px; font-size: 11px;">🔔 In-App</span>
                                @else
                                    <span style="font-weight: 700; color: #059669; background: #D1FAE5; padding: 2px 7px; border-radius: 4px; font-size: 11px;">🟢 WhatsApp</span>
                                @endif
                            </td>
                            <td style="padding: 10px 14px; font-weight: 600; color: #1E293B;">
                                {{ $log->event_type }}
                            </td>
                            <td style="padding: 10px 14px; color: #475569; font-family: monospace; font-size: 11.5px;">
                                {{ Str::limit($log->recipient, 35) }}
                            </td>
                            <td style="padding: 10px 14px; color: #334155;">
                                {{ $log->title ?: '-' }}
                            </td>
                            <td style="padding: 10px 14px; text-align: center;">
                                @if($log->status === 'delivered')
                                    <span style="font-weight: 700; color: #15803D; background: #DCFCE7; padding: 2px 8px; border-radius: 12px; font-size: 11px;">Delivered</span>
                                @elseif($log->status === 'skipped')
                                    <span style="font-weight: 700; color: #64748B; background: #F1F5F9; padding: 2px 8px; border-radius: 12px; font-size: 11px;">Skipped</span>
                                @else
                                    <span style="font-weight: 700; color: #B91C1C; background: #FEE2E2; padding: 2px 8px; border-radius: 12px; font-size: 11px;" title="{{ $log->error_message }}">Failed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 24px; text-align: center; color: #94A3B8;">
                                No notification dispatches recorded yet. Use the test tools above to verify your setup.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleTestTargetMode(mode) {
            var userField = document.getElementById('target_user_field');
            var tokenField = document.getElementById('target_token_field');
            if (mode === 'token') {
                userField.style.display = 'none';
                tokenField.style.display = 'block';
            } else {
                userField.style.display = 'block';
                tokenField.style.display = 'none';
            }
        }
    </script>
@endsection
