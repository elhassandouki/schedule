<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Auto-generation service: creates TimetableSession records.
 *
 * Design:
 *  - Subjects are INDEPENDENT (not tied to semester)
 *  - ALL subjects → ALL student groups in the semester
 *  - Professor assigned per subject
 *
 * Constraints enforced:
 *  1. No teacher teaches two places at the same time (day/timeslot/semester)
 *  2. No classroom is double-booked (day/timeslot/semester)
 *  3. No student group is double-booked (day/timeslot/semester)
 *  4. Classroom capacity ≥ group size
 *
 * Algorithm: Greedy slot-filling
 *  - For each SUBJECT (all of them, no semester filter)
 *  - For each STUDENT GROUP in the semester
 *  - Fill required sessions_per_week into earliest available (day, timeslot)
 *  - Use subject's teacher, check conflicts, skip if violated
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

    public function generate(int $semesterId): array
    {
        $this->resetStats();

        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) {
            return ['success' => false, 'error' => "Semester #{$semesterId} not found"];
        }

        // Load ALL subjects (independent, no semester filter)
        $subjects = DB::table('subjects')->get();
        if ($subjects->isEmpty()) {
            return ['success' => false, 'error' => "No subjects found in the system"];
        }

        // Load student groups for THIS semester
        $studentGroups = DB::table('student_groups')
            ->where('semester_id', $semesterId)
            ->get();
        if ($studentGroups->isEmpty()) {
            return ['success' => false, 'error' => "No student groups found for semester #{$semesterId}"];
        }

        $days = DB::table('days')->orderBy('position')->get();
        $timeslots = DB::table('timeslots')->orderBy('position')->get();
        $classrooms = DB::table('classrooms')->orderBy('capacity', 'desc')->get();

        if ($days->isEmpty() || $timeslots->isEmpty() || $classrooms->isEmpty()) {
            return ['success' => false, 'error' => "Missing days, timeslots, or classrooms"];
        }

        // Generate: Subject × StudentGroup combinations
        foreach ($subjects as $subject) {
            $this->generated[$subject->id] = 0;
            $this->skipped[$subject->id] = [];

            $sessionsNeeded = (int) ($subject->sessions_per_week ?? 1);
            $requiredForSubject = $sessionsNeeded * count($studentGroups);
            $this->totalRequiredSessions += $requiredForSubject;

            // Use subject's teacher, or skip if none assigned
            $teacherId = $subject->teacher_id;
            if (!$teacherId) {
                $this->skipped[$subject->id][] = "No teacher assigned to subject";
                $this->totalSkippedSessions += $requiredForSubject;
                continue;
            }

            foreach ($studentGroups as $group) {
                $remaining = $sessionsNeeded;

                foreach ($days as $day) {
                    if ($remaining <= 0) break;

                    foreach ($timeslots as $timeslot) {
                        if ($remaining <= 0) break;

                        // Find available classroom for this group size
                        $classroom = $this->findAvailableClassroom(
                            $group->capacity,
                            $day->id,
                            $timeslot->id,
                            $classrooms,
                            $semesterId
                        );

                        if (!$classroom) {
                            $this->unavailableSlots++;
                            $this->skipped[$subject->id][] = "No available classroom for {$group->name} on {$day->name}";
                            $this->totalSkippedSessions++;
                            continue;
                        }

                        // Check conflicts before inserting
                        $conflict = $this->checkConstraints(
                            $teacherId,
                            $group->id,
                            $day->id,
                            $timeslot->id,
                            $classroom->id,
                            $semesterId
                        );

                        if ($conflict) {
                            $this->conflictsEncountered++;
                            $this->skipped[$subject->id][] = $conflict;
                            $this->totalSkippedSessions++;
                            continue;
                        }

                        // INSERT session
                        DB::table('timetable_sessions')->insert([
                            'subject_id' => $subject->id,
                            'teacher_id' => $teacherId,
                            'student_group_id' => $group->id,
                            'classroom_id' => $classroom->id,
                            'semester_id' => $semesterId,
                            'day_id' => $day->id,
                            'timeslot_id' => $timeslot->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $this->generated[$subject->id]++;
                        $this->totalGeneratedSessions++;
                        $remaining--;
                    }
                }
            }
        }

        $success = $this->totalSkippedSessions === 0;

        return [
            'success' => $success,
            'sessions_generated' => $this->totalGeneratedSessions,
            'sessions_skipped' => $this->totalSkippedSessions,
            'conflicts_encountered' => $this->conflictsEncountered,
            'unavailable_slots' => $this->unavailableSlots,
            'generated_per_subject' => $this->generated,
            'skipped_per_subject' => $this->skipped,
        ];
    }

    private function findAvailableClassroom(
        int $requiredCapacity,
        int $dayId,
        int $timeslotId,
        $classrooms,
        int $semesterId
    ) {
        foreach ($classrooms as $classroom) {
            // Capacity check
            if ($classroom->capacity < $requiredCapacity) {
                continue;
            }

            // Check if booked
            $isBooked = DB::table('timetable_sessions')
                ->where('classroom_id', $classroom->id)
                ->where('day_id', $dayId)
                ->where('timeslot_id', $timeslotId)
                ->where('semester_id', $semesterId)
                ->exists();

            if (!$isBooked) {
                return $classroom;
            }
        }

        return null;
    }

    private function checkConstraints(
        int $teacherId,
        int $studentGroupId,
        int $dayId,
        int $timeslotId,
        int $classroomId,
        int $semesterId
    ): ?string {
        // Teacher conflict
        $teacherConflict = DB::table('timetable_sessions')
            ->where('teacher_id', $teacherId)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->where('semester_id', $semesterId)
            ->exists();

        if ($teacherConflict) {
            return "Teacher already teaching at this time";
        }

        // Group conflict
        $groupConflict = DB::table('timetable_sessions')
            ->where('student_group_id', $studentGroupId)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->where('semester_id', $semesterId)
            ->exists();

        if ($groupConflict) {
            return "Group already has class at this time";
        }

        // Classroom conflict (double-check)
        $classroomConflict = DB::table('timetable_sessions')
            ->where('classroom_id', $classroomId)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->where('semester_id', $semesterId)
            ->exists();

        if ($classroomConflict) {
            return "Classroom already booked at this time";
        }

        return null;
    }

    private function resetStats(): void
    {
        $this->generated = [];
        $this->skipped = [];
        $this->totalRequiredSessions = 0;
        $this->totalGeneratedSessions = 0;
        $this->totalSkippedSessions = 0;
        $this->conflictsEncountered = 0;
        $this->unavailableSlots = 0;
    }
}
