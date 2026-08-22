<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UnifiedDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GroupedSemesterGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_semester_number_generates_all_matching_programs(): void
    {
        $this->seed(UnifiedDemoSeeder::class);
        $this->actingAs(User::where('role', 'super_admin')->first());

        $semesterIds = DB::table('semesters')->where('number', 1)->pluck('id');
        $this->assertGreaterThanOrEqual(2, $semesterIds->count());

        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);
        $response = $this->post(route('timetable.generate'), [
            'semester_number' => 1,
            'name' => 'Génération groupée S1',
        ]);

        $response->assertRedirect(route('timetable.semester-number', 1));
        foreach ($semesterIds as $semesterId) {
            $hasData = DB::table('modules')->where('semester_id', $semesterId)->exists()
                && DB::table('student_groups')->where('semester_id', $semesterId)->exists();
            if ($hasData) {
                $this->assertGreaterThan(0, DB::table('timetable_sessions')->where('semester_id', $semesterId)->count());
                $this->assertDatabaseHas('schedule_histories', ['semester_id' => $semesterId]);
            }
        }
    }
}

