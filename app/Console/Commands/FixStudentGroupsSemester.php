<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixStudentGroupsSemester extends Command
{
    protected $signature = 'fix:student-groups {--fresh}';
    protected $description = 'Fix student_groups - ensure semester_id is set, or reseed from scratch';

    public function handle()
    {
        $this->info('🔧 FIXING STUDENT GROUPS\n');

        if ($this->option('fresh')) {
            $this->info('Deleting all student_groups...');
            DB::table('student_groups')->truncate();
            
            $this->info('Reseeding with StudentGroupsArchitectureSeeder...');
            $this->call('db:seed', ['--class' => 'StudentGroupsArchitectureSeeder']);
            $this->info('✅ Done! Student groups have been recreated with proper semester_id');
            return;
        }

        // Check how many have NULL semester_id
        $nullCount = DB::table('student_groups')
            ->whereNull('semester_id')
            ->count();

        if ($nullCount === 0) {
            $this->info('✅ All student_groups have semester_id set correctly!');
            $this->line('');
            $this->call('check:groups');
            return;
        }

        $this->warn("⚠️  Found $nullCount groups with NULL semester_id");
        $this->info('This is from the old seeder that didn\'t set semester_id\n');

        $this->info('Two options:');
        $this->line('1. php artisan fix:student-groups --fresh');
        $this->line('   (Delete all, reseed with proper data)');
        $this->line('');
        $this->line('2. Manually assign semester_id:');
        $this->line('   UPDATE student_groups SET semester_id = 1 WHERE semester_id IS NULL;');
        $this->line('');
        $this->info('Recommended: Use option 1 (--fresh)');
    }
}
