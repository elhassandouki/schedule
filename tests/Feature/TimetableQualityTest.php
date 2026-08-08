<?php

namespace Tests\Feature;

use App\Services\TimetableQualityAnalyzer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimetableQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed demo data
        $this->seed(\Database\Seeders\UnifiedDemoSeeder::class);
        
        // Generate timetables
        $semesters = DB::table('semesters')->limit(3)->pluck('id');
        foreach ($semesters as $semesterId) {
            $generator = new \App\Services\AutoGenerateTimetable();
            $generator->generate($semesterId);
        }
    }

    /**
     * Test quality report is visible to admin
     */
    public function test_admin_can_view_quality_report()
    {
        $admin = User::where('role', 'super_admin')
            ->first();
        
        $semester = DB::table('semesters')->first();
        
        $response = $this->actingAs($admin)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertStatus(200);
        $response->assertSee('Quality Score');
        $response->assertSee('Timetable Quality Report');
    }

    /**
     * Test quality report is visible to chef_departement (if authorized)
     */
    public function test_chef_can_view_quality_report_for_own_department()
    {
        $chef = User::where('role', 'chef_departement')
            ->first();
        
        $program = DB::table('programs')
            ->where('department_id', $chef->department_id)
            ->first();
        
        if (!$program) {
            $this->markTestSkipped('No program in chef department');
        }
        
        $semester = DB::table('semesters')
            ->where('program_id', $program->id)
            ->first();
        
        if (!$semester) {
            $this->markTestSkipped('No semester for program');
        }
        
        $response = $this->actingAs($chef)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertStatus(200);
    }

    /**
     * Test chef cannot view quality report for unauthorized department
     */
    public function test_chef_cannot_view_quality_report_for_other_department()
    {
        $chef = User::where('role', 'chef_departement')
            ->first();
        
        // Find a semester from a different department
        $otherProgram = DB::table('programs')
            ->where('department_id', '!=', $chef->department_id)
            ->first();
        
        if (!$otherProgram) {
            $this->markTestSkipped('No other department found');
        }
        
        $semester = DB::table('semesters')
            ->where('program_id', $otherProgram->id)
            ->first();
        
        if (!$semester) {
            $this->markTestSkipped('No semester for other program');
        }
        
        $response = $this->actingAs($chef)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertStatus(403);
    }

    /**
     * Test professor can view quality report for semesters they teach
     */
    public function test_prof_can_view_quality_report_for_own_semester()
    {
        $prof = User::where('role', 'prof')
            ->first();
        
        if (!$prof) {
            $this->markTestSkipped('No professor user found');
        }
        
        $teacher = DB::table('teachers')
            ->where('user_id', $prof->id)
            ->first();
        
        if (!$teacher) {
            $this->markTestSkipped('Professor has no teacher record');
        }
        
        $semester = DB::table('timetable_sessions')
            ->join('subjects', 'subjects.id', '=', 'timetable_sessions.subject_id')
            ->where('subjects.teacher_id', $teacher->id)
            ->value('timetable_sessions.semester_id');
        
        if (!$semester) {
            $this->markTestSkipped('Professor teaches no sessions');
        }
        
        $response = $this->actingAs($prof)
            ->get("/timetable/{$semester}/quality");
        
        $response->assertStatus(200);
    }

    /**
     * Test professor cannot view quality report for semesters they don't teach
     */
    public function test_prof_cannot_view_quality_report_for_other_semester()
    {
        $prof = User::where('role', 'prof')
            ->first();
        
        if (!$prof) {
            $this->markTestSkipped('No professor user found');
        }
        
        $teacher = DB::table('teachers')
            ->where('user_id', $prof->id)
            ->first();
        
        // Find a semester they don't teach
        $semester = DB::table('semesters')
            ->whereNotIn('id', function ($query) use ($teacher) {
                $query->select('timetable_sessions.semester_id')
                    ->from('timetable_sessions')
                    ->where('teacher_id', $teacher->id);
            })
            ->first();
        
        if (!$semester) {
            $this->markTestSkipped('Professor teaches all semesters');
        }
        
        $response = $this->actingAs($prof)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertStatus(403);
    }

    /**
     * Test quality score is displayed
     */
    public function test_quality_score_is_displayed()
    {
        $admin = User::where('role', 'super_admin')->first();
        $semester = DB::table('semesters')->first();
        
        $response = $this->actingAs($admin)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertSee('/100');
        $response->assertSee('Excellent', 'Good', 'Needs Improvement', 'Poor');
    }

    /**
     * Test hard conflicts are displayed
     */
    public function test_hard_conflicts_section_displayed()
    {
        $admin = User::where('role', 'super_admin')->first();
        $semester = DB::table('semesters')->first();
        
        $response = $this->actingAs($admin)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertSee('Hard Conflicts');
    }

    /**
     * Test warnings section is displayed
     */
    public function test_warnings_section_displayed()
    {
        $admin = User::where('role', 'super_admin')->first();
        $semester = DB::table('semesters')->first();
        
        $response = $this->actingAs($admin)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertSee('Warnings');
    }

    /**
     * Test coverage percentage displayed
     */
    public function test_coverage_percentage_displayed()
    {
        $admin = User::where('role', 'super_admin')->first();
        $semester = DB::table('semesters')->first();
        
        $response = $this->actingAs($admin)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertSee('Coverage');
        $response->assertSee('%');
    }

    /**
     * Test teacher workload section
     */
    public function test_teacher_workload_section_displayed()
    {
        $admin = User::where('role', 'super_admin')->first();
        $semester = DB::table('semesters')->first();
        
        $response = $this->actingAs($admin)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertSee('Teacher Workload');
    }

    /**
     * Test classroom utilization section
     */
    public function test_classroom_utilization_section_displayed()
    {
        $admin = User::where('role', 'super_admin')->first();
        $semester = DB::table('semesters')->first();
        
        $response = $this->actingAs($admin)
            ->get("/timetable/{$semester->id}/quality");
        
        $response->assertSee('Classroom Utilization');
    }

    /**
     * Test quality API endpoint returns JSON
     */
    public function test_quality_api_endpoint_returns_json()
    {
        $admin = User::where('role', 'super_admin')->first();
        $semester = DB::table('semesters')->first();
        
        $response = $this->actingAs($admin)
            ->getJson("/api/timetable/{$semester->id}/quality/summary");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'quality_score',
            'quality_rating',
            'generated_sessions',
            'required_sessions',
            'skipped_sessions',
            'conflict_count',
            'warning_count',
        ]);
    }

    /**
     * Test TimetableQualityAnalyzer calculates score
     */
    public function test_quality_analyzer_calculates_score()
    {
        $semester = DB::table('semesters')->first();
        
        $analyzer = new TimetableQualityAnalyzer();
        $report = $analyzer->analyze($semester->id);
        
        $this->assertIsArray($report);
        $this->assertArrayHasKey('quality_score', $report);
        $this->assertArrayHasKey('quality_rating', $report);
        $this->assertArrayHasKey('generated_sessions', $report);
        $this->assertArrayHasKey('required_sessions', $report);
        $this->assertArrayHasKey('hard_conflicts', $report);
        $this->assertArrayHasKey('soft_warnings', $report);
    }

    /**
     * Test quality score is between 0-100
     */
    public function test_quality_score_between_0_and_100()
    {
        $semester = DB::table('semesters')->first();
        
        $analyzer = new TimetableQualityAnalyzer();
        $report = $analyzer->analyze($semester->id);
        
        $this->assertGreaterThanOrEqual(0, $report['quality_score']);
        $this->assertLessThanOrEqual(100, $report['quality_score']);
    }

    /**
     * Test quality rating is valid
     */
    public function test_quality_rating_is_valid()
    {
        $semester = DB::table('semesters')->first();
        
        $analyzer = new TimetableQualityAnalyzer();
        $report = $analyzer->analyze($semester->id);
        
        $this->assertContains($report['quality_rating'], ['Excellent', 'Good', 'Needs Improvement', 'Poor']);
    }

    /**
     * Test unauthenticated user cannot view quality report
     */
    public function test_unauthenticated_user_cannot_view_quality_report()
    {
        $semester = DB::table('semesters')->first();
        
        $response = $this->get("/timetable/{$semester->id}/quality");
        
        $response->assertStatus(302); // Redirect to login
    }
}
