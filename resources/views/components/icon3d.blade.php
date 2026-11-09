@props(['variant' => 'emerald', 'float' => false, 'size' => 48])
@php $classes = 'icon3d icon3d-'.$variant.($float ? ' icon3d-float' : ''); @endphp
<span {{ $attributes->merge(['class' => $classes]) }} style="width:{{ $size }}px;height:{{ $size }}px">
    {{ $slot }}
</span>
