<?php

namespace App\Services;

use App\Models\TimetableSession;
use Illuminate\Support\Facades\DB;

/**
 * Analyzes timetable quality for a semester without modifying the scheduling algorithm.
 * 
 * Metrics calculated:
 *  - Overall quality score (0-100)
 *  - Hard conflicts (constraint violations)
 *  - Soft warnings (efficiency issues)
 *  - Workload analysis (teacher/section)
 *  - Classroom utilization
 *  - Gap analysis
 *  - Consecutive sessions
 */
class TimetableQualityAnalyzer
{
    private int $semesterId;
    private mixed $sessions = [];
    private mixed $subjects = [];
    private mixed $sections = [];
    private mixed $teachers = [];
    private mixed $classrooms = [];
    private mixed $timeslots = [];
    private mixed $days = [];
    
    private array $hardConflicts = [];
    private array $softWarnings = [];
    private int $requiredSessions = 0;
    private int $generatedSessions = 0;
    private int $skippedSessions = 0;
    
    public function analyze(int $semesterId, array $options = []): array
    {
        $this->semesterId = $semesterId;
        
        // Fetch data
        $this->loadData();
        
        // Check for conflicts
        $this->detectHardConflicts();
        
        // Analyze quality aspects
        $this->analyzeWorkload();
        $this->analyzeClassroomUtilization();
        $this->analyzeGaps();
        $this->analyzeConsecutiveSessions();
        
        // Calculate overall score
        $score = $this->calculateScore();
        $workload = $this->analyzeWorkload();
        $classroomUtilization = $this->analyzeClassroomUtilization();
        $totalSlots = max(1, count($this->days) * count($this->timeslots));
        $totalCapacity = $totalSlots * array_sum(array_column((array) $this->classrooms, 'capacity'));
        $classroomUtilizationPct = $totalCapacity > 0
            ? round((count($this->sessions) / $totalCapacity) * 100, 1)
            : 0.0;
        
        $teacherWorkload = array_key_exists('teachers', $workload) ? $workload['teachers'] : [];
        $classroomUsage = [];
        foreach ($this->sessions as $s) {
            $classroomId = $s->classroom_id;
            if (!isset($classroomUsage[$classroomId])) {
                $classroomUsage[$classroomId] = ['sessions' => 0, 'hours' => 0];
            }
            $classroomUsage[$classroomId]['sessions']++;
            $classroomUsage[$classroomId]['hours'] += 2;
        }
        
        $qualitySummary = sprintf(
            "Timetable Quality: score %d/100 (%s). %d/%d required sessions generated (%.1f%% coverage). %d hard conflict(s) and %d warning(s) detected.",
            $score,
            $this->getQualityRating($score),
            $this->generatedSessions,
            $this->requiredSessions,
            $this->requiredSessions > 0 ? ($this->generatedSessions / $this->requiredSessions) * 100 : 100.0,
            count($this->hardConflicts),
            count($this->softWarnings)
        );
        
        return [
            'semester_id' => $semesterId,
            'quality_score' => $score,
            'quality_rating' => $this->getQualityRating($score),
            'quality_summary' => $qualitySummary,
            'required_sessions' => $this->requiredSessions,
            'generated_sessions' => $this->generatedSessions,
            'skipped_sessions' => $this->skippedSessions,
            'sessions_skipped' => ($options['sessions_skipped'] ?? 0) + $this->skippedSessions,
            'coverage_percentage' => $this->requiredSessions > 0 
                ? round(($this->generatedSessions / $this->requiredSessions) * 100, 1)
                : 100.0,
            'hard_conflicts' => $this->hardConflicts,
            'conflict_count' => count($this->hardConflicts),
            'soft_warnings' => $this->softWarnings,
            'warning_count' => count($this->softWarnings),
            'workload' => $workload,
            'teacher_workload' => $teacherWorkload,
            'classroom_utilization' => $classroomUtilization,
            'classroom_usage' => $classroomUsage,
            'classroom_utilization_percentage' => $classroomUtilizationPct,
            'gaps' => $this->analyzeGaps(),
            'consecutive' => $this->analyzeConsecutiveSessions(),
        ];
    }
    
