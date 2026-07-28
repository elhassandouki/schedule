<?php
namespace App\Services;

use App\Models\TimetableSession;
use Illuminate\Validation\ValidationException;

/** Reusable availability guard for forms, imports, and drag-and-drop updates. */
class TimetableConflictValidator
{
    public function validate(array $attributes, ?TimetableSession $ignore = null): void
    {
        app(SessionConflictChecker::class)->validate($attributes, $ignore);
    }
}
