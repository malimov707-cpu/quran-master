<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Group;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, string> */
    private const ROLES = [
        'SUPER_ADMIN' => 'Super administrator',
        'CEO' => 'Bosh ijrochi direktor',
        'CENTER_ADMIN' => 'Markaz administratori',
        'HEAD_TEACHER' => 'Bosh ustoz',
        'MENTOR' => 'Mentor',
        'STUDENT' => 'O\'quvchi',
        'PARENT' => 'Ota-ona',
    ];

    /**
     * Har bir rol uchun permission matritsasi.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'CEO' => ['*'],
        'SUPER_ADMIN' => ['*'],
        'CENTER_ADMIN' => [
            'users.view', 'users.create', 'users.update', 'users.deactivate',
            'courses.view', 'courses.create', 'courses.update', 'courses.archive',
            'groups.view', 'groups.create', 'groups.update', 'groups.delete',
            'schedules.view', 'schedules.manage',
            'attendance.view', 'attendance.manage',
            'assignments.view', 'assignments.manage',
            'reports.view',
            'settings.center',
        ],
        'HEAD_TEACHER' => [
            'users.view',
            'courses.view', 'courses.create', 'courses.update',
            'lessons.view', 'lessons.create', 'lessons.update', 'lessons.publish',
            'materials.manage',
            'tajweed.view', 'tajweed.manage',
            'assignments.view', 'assignments.manage',
            'groups.view', 'groups.create', 'groups.update',
            'mentor.review',
            'progress.view',
            'reports.view',
        ],
        'MENTOR' => [
            'students.view',
            'submissions.view', 'submissions.review',
            'lessons.view',
            'assignments.view',
            'progress.view',
            'comments.create',
        ],
        'STUDENT' => [
            'courses.view', 'lessons.view', 'quran.read',
            'assignments.view', 'submissions.create',
            'progress.view', 'notifications.view',
        ],
        'PARENT' => [
            'children.view', 'attendance.view', 'grades.view',
            'assignments.view', 'progress.view', 'notifications.view',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('testing')) {
            $this->call(TajweedRuleSeeder::class);
            $this->call(QuranDataSeeder::class);
            $this->call(SubscriptionPlanSeeder::class);
        } else {
            // Testlarda ham tajvid qoidalari va rejalari kerak
            $this->call(TajweedRuleSeeder::class);
            $this->call(SubscriptionPlanSeeder::class);
        }

        // Rollar
        $roles = [];
        foreach (self::ROLES as $name => $label) {
            $roles[$name] = Role::updateOrCreate(['name' => $name], ['label' => $label]);
        }

        // Permissionlar — barcha rollardan yig'iladi
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                if ($permissionName === '*') {
                    continue;
                }
                [$module, $action] = explode('.', $permissionName);
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    ['label' => ucfirst($action).' '.ucfirst($module), 'module' => $module],
                );
                $roles[$roleName]->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }

        // Super admin — barcha permissionlar (wildcard mantiqiy tekshiriladi)
        $allPermissions = Permission::all();
        $roles['SUPER_ADMIN']->permissions()->sync($allPermissions->pluck('id'));
        $roles['CEO']->permissions()->sync($allPermissions->pluck('id'));

        // Super admin foydalanuvchi — production'da seed TAQIQLANGAN (AppServiceProvider'da tekshiriladi)
        if (app()->environment('production')) {
            throw new \RuntimeException('Seeder production muhitida ishga tushirilmaydi — demo akkauntlar xavfsizlik xavfi.');
        }

        $adminEmail = (string) env('SUPER_ADMIN_EMAIL', 'malimov707@gmail.com');
        $adminPassword = (string) env('SUPER_ADMIN_PASSWORD', '');

        if ($adminEmail === '' || $adminPassword === '') {
            throw new \RuntimeException('SUPER_ADMIN_EMAIL va SUPER_ADMIN_PASSWORD muhit o‘zgaruvchilari majburiy.');
        }

        $admin = User::where('email', 'admin@quronai.uz')->first()
            ?? User::where('email', $adminEmail)->first()
            ?? new User;
        $admin->fill([
            'name' => 'CEO',
            'email' => $adminEmail,
            'password' => $adminPassword,
            'is_active' => true,
        ]);
        $admin->save();
        $admin->roles()->syncWithoutDetaching([$roles['SUPER_ADMIN']->id, $roles['CEO']->id]);

        // Demo foydalanuvchilar (har bir roldan bittadan)
        $demoUsers = [];
        foreach (['CENTER_ADMIN' => 'admin@markaz.uz', 'HEAD_TEACHER' => 'ustoz@markaz.uz', 'MENTOR' => 'mentor@markaz.uz', 'STUDENT' => 'oquvchi@markaz.uz', 'PARENT' => 'ota@markaz.uz'] as $role => $email) {
            $user = User::create([
                'name' => self::ROLES[$role],
                'email' => $email,
                'password' => 'Parol!123',
                'is_active' => true,
            ]);
            $user->roles()->attach($roles[$role]->id);
            $demoUsers[$role] = $user;
        }

        // Demo kurs va guruh (Phase 2)
        $course = Course::create([
            'name' => 'Qur\'on o\'qish — Boshlang\'ich',
            'description' => 'Harflardan boshlab Qur\'on o\'qishni o\'rganish',
            'level' => 'beginner',
            'duration_weeks' => 24,
            'status' => 'published',
            'head_teacher_id' => $demoUsers['HEAD_TEACHER']->id,
        ]);

        Course::create([
            'name' => 'Tajvid asoslari',
            'description' => 'Tajvid qoidalarini o\'rganish',
            'level' => 'intermediate',
            'duration_weeks' => 16,
            'status' => 'published',
            'head_teacher_id' => $demoUsers['HEAD_TEACHER']->id,
        ]);

        $group = Group::create([
            'name' => '1-guruh',
            'course_id' => $course->id,
            'teacher_id' => $demoUsers['HEAD_TEACHER']->id,
            'mentor_id' => $demoUsers['MENTOR']->id,
            'capacity' => 15,
        ]);

        $group->students()->attach([$demoUsers['STUDENT']->id => ['joined_at' => now()]]);
    }
}