    private function loadData(): void
    {
        // Load all sessions
        $this->sessions = TimetableSession::where('semester_id', $this->semesterId)
            ->with(['subject', 'teacher', 'studentGroup', 'classroom', 'day', 'timeslot'])
            ->get()
            ->keyBy('id');
        
        $this->generatedSessions = count($this->sessions);
        
        // Load subjects and calculate required sessions
        $this->subjects = DB::table('subjects')
            ->whereIn('id', DB::table('timetable_sessions')
                ->where('semester_id', $this->semesterId)
                ->distinct('subject_id')
                ->pluck('subject_id'))
            ->get()
            ->keyBy('id');
        
        foreach ($this->subjects as $subject) {
            $this->requiredSessions += ($subject->sessions_per_week ?? 1);
        }
        
        $this->skippedSessions = 0; // skipped sessions are now reported by the generator

        // Required sessions: sum of sessions_per_week across subjects scheduled for this semester
        $this->requiredSessions = (int) (DB::table('timetable_sessions')
            ->join('subjects', 'subjects.id', '=', 'timetable_sessions.subject_id')
            ->where('timetable_sessions.semester_id', $this->semesterId)
            ->sum(DB::raw('subjects.sessions_per_week')) ?? 0);
        
        // Load other data
        $this->groups = DB::table('student_groups')
            ->get()
            ->keyBy('id');        if (is_object($this->groups) && method_exists($this->groups, 'all')) $this->groups = $this->groups->all();
        
        $this->teachers = DB::table('teachers')
            ->get()
            ->keyBy('id');        if (is_object($this->teachers) && method_exists($this->teachers, 'all')) $this->teachers = $this->teachers->all();
        
        $this->classrooms = DB::table('classrooms')
            ->get()
            ->keyBy('id');        if (is_object($this->classrooms) && method_exists($this->classrooms, 'all')) $this->classrooms = $this->classrooms->all();
        
        $this->timeslots = DB::table('timeslots')
            ->orderBy('position')
            ->get()
            ->keyBy('id');        if (is_object($this->timeslots) && method_exists($this->timeslots, 'all')) $this->timeslots = $this->timeslots->all();
        
        $this->days = DB::table('days')
            ->orderBy('position')
            ->get()
            ->keyBy('id');        if (is_object($this->days) && method_exists($this->days, 'all')) $this->days = $this->days->all();
    }
    
    private function detectHardConflicts(): void
    {
        // Check for constraint violations (should not happen due to DB constraints, but verify)
        
        // 1. Teacher double-booking?
        $teacherSlots = [];
        foreach ($this->sessions as $s) {
            $key = "{$s->teacher_id}_{$s->day_id}_{$s->timeslot_id}";
            if (isset($teacherSlots[$key])) {
                $this->hardConflicts[] = [
                    'type' => 'teacher_conflict',
                    'message' => sprintf('Teacher %s teaches two sessions at same time', $this->teachers[$s->teacher_id]->name ?? 'Unknown'),
                    'day' => $this->days[$s->day_id]->name ?? 'Unknown',
                    'timeslot' => "{$this->timeslots[$s->timeslot_id]->starts_at}-{$this->timeslots[$s->timeslot_id]->ends_at}",
                ];
            }
            $teacherSlots[$key] = true;
        }
        
        // 2. Classroom double-booking?
        $classroomSlots = [];
        foreach ($this->sessions as $s) {
            $key = "{$s->classroom_id}_{$s->day_id}_{$s->timeslot_id}";
            if (isset($classroomSlots[$key])) {
                $this->hardConflicts[] = [
                    'type' => 'classroom_conflict',
                    'message' => sprintf('Classroom %s is double-booked', $this->classrooms[$s->classroom_id]->name ?? 'Unknown'),
                    'day' => $this->days[$s->day_id]->name ?? 'Unknown',
                    'timeslot' => "{$this->timeslots[$s->timeslot_id]->starts_at}-{$this->timeslots[$s->timeslot_id]->ends_at}",
                ];
            }
            $classroomSlots[$key] = true;
        }
        
        // 3. Section double-booking?
        $sectionSlots = [];
        foreach ($this->sessions as $s) {
            $key = "{$s->student_group_id}_{$s->day_id}_{$s->timeslot_id}";
            if (isset($sectionSlots[$key])) {
                $this->hardConflicts[] = [
                    'type' => 'group_conflict',
                    'message' => sprintf('Group %s is double-scheduled', $this->groups[$s->student_group_id]->name ?? 'Unknown'),
                    'day' => $this->days[$s->day_id]->name ?? 'Unknown',
                    'timeslot' => "{$this->timeslots[$s->timeslot_id]->starts_at}-{$this->timeslots[$s->timeslot_id]->ends_at}",
                ];
            }
            $sectionSlots[$key] = true;
        }
        
        // 4. Capacity violations?
        foreach ($this->sessions as $s) {
            $classroom = $this->classrooms[$s->classroom_id] ?? null;
            $section = $this->groups[$s->student_group_id] ?? null;
            if ($classroom && $section && $classroom->capacity < $section->capacity) {
                $this->hardConflicts[] = [
                    'type' => 'capacity_violation',
                    'message' => "Classroom {$classroom->name} (capacity {$classroom->capacity}) too small for section {$section->name} ({$section->capacity} students)",
                    'subject' => $this->subjects[$s->subject_id]->name ?? 'Unknown',
                ];
            }
        }
    }
    
