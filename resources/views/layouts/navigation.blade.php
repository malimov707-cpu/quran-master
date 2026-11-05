@foreach ($navigation as $item)
    <a href="{{ route($item['route']) }}"
       @if (request()->routeIs($item['route'])) aria-current="page" @endif
       class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs($item['route']) ? 'bg-forest text-gold' : 'text-cream/80 hover:bg-forest/50' }}">
        {{ $item['label'] }}
    </a>
@endforeach
