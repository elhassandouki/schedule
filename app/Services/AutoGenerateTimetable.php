<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Unified auto-generation service: creates TimetableSession records.
 *
 * Constraints enforced:
 *  1. No teacher teaches two places at the same time (day/timeslot)
 *  2. No classroom is double-booked (day/timeslot)
 *  3. No student group is double-booked (day/timeslot)
 *  4. Classroom capacity ≥ section size
 *  5. All records respect semester scoping
 *
 * Algorithm: Greedy slot-filling per subject:
 *  - For each subject in a semester
 *  - For each section that needs this subject
 *  - Fill required sessions_per_week into earliest available (day, timeslot)
 *  - Skip if any constraint violated
 *  - Report success/skips at end
 */
class AutoGenerateTimetable
{
    private array $generated = [];
    private array $skipped = [];
    private int $totalRequiredSessions = 0;
    private int $totalGeneratedSessions = 0;
    private int $totalSkippedSessions = 0;
    private int $conflictsEncountered = 0;
    private int $unavailableSlots = 0;
    private array $affectedTeachers = [];
    private array $affectedSections = [];
    private array $affectedClassrooms = [];
    private array $incompleteSubjects = [];

    public function generate(int $semesterId): array
    {
        $this->generated = [];
        $this->skipped = [];
        $this->totalRequiredSessions = 0;
        $this->totalGeneratedSessions = 0;
        $this->totalSkippedSessions = 0;
        $this->conflictsEncountered = 0;
        $this->unavailableSlots = 0;
        $this->affectedTeachers = [];
        $this->affectedSections = [];
        $this->affectedClassrooms = [];
        $this->incompleteSubjects = [];

        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) {
            return ['success' => false, 'error' => "Semester #{$semesterId} not found"];
        }

        $subjects = DB::table('subjects')->where('semester_id', $semesterId)->get();
        $sections = DB::table('sections')->where('program_id', $semester->program_id)->get();
        $days = DB::table('days')->orderBy('position')->get();
        $timeslots = DB::table('timeslots')->orderBy('position')->get();
        $classrooms = DB::table('classrooms')->orderBy('capacity', 'desc')->get();

        if ($subjects->isEmpty()) {
            return ['success' => false, 'error' => "No subjects found for semester #{$semesterId}"];
        }
        if ($sections->isEmpty()) {
            return ['success' => false, 'error' => "No sections found for program #{$semester->program_id}"];
        }