    private function analyzeWorkload(): array
    {
        $workload = [
            'teachers' => [],
            'student_groups' => [],
        ];
        
        // Teacher workload
        $teacherLoads = [];
        foreach ($this->sessions as $s) {
            if (!isset($teacherLoads[$s->teacher_id])) {
                $teacherLoads[$s->teacher_id] = [];
            }
            $teacherLoads[$s->teacher_id][] = $s;
        }
        
        foreach ($teacherLoads as $teacherId => $sessions) {
            $teacher = $this->teachers[$teacherId] ?? null;
            $hoursPerWeek = count($sessions) * 2; // Assuming 2 hours per slot
            
            $warning = null;
            if ($hoursPerWeek > 10) {
                $warning = "High workload: {$hoursPerWeek}h/week";
                $this->softWarnings[] = [
                    'type' => 'teacher_overload',
                    'message' => $warning,
                    'teacher' => $teacher->name ?? 'Unknown',
                    'value' => "{$hoursPerWeek}h/week",
                ];
            }
            
            $workload['teachers'][] = [
                'teacher_id' => $teacherId,
                'teacher_name' => $teacher->name ?? 'Unknown',
                'sessions_count' => count($sessions),
                'hours_per_week' => $hoursPerWeek,
                'warning' => $warning,
            ];
        }
        
        // Section workload (hours per day)
        $sectionDayLoads = [];
        foreach ($this->sessions as $s) {
            $key = "{$s->student_group_id}_{$s->day_id}";
            if (!isset($sectionDayLoads[$key])) {
                $sectionDayLoads[$key] = [
                    'student_group_id' => $s->student_group_id,
                    'day_id' => $s->day_id,
                    'hours' => 0,
                    'sessions' => [],
                ];
            }
            $sectionDayLoads[$key]['hours'] += 2;
            $sectionDayLoads[$key]['sessions'][] = $s;
        }
        
        foreach ($sectionDayLoads as $load) {
            if ($load['hours'] > 6) {
                $section = $this->groups[$load['student_group_id']] ?? null;
                $day = $this->days[$load['day_id']] ?? null;
                $this->softWarnings[] = [
                    'type' => 'group_overload',
                    'message' => sprintf("Group has %s hours on %s", $load['hours'], $day->name ?? 'Unknown'),
                    'group' => $section->name ?? 'Unknown',
                    'day' => $day->name ?? 'Unknown',
                    'value' => "{$load['hours']}h",
                ];
            }
            
            $workload['student_groups'][] = $load;
        }
        
        return $workload;
    }
    
    private function analyzeClassroomUtilization(): array
    {
        $utilization = [];
        
        $classroomUsage = [];
        foreach ($this->sessions as $s) {
            if (!isset($classroomUsage[$s->classroom_id])) {
                $classroomUsage[$s->classroom_id] = 0;
            }
            $classroomUsage[$s->classroom_id]++;
        }
        
        $totalSlots = count($this->days) * count($this->timeslots);
        
        foreach ($this->classrooms as $classId => $classroom) {
            $usage = $classroomUsage[$classId] ?? 0;
            $utilization_pct = $totalSlots > 0 ? round(($usage / $totalSlots) * 100, 1) : 0;
            
            $warning = null;
            if ($utilization_pct < 20) {
                $warning = "Low utilization: {$utilization_pct}%";
                $this->softWarnings[] = [
                    'type' => 'low_utilization',
                    'message' => $warning,
                    'classroom' => $classroom->name,
                    'value' => "{$utilization_pct}%",
                ];
            }
            
            $utilization[] = [
                'classroom_id' => $classId,
                'classroom_name' => $classroom->name,
                'usage_count' => $usage,
                'total_slots' => $totalSlots,
                'utilization_percent' => $utilization_pct,
                'warning' => $warning,
            ];
        }
        
        return $utilization;
    }
    
