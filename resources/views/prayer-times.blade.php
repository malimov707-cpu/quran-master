@extends(auth()->check() ? 'layouts.app' : 'layouts.auth')
@section('title', __('O‘zbekiston namoz vaqtlari'))
@section('content')
    @include('components.prayer-times')
    @guest<p class="text-center text-sm"><a href="{{ route('login') }}" class="text-forest underline">{{ __('Tizimga kirish') }}</a></p>@endguest
@endsection
