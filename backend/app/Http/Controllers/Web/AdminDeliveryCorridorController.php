<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\DeliveryCorridor;
use App\Models\Lga;
use App\Models\LogisticsCompanyCorridorRate;
use App\Models\LogisticsCompanyProfile;
use App\Models\SystemSetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AdminDeliveryCorridorController extends Controller
{
    private function requireSuperAdmin(Request $request): void
    {
        $admin = $request->user();
        if (!$admin || $admin->status !== 'active' || $admin->user_type !== 'super_admin') {
            throw new AuthorizationException('Only an active Super Admin may manage delivery corridors and company tariffs.');
        }
    }

    public function page(Request $request)
    {
        $this->requireSuperAdmin($request);
        $uyo = Lga::whereRaw('LOWER(TRIM(name)) = ?', ['uyo'])
            ->whereHas('state', fn ($state) => $state->whereRaw('LOWER(TRIM(name)) = ?', ['akwa ibom']))
            ->firstOrFail();
        $corridors = DeliveryCorridor::with(['companyRates.logisticsCompany', 'lga'])
            ->orderBy('name')
            ->get();
        $companies = LogisticsCompanyProfile::with('hqLga.state')
            ->where('status', 'active')
            ->whereHas('user', fn ($user) => $user->where('status', 'active'))
            ->whereHas('hqLga', function ($q) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', ['uyo'])
                    ->whereHas('state', fn ($state) => $state->whereRaw('LOWER(TRIM(name)) = ?', ['akwa ibom']));
            })
            ->orderBy('company_name')
            ->get();

        $customerBaseFee = SystemSetting::get('zone_intra_lga_fee', '500.00');
        $customerBulkySurcharge = SystemSetting::get('zone_bulky_cargo_surcharge', '1000.00');

        return view('admin.delivery_corridors', compact('uyo', 'corridors', 'companies', 'customerBaseFee', 'customerBulkySurcharge'));
    }

    public function updateCustomerFees(Request $request)
    {
        $this->requireSuperAdmin($request);
        $data = $request->validate([
            'customer_base_fee' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'customer_bulky_surcharge_per_unit' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $old = [
                'zone_intra_lga_fee' => SystemSetting::get('zone_intra_lga_fee', '500.00'),
                'zone_bulky_cargo_surcharge' => SystemSetting::get('zone_bulky_cargo_surcharge', '1000.00'),
            ];
            SystemSetting::set('zone_intra_lga_fee', number_format((float) $data['customer_base_fee'], 2, '.', ''), 'delivery');
            SystemSetting::set('zone_bulky_cargo_surcharge', number_format((float) $data['customer_bulky_surcharge_per_unit'], 2, '.', ''), 'delivery');
            $new = [
                'zone_intra_lga_fee' => SystemSetting::get('zone_intra_lga_fee', '500.00'),
                'zone_bulky_cargo_surcharge' => SystemSetting::get('zone_bulky_cargo_surcharge', '1000.00'),
            ];
            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'customer_delivery_pricing_updated',
                'target_type' => SystemSetting::class,
                'target_id' => null,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.delivery_corridors.manage')->with('status', 'Customer flat delivery pricing updated. Existing orders keep their snapshots.');
        });
    }

    public function index(Request $request)
    {
        $this->requireSuperAdmin($request);

        return response()->json([
            'success' => true,
            'corridors' => DeliveryCorridor::with(['lga.state', 'companyRates.logisticsCompany'])
                ->orderBy('name')
                ->get(),
            'eligible_logistics_companies' => LogisticsCompanyProfile::with(['hqLga.state', 'user'])
                ->where('status', 'active')
                ->whereHas('user', fn ($user) => $user->where('status', 'active'))
                ->whereHas('hqLga', function ($q) {
                    $q->whereRaw('LOWER(TRIM(name)) = ?', ['uyo'])
                        ->whereHas('state', fn ($state) => $state->whereRaw('LOWER(TRIM(name)) = ?', ['akwa ibom']));
                })
                ->orderBy('company_name')
                ->get(['id', 'company_name', 'lga_id', 'status']),
        ]);
    }

    public function storeCorridor(Request $request)
    {
        $this->requireSuperAdmin($request);
        if ($request->has('landmark_aliases_text') && !$request->has('landmark_aliases')) {
            $aliases = str_replace([chr(13), ','], [chr(10), chr(10)], (string) $request->input('landmark_aliases_text'));
            $request->merge(['landmark_aliases' => array_values(array_filter(array_map('trim', explode(chr(10), $aliases)), fn ($alias) => $alias !== ''))]);
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'unique:delivery_corridors,code'],
            'name' => ['required', 'string', 'max:160'],
            'lga_id' => ['required', 'integer', 'exists:lgas,id'],
            'landmark_aliases' => ['required', 'array', 'min:1', 'max:100'],
            'landmark_aliases.*' => ['required', 'string', 'max:160'],
            'estimated_delivery_hours_min' => ['required', 'integer', 'min:1', 'max:168'],
            'estimated_delivery_hours_max' => ['required', 'integer', 'gte:estimated_delivery_hours_min', 'max:168'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $lga = Lga::with('state')->findOrFail($data['lga_id']);
        if (strtolower(trim((string) $lga->name)) !== 'uyo'
            || strtolower(trim((string) ($lga->state->name ?? ''))) !== 'akwa ibom') {
            throw new InvalidArgumentException('Delivery corridors must be located within Uyo, Akwa Ibom State.');
        }

        $data['landmark_aliases'] = array_values(array_unique(array_map(
            fn ($alias) => trim($alias),
            $data['landmark_aliases']
        )));
        if (collect($data['landmark_aliases'])->contains(fn ($alias) => $alias === '')) {
            throw new InvalidArgumentException('Landmark aliases cannot be empty.');
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $corridor = DB::transaction(function () use ($request, $data) {
            $corridor = DeliveryCorridor::create($data);
            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'delivery_corridor_created',
                'target_type' => DeliveryCorridor::class,
                'target_id' => $corridor->id,
                'old_values' => null,
                'new_values' => $corridor->toArray(),
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);
            return $corridor;
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'corridor' => $corridor], 201);
        }

        return redirect()->route('admin.delivery_corridors.manage')->with('status', 'Delivery corridor created. Activate it after verifying its landmark aliases.');
    }

    public function updateCorridor(Request $request, int $id)
    {
        $this->requireSuperAdmin($request);
        if ($request->has('landmark_aliases_text') && !$request->has('landmark_aliases')) {
            $aliases = str_replace([chr(13), ','], [chr(10), chr(10)], (string) $request->input('landmark_aliases_text'));
            $request->merge(['landmark_aliases' => array_values(array_filter(array_map('trim', explode(chr(10), $aliases)), fn ($alias) => $alias !== ''))]);
        }
        $corridor = DeliveryCorridor::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'landmark_aliases' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'landmark_aliases.*' => ['required', 'string', 'max:160'],
            'estimated_delivery_hours_min' => ['sometimes', 'required', 'integer', 'min:1', 'max:168'],
            'estimated_delivery_hours_max' => ['sometimes', 'required', 'integer', 'min:1', 'max:168'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['landmark_aliases'])) {
            $data['landmark_aliases'] = array_values(array_unique(array_map(fn ($alias) => trim($alias), $data['landmark_aliases'])));
        }
        $minHours = (int) ($data['estimated_delivery_hours_min'] ?? $corridor->estimated_delivery_hours_min);
        $maxHours = (int) ($data['estimated_delivery_hours_max'] ?? $corridor->estimated_delivery_hours_max);
        if ($maxHours < $minHours) {
            throw new InvalidArgumentException('Maximum delivery hours must be greater than or equal to minimum hours.');
        }

        $updated = DB::transaction(function () use ($request, $corridor, $data) {
            $locked = DeliveryCorridor::whereKey($corridor->id)->lockForUpdate()->firstOrFail();
            $old = $locked->getOriginal();
            $locked->fill($data);
            $locked->save();
            AdminAuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'delivery_corridor_updated',
                'target_type' => DeliveryCorridor::class,
                'target_id' => $locked->id,
                'old_values' => $old,
                'new_values' => $locked->fresh()->toArray(),
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
            ]);
            return $locked->fresh();
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'corridor' => $updated]);
        }

        return redirect()->route('admin.delivery_corridors.manage')->with('status', 'Delivery corridor updated. Existing order snapshots are unchanged.');
    }

    public function upsertCompanyRate(Request $request, int $id)
    {
        $this->requireSuperAdmin($request);
        $data = $request->validate([
            'logistics_company_id' => ['required', 'integer', 'exists:logistics_company_profiles,id'],
            'base_payable' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'bulky_surcharge_per_unit' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($request, $id, $data) {
            $corridor = DeliveryCorridor::whereKey($id)->lockForUpdate()->firstOrFail();
            $company = LogisticsCompanyProfile::query()
                ->whereKey($data['logistics_company_id'])
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->with('hqLga.state')
                ->lockForUpdate()
                ->first();
            if (!$company
                || !$company->user
                || $company->user->status !== 'active'
                || strtolower(trim((string) ($company->hqLga->name ?? ''))) !== 'uyo'
                || strtolower(trim((string) ($company->hqLga->state->name ?? ''))) !== 'akwa ibom') {
                throw new InvalidArgumentException('The tariff must belong to an active logistics company based in Uyo, Akwa Ibom State.');
            }

            $rate = LogisticsCompanyCorridorRate::where('delivery_corridor_id', $corridor->id)
                ->where('logistics_company_id', $company->id)
                ->lockForUpdate()
                ->first();
            $old = $rate?->toArray();
            $changed = !$rate
                || bccomp((string) $rate->base_payable, (string) $data['base_payable'], 2) !== 0
                || bccomp((string) $rate->bulky_surcharge_per_unit, (string) $data['bulky_surcharge_per_unit'], 2) !== 0
                || (bool) $rate->is_active !== (bool) ($data['is_active'] ?? true);

            if (!$rate) {
                $rate = new LogisticsCompanyCorridorRate([
                    'delivery_corridor_id' => $corridor->id,
                    'logistics_company_id' => $company->id,
                    'tariff_version' => 1,
                ]);
            } elseif ($changed) {
                $rate->tariff_version = (int) $rate->tariff_version + 1;
            }

            $rate->base_payable = $data['base_payable'];
            $rate->bulky_surcharge_per_unit = $data['bulky_surcharge_per_unit'];
            $rate->is_active = (bool) ($data['is_active'] ?? true);
            $rate->save();

            if ($changed) {
                AdminAuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'logistics_company_corridor_rate_changed',
                    'target_type' => LogisticsCompanyCorridorRate::class,
                    'target_id' => $rate->id,
                    'old_values' => $old,
                    'new_values' => [
                        ...$rate->fresh()->toArray(),
                        'corridor_code' => $corridor->code,
                        'company_name' => $company->company_name,
                    ],
                    'ip_address' => $request->ip() ?? '127.0.0.1',
                    'user_agent' => $request->userAgent(),
                ]);
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'rate' => $rate->fresh()]);
            }

            return redirect()->route('admin.delivery_corridors.manage')->with('status', 'Company corridor tariff saved. Existing order snapshots are unchanged.');
        });
    }
}
