<?php

namespace Tests\Unit;

use App\Services\ProfessorModuleEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProfessorModuleEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_professor_is_allowed(): void
    {
        $professorId = DB::table('users')->insertGetId([
            'name' => 'Prof A',
            'email' => 'prof-a@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $moduleId = DB::table('modules')->insertGetId([
            'program_id' => DB::table('programs')->insertGetId([
                'department_id' => DB::table('departments')->insertGetId([
                    'name' => 'Informatique',
                    'code' => 'INFO',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                'name' => 'Licence',
                'code' => 'LIC',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'name' => 'Algorithms',
            'code' => 'ALG',
            'weekly_hours' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('professor_module')->insert([
            'professor_id' => $professorId,
            'module_id' => $moduleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ProfessorModuleEligibility();
        $service->validate($professorId, $moduleId);

        $this->assertTrue(true);
    }

    public function test_unauthorized_professor_is_rejected(): void
    {
        $professorId = DB::table('users')->insertGetId([
            'name' => 'Prof B',
            'email' => 'prof-b@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $moduleId = DB::table('modules')->insertGetId([
            'program_id' => DB::table('programs')->insertGetId([
                'department_id' => DB::table('departments')->insertGetId([
                    'name' => 'Maths',
                    'code' => 'MATH',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                'name' => 'Master',
                'code' => 'MAS',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'name' => 'Physics',
            'code' => 'PHY',
            'weekly_hours' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ProfessorModuleEligibility();

        try {
            $service->validate($professorId, $moduleId);
            $this->fail('Expected a validation exception for an unauthorized professor.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('professor_id', $exception->errors());
            $this->assertStringContainsString('not assigned', $exception->errors()['professor_id'][0]);
        }
    }

    public function test_missing_pivot_relationship_is_rejected(): void
    {
        $professorId = DB::table('users')->insertGetId([
            'name' => 'Prof C',
            'email' => 'prof-c@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $moduleId = DB::table('modules')->insertGetId([
            'program_id' => DB::table('programs')->insertGetId([
                'department_id' => DB::table('departments')->insertGetId([
                    'name' => 'Chimie',
                    'code' => 'CHIM',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                'name' => 'Licence',
                'code' => 'LIC2',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'name' => 'Chemistry',
            'code' => 'CHE',
            'weekly_hours' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ProfessorModuleEligibility();

        try {
            $service->validate($professorId, $moduleId);
            $this->fail('Expected a validation exception when the pivot relationship is missing.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('professor_id', $exception->errors());
        }
    }
}