        foreach ($subjects as $subject) {
            $this->generated[$subject->id] = 0;
            $this->skipped[$subject->id] = [];

            $sessionsNeeded = (int) ($subject->sessions_per_week ?? 1);
            $requiredForSubject = $sessionsNeeded * count($sections);
            $this->totalRequiredSessions += $requiredForSubject;

            foreach ($sections as $section) {
                $remaining = $sessionsNeeded;

                foreach ($days as $day) {
                    if ($remaining <= 0) break;

                    foreach ($timeslots as $timeslot) {
                        if ($remaining <= 0) break;

                        $classroom = $this->findAvailableClassroom(
                            $subject, $section, $day->id, $timeslot->id, $classrooms
                        );

                        if (!$classroom) {
                            $this->unavailableSlots++;
                            $this->skipped[$subject->id][] = "No available classroom for {$section->name} on {$day->name} {$timeslot->starts_at}";
                            $this->totalSkippedSessions++;
                            $this->recordAffectedSection($section->name);
                            continue;
                        }

                        $conflict = $this->checkConstraints(
                            $subject, $section, $day->id, $timeslot->id, $classroom->id, $semesterId
                        );

                        if ($conflict) {
                            $this->conflictsEncountered++;
                            $this->skipped[$subject->id][] = $conflict;
                            $this->totalSkippedSessions++;
                            $this->recordAffectedTeacher($subject->teacher_id);
                            $this->recordAffectedSection($section->name);
                            $this->recordAffectedClassroom($classroom->id);
                            continue;
                        }

                        DB::table('timetable_sessions')->insert([
                            'subject_id' => $subject->id,
                            'teacher_id' => $subject->teacher_id,
                            'section_id' => $section->id,
                            'classroom_id' => $classroom->id,
                            'semester_id' => $semesterId,
                            'day_id' => $day->id,
                            'timeslot_id' => $timeslot->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $this->generated[$subject->id]++;
                        $this->totalGeneratedSessions++;
                        $this->recordAffectedTeacher($subject->teacher_id);
                        $this->recordAffectedSection($section->name);
                        $this->recordAffectedClassroom($classroom->id);
                        $remaining--;
                    }
                }
            }

            if ($this->generated[$subject->id] < $requiredForSubject) {
                $this->incompleteSubjects[] = [
                    'subject_id' => $subject->id,
                    'subject_name' => DB::table('subjects')->find($subject->id)?->name ?? 'Unknown',
                    'required' => $requiredForSubject,
                    'generated' => $this->generated[$subject->id],
                ];
            }
        }

        return $this->buildSummary($semesterId);
    }

    private function findAvailableClassroom($subject, $section, $dayId, $timeslotId, $classrooms)
    {
        foreach ($classrooms as $classroom) {
            if ($classroom->capacity < $section->capacity) {
                continue;
            }

            $booked = DB::table('timetable_sessions')
                ->where('semester_id', DB::raw("(SELECT id FROM semesters WHERE id = {$subject->semester_id} LIMIT 1)"))
                ->where('day_id', $dayId)
                ->where('timeslot_id', $timeslotId)
                ->where('classroom_id', $classroom->id)
                ->exists();

            if (!$booked) {
                return $classroom;
            }
        }

        return null;
    }

    private function checkConstraints($subject, $section, $dayId, $timeslotId, $classroomId, $semesterId): ?string
    {
        $teacherConflict = DB::table('timetable_sessions')
            ->where('semester_id', $semesterId)
            ->where('teacher_id', $subject->teacher_id)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->exists();
        if ($teacherConflict) {
            $teacher = DB::table('teachers')->find($subject->teacher_id);
            return "Teacher '{$teacher->name}' already teaches at this time.";
        }

        $classroomConflict = DB::table('timetable_sessions')
            ->where('semester_id', $semesterId)
            ->where('classroom_id', $classroomId)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->exists();
        if ($classroomConflict) {
            $classroom = DB::table('classrooms')->find($classroomId);
            return "Classroom '{$classroom->name}' is already booked at this time.";
        }

        $sectionConflict = DB::table('timetable_sessions')
            ->where('semester_id', $semesterId)
            ->where('section_id', $section->id)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->exists();
        if ($sectionConflict) {
            return "Section '{$section->name}' is already scheduled at this time.";
        }

        $duplicate = DB::table('timetable_sessions')
            ->where('subject_id', $subject->id)
            ->where('section_id', $section->id)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->exists();
        if ($duplicate) {
            return "Subject already scheduled for this section at this time.";
        }

        return null;
    }

    private function buildSummary(int $semesterId): array
    {
        $totalAttempts = $this->totalGeneratedSessions + $this->totalSkippedSessions;
        $successRate = $totalAttempts > 0
            ? round(($this->totalGeneratedSessions / $totalAttempts) * 100)
            : 0;

        $report = [
            'success' => $this->totalSkippedSessions === 0,
            'semester_id' => $semesterId,
            'required_sessions' => $this->totalRequiredSessions,
            'sessions_generated' => $this->totalGeneratedSessions,
            'sessions_skipped' => $this->totalSkippedSessions,
            'success_percentage' => $successRate,
            'conflicts_encountered' => $this->conflictsEncountered,
            'unavailable_slots' => $this->unavailableSlots,
            'affected_teachers' => array_values(array_unique($this->affectedTeachers)),
            'affected_sections' => array_values(array_unique($this->affectedSections)),
            'affected_classrooms' => array_values(array_unique($this->affectedClassrooms)),
            'subjects_incomplete' => $this->incompleteSubjects,
            'subjects' => [],
            'summary' => $this->buildSummaryText($successRate),
        ];

        foreach ($this->generated as $subjectId => $count) {
            $subject = DB::table('subjects')->find($subjectId);
            $errors = $this->skipped[$subjectId] ?? [];

            $report['subjects'][] = [
                'subject_id' => $subjectId,
                'subject_name' => $subject->name ?? 'Unknown',
                'generated' => $count,
                'skipped' => count($errors),
                'errors' => $errors,
            ];
        }

        return $report;
    }

    private function buildSummaryText(int $successRate): string
    {
        return "Generation Result\nRequired sessions: {$this->totalRequiredSessions}\nGenerated: {$this->totalGeneratedSessions}\nSkipped: {$this->totalSkippedSessions}\nSuccess rate: {$successRate}%";
    }

    private function recordAffectedTeacher($teacherId): void
    {
        if (!$teacherId) {
            return;
        }

        $teacher = DB::table('teachers')->find($teacherId);
        if ($teacher) {
            $this->affectedTeachers[] = $teacher->name;
        }
    }

    private function recordAffectedSection($sectionName): void
    {
        if ($sectionName) {
            $this->affectedSections[] = $sectionName;
        }
    }

    private function recordAffectedClassroom($classroomId): void
    {
        if (!$classroomId) {
            return;
        }

        $classroom = DB::table('classrooms')->find($classroomId);
        if ($classroom) {
            $this->affectedClassrooms[] = $classroom->name;
        }
    }
}
