<?php

namespace Modules\Courses\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One learner's run through one course.
 */
class CourseEnrollment extends Model
{
    protected $fillable = [
        'course_id',
        'user_id',
        'started_at',
        'completed_at',
        'last_lesson_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastLesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'last_lesson_id');
    }
}
