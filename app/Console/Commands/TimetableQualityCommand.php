<?php

namespace App\Console\Commands;

use App\Services\TimetableQualityAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TimetableQualityCommand extends Command
{
    protected $signature = 'timetable:quality {semester_id}';
    protected $description = 'Display quality report for a semester timetable.';

    public function handle(): int
    {
        $semesterId = $this->argument('semester_id');
        
        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) {
            $this->error("Semester #{$semesterId} not found.");
            return self::FAILURE;
        }
        
        $program = DB::table('programs')->find($semester->program_id);
        $this->info("Timetable Quality Report: {$program->name} → {$semester->name}\n");
        
        $analyzer = new TimetableQualityAnalyzer();
        $report = $analyzer->analyze($semesterId);
        
        // Display quality score
        $this->line("<fg=cyan>Quality Score: {$report['quality_score']}/100 ({$report['quality_rating']})</>");
        $this->line("Coverage: {$report['coverage_percentage']}% ({$report['generated_sessions']}/{$report['required_sessions']})\n");
        
        // Hard conflicts
        if ($report['conflict_count'] > 0) {
            $this->line("<fg=red>✗ HARD CONFLICTS ({$report['conflict_count']})</>");
            foreach ($report['hard_conflicts'] as $conflict) {
                $this->line("  - {$conflict['message']}");
            }
            $this->newLine();
        } else {
            $this->line("<fg=green>✓ No hard conflicts</>");
            $this->newLine();
        }
        
        // Skipped sessions
        if ($report['skipped_sessions'] > 0) {
            $this->line("<fg=yellow>⚠ SKIPPED SESSIONS ({$report['skipped_sessions']})</>");
            $skipped = DB::table('timetable_generation_skips')
                ->where('semester_id', $semesterId)
                ->get();
            foreach ($skipped as $skip) {
                $this->line("  Subject: {$skip->subject_name}, Section: {$skip->section_name}");
                $this->line("  Reason: {$skip->reason}");
            }
            $this->newLine();
        }
        
        // Soft warnings
        if ($report['warning_count'] > 0) {
            $this->line("<fg=yellow>Warnings ({$report['warning_count']})</>");
            foreach ($report['soft_warnings'] as $warning) {
                $this->line("  - {$warning['message']}");
            }
            $this->newLine();
        }
        
        // Summary
        $this->line('<fg=cyan>Summary</>');
        $this->line("  Generated sessions:      {$report['generated_sessions']}");
        $this->line("  Required sessions:       {$report['required_sessions']}");
        $this->line("  Skipped sessions:        {$report['skipped_sessions']}");
        $this->line("  Hard conflicts:          {$report['conflict_count']}");
        $this->line("  Soft warnings:           {$report['warning_count']}");
        
        return self::SUCCESS;
    }
}
