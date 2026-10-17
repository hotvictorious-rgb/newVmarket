@extends('layouts.dashboard')

@section('title', 'Notification Orchestration & Firebase Control Center')
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
                Configure Firebase Cloud Messaging (FCM HTTP v1), Termii SMS failover, real-time in-app alerts, and fine-grained event routing rules.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <a href="#test-push-card" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 6px 14px;">
                Send Test Push 🚀
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
            <div style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Active Registered Devices</div>
            <div style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 4px 0;">{{ number_format($activeTokensCount) }}</div>
            <div style="font-size: 11.5px; color: #3B82F6; font-weight: 600;">📱 Live multi-device tokens</div>
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

        <div class="dash-card" style="padding: 18px; border-left: 4px solid #F59E0B;">
            <div style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Recent Logged Dispatches</div>
            <div style="font-size: 26px; font-weight: 800; color: #0F172A; margin: 4px 0;">{{ number_format($recentLogs->count()) }}</div>
            <div style="font-size: 11.5px; color: #F59E0B; font-weight: 600;">📊 Live audit audit trail</div>
        </div>
    </div>

    <!-- Master Channels & Firebase Credentials Form -->
    <form action="{{ route('admin.notifications.update') }}" method="POST">
        @csrf

        <div class="dash-card" style="margin-bottom: 24px; border: 1.5px solid rgba(59, 130, 246, 0.25);">
            <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(239, 246, 255, 0.5) 100%);">
                <div class="dash-card-title">
                    <span style="font-size: 20px;">⚙️</span>
                    <div>
                        <span style="font-weight: 800; color: #0F172A;">Master Channels &amp; Gateway Credentials</span>
                        <div class="dash-card-subtitle">Toggle platform-wide communication channels and configure Google Firebase OAuth2.</div>
                    </div>
                </div>
                <span class="dash-badge dash-badge-blue">System Infrastructure</span>
            </div>

            <div style="padding: 24px;">
                <!-- Master Switches Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #E2E8F0;">
                    <label style="display: flex; align-items: flex-start; gap: 12px; background: #F8FAFC; padding: 14px; border-radius: 10px; border: 1px solid #E2E8F0; cursor: pointer;">
                        <input type="checkbox" name="fcm_enabled" value="1" {{ $settings['fcm_enabled'] ? 'checked' : '' }} style="margin-top: 3px; transform: scale(1.2);">
                        <div>
                            <div style="font-weight: 700; color: #0F172A; font-size: 13.5px;">🔥 Firebase Push (FCM)</div>
                            <div style="font-size: 11.5px; color: #64748B;">Instant push to rider, vendor &amp; customer mobile apps.</div>
                        </div>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 12px; background: #F8FAFC; padding: 14px; border-radius: 10px; border: 1px solid #E2E8F0; cursor: pointer;">
                        <input type="checkbox" name="sms_enabled" value="1" {{ $settings['sms_enabled'] ? 'checked' : '' }} style="margin-top: 3px; transform: scale(1.2);">
                        <div>
                            <div style="font-weight: 700; color: #0F172A; font-size: 13.5px;">💬 SMS Gateway (Termii)</div>
                            <div style="font-size: 11.5px; color: #64748B;">Mission-critical OTPs and delivery doorstep alerts.</div>
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
                            <div style="font-size: 11.5px; color: #64748B;">Merchant counter alerts &amp; corporate notices.</div>
                        </div>
                    </label>
                </div>

                <!-- Firebase HTTP v1 Credentials Section -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Firebase Project ID
                        </label>
                        <input type="text" name="firebase_project_id" value="{{ old('firebase_project_id', $settings['firebase_project_id']) }}"
                               placeholder="e.g. victorious-market-prod"
                               style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                        <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Found in Firebase Console &rarr; Project Settings &rarr; General.</div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Client Email (Service Account)
                        </label>
                        <input type="email" name="firebase_client_email" value="{{ old('firebase_client_email', $settings['firebase_client_email']) }}"
                               placeholder="firebase-adminsdk-xxx@project.iam.gserviceaccount.com"
                               style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                        <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Google Service Account authorized for Firebase Cloud Messaging.</div>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Service Account Private Key (RSA Private Key PEM)
                        </label>
                        <textarea name="firebase_private_key" rows="4" placeholder="-----BEGIN PRIVATE KEY-----&#10;MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC...&#10;-----END PRIVATE KEY-----"
                                  style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-family: monospace; font-size: 11.5px; resize: vertical;">{{ old('firebase_private_key', $settings['firebase_private_key']) }}</textarea>
                        <div style="font-size: 11px; color: #64748B; margin-top: 4px;">From your downloaded Google Service Account JSON file. Kept encrypted and secure.</div>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Optional: Alternative JSON File Path on Server
                        </label>
                        <input type="text" name="firebase_credentials_path" value="{{ old('firebase_credentials_path', $settings['firebase_credentials_path']) }}"
                               placeholder="e.g. storage/app/firebase/service-account.json"
                               style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                        <div style="font-size: 11px; color: #64748B; margin-top: 4px;">If uploaded to disk, the server will load credentials directly from this absolute or relative path.</div>
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
                        <span style="font-weight: 800; color: #0F172A;">Event-by-Event Multi-Channel Routing Matrix</span>
                        <div class="dash-card-subtitle">Granularly control which channels fire for each business event to minimize SMS costs while guaranteeing delivery.</div>
                    </div>
                </div>
                <span class="dash-badge dash-badge-purple">Cost &amp; Dispatch Matrix</span>
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
                                'order_placed' => ['name' => '1. New Order Placed', 'desc' => 'Customer confirmation & Merchant preparation alert', 'persona' => 'Customer, Vendor'],
                                'vendor_handover_ready' => ['name' => '2. Merchant Ready for Handover', 'desc' => 'Courier dispatched to collect sealed package', 'persona' => 'Assigned Courier'],
                                'out_for_delivery' => ['name' => '3. Package Out For Delivery', 'desc' => 'Courier custody confirmed; package in transit', 'persona' => 'Customer, Vendor'],
                                'doorstep_arrived' => ['name' => '4. Courier Arrived at Doorstep', 'desc' => 'Urgent arrival alert with 6-digit Security PIN', 'persona' => 'Customer'],
                                'delivery_completed' => ['name' => '5. Doorstep Handover Sealed', 'desc' => 'Instant Cashback credited; Fleet & Vendor escrow', 'persona' => 'Customer, Fleet, Vendor'],
                                'doorstep_return_dispute' => ['name' => '6. Return Dispute Opened', 'desc' => 'Item rejected at doorstep; inspection required', 'persona' => 'Vendor, Admin'],
                                'payout_disbursed' => ['name' => '7. Payout Bank Transfer Sent', 'desc' => 'Paystack NUBAN disbursement confirmed', 'persona' => 'Merchant, Fleet'],
                                'security_bank_cooldown' => ['name' => '8. Bank Update 48h Cooldown', 'desc' => 'Security freeze notice on bank details change', 'persona' => 'Merchant, Fleet'],
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

                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-dash btn-dash-primary" style="padding: 12px 28px; font-size: 13.5px; font-weight: 700;">
                        💾 Save Multi-Channel Notification Rules
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Push Diagnostic Tester Card -->
    <div class="dash-card" id="test-push-card" style="margin-bottom: 24px; border: 1.5px solid rgba(16, 185, 129, 0.25);">
        <div class="dash-card-header" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(236, 253, 245, 0.5) 100%);">
            <div class="dash-card-title">
                <span style="font-size: 20px;">🚀</span>
                <div>
                    <span style="font-weight: 800; color: #0F172A;">FCM Push Diagnostic &amp; Live Terminal Tester</span>
                    <div class="dash-card-subtitle">Dispatch a live test notification to any courier, merchant, or raw device token to verify Google connectivity.</div>
                </div>
            </div>
            <span class="dash-badge dash-badge-green">Live Test Tool</span>
        </div>

        <form action="{{ route('admin.notifications.test_push') }}" method="POST" style="padding: 24px;">
            @csrf

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 18px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Target Dispatch Mode
                    </label>
                    <select name="target_type" id="target_type_select" style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;" onchange="toggleTestTargetMode(this.value)">
                        <option value="user">Registered Platform User (by ID)</option>
                        <option value="token">Raw FCM Registration Token</option>
                    </select>
                </div>

                <div id="target_user_field">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Target User ID
                    </label>
                    <input type="number" name="target_id" value="{{ auth()->id() }}" placeholder="e.g. {{ auth()->id() }}"
                           style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                    <div style="font-size: 11px; color: #64748B; margin-top: 4px;">Defaults to your current Super Admin ID.</div>
                </div>

                <div id="target_token_field" style="display: none; grid-column: 1 / -1;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Raw FCM Device Token
                    </label>
                    <input type="text" name="device_token" placeholder="Paste FCM registration token (from Flutter or Web Console)..."
                           style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px; font-family: monospace;">
                </div>

                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Notification Title
                    </label>
                    <input type="text" name="title" value="Test Courier Alert 🚀" required
                           style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Notification Message Body
                    </label>
                    <input type="text" name="body" value="Victorious Dispatch Terminal connectivity check. Audio and heads-up channel confirmed." required
                           style="width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn-dash btn-dash-success" style="padding: 10px 24px; font-size: 13px; font-weight: 700;">
                    🚀 Fire Test Push Notification
                </button>
            </div>
        </form>
    </div>

    <!-- Live Delivery Audit Log Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span style="font-size: 18px;">📊</span>
                <div>
                    <span style="font-weight: 800; color: #0F172A;">Recent Notification Delivery Logs</span>
                    <div class="dash-card-subtitle">Real-time gateway delivery receipts, failover tracking, and error diagnostics.</div>
                </div>
            </div>
            <span class="dash-badge dash-badge-gray">Last 20 Dispatches</span>
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
                                No notification dispatches recorded yet. Use the test tool above to verify your setup.
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
