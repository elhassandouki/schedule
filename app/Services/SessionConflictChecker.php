<?php
namespace App\Services;

use App\Models\{Classroom, Module, StudentGroup, TimetableSession};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionConflictChecker
{
    public function validate(array $attributes, ?TimetableSession $ignore = null): void
    {
        // Contrôle par chevauchement temporel réel : les sessions existantes dont les
        // horaires chevauchent le créneau candidat (même si timeslot_id différent) sont
        // considérées en conflit : une salle / prof / groupe ne peut jamais être
        // double-bookée, y compris entre créneaux qui se chevauchent.
        $base = TimetableSession::where('semester_id', $attributes['semester_id'])->where('day_id', $attributes['day_id'])
            ->join('timeslots', 'timeslots.id', '=', 'timetable_sessions.timeslot_id')
            ->where('timeslots.starts_at', '<', DB::table('timeslots')->where('id', $attributes['timeslot_id'])->value('ends_at'))
            ->where('timeslots.ends_at', '>', DB::table('timeslots')->where('id', $attributes['timeslot_id'])->value('starts_at'))
            ->select('timetable_sessions.*')
            ->when($ignore, fn ($q) => $q->where('timetable_sessions.id', '!=', $ignore->id));
        $errors = [];
        foreach (['professor_id' => 'professeur', 'classroom_id' => 'salle', 'student_group_id' => 'groupe'] as $field => $label)
            if ((clone $base)->where("timetable_sessions.{$field}", $attributes[$field])->exists()) $errors[$field] = "Conflit : ce {$label} est déjà occupé dans ce créneau.";

        $module = Module::find($attributes['module_id']); $group = StudentGroup::find($attributes['student_group_id']); $room = Classroom::find($attributes['classroom_id']);
        if (!$module || $module->semester_id !== (int) $attributes['semester_id']) $errors['module_id'] = 'Le module doit appartenir au semestre choisi.';
        if (!$group || $group->semester_id !== (int) $attributes['semester_id']) $errors['student_group_id'] = 'Le groupe doit appartenir au semestre choisi.';
        if (!DB::table('professor_module')->where(['professor_id' => $attributes['professor_id'], 'module_id' => $attributes['module_id']])->exists()) $errors['professor_id'] = 'Ce professeur ne peut pas enseigner ce module.';
        if ($group && $room && $room->capacity < $group->capacity) $errors['classroom_id'] = 'La capacité de la salle est insuffisante.';
        if ($errors) throw ValidationException::withMessages($errors);
    }
}
