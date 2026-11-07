@extends('layouts.app')
@section('title', __('Rol va bog‘lanishlar'))
@section('content')
    @php
        $selectedRoles = old('role_ids', $user->roles->pluck('id')->all());
        $selectedRoles = is_array($selectedRoles) ? $selectedRoles : [];
        $selectedGroups = old('group_ids', $groups->where('mentor_id', $user->id)->pluck('id')->all());
        $selectedGroups = is_array($selectedGroups) ? $selectedGroups : [];
        $selectedChildren = old('student_ids', $user->children->pluck('id')->all());
        $selectedChildren = is_array($selectedChildren) ? $selectedChildren : [];
    @endphp
    <a href="{{ route('administration', 'users') }}" class="text-sm text-forest">← {{ __('Foydalanuvchilar') }}</a>
    <h1 class="mt-4 text-2xl font-bold text-forest">{{ $user->name }}</h1>
    <p class="mt-2 break-all text-sm text-graysoft">{{ $user->email }} · {{ $user->is_active ? __('Faol') : __('Faol emas') }}</p>
    @if ($errors->any())<div role="alert" class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="mt-6 grid items-start gap-5 xl:grid-cols-2">
        <section class="glass-panel p-5 sm:p-7">
            <h2 class="mb-4 text-lg font-bold text-forest">{{ __('Rollar') }}</h2>
            @if (auth()->id() === $user->id)
                <p class="text-sm text-graysoft">{{ __('O‘z rolingizni bu yerdan o‘zgartira olmaysiz.') }}</p><p class="mt-3 text-sm">{{ $user->roles->pluck('label')->join(', ') }}</p>
            @else
                <form method="POST" action="{{ route('administration.users.roles', $user) }}">
                    @csrf @method('PATCH')
                    <div class="grid gap-3 sm:grid-cols-2">@foreach ($roles as $role)<label class="flex items-center gap-3 rounded-xl bg-cream p-3"><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, $selectedRoles))><span class="text-sm">{{ __($role->label) }}</span></label>@endforeach</div>
                    <p class="mt-4 text-xs leading-5 text-graysoft">{{ __('Rol o‘zgarsa, foydalanuvchi qayta tizimga kiradi. Bog‘langan rolni olib tashlashdan oldin uning bog‘lanishlarini uzing.') }}</p>
                    <button type="submit" class="mt-5 rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __('Rollarni saqlash') }}</button>
                </form>
            @endif
        </section>
        @if ($user->roles->contains('name', 'MENTOR'))
            <section class="glass-panel p-5 sm:p-7"><h2 class="text-lg font-bold text-forest">{{ __('Mentorning guruhlari') }}</h2><p class="mt-2 text-sm leading-6 text-graysoft">{{ __('Belgilangan guruhlar shu mentorga o‘tadi. Belgisi olib tashlangan guruhlardan mentor ajratiladi.') }}</p>
                <form method="POST" action="{{ route('administration.users.mentor-groups', $user) }}" class="mt-5">
                    @csrf @method('PUT')
                    <fieldset class="max-h-96 space-y-3 overflow-y-auto"><legend class="sr-only">{{ __('Guruhlar') }}</legend>@forelse ($groups as $group)<label class="flex items-start gap-3 rounded-xl bg-cream p-3"><input type="checkbox" class="mt-1" name="group_ids[]" value="{{ $group->id }}" @checked(in_array($group->id, $selectedGroups))><span><strong class="block text-sm">{{ $group->name }}</strong><small class="text-graysoft">{{ __('Mentor') }}: {{ $group->mentor?->name ?? '—' }} · {{ $group->status }}</small></span></label>@empty<p class="text-sm text-graysoft">{{ __('Guruhlar topilmadi.') }}</p>@endforelse</fieldset>
                    <button type="submit" class="mt-5 rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __('Guruhlarni saqlash') }}</button>
                </form>
            </section>
        @endif
        @if ($user->roles->contains('name', 'PARENT'))
            <section class="glass-panel p-5 sm:p-7"><h2 class="text-lg font-bold text-forest">{{ __('Ota-ona va farzandlar') }}</h2><p class="mt-2 text-sm leading-6 text-graysoft">{{ __('Ota-ona faqat shu yerda belgilangan farzandlarining natijalarini ko‘radi. Belgini olib tashlash bog‘lanishni uzadi.') }}</p>
                <form method="POST" action="{{ route('administration.users.children', $user) }}" class="mt-5">
                    @csrf @method('PUT')
                    <fieldset class="max-h-96 space-y-3 overflow-y-auto"><legend class="sr-only">{{ __('Farzandlar') }}</legend>@forelse ($students as $student)<label class="flex items-start gap-3 rounded-xl bg-cream p-3"><input type="checkbox" class="mt-1" name="student_ids[]" value="{{ $student->id }}" @checked(in_array($student->id, $selectedChildren))><span class="min-w-0"><strong class="block text-sm">{{ $student->name }}</strong><small class="break-all text-graysoft">{{ $student->email }}</small></span></label>@empty<p class="text-sm text-graysoft">{{ __('O‘quvchilar topilmadi.') }}</p>@endforelse</fieldset>
                    <button type="submit" class="mt-5 rounded-xl bg-forest px-5 py-3 font-semibold text-white">{{ __('Farzandlarni saqlash') }}</button>
                </form>
            </section>
        @endif
        @if ($parents->isNotEmpty())<section class="glass-panel p-5 sm:p-7"><h2 class="font-bold text-forest">{{ __('Bog‘langan ota-onalar') }}</h2>@foreach ($parents as $parent)<a href="{{ route('administration.users.edit', $parent) }}" class="mt-3 block text-sm text-forest underline">{{ $parent->name }} →</a>@endforeach</section>@endif
    </div>
@endsection
