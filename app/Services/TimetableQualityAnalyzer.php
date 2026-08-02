<?php

namespace App\Services;

use App\Models\TimetableSession;
use Illuminate\Support\Facades\DB;

class TimetableQualityAnalyzer
{
    public function analyze(int $semesterId, array $generationReport = []): array
    {
        $sessions = TimetableSession::where('semester_id', $semesterId)->with(['teacher', 'section', 'classroom', 'day', 'timeslot', 'subject'])->get();
        $days = DB::table('days')->orderBy('position')->pluck('name', 'id');
        $timeslots = DB::table('timeslots')->orderBy('position')->get();

        $hardConflicts = [];
        $softWarnings = [];

        $teacherWorkload = [];
        $sectionWorkload = [];
        $classroomUsage = [];

        foreach ($sessions as $session) {
            $teacherWorkload[$session->teacher_id]['sessions'] = ($teacherWorkload[$session->teacher_id]['sessions'] ?? 0) + 1;
            $teacherWorkload[$session->teacher_id]['hours'] = ($teacherWorkload[$session->teacher_id]['hours'] ?? 0) + $this->durationHours($session->timeslot);
            $teacherWorkload[$session->teacher_id]['day_counts'][$session->day_id] = ($teacherWorkload[$session->teacher_id]['day_counts'][$session->day_id] ?? 0) + 1;

            $sectionWorkload[$session->section_id]['sessions'] = ($sectionWorkload[$session->section_id]['sessions'] ?? 0) + 1;
            $sectionWorkload[$session->section_id]['day_counts'][$session->day_id] = ($sectionWorkload[$session->section_id]['day_counts'][$session->day_id] ?? 0) + 1;
            $sectionWorkload[$session->section_id]['hours'] = ($sectionWorkload[$session->section_id]['hours'] ?? 0) + $this->durationHours($session->timeslot);

            $classroomUsage[$session->classroom_id]['sessions'] = ($classroomUsage[$session->classroom_id]['sessions'] ?? 0) + 1;
            $classroomUsage[$session->classroom_id]['hours'] = ($classroomUsage[$session->classroom_id]['hours'] ?? 0) + $this->durationHours($session->timeslot);
        }

        foreach ($teacherWorkload as $teacherId => $data) {
            $maxDayCount = max($data['day_counts'] ?? [0]);
            if ($maxDayCount >= 3) {
                $softWarnings[] = 'Teacher '.($sessions->firstWhere('teacher_id', $teacherId)?->teacher?->name ?? '#'.$teacherId).' is concentrated on one day.';
            }
        }

        foreach ($sectionWorkload as $sectionId => $data) {
            $dayEntries = $data['day_counts'] ?? [];
            $daysWithSessions = count($dayEntries);
            if ($daysWithSessions === 0) {
                $softWarnings[] = 'Section '.($sessions->firstWhere('section_id', $sectionId)?->section?->name ?? '#'.$sectionId).' has no sessions.';
                continue;
            }
            if ($daysWithSessions <= 1) {
                $softWarnings[] = 'Section '.($sessions->firstWhere('section_id', $sectionId)?->section?->name ?? '#'.$sectionId).' is clustered on too few days.';
            }
        }

        $availableClassrooms = DB::table('classrooms')->count();
        $usedClassrooms = count($classroomUsage);
        $utilizationPercentage = $availableClassrooms > 0 ? round(($usedClassrooms / $availableClassrooms) * 100) : 0;

        $gaps = $this->detectGaps($sessions, $days, $timeslots);
        if ($gaps > 0) {
            $softWarnings[] = 'Detected '.$gaps.' large gaps in the schedule.';
        }

        $consecutive = $this->detectConsecutiveSessions($sessions, $days, $timeslots);
        if ($consecutive > 0) {
            $softWarnings[] = 'Detected '.$consecutive.' excessive consecutive session clusters.';
        }

        foreach ($teacherWorkload as $teacherId => $data) {
            if (($data['sessions'] ?? 0) > 6) {
                $softWarnings[] = 'Teacher '.($sessions->firstWhere('teacher_id', $teacherId)?->teacher?->name ?? '#'.$teacherId).' has an overloaded weekly workload.';
            }
        }

        $score = $this->score($generationReport, $hardConflicts, $softWarnings, $teacherWorkload, $sectionWorkload, $classroomUsage, $utilizationPercentage);

        return [
            'semester_id' => $semesterId,
            'hard_conflicts' => $hardConflicts,
            'soft_warnings' => $softWarnings,
            'teacher_workload' => $teacherWorkload,
            'section_workload' => $sectionWorkload,
            'classroom_usage' => $classroomUsage,
            'classroom_utilization_percentage' => $utilizationPercentage,
            'unused_classrooms' => $availableClassrooms - $usedClassrooms,
            'quality_score' => $score,
            'quality_summary' => $this->formatSummary($score, $hardConflicts, $softWarnings, $generationReport, $utilizationPercentage),
        ];
    }

