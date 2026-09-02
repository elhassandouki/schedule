<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UnifiedDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExcelImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UnifiedDemoSeeder::class);
    }

    public function test_professors_csv_import_creates_professor_and_syncs_modules(): void
    {
        $csv = "name,email,password,max_weekly_hours,max_daily_minutes,module_codes\n"
            . "Dr Import,import.prof@school.local,Password123,12,360,INF101;INF102\n";

        $response = $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ])->actingAs($this->admin())->post(route('professors.import'), [
            'file' => UploadedFile::fake()->createWithContent('professeurs.csv', $csv),
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $professor = User::where('email', 'import.prof@school.local')->first();
        $this->assertNotNull($professor);
        $this->assertSame('prof', $professor->role);
        $this->assertSame(12, (int) $professor->max_weekly_hours);
        $this->assertCount(2, DB::table('professor_module')->where('professor_id', $professor->id)->get());
    }

    public function test_modules_csv_import_resolves_program_semester_and_professors(): void
    {
        $csv = "name,code,program_code,semester_number,academic_year_name,type,weekly_hours,professor_emails\n"
            . "Module importé,INF999,LIC-INF,1,2026/2027,td,3,alice@school.local\n";

        $response = $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ])->actingAs($this->admin())->post(route('modules.import'), [
            'file' => UploadedFile::fake()->createWithContent('modules.csv', $csv),
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $module = DB::table('modules')->where('code', 'INF999')->first();
        $this->assertNotNull($module);
        $this->assertSame('td', $module->type);
        $this->assertSame(3, (int) $module->weekly_hours);
        $this->assertDatabaseHas('professor_module', [
            'module_id' => $module->id,
            'professor_id' => User::where('email', 'alice@school.local')->value('id'),
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@school.local')->firstOrFail();
    }
}
