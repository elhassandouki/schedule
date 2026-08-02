<?php

namespace App\Services;

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
    private array $sessions = [];
    private array $subjects = [];
    private array $sections = [];
    private array $teachers = [];
    private array $classrooms = [];
    private array $timeslots = [];
    private array $days = [];
    
    private array $hardConflicts = [];
    private array $softWarnings = [];
    private int $requiredSessions = 0;
    private int $generatedSessions = 0;
    private int $skippedSessions = 0;
    
    public function analyze(int $semesterId): array
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
        
        return [
            'semester_id' => $semesterId,
            'quality_score' => $score,
            'quality_rating' => $this->getQualityRating($score),
            'required_sessions' => $this->requiredSessions,
            'generated_sessions' => $this->generatedSessions,
            'skipped_sessions' => $this->skippedSessions,
            'coverage_percentage' => $this->requiredSessions > 0 
                ? round(($this->generatedSessions / $this->requiredSessions) * 100, 1)
                : 100.0,
            'hard_conflicts' => $this->hardConflicts,
            'conflict_count' => count($this->hardConflicts),
            'soft_warnings' => $this->softWarnings,
            'warning_count' => count($this->softWarnings),
            'workload' => $this->analyzeWorkload(),
            'classroom_utilization' => $this->analyzeClassroomUtilization(),
            'gaps' => $this->analyzeGaps(),
            'consecutive' => $this->analyzeConsecutiveSessions(),
        ];
    }
    
    private function loadData(): void
    {
        // Load all sessions
        $this->sessions = DB::table('timetable_sessions')
            ->where('semester_id', $this->semesterId)
            ->with(['subject', 'teacher', 'section', 'classroom', 'day', 'timeslot'])
            ->get()
            ->keyBy('id')
            ->toArray();
        
        $this->generatedSessions = count($this->sessions);
        
        // Load subjects and calculate required sessions
        $this->subjects = DB::table('subjects')
            ->whereIn('id', DB::table('timetable_sessions')
                ->where('semester_id', $this->semesterId)
                ->distinct('subject_id')
                ->pluck('subject_id'))
            ->get()
            ->keyBy('id')
            ->toArray();
        
        foreach ($this->subjects as $subject) {
            $this->requiredSessions += ($subject->sessions_per_week ?? 1);
        }
        
        $this->skippedSessions = max(0, $this->requiredSessions - $this->generatedSessions);
        
        // Load other data
        $this->sections = DB::table('sections')
            ->get()
            ->keyBy('id')
            ->toArray();
        
        $this->teachers = DB::table('teachers')
            ->get()
            ->keyBy('id')
            ->toArray();
        
        $this->classrooms = DB::table('classrooms')
            ->get()
            ->keyBy('id')
            ->toArray();
        
        $this->timeslots = DB::table('timeslots')
            ->orderBy('position')
            ->get()
            ->keyBy('id')
            ->toArray();
        
        $this->days = DB::table('days')
            ->orderBy('position')
            ->get()
            ->keyBy('id')
            ->toArray();
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
                    'message' => "Teacher {$this->teachers[$s->teacher_id]->name ?? 'Unknown'} teaches two sessions at same time",
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
                    'message' => "Classroom {$this->classrooms[$s->classroom_id]->name ?? 'Unknown'} is double-booked",
                    'day' => $this->days[$s->day_id]->name ?? 'Unknown',
                    'timeslot' => "{$this->timeslots[$s->timeslot_id]->starts_at}-{$this->timeslots[$s->timeslot_id]->ends_at}",
                ];
            }
            $classroomSlots[$key] = true;
        }
        
        // 3. Section double-booking?
        $sectionSlots = [];
        foreach ($this->sessions as $s) {
            $key = "{$s->section_id}_{$s->day_id}_{$s->timeslot_id}";
            if (isset($sectionSlots[$key])) {
                $this->hardConflicts[] = [
                    'type' => 'section_conflict',
                    'message' => "Section {$this->sections[$s->section_id]->name ?? 'Unknown'} is double-scheduled",
                    'day' => $this->days[$s->day_id]->name ?? 'Unknown',
                    'timeslot' => "{$this->timeslots[$s->timeslot_id]->starts_at}-{$this->timeslots[$s->timeslot_id]->ends_at}",
                ];
            }
            $sectionSlots[$key] = true;
        }
        
        // 4. Capacity violations?
        foreach ($this->sessions as $s) {
            $classroom = $this->classrooms[$s->classroom_id] ?? null;
            $section = $this->sections[$s->section_id] ?? null;
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
            'sections' => [],
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
            if ($hoursPerWeek > 20) {
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
            $key = "{$s->section_id}_{$s->day_id}";
            if (!isset($sectionDayLoads[$key])) {
                $sectionDayLoads[$key] = [
                    'section_id' => $s->section_id,
                    'day_id' => $s->day_id,
                    'hours' => 0,
                    'sessions' => [],
                ];
            }
            $sectionDayLoads[$key]['hours'] += 2;
            $sectionDayLoads[$key]['sessions'][] = $s;
        }
        
        foreach ($sectionDayLoads as $load) {
            if ($load['hours'] > 8) {
                $section = $this->sections[$load['section_id']] ?? null;
                $day = $this->days[$load['day_id']] ?? null;
                $this->softWarnings[] = [
                    'type' => 'section_overload',
                    'message' => "Section has {$load['hours']} hours on {$day->name ?? 'Unknown'}",
                    'section' => $section->name ?? 'Unknown',
                    'day' => $day->name ?? 'Unknown',
                    'value' => "{$load['hours']}h",
                ];
            }
            
            $workload['sections'][] = $load;
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
            $key = "{$s->section_id}_{$s->day_id}";
            if (!isset($sectionDaySchedules[$key])) {
                $sectionDaySchedules[$key] = [
                    'section_id' => $s->section_id,
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
            
            for ($i = 0; $i < count($timeslots) - 1; $i++) {
                $current = $timeslots[$i];
                $next = $timeslots[$i + 1];
                
                if ($next - $current > 1) {
                    $gap_found = true;
                    $section = $this->sections[$schedule['section_id']] ?? null;
                    $day = $this->days[$schedule['day_id']] ?? null;
                    $this->softWarnings[] = [
                        'type' => 'gap',
                        'message' => "Large gap in schedule for section {$section->name ?? 'Unknown'} on {$day->name ?? 'Unknown'}",
                        'section' => $section->name ?? 'Unknown',
                        'day' => $day->name ?? 'Unknown',
                    ];
                    break;
                }
            }
            
            if ($gap_found || count($timeslots) > 0) {
                $gaps[] = [
                    'section_id' => $schedule['section_id'],
                    'section_name' => $this->sections[$schedule['section_id']]->name ?? 'Unknown',
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
            $timeslots = array_unique($schedule['timeslots']);
            sort($timeslots);
            
            $maxConsec = 1;
            $currentConsec = 1;
            
            for ($i = 0; $i < count($timeslots) - 1; $i++) {
                if ($timeslots[$i + 1] - $timeslots[$i] === 1) {
                    $currentConsec++;
                } else {
                    $maxConsec = max($maxConsec, $currentConsec);
                    $currentConsec = 1;
                }
            }
            $maxConsec = max($maxConsec, $currentConsec);
            
            if ($maxConsec >= 4) {
                $teacher = $this->teachers[$schedule['teacher_id']] ?? null;
                $this->softWarnings[] = [
                    'type' => 'long_consecutive',
                    'message' => "Teacher {$teacher->name ?? 'Unknown'} has {$maxConsec} consecutive sessions",
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
                'warning' => $maxConsec >= 4 ? "Long consecutive sessions" : null,
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
