<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckStudentGroups extends Command
{
    protected $signature = 'check:groups {semester_id?}';
    protected $description = 'Check student_groups data structure and contents';

    public function handle()
    {
        $this->info('📋 STUDENT GROUPS DIAGNOSTIC\n');

        // Check columns
        $this->info('✅ Columns in student_groups table:');
        $columns = Schema::getColumnListing('student_groups');
        foreach ($columns as $col) {
            $this->line("   - $col");
        }
        $this->line("");

        // Check data
        $groups = DB::table('student_groups')->get();
        $this->info("Total student groups: " . count($groups));
        $this->line("");

        if (count($groups) === 0) {
            $this->warn("❌ NO DATA in student_groups table!");
            $this->info("Run: php artisan db:seed --class=StudentGroupsArchitectureSeeder");
            return;
        }

        // Show data
        $this->info("📊 Student Groups Data:");
        foreach ($groups as $group) {
            $this->line("  ID: {$group->id}");
            $this->line("  Name: {$group->name}");
            $this->line("  Semester ID: " . ($group->semester_id ?? 'NULL ❌'));
            if (isset($group->program_id)) {
                $this->line("  Program ID: {$group->program_id}");
            }
            $this->line("  Capacity: {$group->student_count}");
            $this->line("");
        }

        // Check for specific semester
        if ($this->argument('semester_id')) {
            $semesterId = $this->argument('semester_id');
            $groupsForSem = DB::table('student_groups')
                ->where('semester_id', $semesterId)
                ->get();
            
            $this->info("📌 Groups for Semester $semesterId: " . count($groupsForSem));
            if (count($groupsForSem) === 0) {
                $this->warn("❌ NO groups found for semester $semesterId");
                $this->info("Try: php artisan check:groups (to see all groups)");
            }
        }
    }
}
