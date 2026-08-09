<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Seeders\UnifiedDemoSeeder;
use App\Models\User;
use App\Services\AutoGenerateTimetable;
use Illuminate\Support\Facades\DB;

class GenerateTimetableBugTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_with_demo_data_returns_sessions(): void
    {
        $this->seed(UnifiedDemoSeeder::class);

        $semester = DB::table('semesters')->first();
        $this->assertNotNull($semester, 'Semester should exist');

        $generator = app(AutoGenerateTimetable::class);
        $report = $generator->generate((int) $semester->id);

        $this->assertArrayHasKey('sessions_generated', $report);
        $this->assertGreaterThan(0, $report['sessions_generated']);
        $this->assertGreaterThan(0, DB::table('timetable_sessions')->count());

        // Success percentage must account for skipped sessions
        $totalAttempts = $report['sessions_generated'] + $report['sessions_skipped'];
        $expected = $totalAttempts > 0
            ? round(($report['sessions_generated'] / $totalAttempts) * 100)
            : 0;
        $this->assertEquals($expected, $report['success_percentage']);
    }

    public function test_generation_without_subjects_returns_error_not_partial(): void
    {
        $this->seed(UnifiedDemoSeeder::class);

        $year = DB::table('academic_years')->insertGetId([
            'name' => '2027/2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $program = DB::table('programs')->first();
        $semester = DB::table('semesters')->insertGetId([
            'program_id' => $program->id,
            'academic_year_id' => $year,
            'name' => 'Semestre vide',
            'number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $generator = app(AutoGenerateTimetable::class);
        $report = $generator->generate((int) $semester);

        $this->assertArrayHasKey('error', $report);
        $this->assertArrayNotHasKey('sessions_generated', $report);
    }

    public function test_dashboard_does_not_record_partial_history_on_generator_error(): void
    {
        $this->seed(UnifiedDemoSeeder::class);

        $year = DB::table('academic_years')->insertGetId([
            'name' => '2027/2028', 'starts_on' => '2027-09-01', 'ends_on' => '2028-06-30', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $program = DB::table('programs')->first();
        $semester = DB::table('semesters')->insertGetId([
            'program_id' => $program->id,
            'academic_year_id' => $year,
            'name' => 'Semestre vide',
            'number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::where('role', 'super_admin')->first();
        $this->actingAs($user);

        DB::table('schedule_histories')->truncate();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
        $this->post(route('timetable.generate'), [
                'semester_id' => $semester,
                'name' => 'Proposition test',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('generation');

        $this->assertEquals(0, DB::table('schedule_histories')->count(),
            'A generator error must never be recorded as a partial history');
    }
}
