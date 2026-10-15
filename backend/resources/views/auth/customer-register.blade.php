@include('auth.customer-auth', ['initialTab' => 'register', 'lgas' => $lgas ?? \App\Models\Lga::where('is_active', true)->orderBy('name', 'asc')->get()])
