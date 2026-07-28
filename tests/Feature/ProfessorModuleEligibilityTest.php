<?php
namespace Tests\Feature;

use App\Models\Module;
use App\Models\Program;
use App\Models\Department;
use App\Models\User;
use App\Services\ProfessorModuleEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProfessorModuleEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_professor_must_be_assigned_to_a_module(): void
    {
        $professor = User::create(['name'=>'Prof','email'=>'prof@test.test','password'=>'secret','role'=>'prof']);
        $department = Department::create(['name'=>'Science','code'=>'SCI']);
        $program = Program::create(['department_id'=>$department->id,'name'=>'Licence','code'=>'LIC']);
        $module = Module::create(['program_id'=>$program->id,'name'=>'Maths','code'=>'MAT']);
        $service = new ProfessorModuleEligibility;

        try { $service->validate($professor->id, $module->id); $this->fail('Expected module eligibility validation to fail.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('professor_id', $exception->errors()); }

        DB::table('professor_module')->insert(['professor_id'=>$professor->id,'module_id'=>$module->id,'created_at'=>now(),'updated_at'=>now()]);
        $service->validate($professor->id, $module->id);
        $this->assertTrue(true);
    }
}
