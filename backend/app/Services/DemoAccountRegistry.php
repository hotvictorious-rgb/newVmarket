<?php

namespace App\Services;

class DemoAccountRegistry
{
    public const DEMO_PASSWORD = 'DemoPassword123!';

    /**
     * Determine if demo mode is active.
     * HARD INVARIANT: Permanently disabled in production regardless of config/env.
     */
    public static function isDemoEnabled(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return (bool) config('app.demo_mode', env('DEMO_MODE', false));
    }

    /**
     * Get the 3 registered demo accounts for a specific portal role.
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

        return $catalog[$role] ?? [];
    }

    /**
     * Lookup demo account metadata by email.
     */
    public static function findDemoAccount(string $email): ?array
    {
        if (!self::isDemoEnabled()) {
            return null;
        }

        foreach (self::demoCatalog() as $role => $accounts) {
            foreach ($accounts as $account) {
                if (strtolower($account['email']) === strtolower($email)) {
                    return $account;
                }
            }
        }

        return null;
    }

    /**
     * Complete catalog of demo personas.
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
                    'description' => 'Uyo Municipal Lane • ₦4,500 Cashback Rewards Ledger Active',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.customer2@victoriousmarket.com.ng',
                    'name' => 'Amina Bello',
                    'badge' => 'Active Buyer',
                    'role_label' => 'Customer',
                    'description' => 'Eket Inter-LGA Lane • 3 In-Transit Doorstep Deliveries',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.customer3@victoriousmarket.com.ng',
                    'name' => 'Chinedu Eze',
                    'badge' => 'Fresh Market Buyer',
                    'role_label' => 'Customer',
                    'description' => 'Ikot Ekpene Corridor • 5 Completed Orders & Reviews',
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
                    'description' => 'Victor Tech Plaza (Uyo) • ₦1.8M Gross Sales & 14 Products',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.vendor2@victoriousmarket.com.ng',
                    'name' => 'Mercy Etim (Ibom Fresh)',
                    'badge' => 'Farm Produce & Groceries',
                    'role_label' => 'Vendor Merchant',
                    'description' => 'Ibom Fresh Farm Hub • 48 Fresh Produce SKU Inventory',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.vendor3@victoriousmarket.com.ng',
                    'name' => 'David Okon (Eket Style)',
                    'badge' => 'Apparel & Accessories',
                    'role_label' => 'Vendor Merchant',
                    'description' => 'Eket Fashion Emporium • Verified Merchant Badge Active',
                    'password' => self::DEMO_PASSWORD,
                ],
            ],

            'vendor_employee' => [
                [
                    'email' => 'demo.counter1@victoriousmarket.com.ng',
                    'name' => 'Idara Bassey',
                    'badge' => 'Senior Station Officer',
                    'role_label' => 'Counter Staff',
                    'description' => 'Uyo Plaza Station 1 • Physical Quality Inspection Desk',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.counter2@victoriousmarket.com.ng',
                    'name' => 'Samuel Udoh',
                    'badge' => 'Customer Handover Lead',
                    'role_label' => 'Counter Staff',
                    'description' => 'Station Handover Station • OTP Release & Inspection Check',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.counter3@victoriousmarket.com.ng',
                    'name' => 'Grace Sunday',
                    'badge' => 'Intake & Returns Clerk',
                    'role_label' => 'Counter Staff',
                    'description' => 'Inbound Waybill Staging & Customer Returns Terminal',
                    'password' => self::DEMO_PASSWORD,
                ],
            ],

            'logistics_company' => [
                [
                    'email' => 'demo.logistics1@victoriousmarket.com.ng',
                    'name' => 'Akwa Express Logistics Ltd',
                    'badge' => 'Licensed Fleet Carrier',
                    'role_label' => 'Logistics Fleet',
                    'description' => '15 Active Couriers • 98.4% On-Time SLA • Escrow Wallet Active',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.logistics2@victoriousmarket.com.ng',
                    'name' => 'Cross River FastWay Cargo',
                    'badge' => 'Regional Corridor Carrier',
                    'role_label' => 'Logistics Fleet',
                    'description' => 'Inter-State Freight & Regional Distribution Fleet',
                    'password' => self::DEMO_PASSWORD,
                ],
                [
                    'email' => 'demo.logistics3@victoriousmarket.com.ng',
                    'name' => 'Urban Swift Couriers',
                    'badge' => 'Express Urban Dispatch',
                    'role_label' => 'Logistics Fleet',
                    'description' => 'Municipal On-Demand Motorcycle Dispatch Roster',
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
                    'description' => 'Eket ↔ Uyo Highway Corridor • 18 Scheduled Drops Today',
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
}
