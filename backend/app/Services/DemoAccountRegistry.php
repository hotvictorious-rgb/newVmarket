<?php

namespace App\Services;

class DemoAccountRegistry
{
    public const DEMO_PASSWORD = 'DemoPassword123!';

    /**
     * Determine if demo mode is active.
     * MULTI-LAYER HARDENED INVARIANT:
     * Layer 1: Permanently disabled in production (APP_ENV=production).
     * Layer 2: Must be explicitly enabled via config (app.demo_mode / DEMO_MODE=true).
     * Layer 3: Failsafe Domain Barrier - if request hostname is not localhost/127.0.0.1/.test/.local,
     *          demo mode is forcibly killed even if APP_ENV was mistakenly misconfigured.
     */
    public static function isDemoEnabled(): bool
    {
        // Layer 1: Environment check
        if (app()->environment('production')) {
            return false;
        }

        // Layer 2: Explicit config switch
        if (!config('app.demo_mode', false) && !env('DEMO_MODE', false)) {
            return false;
        }

        // Layer 3: Live Host / Domain Barrier
        if (!app()->runningInConsole() && request() && request()->hasHeader('Host')) {
            $host = strtolower(request()->getHost());
            $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'])
                || str_ends_with($host, '.test')
                || str_ends_with($host, '.local');

            if (!$isLocal) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the 3 featured demo accounts for a specific portal role.
     * Note: Super Admin returns EMPTY array — Superadmin credentials come strictly from ENV.
     */
    public static function getDemoAccounts(string $role): array
    {
        if (!self::isDemoEnabled()) {
            return [];
        }

        if ($role === 'super_admin') {
            return [];
        }

        $catalog = self::demoCatalog();

        // Return first 3 featured demo accounts for the portal view
        $roleAccounts = $catalog[$role] ?? [];
        return array_slice($roleAccounts, 0, 3);
    }

    /**
     * Lookup demo account metadata by email across all registered and alias demo accounts.
     */
    public static function findDemoAccount(string $email): ?array
    {
        if (!self::isDemoEnabled()) {
            return null;
        }

        $searchEmail = strtolower(trim($email));

        foreach (self::allDemoAccounts() as $account) {
            if (strtolower($account['email']) === $searchEmail) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Primary catalog of 3 featured demo personas per portal role.
     */
    public static function demoCatalog(): array
    {
        return [
            'customer' => [
                [
                    'email' => 'demo.customer1@victoriousmarket.com.ng',
                    'name' => 'Emeka Okonkwo',
                    'badge' => 'VIP Gold Shopper',
                    'role_label' => 'Customer',
                    'description' => 'Uyo Municipal Lane • ₦72,500 Cashback Rewards Ledger Active',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.customer2@victoriousmarket.com.ng',
                    'name' => 'Amina Bello',
                    'badge' => 'Active Buyer',
                    'role_label' => 'Customer',
                    'description' => 'Eket Inter-LGA Lane • In-Transit Doorstep Deliveries with Live OTP',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.customer3@victoriousmarket.com.ng',
                    'name' => 'Chinedu Eze',
                    'badge' => 'Fresh Market Buyer',
                    'role_label' => 'Customer',
                    'description' => 'Ikot Ekpene Corridor • Completed Orders, Reviews & Pickup Passes',
                    'password' => self::DEMO_PASSWORD,
                ],
            ],

            'admin_employee' => [
                [
                    'email' => 'demo.staff1@victoriousmarket.com.ng',
                    'name' => 'Blessing Akpan',
                    'badge' => 'Catalog Moderation Desk',
                    'role_label' => 'Admin Staff',
                    'description' => 'Merchant Product Compliance & Brand Verification Desk',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.staff2@victoriousmarket.com.ng',
                    'name' => 'Iniobong Effiong',
                    'badge' => 'Dispute & Support Officer',
                    'role_label' => 'Admin Staff',
                    'description' => 'Customer Dispute Mediation & Settlement Auditing Desk',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.staff3@victoriousmarket.com.ng',
                    'name' => 'Kufre Asuquo',
                    'badge' => 'Operations & Dispatch',
                    'role_label' => 'Admin Staff',
                    'description' => 'System Waybill Dispatch & Corridor Lane Monitor',
                    'password' => self::DEMO_PASSWORD,
                ],
            ],

            'vendor' => [
                [
                    'email' => 'demo.vendor1@victoriousmarket.com.ng',
                    'name' => 'Victor Bassey (Victor Tech)',
                    'badge' => 'Flagship Electronics',
                    'role_label' => 'Vendor Merchant',
                    'description' => 'Victor Tech Plaza (Uyo) • ₦1.8M Gross Sales & 5 Active Products',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.vendor2@victoriousmarket.com.ng',
                    'name' => 'Mercy Etim (Ibom Fresh)',
                    'badge' => 'Farm Produce & Groceries',
                    'role_label' => 'Vendor Merchant',
                    'description' => 'Ibom Fresh Farm Hub (Eket) • Grade-A Palm Oil, Crayfish & Produce',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.vendor3@victoriousmarket.com.ng',
                    'name' => 'David Okon (Eket Style)',
                    'badge' => 'Apparel & Accessories',
                    'role_label' => 'Vendor Merchant',
                    'description' => 'Eket Fashion Emporium • Verified Merchant Badge & African Wear',
                    'password' => self::DEMO_PASSWORD,
                ],
            ],

            'vendor_employee' => [
                [
                    'email' => 'demo.counter1@victoriousmarket.com.ng',
                    'name' => 'Idara Bassey',
                    'badge' => 'Senior Station Officer',
                    'role_label' => 'Counter Staff',
                    'description' => 'Victor Tech Uyo Station • Physical Quality Inspection Desk',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.counter2@victoriousmarket.com.ng',
                    'name' => 'Samuel Udoh',
                    'badge' => 'Customer Handover Lead',
                    'role_label' => 'Counter Staff',
                    'description' => 'Ibom Fresh Eket Station • OTP Release & Freshness Verification',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.counter3@victoriousmarket.com.ng',
                    'name' => 'Grace Sunday',
                    'badge' => 'Intake & Returns Clerk',
                    'role_label' => 'Counter Staff',
                    'description' => 'Eket Style Station • Inbound Waybill Staging & Returns Terminal',
                    'password' => self::DEMO_PASSWORD,
                ],
            ],

            'logistics_company' => [
                [
                    'email' => 'demo.logistics1@victoriousmarket.com.ng',
                    'name' => 'Akwa Express Logistics Ltd',
                    'badge' => 'Licensed Fleet Carrier',
                    'role_label' => 'Logistics Fleet',
                    'description' => '18 Active Couriers • 98.4% On-Time SLA • Escrow Wallet Active',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.logistics2@victoriousmarket.com.ng',
                    'name' => 'Cross River FastWay Cargo',
                    'badge' => 'Regional Corridor Carrier',
                    'role_label' => 'Logistics Fleet',
                    'description' => '12 Heavy Freight & Parcel Vans • Inter-LGA Distribution',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.logistics3@victoriousmarket.com.ng',
                    'name' => 'Urban Swift Couriers Ltd',
                    'badge' => 'Express Urban Dispatch',
                    'role_label' => 'Logistics Fleet',
                    'description' => 'Speedy Parcel Express • 10 Motorcycle & Keke Dispatch Units',
                    'password' => self::DEMO_PASSWORD,
                ],
            ],

            'rider' => [
                [
                    'email' => 'demo.rider1@victoriousmarket.com.ng',
                    'name' => 'Edidiong Udoh',
                    'badge' => 'Lead Courier (Motorcycle)',
                    'role_label' => 'Delivery Rider',
                    'description' => 'Uyo Municipal Corridor • Rating 4.9★ (342 Completed Runs)',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.rider2@victoriousmarket.com.ng',
                    'name' => 'Aniefiok Ekpo',
                    'badge' => 'Express Inter-LGA Rider',
                    'role_label' => 'Delivery Rider',
                    'description' => 'Eket ↔ Uyo Highway Corridor • Keke Cargo Shuttle • 18 Drops',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.rider3@victoriousmarket.com.ng',
                    'name' => 'Ubong Etim',
                    'badge' => 'Cargo Van Courier',
                    'role_label' => 'Delivery Rider',
                    'description' => 'Akwa Ibom Zonal Distribution • Heavy Freight Parcel Van',
                    'password' => self::DEMO_PASSWORD,
                ],
            ],
        ];
    }

    /**
     * All registered demo personas, including base platform accounts and mobile app quick-fill accounts.
     */
    public static function allDemoAccounts(): array
    {
        $primary = [];
        foreach (self::demoCatalog() as $role => $accounts) {
            foreach ($accounts as $acc) {
                $primary[] = $acc;
            }
        }

        $additional = [
            // Base platform accounts updated as demo accounts
            [
                'email' => 'finance@victoriousmarket.com.ng',
                'name' => 'Finance Controller (Treasury)',
                'badge' => 'Treasury Settlement',
                'role_label' => 'Admin Staff',
                'description' => 'Treasury Settlement, Escrow Release & Ledger Audit Desk',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'demo.staff.compliance@victoriousmarket.com.ng',
                'name' => 'Bassey Ntuk (Compliance Auditor)',
                'badge' => 'KYC & Legal Auditor',
                'role_label' => 'Admin Staff',
                'description' => 'Merchant CAC Verification, KYC Enforcement & Risk Audit',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'vendor@victoriousmarket.com.ng',
                'name' => 'Victor Tech Solutions (Base)',
                'badge' => 'Flagship Electronics',
                'role_label' => 'Vendor Merchant',
                'description' => 'Victor Tech Uyo Flagship Plaza • Primary Merchant Terminal',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'vendor@example.com',
                'name' => 'Victor Tech Solutions (Mobile Quick-Fill)',
                'badge' => 'Flagship Electronics',
                'role_label' => 'Vendor Merchant',
                'description' => 'Vendor Mobile Terminal One-Tap Demo Merchant Account',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'demo.vendor4@victoriousmarket.com.ng',
                'name' => 'Anietie Archibong (Calabar Living)',
                'badge' => 'Home Appliances & Living',
                'role_label' => 'Vendor Merchant',
                'description' => 'Calabar Living Depot • Kitchen & Home Appliances Store',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'staff@victoriousmarket.com.ng',
                'name' => 'Counter Inspection Clerk (Base)',
                'badge' => 'Inspection Desk',
                'role_label' => 'Counter Staff',
                'description' => 'Victor Tech Uyo Plaza • Physical Quality Handshake Station',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'logistics@victoriousmarket.com.ng',
                'name' => 'Akwa Express Logistics Ltd (Base)',
                'badge' => 'Licensed Fleet Carrier',
                'role_label' => 'Logistics Fleet',
                'description' => 'Akwa Express Central Hub • Primary Fleet Operator',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'rider@victoriousmarket.com.ng',
                'name' => 'Edidiong Udoh (Base)',
                'badge' => 'Lead City Courier',
                'role_label' => 'Delivery Rider',
                'description' => 'Akwa Express Motorcycle Courier • Terminal Quick Fill',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'musa@speedyfleet.ng',
                'name' => 'Musa Danjuma (Speedy Fleet)',
                'badge' => 'Speedy Express Motorbike',
                'role_label' => 'Delivery Rider',
                'description' => 'Urban Swift Couriers • Mobile App Development Quick-Fill Courier',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'ubong.rider@vanlogistics.ng',
                'name' => 'Ubong Etim (Van Logistics)',
                'badge' => 'Cargo Van Courier',
                'role_label' => 'Delivery Rider',
                'description' => 'Cross River FastWay Cargo • Van Logistics Development Quick Fill',
                'password' => self::DEMO_PASSWORD,
            ],
            [
                'email' => 'customer@victoriousmarket.com.ng',
                'name' => 'Emeka Okonkwo (Base)',
                'badge' => 'VIP Gold Shopper',
                'role_label' => 'Customer',
                'description' => 'Primary Customer Account • ₦72,500 Cashback Rewards Ledger',
                'password' => self::DEMO_PASSWORD,
            ],
        ];

        return array_merge($primary, $additional);
    }
}