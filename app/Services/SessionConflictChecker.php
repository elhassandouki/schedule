<?php

namespace App\Services;

use App\Models\TimetableSession;
use Illuminate\Validation\ValidationException;

class SessionConflictChecker
{
    public function validate(array $attributes, ?TimetableSession $ignore = null): void
    {
        $base = TimetableSession::with(['subject', 'studentGroup', 'classroom'])
            ->where('day_id', $attributes['day_id'])
            ->where('timeslot_id', $attributes['timeslot_id'])
            ->when(isset($attributes['semester_id']) && $attributes['semester_id'] !== null, fn ($query) => $query->where('semester_id', $attributes['semester_id']))
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()));

        $errors = [];
        $teacherName = \App\Models\Teacher::find($attributes['teacher_id'])?->name ?? 'Selected teacher';

        if ($conflict = (clone $base)->where('teacher_id', $attributes['teacher_id'])->first()) {
            $errors['teacher_id'] = 'Conflit professeur : ' . $teacherName . ' enseigne déjà ' . $this->description($conflict) . '.';
        }
        if ($conflict = (clone $base)->where('classroom_id', $attributes['classroom_id'])->first()) {
            $errors['classroom_id'] = 'Conflit salle : ' . $conflict->classroom->name . ' est déjà utilisée pour ' . $this->description($conflict) . '.';
        }
        if ($conflict = (clone $base)->where('student_group_id', $attributes['student_group_id'])->first()) {
            $errors['student_group_id'] = 'Conflit groupe : ' . $conflict->studentGroup->name . ' a déjà ' . $this->description($conflict) . '.';
        }

        if (!isset($attributes['semester_id'])) {
            if ($errors) throw ValidationException::withMessages($errors);
            return;
        }

        $subject = \App\Models\Subject::find($attributes['subject_id']);
        $group = \App\Models\StudentGroup::find($attributes['student_group_id']);
        $classroom = \App\Models\Classroom::find($attributes['classroom_id']);

        // Subjects are now independent of the semester (post-refactor).
        if ($subject && $subject->teacher_id && $subject->teacher_id !== (int) $attributes['teacher_id']) {
            $errors['teacher_id'] = 'The selected subject is assigned to another teacher.';
        }
        if ($group && $group->semester_id && $group->semester_id !== (int) $attributes['semester_id']) {
            $errors['student_group_id'] = 'The selected group does not belong to the selected semester.';
        }
        if ($group && $classroom) {
            $requiredCapacity = (int) ($group->capacity ?? 0);
            if ($requiredCapacity > 0 && $classroom->capacity < $requiredCapacity) {
                $errors['classroom_id'] = 'Classroom capacity ' . $classroom->capacity . ' is insufficient for group size ' . $requiredCapacity . '.';
            }
        }
        if ($subject && $group && (clone $base)->where('subject_id', $attributes['subject_id'])->where('student_group_id', $attributes['student_group_id'])->exists()) {
            $errors['subject_id'] = 'The selected subject is already scheduled for this group at this time.';
        }

        if ($errors) throw ValidationException::withMessages($errors);
    }

    private function description(TimetableSession $session): string
    {
        return ($session->subject->name ?? 'another session') . ' for group ' . ($session->studentGroup->name ?? '#' . $session->student_group_id);
    }
}
