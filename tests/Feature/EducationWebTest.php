<?php

use App\Models\Course;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('admin sees courses page', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();

    Course::create(['name' => 'Qiroat asoslari', 'level' => 'beginner', 'status' => 'published']);

    $this->actingAs($admin)->get(route('education.courses'))
        ->assertOk()
        ->assertSee('Qiroat asoslari');
});

test('head teacher can create course via web form', function () {
    $teacher = User::where('email', 'ustoz@markaz.uz')->first();

    $this->actingAs($teacher)->post(route('education.courses.store'), [
        'name' => 'Yangi kurs web',
        'level' => 'beginner',
    ])->assertRedirect()->assertSessionHas('status');

    expect(Course::where('name', 'Yangi kurs web')->exists())->toBeTrue();
});

test('admin sees groups page', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();

    $course = Course::create(['name' => 'Kurs', 'level' => 'beginner']);
    Group::create(['name' => '1-guruh', 'course_id' => $course->id]);

    $this->actingAs($admin)->get(route('education.groups'))
        ->assertOk()
        ->assertSee('1-guruh');
});

test('admin can create group via web form', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $course = Course::create(['name' => 'Kurs', 'level' => 'beginner']);

    $this->actingAs($admin)->post(route('education.groups.store'), [
        'name' => '2-guruh',
        'course_id' => $course->id,
        'capacity' => 10,
    ])->assertRedirect()->assertSessionHas('status');

    expect(Group::where('name', '2-guruh')->exists())->toBeTrue();
});

test('admin can add student to group via web form', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $course = Course::create(['name' => 'Kurs', 'level' => 'beginner']);
    $group = Group::create(['name' => 'G', 'course_id' => $course->id]);
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $this->actingAs($admin)->post(route('education.groups.add-student.select'), [
        'group_id' => $group->id,
        'student_id' => $student->id,
    ])->assertRedirect()->assertSessionHas('status');

    expect($group->refresh()->students->pluck('id'))->toContain($student->id);
});

test('guest cannot access courses page', function () {
    $this->get(route('education.courses'))->assertRedirect('/login');
});
