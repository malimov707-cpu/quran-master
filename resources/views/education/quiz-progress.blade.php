<section class="glass-panel my-5 p-5">
    <h2 class="font-bold text-forest">{{ __('Test natijalari') }}</h2>
    <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <p>{{ __('Topshirilgan testlar') }}<strong class="block text-2xl">{{ $quizProgress['completed'] }}</strong></p>
        <p>{{ __('Muvaffaqiyatli testlar') }}<strong class="block text-2xl">{{ $quizProgress['passed'] }}</strong></p>
        <p>{{ __('Eng yaxshi natijalar o‘rtachasi') }}<strong class="block text-2xl">{{ $quizProgress['average_best_score'] ?? '—' }}%</strong></p>
        <p>{{ __('Urinishlar') }}<strong class="block text-2xl">{{ $quizProgress['attempts'] }}</strong></p>
    </div>
    <p class="mt-3 text-sm text-graysoft">{{ __('Har bir testning eng yaxshi natijasi hisoblanadi. Quiz bilimni tekshiradi; tilovat bahosini ustoz qo‘yadi.') }}</p>
</section>
