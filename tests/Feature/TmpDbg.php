<?php
use App\Models\User;
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);
test('dbg mentor perms', function () {
    $this->seed(DatabaseSeeder::class);
    $m = User::where('email','mentor@markaz.uz')->first();
    dump('ROLES:'.json_encode($m->roles->pluck('name')));
    dump('REVIEW:'.($m->hasPermission('submissions.review') ? 'YES':'NO'));
    fail('stop');
});
