{{-- 3D-ish SVG art icons: real extrusion with layered gradients --}}
@props(['name'])
@php $uid = 'g'.md5($name.uniqid()); @endphp

@if ($name === 'mushaf')
    <svg {{ $attributes->merge(['class' => 'w-full h-full']) }} viewBox="0 0 64 64" fill="none">
        <defs>
            <linearGradient id="{{ $uid }}-cover" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#2fd39a"/><stop offset="1" stop-color="#0a5540"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-side" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#0d3f30"/><stop offset="1" stop-color="#062e23"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-gold" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#f0d687"/><stop offset="1" stop-color="#b98f1f"/>
            </linearGradient>
        </defs>
        {{-- extrusion side --}}
        <path d="M14 16v32l6 4V20z" fill="url(#{{ $uid }}-side)"/>
        <path d="M50 16v32l-6 4V20z" fill="url(#{{ $uid }}-side)"/>
        {{-- base book bottom shadow layer --}}
        <path d="M14 48l8 6h20l8-6z" fill="url(#{{ $uid }}-side)" opacity=".7"/>
        {{-- pages left/right raised --}}
        <path d="M18 14l14 4v28l-14-4z" fill="#fdf8ea"/>
        <path d="M46 14l-14 4v28l14-4z" fill="#f4ecd6"/>
        {{-- cover rim --}}
        <path d="M18 14l14 4-0 28l-14-4z" fill="none" stroke="url(#{{ $uid }}-gold)" stroke-width="1.6"/>
        <path d="M46 14l-14 4v28l14-4z" fill="none" stroke="url(#{{ $uid }}-gold)" stroke-width="1.6"/>
        {{-- center spine star --}}
        <path d="M32 18v28" stroke="url(#{{ $uid }}-gold)" stroke-width="1.4"/>
        <path d="M32 26l3 3-3 3-3-3z" fill="url(#{{ $uid }}-gold)"/>
    </svg>
@elseif ($name === 'moon')
    <svg {{ $attributes->merge(['class' => 'w-full h-full']) }} viewBox="0 0 64 64" fill="none">
        <defs>
            <linearGradient id="{{ $uid }}-moon" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#f6e19a"/><stop offset="1" stop-color="#c9a227"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-mside" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#8a6d14"/><stop offset="1" stop-color="#5d470d"/>
            </linearGradient>
        </defs>
        <circle cx="36" cy="34" r="17" fill="url(#{{ $uid }}-mside)" opacity=".55"/>
        <circle cx="32" cy="30" r="17" fill="url(#{{ $uid }}-moon)"/>
        <path d="M26 18a17 17 0 100 24 20 20 0 010-24z" fill="url(#{{ $uid }}-mside)" opacity=".25"/>
        <path d="M39 22l2.6 5.4 5.4 2.6-5.4 2.6L39 36l-2.6-5.4L31 28l5.4-2.6z" fill="#fff8dd"/>
        <circle cx="24" cy="24" r="1.6" fill="#fff8dd" opacity=".8"/>
    </svg>
@elseif ($name === 'trophy')
    <svg {{ $attributes->merge(['class' => 'w-full h-full']) }} viewBox="0 0 64 64" fill="none">
        <defs>
            <linearGradient id="{{ $uid }}-cup" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#f5dc8b"/><stop offset="1" stop-color="#b8921d"/>
            </linearGradient>
        </defs>
        <path d="M20 14h24v10c0 8-5 14-12 14s-12-6-12-14z" fill="url(#{{ $uid }}-cup)"/>
        <path d="M20 16h-6c0 8 4 12 8 13z" fill="url(#{{ $uid }}-cup)" opacity=".8"/>
        <path d="M44 16h6c0 8-4 12-8 13z" fill="url(#{{ $uid }}-cup)" opacity=".8"/>
        <rect x="29" y="38" width="6" height="8" fill="url(#{{ $uid }}-cup)"/>
        <rect x="22" y="46" width="20" height="6" rx="2" fill="url(#{{ $uid }}-cup)"/>
        <path d="M26 20h12v3H26z" fill="#fff8dd" opacity=".6"/>
    </svg>
