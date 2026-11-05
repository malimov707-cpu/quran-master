<?php
use App\Models\User;
use App\Models\Course;
use App\Models\Group;
use App\Models\Assignment;
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);
test('dbg http submit', function () {
    $this->seed(DatabaseSeeder::class);
    $s = User::create(['name'=>'S','email'=>'s1@test.uz','password'=>'Parol!123']);
    $s->roles()->attach(App\Models\Role::where('name','STUDENT')->firstOrFail()->id);
    $tok = $s->createToken('e')->plainTextToken;
    $audio = Illuminate\Http\Testing\File::fake()->createWithContent('r.wav', str_repeat('RIFF',100));
    $res = $this->withToken($tok)->post('/api/v1/assignments/999/submit', ['audio'=>[$audio]]);
    dump('status: '.$res->getStatusCode());
    fail('stop');
});
