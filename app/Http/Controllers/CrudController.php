<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\ProfessorModuleEligibility;

class CrudController extends Controller
{
    private function resources(): array { return [
        'annees' => ['table'=>'academic_years','title'=>'Années universitaires','fields'=>['name'=>'Année (ex: 2026/2027)','starts_on'=>'Date début','ends_on'=>'Date fin','is_active'=>'Année active'], 'types'=>['starts_on'=>'date','ends_on'=>'date','is_active'=>'checkbox']],
        'departements' => ['table'=>'departments','title'=>'Départements','fields'=>['name'=>'Nom','code'=>'Code']],
        'filieres' => ['table'=>'programs','title'=>'Filières','fields'=>['department_id'=>'Département','name'=>'Nom (français)','name_ar'=>'Nom (arabe)','code'=>'Code'], 'selects'=>['department_id'=>['departments','name']]],
        'semestres' => ['table'=>'semesters','title'=>'Semestres','fields'=>['program_id'=>'Filière','academic_year_id'=>'Année','name'=>'Libellé','number'=>'Numéro'], 'types'=>['number'=>'number'], 'selects'=>['program_id'=>['programs','name'],'academic_year_id'=>['academic_years','name']]],
        'modules' => ['table'=>'modules','title'=>'Modules','fields'=>['program_id'=>'Filière','semester_id'=>'Semestre','name'=>'Nom','code'=>'Code','weekly_hours'=>'Heures / semaine'], 'types'=>['weekly_hours'=>'number'], 'selects'=>['program_id'=>['programs','name'],'semester_id'=>['semesters','name']]],
        'salles' => ['table'=>'classrooms','title'=>'Salles','fields'=>['name'=>'Nom','capacity'=>'Capacité','type'=>'Type'], 'types'=>['capacity'=>'number','type'=>'select'], 'options'=>['type'=>['cours'=>'Cours','td'=>'TD','tp'=>'TP','mixte'=>'Mixte']]],
        'professeurs' => ['table'=>'users','title'=>'Utilisateurs et professeurs','fields'=>['name'=>'Nom complet','email'=>'Email','password'=>'Mot de passe','role'=>'Rôle','max_weekly_hours'=>'Maximum heures / semaine (prof)'], 'types'=>['password'=>'password','role'=>'select','max_weekly_hours'=>'number'], 'options'=>['role'=>array_combine(\App\Models\User::ROLES, \App\Models\User::ROLES)]],
        'affectations-modules' => ['table'=>'professor_module','title'=>'Modules autorisés par professeur','fields'=>['professor_id'=>'Professeur','module_id'=>'Module'], 'selects'=>['professor_id'=>['users','name'],'module_id'=>['modules','name']]],
        'teachers' => ['table'=>'teachers','title'=>'Enseignants','fields'=>['name'=>'Nom','email'=>'Email','phone'=>'Téléphone']],
        'subjects' => ['table'=>'subjects','title'=>'Matières','fields'=>['semester_id'=>'Semestre','teacher_id'=>'Enseignant','name'=>'Nom','code'=>'Code'], 'selects'=>['semester_id'=>['semesters','name'],'teacher_id'=>['teachers','name']]],
        'sections' => ['table'=>'sections','title'=>'Groupes','fields'=>['program_id'=>'Filière','name'=>'Nom','capacity'=>'Capacité'], 'types'=>['capacity'=>'number'], 'selects'=>['program_id'=>['programs','name']]],
        'students' => ['table'=>'students','title'=>'Étudiants','fields'=>['section_id'=>'Groupe','name'=>'Nom','email'=>'Email'], 'selects'=>['section_id'=>['sections','name']]],
        'timeslots' => ['table'=>'timeslots','title'=>'Créneaux horaires','fields'=>['name'=>'Libellé','starts_at'=>'Heure début','ends_at'=>'Heure fin','position'=>'Ordre'], 'types'=>['starts_at'=>'time','ends_at'=>'time','position'=>'number']],
        'days' => ['table'=>'days','title'=>'Jours de l\'école','fields'=>['name'=>'Nom','position'=>'Ordre'], 'types'=>['position'=>'number']],
    ]; }
    private function resource(string $key): array { abort_unless(isset($this->resources()[$key]),404); return $this->resources()[$key]; }
    public function index(string $resource) { $meta=$this->resource($resource); $rows=DB::table($meta['table'])->orderByDesc('id')->paginate(15); return view('crud.index',compact('resource','meta','rows')+['choices'=>$this->choices($meta)]); }
    public function create(string $resource) { $meta=$this->resource($resource); return view('crud.form',['resource'=>$resource,'meta'=>$meta,'row'=>null,'choices'=>$this->choices($meta)]); }
    public function store(Request $request,string $resource) { $meta=$this->resource($resource); $data=$this->validated($request,$meta); $this->validateProfessorModule($meta, $data); DB::table($meta['table'])->insert(array_merge($data,['created_at'=>now(),'updated_at'=>now()])); return redirect()->route('crud.index',$resource)->with('success','Enregistrement ajouté.'); }
    public function edit(string $resource,int $id) { $meta=$this->resource($resource); $row=DB::table($meta['table'])->find($id); abort_unless($row,404); return view('crud.form',compact('resource','meta','row')+['choices'=>$this->choices($meta)]); }
    public function update(Request $request,string $resource,int $id) { $meta=$this->resource($resource); $data=$this->validated($request,$meta,$id); $this->validateProfessorModule($meta, $data); DB::table($meta['table'])->where('id',$id)->update(array_merge($data,['updated_at'=>now()])); return redirect()->route('crud.index',$resource)->with('success','Enregistrement modifié.'); }
    public function destroy(string $resource,int $id) { $meta=$this->resource($resource); if($meta['table']==='users' && auth()->id()===$id) return back()->with('error','Impossible de supprimer votre propre compte.'); try { DB::table($meta['table'])->where('id',$id)->delete(); return back()->with('success','Enregistrement supprimé.'); } catch (\Throwable) { return back()->with('error','Suppression impossible : cet élément est utilisé par une autre donnée.'); } }
    private function choices(array $meta): array { $all=[]; foreach($meta['selects']??[] as $field=>[$table,$label]) $all[$field]=DB::table($table)->orderBy($label)->pluck($label,'id'); return $all; }
    private function validateProfessorModule(array $meta, array $data): void { if ($meta['table'] === 'teaching_sessions') app(ProfessorModuleEligibility::class)->validateTeachingSession($data); }
    private function validated(Request $request,array $meta,?int $id=null): array { $rules=[]; foreach($meta['fields'] as $field=>$label) { $rules[$field]=($field==='password'&&$id?'nullable':'required').'|max:255'; if(in_array($meta['types'][$field]??'', ['number']))$rules[$field]='required|integer|min:0'; if(in_array($field, ['max_weekly_hours','max_daily_minutes'], true))$rules[$field]='nullable|integer|min:0'; if($field==='email')$rules[$field]='required|email|unique:users,email'.($id?','.$id:''); if(isset($meta['selects'][$field]))$rules[$field]='required|integer'; }
        if (in_array($meta['table'], ['teachers','students'], true) && array_key_exists('email', $meta['fields'])) $rules['email']='nullable|email|unique:'.$meta['table'].',email'.($id?','.$id:'');
        $data=Validator::make($request->all(),$rules)->validate(); foreach($meta['types']??[] as $field=>$type) if($type==='checkbox')$data[$field]=$request->boolean($field); if($meta['table']==='users'){ if(empty($data['password']))unset($data['password']); else $data['password']=Hash::make($data['password']); } return $data; }
}
