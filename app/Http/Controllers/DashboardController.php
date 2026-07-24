<?php
namespace App\Http\Controllers;
use App\Models\Schedule;
use App\Services\ScheduleGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class DashboardController extends Controller {
 public function index(){ return view('dashboard',['counts'=>['filières'=>DB::table('programs')->count(),'semestres'=>DB::table('semesters')->count(),'groupes'=>DB::table('student_groups')->count(),'professeurs'=>DB::table('users')->where('role','prof')->count()],'schedules'=>Schedule::latest()->take(6)->get(),'semesters'=>DB::table('semesters')->join('programs','programs.id','=','semesters.program_id')->select('semesters.*','programs.name as program')->get()]); }
 public function generate(Request $request, ScheduleGenerator $generator){ $data=$request->validate(['semester_id'=>'required|exists:semesters,id','name'=>'required|string|max:100']); [$schedule,$unplaced]=$generator->generate($data['semester_id'],$data['name']); return redirect()->route('schedules.show',$schedule)->with('generation',empty($unplaced)?'Emploi généré sans conflit.':count($unplaced).' séance(s) non placée(s): vérifiez salles, capacités ou indisponibilités.')->with('unplaced',$unplaced); }
 public function show(Schedule $schedule){ $entries=DB::table('timetable_entries as e')->join('teaching_sessions as s','s.id','=','e.teaching_session_id')->join('modules as m','m.id','=','s.module_id')->join('student_groups as g','g.id','=','s.student_group_id')->join('users as p','p.id','=','s.professor_id')->join('classrooms as c','c.id','=','e.classroom_id')->where('e.schedule_id',$schedule->id)->orderBy('day_of_week')->orderBy('start_minute')->select('e.*','m.name as module','g.name as groupe','p.name as professeur','c.name as salle')->get(); return view('schedules.show',compact('schedule','entries')); }
}