    private function analyzeGaps(): array
    {
        $gaps = [];
        
        $sectionDaySchedules = [];
        foreach ($this->sessions as $s) {
            $key = "{$s->student_group_id}_{$s->day_id}";
            if (!isset($sectionDaySchedules[$key])) {
                $sectionDaySchedules[$key] = [
                    'student_group_id' => $s->student_group_id,
                    'day_id' => $s->day_id,
                    'timeslots' => [],
                ];
            }
            $sectionDaySchedules[$key]['timeslots'][] = $s->timeslot_id;
        }
        
        foreach ($sectionDaySchedules as $schedule) {
            $timeslots = array_unique($schedule['timeslots']);
            sort($timeslots);
            
            $ts_list = array_values($this->timeslots);
            $gap_found = false;
            
            // Use timeslot positions (order), not IDs, since IDs may not match insertion order
            $positions = array_map(
                fn($tid) => (int) ($this->timeslots[$tid]->position ?? 0),
                $timeslots
            );
            sort($positions);

            for ($i = 0; $i < count($positions) - 1; $i++) {
                $current = $positions[$i];
                $next = $positions[$i + 1];
                
                if ($next - $current > 1) {
                    $gap_found = true;
                    $section = $this->groups[$schedule['student_group_id']] ?? null;
                    $day = $this->days[$schedule['day_id']] ?? null;
                    $this->softWarnings[] = [
                        'type' => 'gap',
                        'message' => sprintf('Large gap in schedule for group %s on %s', $section->name ?? 'Unknown', $day->name ?? 'Unknown'),
                        'group' => $section->name ?? 'Unknown',
                        'day' => $day->name ?? 'Unknown',
                    ];
                    break;
                }
            }
            
            if ($gap_found || count($timeslots) >= 2) {
                $gaps[] = [
                    'student_group_id' => $schedule['student_group_id'],
                    'group_name' => $this->groups[$schedule['student_group_id']]->name ?? 'Unknown',
                    'day_id' => $schedule['day_id'],
                    'day_name' => $this->days[$schedule['day_id']]->name ?? 'Unknown',
                    'has_gap' => $gap_found,
                ];
            }
        }
        
        return $gaps;
    }
    
    private function analyzeConsecutiveSessions(): array
    {
        $consecutive = [];
        
        $teacherDaySchedules = [];
        foreach ($this->sessions as $s) {
            $key = "{$s->teacher_id}_{$s->day_id}";
            if (!isset($teacherDaySchedules[$key])) {
                $teacherDaySchedules[$key] = [
                    'teacher_id' => $s->teacher_id,
                    'day_id' => $s->day_id,
                    'timeslots' => [],
                ];
            }
            $teacherDaySchedules[$key]['timeslots'][] = $s->timeslot_id;
        }
        
        foreach ($teacherDaySchedules as $schedule) {
            // Use timeslot positions (order), not IDs, since IDs may not match insertion order
            $positions = array_map(
                fn($tid) => ($this->timeslots[$tid]->position ?? 0),
                array_unique($schedule['timeslots'])
            );
            sort($positions);
            
            $maxConsec = 1;
            $currentConsec = 1;
            
            for ($i = 0; $i < count($positions) - 1; $i++) {
                if ($positions[$i + 1] - $positions[$i] === 1) {
                    $currentConsec++;
                } else {
                    $maxConsec = max($maxConsec, $currentConsec);
                    $currentConsec = 1;
                }
            }
            $maxConsec = max($maxConsec, $currentConsec);
            
            if ($maxConsec >= 3) {
                $teacher = $this->teachers[$schedule['teacher_id']] ?? null;
                $this->softWarnings[] = [
                    'type' => 'long_consecutive',
                    'message' => sprintf('Teacher %s has %d consecutive sessions', $teacher->name ?? 'Unknown', $maxConsec),
                    'teacher' => $teacher->name ?? 'Unknown',
                    'value' => "{$maxConsec} consecutive",
                ];
            }
            
            $consecutive[] = [
                'teacher_id' => $schedule['teacher_id'],
                'teacher_name' => $this->teachers[$schedule['teacher_id']]->name ?? 'Unknown',
                'day_id' => $schedule['day_id'],
                'day_name' => $this->days[$schedule['day_id']]->name ?? 'Unknown',
                'max_consecutive' => $maxConsec,
                'warning' => $maxConsec >= 3 ? "Long consecutive sessions" : null,
            ];
        }
        
        return $consecutive;
    }
    
    private function calculateScore(): int
    {
        $score = 100;
        
        // Hard conflicts (major deductions)
        $score -= count($this->hardConflicts) * 10;
        
        // Skipped sessions (proportional deduction)
        if ($this->requiredSessions > 0) {
            $skipPercentage = ($this->skippedSessions / $this->requiredSessions) * 100;
            $score -= min(30, ($skipPercentage / 100) * 30);
        }
        
        // Soft warnings (minor deductions)
        $score -= min(20, count($this->softWarnings) * 2);
        
        return max(0, min(100, (int) $score));
    }
    
    private function getQualityRating(int $score): string
    {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Good';
        if ($score >= 60) return 'Needs Improvement';
        return 'Poor';
    }
}
