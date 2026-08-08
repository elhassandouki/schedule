<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckGenerationReadiness extends Command
{
    protected $signature = 'timetable:check-ready {semester_id}';
    protected $description = 'Check if a semester has all required data for auto-generation';

    public function handle()
    {
        $semesterId = $this->argument('semester_id');

        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) {
            $this->error("❌ Semester #{$semesterId} not found");
            return;
        }

        $this->info("\n📋 GENERATION READINESS CHECK for Semester: {$semester->name}\n");

        // Check subjects
        $subjects = DB::table('subjects')->where('semester_id', $semesterId)->get();
        $this->checkData('Subjects', $subjects, 'subjects', 'A subject defines which courses need to be taught');

        // Check sections
        $sections = DB::table('sections')->where('program_id', $semester->program_id)->get();
        $this->checkData('Sections (Student Groups)', $sections, 'sections', 'A section is a group of students that need the subject');

        // Check classrooms
        $classrooms = DB::table('classrooms')->get();
        $this->checkData('Classrooms', $classrooms, 'classrooms', 'Rooms where classes will be held');

        // Check days
        $days = DB::table('days')->get();
        $this->checkData('Days', $days, 'days', 'Days of the week when classes can be scheduled');

        // Check timeslots
        $timeslots = DB::table('timeslots')->get();
        $this->checkData('Timeslots', $timeslots, 'timeslots', 'Time periods (e.g. 8:00-10:00, 10:00-12:00)');

        // Check teachers
        $teachers = DB::table('teachers')->get();
        if ($teachers->isEmpty()) {
            $this->warn("⚠️  No teachers found - subjects won't be assigned");
        }

        $this->info("\n✅ ALL DATA PRESENT - READY TO GENERATE!\n");
        $this->info("Run: php artisan timetable:generate {$semesterId}\n");
    }

    private function checkData($name, $collection, $table, $description)
    {
        if ($collection->isEmpty()) {
            $this->error("❌ $name: MISSING");
            $this->line("   $description");
            $this->line("   Create some using the admin panel or seeders.\n");
        } else {
            $this->info("✅ $name: " . count($collection));
            foreach ($collection as $item) {
                $displayName = $item->name ?? $item->id ?? 'N/A';
                $this->line("   - $displayName");
            }
            $this->line("");
        }
    }
}
