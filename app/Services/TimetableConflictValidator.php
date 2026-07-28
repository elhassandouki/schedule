<?php
namespace App\Services;

use App\Models\TimetableSession;
use Illuminate\Validation\ValidationException;

/** Reusable availability guard for forms, imports, and drag-and-drop updates. */
class TimetableConflictValidator
{
    public function validate(array $attributes, ?TimetableSession $ignore = null): void
    {
        $base = TimetableSession::query()
            ->where('day_id', $attributes['day_id'])
            ->where('timeslot_id', $attributes['timeslot_id'])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()));

        $errors = [];
        if ((clone $base)->where('teacher_id', $attributes['teacher_id'])->exists()) {
            $errors['teacher_id'] = 'This teacher already has a session in this day and timeslot.';
        }
        if ((clone $base)->where('classroom_id', $attributes['classroom_id'])->exists()) {
            $errors['classroom_id'] = 'This classroom is already booked in this day and timeslot.';
        }
        if ((clone $base)->where('section_id', $attributes['section_id'])->exists()) {
            $errors['section_id'] = 'This section already has a session in this day and timeslot.';
        }
        if ($errors) throw ValidationException::withMessages($errors);
    }
}
