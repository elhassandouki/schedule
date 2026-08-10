<?php
namespace App\Http\Requests;

use App\Services\TimetableConflictValidator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimetableSessionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'subject_id' => ['required', 'exists:subjects,id'], 'semester_id' => ['required', 'exists:semesters,id'], 'teacher_id' => ['required', 'exists:teachers,id'],
        'classroom_id' => ['required', 'exists:classrooms,id'], 'student_group_id' => ['required', 'exists:student_groups,id'],
        'timeslot_id' => ['required', 'exists:timeslots,id'], 'day_id' => ['required', 'exists:days,id'],
    ]; }
    public function after(): array { return [function ($validator) {
        if (!$validator->errors()->isEmpty()) return;
        try { app(TimetableConflictValidator::class)->validate($this->validated(), $this->route('timetableSession')); }
        catch (\Illuminate\Validation\ValidationException $exception) { foreach ($exception->errors() as $field => $messages) foreach ($messages as $message) $validator->errors()->add($field, $message); }
    }]; }
}
