<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AutoGenerateTimetable;
use Database\Seeders\UnifiedDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfessorEtatTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_pdf_contains_the_professors_planned_timetable(): void
    {
        $this->seed(UnifiedDemoSeeder::class);
        $admin = User::where('role', 'super_admin')->first();
        $this->actingAs($admin);
        $semester = DB::table('semesters')->whereIn('id', DB::table('modules')->pluck('semester_id'))->first();
        $this->assertNotNull($semester);
        app(AutoGenerateTimetable::class)->generate((int) $semester->id);
        $professorId = DB::table('timetable_sessions')->where('semester_id', $semester->id)->value('professor_id');
        $this->assertNotNull($professorId);

        $this->get(route('etat.pdf.professor', ['professor_id' => $professorId]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}