    private function durationHours($timeslot): int
    {
        if (!$timeslot) {
            return 0;
        }

        $start = strtotime('1970-01-01 '.$timeslot->starts_at);
        $end = strtotime('1970-01-01 '.$timeslot->ends_at);
        return (int) (($end - $start) / 3600);
    }

    private function detectGaps($sessions, $days, $timeslots): int
    {
        $gaps = 0;
        foreach ($sessions->groupBy('section_id') as $sectionSessions) {
            $ordered = $sectionSessions->sortBy(fn ($session) => ($session->day->position ?? 0) * 100 + ($session->timeslot->position ?? 0));
            if ($ordered->count() < 2) {
                continue;
            }
            foreach ($ordered->slice(1) as $index => $session) {
                $previous = $ordered[$index];
                $gap = (($session->day->position ?? 0) * 100 + ($session->timeslot->position ?? 0)) - (($previous->day->position ?? 0) * 100 + ($previous->timeslot->position ?? 0));
                if ($gap > 10) {
                    $gaps++;
                }
            }
        }

        return $gaps;
    }

    private function detectConsecutiveSessions($sessions, $days, $timeslots): int
    {
        $clusters = 0;
        foreach ($sessions->groupBy('section_id') as $sectionSessions) {
            $ordered = $sectionSessions->sortBy(fn ($session) => ($session->day->position ?? 0) * 100 + ($session->timeslot->position ?? 0));
            $count = 0;
            foreach ($ordered as $session) {
                $count++;
                if ($count >= 3) {
                    $clusters++;
                    break;
                }
            }
        }

        return $clusters;
    }

    private function score(array $generationReport, array $hardConflicts, array $softWarnings, array $teacherWorkload, array $sectionWorkload, array $classroomUsage, int $utilizationPercentage): int
    {
        $score = 100;

        if ($hardConflicts) {
            $score -= 40;
        }

        $skipped = $generationReport['sessions_skipped'] ?? 0;
        if ($skipped > 0) {
            $score -= min(20, $skipped * 3);
        }

        if (count($teacherWorkload) > 0) {
            $overloadedTeachers = count(array_filter($teacherWorkload, fn ($entry) => ($entry['sessions'] ?? 0) > 6));
            $score -= min(10, $overloadedTeachers * 2);
        }

        if (count($sectionWorkload) > 0) {
            $clusteredSections = count(array_filter($sectionWorkload, fn ($entry) => ($entry['sessions'] ?? 0) > 4));
            $score -= min(10, $clusteredSections);
        }

        if ($utilizationPercentage > 0) {
            $score -= min(10, max(0, 80 - $utilizationPercentage) / 10);
        }

        $score -= min(10, count($softWarnings));
        return max(0, min(100, $score));
    }

    private function formatSummary(int $score, array $hardConflicts, array $softWarnings, array $generationReport, int $utilizationPercentage): string
    {
        return "Timetable Quality: {$score}/100\nHard conflicts: " . count($hardConflicts) . "\nSkipped sessions: " . ($generationReport['sessions_skipped'] ?? 0) . "\nClassroom utilization: {$utilizationPercentage}%\nWarnings: " . count($softWarnings);
    }
}
