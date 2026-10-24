@php
    $categories = [
        ['name' => 'Groceries & Pantry', 'icon' => '🛒', 'tag' => 'Daily Fresh'],
        ['name' => 'Kitchen & Home', 'icon' => '🍳', 'tag' => 'Quality Verified'],
        ['name' => 'Personal Care', 'icon' => '✨', 'tag' => '100% Genuine'],
        ['name' => 'Electronics', 'icon' => '⚡', 'tag' => 'Warranty Backed'],
    ];
@endphp

@foreach($categories as $cat)
    <a href="{{ route('storefront') }}#catalog" class="vm-discovery-tile" style="text-decoration: none; transition: transform 0.2s ease;">
        <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 10px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
            <span style="font-size: 28px; line-height: 1; margin-bottom: 6px;">{{ $cat['icon'] }}</span>
            <span class="vm-tile-floating-badge" style="position: absolute; bottom: 5px; left: 5px; right: 5px; font-size: 9px; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(4px); color: #FFFFFF; padding: 2px 4px; border-radius: 4px; font-weight: 700; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                {{ $cat['tag'] }}
            </span>
        </div>
        <span class="vm-discovery-tile-caption" style="display: block; font-size: 11.5px; color: #0F172A; margin-top: 5px; font-weight: 700; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $cat['name'] }}</span>
    </a>
@endforeach