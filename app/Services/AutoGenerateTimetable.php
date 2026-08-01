<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

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
    private array $generated = [];    // [subject_id => count_generated]
    private array $skipped = [];      // [subject_id => [errors]]
    private array $summary = [];      // Human-readable report
    
    public function generate(int $semesterId): array
    {
        $this->generated = [];
        $this->skipped = [];
        $this->summary = [];
        
        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) {
            return ['success' => false, 'error' => "Semester #{$semesterId} not found"];
        }
        
        // Fetch all data needed for generation
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
        
        // Clear existing sessions for this semester (optional: safe re-generation)
        // Uncomment to allow re-generation:
        // DB::table('timetable_sessions')->where('semester_id', $semesterId)->delete();
        
        // Main generation loop
        foreach ($subjects as $subject) {
            $this->generated[$subject->id] = 0;
            $this->skipped[$subject->id] = [];
            
            $sessionsNeeded = $subject->sessions_per_week ?? 1;
            $sessionsFilled = 0;
            
            foreach ($sections as $section) {
                // Try to allocate $sessionsNeeded slots for this subject+section
                $remaining = $sessionsNeeded;
                
                foreach ($days as $day) {
                    if ($remaining <= 0) break;
                    
                    foreach ($timeslots as $timeslot) {
                        if ($remaining <= 0) break;
                        
                        // Try to fit in this (day, timeslot)
                        $classroom = $this->findAvailableClassroom(
                            $subject, $section, $day->id, $timeslot->id, $classrooms
                        );
                        
                        if (!$classroom) {
                            $this->skipped[$subject->id][] = 
                                "No available classroom for {$section->name} on {$day->name} {$timeslot->starts_at}";
                            continue;
                        }
                        
                        // Check constraints
                        $conflict = $this->checkConstraints(
                            $subject, $section, $day->id, $timeslot->id, $classroom->id, $semesterId
                        );
                        
                        if ($conflict) {
                            $this->skipped[$subject->id][] = $conflict;
                            continue;
                        }
                        
                        // All clear — create session
                        DB::table('timetable_sessions')->insert([
                            'subject_id'   => $subject->id,
                            'teacher_id'   => $subject->teacher_id,
                            'section_id'   => $section->id,
                            'classroom_id' => $classroom->id,
                            'semester_id'  => $semesterId,
                            'day_id'       => $day->id,
                            'timeslot_id'  => $timeslot->id,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                        
                        $this->generated[$subject->id]++;
                        $sessionsFilled++;
                        $remaining--;
                    }
                }
            }
        }
        
        // Build summary
        return $this->buildSummary($semesterId);
    }
    
    /**
     * Find an available classroom that fits section size.
     * Returns the first available (by capacity ascending) or null.
     */
    private function findAvailableClassroom($subject, $section, $dayId, $timeslotId, $classrooms)
    {
        foreach ($classrooms as $classroom) {
            // Capacity check
            if ($classroom->capacity < $section->capacity) {
                continue;
            }
            
            // Already booked?
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
    
    /**
     * Check all constraints for a potential session.
     * Returns null if OK, else error message.
     */
    private function checkConstraints($subject, $section, $dayId, $timeslotId, $classroomId, $semesterId): ?string
    {
        // 1. Teacher conflict?
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
        
        // 2. Classroom conflict? (should not happen if findAvailableClassroom worked, but double-check)
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
        
        // 3. Section conflict?
        $sectionConflict = DB::table('timetable_sessions')
            ->where('semester_id', $semesterId)
            ->where('section_id', $section->id)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->exists();
        if ($sectionConflict) {
            return "Section '{$section->name}' is already scheduled at this time.";
        }
        
        // 4. Duplicate (subject+section already scheduled at exact same time)?
        $duplicate = DB::table('timetable_sessions')
            ->where('subject_id', $subject->id)
            ->where('section_id', $section->id)
            ->where('day_id', $dayId)
            ->where('timeslot_id', $timeslotId)
            ->exists();
        if ($duplicate) {
            return "Subject already scheduled for this section at this time.";
        }
        
        return null; // All OK
    }
    
    /**
     * Build a human-readable summary report.
     */
    private function buildSummary(int $semesterId): array
    {
        $totalGenerated = array_sum($this->generated);
        $totalSkipped = array_sum(array_map('count', $this->skipped));
        
        $report = [
            'success' => $totalSkipped === 0,
            'semester_id' => $semesterId,
            'sessions_generated' => $totalGenerated,
            'sessions_skipped' => $totalSkipped,
            'subjects' => [],
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
}
