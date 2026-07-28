<?php
namespace App\Services;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ProfessorModuleEligibility
{
    public function validate(int $professorId, int $moduleId): void
    {
        if (!DB::table('professor_module')->where('professor_id', $professorId)->where('module_id', $moduleId)->exists()) {
            throw ValidationException::withMessages(['professor_id' => 'This professor is not assigned to teach the selected module.']);
        }
    }

    public function validateTeachingSession(array $data): void
    {
        $this->validate($data['professor_id'], $data['module_id']);
        $semester = DB::table('semesters')->find($data['semester_id']);
        $module = DB::table('modules')->find($data['module_id']);
        $group = DB::table('student_groups')->find($data['student_group_id']);
        $errors = [];
        if (!$semester || !$module || $semester->program_id !== $module->program_id) $errors['module_id'] = 'The module must belong to the semester programme.';
        if (!$group || $group->semester_id !== $data['semester_id']) $errors['student_group_id'] = 'The group must belong to the selected semester.';
        if ($errors) throw ValidationException::withMessages($errors);
    }
}
