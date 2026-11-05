<?php

namespace App\Services\Courses;

use App\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Kurs biznes-mantiqi. Controller faqat HTTP mas'uliyatini o'taydi.
 */
class CourseService
{
    /**
     * Kurslar ro'yxati (filtr + paginate).
     *
     * @param  array{status?: string, level?: string, search?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<Course>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return Course::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['level'] ?? null, fn ($q, $level) => $q->where('level', $level))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->with('headTeacher:id,name')
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Yangi kurs yaratish.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Course
    {
        $data['head_teacher_id'] ??= auth()->id();

        return Course::create($data);
    }

    /**
     * Kursni yangilash.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Course $course, array $data): Course
    {
        $course->update($data);

        return $course->refresh();
    }

    /**
     * Kursni arxivlash (o'chirish emas — ma'lumot saqlanadi).
     */
    public function archive(Course $course): Course
    {
        $course->update(['status' => 'archived']);

        return $course;
    }

    /**
     * O'quvchiga ko'rinadigan kurslar (faqat published).
     *
     * @return LengthAwarePaginator<Course>
     */
    public function publishedForStudents(): LengthAwarePaginator
    {
        return Course::query()->where('status', 'published')->orderBy('name')->paginate(15);
    }
}
