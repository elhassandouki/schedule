<?php

namespace App\Console\Commands;

use App\Services\AutoGenerateTimetable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTimetable extends Command
{
    protected $signature = 'timetable:generate {semester_id} {--dry-run}';
    protected $description = 'Auto-generate timetable sessions for a semester with all constraint checks.';

    public function handle(): int
    {
        $semesterId = $this->argument('semester_id');
        $dryRun = $this->option('dry-run');
        
        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) {
            $this->error("Semester #{$semesterId} not found.");
            return self::FAILURE;
        }
        
        $program = DB::table('programs')->find($semester->program_id);
        $this->info("Generating timetable for: {$program->name} → {$semester->name}");
        
        if ($dryRun) {
            $this->comment('(DRY-RUN: no changes will be written)');
        }
        
        if (!$dryRun) {
            // Clear existing (optional; comment out to avoid re-generation)
            $existing = DB::table('timetable_sessions')
                ->where('semester_id', $semesterId)
                ->count();
            
            if ($existing > 0) {
                if (!$this->confirm("{$existing} existing sessions found. Regenerate (delete & recreate)?")) {
                    $this->info('Aborted.');
                    return self::SUCCESS;
                }
                DB::table('timetable_sessions')->where('semester_id', $semesterId)->delete();
                $this->line("Cleared {$existing} sessions.");
            }
        }
        
        // Run generation
        $generator = new AutoGenerateTimetable();
        $result = $generator->generate($semesterId);
        
        // Report
        $this->line('');
        $this->info("Generated: {$result['sessions_generated']} sessions");
        $this->warn("Skipped: {$result['sessions_skipped']} sessions");
        
        if (!empty($result['subjects'])) {
            $this->line('');
            $this->line('Per-subject breakdown:');
            foreach ($result['subjects'] as $s) {
                $status = $s['skipped'] === 0 ? '✓' : '✗';
                $this->line("  {$status} {$s['subject_name']}: {$s['generated']} generated, {$s['skipped']} skipped");
                
                if (!empty($s['errors'])) {
                    foreach ($s['errors'] as $err) {
                        $this->line("      - {$err}");
                    }
                }
            }
        }
        
        $this->line('');
        if ($result['success']) {
            $this->line('<fg=green>✓ Timetable generation successful!</>');
            if ($dryRun) {
                $this->comment('(Re-run without --dry-run to write to database)');
            }
            return self::SUCCESS;
        } else {
            $this->line('<fg=yellow>⚠ Generation completed with errors. Check details above.</>');
            return self::SUCCESS; // Still return success (partial generation is OK)
        }
    }
}
