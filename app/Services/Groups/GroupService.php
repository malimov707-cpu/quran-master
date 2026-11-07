<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Guruh va mentor taqsimlash biznes-mantiqi.
 */
class GroupService
{
    /**
     * Guruhga o'quvchi qo'shish.
     */
    public function addStudent(Group $group, User $student): void
    {
        DB::transaction(function () use ($group, $student): void {
            $locked = Group::whereKey($group->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active', 422, __('Guruh faol emas.'));
            abort_unless($student->is_active && $student->hasRole('STUDENT'), 422, __('Faol o‘quvchi akkauntini tanlang.'));
            if ($locked->students()->where('users.id', $student->id)->exists()) {
                return;
            }
            if ($locked->capacity !== null && $locked->students()->count() >= $locked->capacity) {
                abort(422, 'Guruh to‘lgan.');
            }
            DB::table('group_students')->updateOrInsert(['group_id' => $locked->id, 'student_id' => $student->id], ['joined_at' => now(), 'left_at' => null]);
        });
    }

    /**
     * O'quvchini guruhdan chiqarish (tarix saqlanadi).
     */
    public function removeStudent(Group $group, User $student): void
    {
        $group->students()->updateExistingPivot($student->id, ['left_at' => now()]);
    }

    /**
     * Guruhga ustoz tayinlash.
     */
    public function assignTeacher(Group $group, User $teacher): Group
    {
        if (! $teacher->hasRole('HEAD_TEACHER', 'MENTOR')) {
            abort(422, 'Foydalanuvchi ustoz emas.');
        }

        $group->update(['teacher_id' => $teacher->id]);

        return $group;
    }

    /**
     * Guruhga mentor tayinlash + o'quvchilarga mentor biriktirish.
     */
    public function assignMentor(Group $group, User $mentor): Group
    {
        if (! $mentor->hasRole('MENTOR')) {
            abort(422, 'Foydalanuvchi mentor emas.');
        }

        DB::transaction(function () use ($group, $mentor): void {
            $group->update(['mentor_id' => $mentor->id]);

            // Guruh o'quvchilariga faol mentor biriktiruvi (oldingi faol biriktiruv tugatiladi)
            foreach ($group->students as $student) {
                $this->assignStudentToMentor($mentor, $student, $group);
            }
        });

        return $group;
    }

    /**
     * O'quvchini mentorga biriktirish. Bir vaqtda faol biriktiruv bitta bo'ladi.
     */
    public function assignStudentToMentor(User $mentor, User $student, ?Group $group = null): void
    {
        DB::transaction(function () use ($mentor, $student, $group): void {
            // Joriy faol biriktiruvni tugatish
            DB::table('mentor_assignments')
                ->where('student_id', $student->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            DB::table('mentor_assignments')->insert([
                'mentor_id' => $mentor->id,
                'student_id' => $student->id,
                'group_id' => $group?->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * O'quvchilarni mentorlar orasida avtomatik taqsimlash.
     * Mentorning joriy yuklamasi va mavjudligi hisobga olinadi.
     *
     * @param  Collection<int, User>  $students
     * @return array<string, int> mentor_id => biriktirilgan o'quvchilar soni
     */
    public function autoDistribute(array $students): array
    {
        $mentors = User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'MENTOR'))
            ->get();

        if ($mentors->isEmpty()) {
            abort(422, 'Faol mentor topilmadi.');
        }

        // Har mentorning joriy faol yuklamasi
        $load = DB::table('mentor_assignments')
            ->whereNull('ended_at')
            ->whereIn('mentor_id', $mentors->pluck('id'))
            ->selectRaw('mentor_id, count(*) as load')
            ->groupBy('mentor_id')
            ->pluck('load', 'mentor_id');

        $distribution = [];
        $studentsToAssign = collect($students);

        while ($studentsToAssign->isNotEmpty()) {
            // Eng kam yuklamali mentorni tanlash
            $mentor = $mentors->sortBy(fn ($m) => $load->get($m->id, 0))->first();
            $student = $studentsToAssign->shift();

            $this->assignStudentToMentor($mentor, $student);
            $load[$mentor->id] = $load->get($mentor->id, 0) + 1;
            $distribution[$mentor->id] = ($distribution[$mentor->id] ?? 0) + 1;
        }

        return $distribution;
    }
}
