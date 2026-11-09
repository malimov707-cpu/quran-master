<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Oylik o'quv to'lovi (invoice) — bir guruh, bir oy, bir o'quvchi.
 */
class FeeItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'student_id', 'group_id', 'course_id', 'month', 'year',
        'amount_tiyin', 'status', 'paid_amount_tiyin', 'due_date', 'payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_tiyin' => 'integer',
            'paid_amount_tiyin' => 'integer',
            'due_date' => 'datetime',
        ];
    }

    /**
     * O'quvchi.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Guruh.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Kurs.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