@elseif ($name === 'clock')
    <svg {{ $attributes->merge(['class' => 'w-full h-full']) }} viewBox="0 0 64 64" fill="none">
        <defs>
            <linearGradient id="{{ $uid }}-face" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#e8f5ee"/><stop offset="1" stop-color="#bfdccb"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-rim" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#159a6f"/><stop offset="1" stop-color="#0a4a37"/>
            </linearGradient>
        </defs>
        <circle cx="33" cy="34" r="18" fill="#0a4a37" opacity=".5"/>
        <circle cx="30" cy="30" r="18" fill="url(#{{ $uid }}-rim)"/>
        <circle cx="30" cy="30" r="14" fill="url(#{{ $uid }}-face)"/>
        <path d="M30 22v9l6 4" stroke="#0b6246" stroke-width="3" stroke-linecap="round"/>
        <circle cx="30" cy="30" r="2" fill="#0b6246"/>
    </svg>
@elseif ($name === 'mic')
    <svg {{ $attributes->merge(['class' => 'w-full h-full']) }} viewBox="0 0 64 64" fill="none">
        <defs>
            <linearGradient id="{{ $uid }}-body" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#f0d687"/><stop offset="1" stop-color="#a57d15"/>
            </linearGradient>
            <linearGradient id="{{ $uid }}-stand" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#22c48c"/><stop offset="1" stop-color="#0a4a37"/>
            </linearGradient>
        </defs>
        <rect x="24" y="10" width="16" height="26" rx="8" fill="url(#{{ $uid }}-body)"/>
        <path d="M18 28c0 8 6 14 14 14s14-6 14-14" stroke="url(#{{ $uid }}-stand)" stroke-width="4" stroke-linecap="round"/>
        <path d="M31 42v8M23 52h18" stroke="url(#{{ $uid }}-stand)" stroke-width="4" stroke-linecap="round"/>
        <rect x="27" y="15" width="10" height="14" rx="5" fill="#fff8dd" opacity=".55"/>
    </svg>
@elseif ($name === 'rocket')
    <svg {{ $attributes->merge(['class' => 'w-full h-full']) }} viewBox="0 0 64 64" fill="none">
        <defs>
            <linearGradient id="{{ $uid }}-rb" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#2fd39a"/><stop offset="1" stop-color="#0a4a37"/>
            </linearGradient>
        </defs>
        <path d="M32 8c9 6 13 15 13 26l-6 8H25l-6-8c0-11 4-20 13-26z" fill="url(#{{ $uid }}-rb)"/>
        <circle cx="32" cy="26" r="6" fill="#eaf6ef"/><circle cx="32" cy="26" r="6" fill="none" stroke="#c9a227" stroke-width="1.6"/>
        <path d="M19 34l-6 10 10-2zM45 34l6 10-10-2z" fill="url(#{{ $uid }}-rb)" opacity=".8"/>
        <path d="M32 46v8c3-2 6-2 8 0-2-5-4-7-8-8z" fill="#e8933f"/>
    </svg>
@elseif ($name === 'users')
    <svg {{ $attributes->merge(['class' => 'w-full h-full']) }} viewBox="0 0 64 64" fill="none">
        <defs>
            <linearGradient id="{{ $uid }}-u" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#2fd39a"/><stop offset="1" stop-color="#0a4a37"/>
            </linearGradient>
        </defs>
        <circle cx="26" cy="24" r="9" fill="url(#{{ $uid }}-u)"/>
        <path d="M12 50c0-9 6-14 14-14s14 5 14 14z" fill="url(#{{ $uid }}-u)"/>
        <circle cx="45" cy="26" r="7" fill="url(#{{ $uid }}-u)" opacity=".75"/>
        <path d="M40 50c0-7 4-12 10-12 5 0 9 4 9 12z" fill="url(#{{ $uid }}-u)" opacity=".75"/>
    </svg>
@endif
