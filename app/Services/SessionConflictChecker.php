<?php

namespace App\Services;

use App\Models\TimetableSession;
use Illuminate\Validation\ValidationException;

class SessionConflictChecker
{
    public function validate(array $attributes, ?TimetableSession $ignore = null): void
    {
        $base = TimetableSession::with(['subject', 'section', 'classroom'])
            ->where('day_id', $attributes['day_id'])
            ->where('timeslot_id', $attributes['timeslot_id'])
            ->when(isset($attributes['semester_id']) && $attributes['semester_id'] !== null, fn ($query) => $query->where('semester_id', $attributes['semester_id']))
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()));
        $errors = [];
        $teacherName = \App\Models\Teacher::find($attributes['teacher_id'])?->name ?? 'Selected teacher';
        if ($conflict = (clone $base)->where('teacher_id', $attributes['teacher_id'])->first()) {
            $errors['teacher_id'] = 'Teacher conflict: '.$teacherName.' is already teaching '.$this->description($conflict).'.';
        }
        if ($conflict = (clone $base)->where('classroom_id', $attributes['classroom_id'])->first()) {
            $errors['classroom_id'] = 'Classroom conflict: '.$conflict->classroom->name.' is already used for '.$this->description($conflict).'.';
        }
        if ($conflict = (clone $base)->where('section_id', $attributes['section_id'])->first()) {
            $errors['section_id'] = 'Group conflict: '.$conflict->section->name.' already has '.$this->description($conflict).'.';
        }

        if (!isset($attributes['semester_id'])) {
            if ($errors) throw ValidationException::withMessages($errors);
            return;
        }
        $subject = \App\Models\Subject::find($attributes['subject_id']);
        $semester = \App\Models\Semester::find($attributes['semester_id']);
        $section = \App\Models\Section::find($attributes['section_id']);
        $classroom = \App\Models\Classroom::find($attributes['classroom_id']);
        if ($subject && $subject->semester_id && $subject->semester_id !== (int) $attributes['semester_id']) {
            $errors['subject_id'] = 'The selected subject does not belong to this semester.';
        }
        if ($subject && $subject->teacher_id && $subject->teacher_id !== (int) $attributes['teacher_id']) {
            $errors['teacher_id'] = 'The selected subject is assigned to another teacher.';
        }
        if ($semester && $section && $section->program_id && $section->program_id !== $semester->program_id) {
            $errors['section_id'] = 'The selected group does not belong to the semester programme.';
        }
        if ($section && $classroom) {
            $requiredCapacity = max((int) ($section->capacity ?? 0), (int) $section->students()->count());
            if ($classroom->capacity < $requiredCapacity) {
                $errors['classroom_id'] = 'Classroom capacity '.$classroom->capacity.' is insufficient for section size '.$requiredCapacity.'.';
            }
        }
        if ($subject && $section && (clone $base)->where('subject_id', $attributes['subject_id'])->where('section_id', $attributes['section_id'])->exists()) {
            $errors['subject_id'] = 'The selected subject is already scheduled for this section at this time.';
        }
        if ($errors) throw ValidationException::withMessages($errors);
    }

    private function description(TimetableSession $session): string
    {
        return ($session->subject->name ?? 'another session').' for group '.($session->section->name ?? '#'.$session->section_id);
    }
}
