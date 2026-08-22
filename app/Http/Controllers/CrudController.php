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
        'annees' => ['table'=>'academic_years','title'=>'Années universitaires','icon'=>'fas fa-calendar-check','fields'=>['name'=>'Année (ex: 2026/2027)','starts_on'=>'Date début','ends_on'=>'Date fin','is_active'=>'Année active'], 'types'=>['starts_on'=>'date','ends_on'=>'date','is_active'=>'checkbox'], 'filters'=>['is_active']],
        'departements' => ['table'=>'departments','title'=>'Départements','icon'=>'fas fa-building','fields'=>['name'=>'Nom','code'=>'Code']],
        'filieres' => ['table'=>'programs','title'=>'Filières','icon'=>'fas fa-graduation-cap','fields'=>['department_id'=>'Département','name'=>'Nom (français)','name_ar'=>'Nom (arabe)','code'=>'Code'], 'selects'=>['department_id'=>['departments','name']], 'filters'=>['department_id']],
        'semestres' => ['table'=>'semesters','title'=>'Semestres','icon'=>'fas fa-layer-group','fields'=>['program_id'=>'Filière','academic_year_id'=>'Année','name'=>'Libellé','number'=>'Numéro','weeks_count'=>'Semaines d\'enseignement'],'types'=>['number'=>'number','weeks_count'=>'number'], 'selects'=>['program_id'=>['programs','name'],'academic_year_id'=>['academic_years','name']], 'filters'=>['program_id','academic_year_id']],
        'modules' => ['table'=>'modules','title'=>'Modules','icon'=>'fas fa-book-open','fields'=>['program_id'=>'Filière','semester_id'=>'Semestre','name'=>'Nom','type'=>'Type','code'=>'Code','weekly_hours'=>'Heures / semaine'], 'types'=>['weekly_hours'=>'number','type'=>'select'], 'options'=>['type'=>['cours'=>'Cours','td'=>'TD','tp'=>'TP']], 'selects'=>['program_id'=>['programs','name'],'semester_id'=>['semesters','name']], 'filters'=>['program_id','semester_id','type']],
        'salles' => ['table'=>'classrooms','title'=>'Salles','icon'=>'fas fa-door-open','fields'=>['name'=>'Nom','capacity'=>'Capacité','type'=>'Type'], 'types'=>['capacity'=>'number','type'=>'select'], 'options'=>['type'=>['cours'=>'Cours','amphi'=>'Amphi','labo'=>'Labo','salle_info'=>'Salle Info']], 'filters'=>['type']],
        'professeurs' => ['table'=>'users','title'=>'Utilisateurs et professeurs','icon'=>'fas fa-user-shield','fields'=>['name'=>'Nom complet','email'=>'Email','password'=>'Mot de passe','role'=>'Rôle','max_weekly_hours'=>'Maximum heures / semaine (prof)'], 'types'=>['password'=>'password','role'=>'select','max_weekly_hours'=>'number'], 'options'=>['role'=>array_combine(\App\Models\User::ROLES, \App\Models\User::ROLES)], 'filters'=>['role']],
        'affectations-modules' => ['table'=>'professor_module','title'=>'Modules autorisés par professeur','icon'=>'fas fa-chalkboard-teacher','fields'=>['professor_id'=>'Professeur','module_id'=>'Module'], 'selects'=>['professor_id'=>['users','name'],'module_id'=>['modules','name']]],
        'disponibilites-profs' => ['table'=>'professor_availabilities','title'=>'Disponibilités des professeurs','icon'=>'fas fa-clock','fields'=>['professor_id'=>'Professeur','day_of_week'=>'Jour (1=Lu … 7=Di)','start_minute'=>'Début (minutes après minuit)','end_minute'=>'Fin (minutes après minuit)','available'=>'Disponible'], 'types'=>['day_of_week'=>'number','start_minute'=>'number','end_minute'=>'number','available'=>'checkbox'], 'selects'=>['professor_id'=>['users','name']]],
        'conditions-groupes' => ['table'=>'group_study_conditions','title'=>'Conditions d’étude des groupes','icon'=>'fas fa-file-alt','fields'=>['student_group_id'=>'Groupe','day_of_week'=>'Jour (1=Lu … 7=Di)','start_minute'=>'Début (minutes après minuit)','end_minute'=>'Fin (minutes après minuit)','max_daily_minutes'=>'Maximum minutes / jour','max_gap_minutes'=>'Maximum pause (minutes)'], 'types'=>['day_of_week'=>'number','start_minute'=>'number','end_minute'=>'number','max_daily_minutes'=>'number','max_gap_minutes'=>'number'], 'selects'=>['student_group_id'=>['student_groups','name']]],
        'groupes' => ['table'=>'student_groups','title'=>'Groupes d’étudiants','icon'=>'fas fa-users','fields'=>['semester_id'=>'Semestre','name'=>'Nom','capacity'=>'Capacité'], 'types'=>['capacity'=>'number'], 'selects'=>['semester_id'=>['semesters','name']], 'filters'=>['program_id']],
        'timeslots' => ['table'=>'timeslots','title'=>'Créneaux horaires','icon'=>'fas fa-clock','fields'=>['name'=>'Libellé','starts_at'=>'Heure début','ends_at'=>'Heure fin','position'=>'Ordre'], 'types'=>['starts_at'=>'time','ends_at'=>'time','position'=>'number']],
        'days' => ['table'=>'days','title'=>"Jours de l'\u00e9cole",'icon'=>'fas fa-calendar-day','fields'=>['name'=>'Nom','position'=>'Ordre'], 'types'=>['position'=>'number']],
    ]; }
    private function resource(string $key): array {
        if ($key === 'groupes') {
            return [
                'table' => 'student_groups', 'title' => 'Groupes d’étudiants', 'icon' => 'fas fa-users',
                'fields' => ['program_id' => 'Filière', 'semester_id' => 'Semestre', 'name' => 'Nom', 'capacity' => 'Capacité', 'student_count' => 'Nombre d’étudiants', 'max_daily_minutes' => 'Maximum minutes / jour'],
                'types' => ['capacity' => 'number', 'student_count' => 'number', 'max_daily_minutes' => 'number'],
                'selects' => ['program_id' => ['programs', 'name'], 'semester_id' => ['semesters', 'name']],
            ];
        }
        if ($key === 'disponibilites-professeurs') {
            return [
                'table' => 'professor_availabilities', 'title' => 'Disponibilités des professeurs', 'icon' => 'fas fa-clock',
                'fields' => ['professor_id' => 'Professeur', 'day_of_week' => 'Jour (1-7)', 'start_minute' => 'Début (minutes)', 'end_minute' => 'Fin (minutes)', 'available' => 'Disponible'],
                'types' => ['day_of_week' => 'number', 'start_minute' => 'number', 'end_minute' => 'number', 'available' => 'checkbox'],
                'selects' => ['professor_id' => ['users', 'name']],
            ];
        }
        if ($key === 'conditions-groupes') {
            return [
                'table' => 'group_study_conditions', 'title' => 'Conditions des groupes', 'icon' => 'fas fa-file-alt',
                'fields' => ['student_group_id' => 'Groupe', 'day_of_week' => 'Jour (1-7)', 'start_minute' => 'Début (minutes)', 'end_minute' => 'Fin (minutes)', 'max_daily_minutes' => 'Maximum minutes / jour', 'max_gap_minutes' => 'Maximum pause (minutes)'],
                'types' => ['day_of_week' => 'number', 'start_minute' => 'number', 'end_minute' => 'number', 'max_daily_minutes' => 'number', 'max_gap_minutes' => 'number'],
                'selects' => ['student_group_id' => ['student_groups', 'name']],
            ];
        }
        $resources = $this->resources();
        abort_unless(isset($resources[$key]), 404);
        return $resources[$key];
    }
    public function index(string $resource) { $meta=$this->resource($resource); $filter=$this->resolveFilter($request=null,$resource,$meta); $query=DB::table($meta['table'])->orderByDesc('id'); foreach($filter['where'] as [$field,$value]) $query->where($field,$value); $rows=$query->get(); return view('crud.index',compact('resource','meta','rows')+['choices'=>$this->choices($meta)]+['filter'=>$filter]); }
    public function create(string $resource) { $meta=$this->resource($resource); return view('crud.form',['resource'=>$resource,'meta'=>$meta,'row'=>null,'choices'=>$this->choices($meta),'programSemesters'=>[]]); }
    public function store(Request $request,string $resource) { $meta=$this->resource($resource); $data=$this->validated($request,$meta); $this->validateProfessorModule($meta, $data); $this->validateSemesterBelongsToProgram($meta,$data); DB::table($meta['table'])->insert(array_merge($data,['created_at'=>now(),'updated_at'=>now()])); return redirect()->route('crud.index',$resource)->with('success','Enregistrement ajouté.'); }
    public function edit(string $resource,int $id) { $meta=$this->resource($resource); $row=DB::table($meta['table'])->find($id); abort_unless($row,404); return view('crud.form',compact('resource','meta','row')+['choices'=>$this->choices($meta),'programSemesters'=>$this->programSemestersFor($meta,$row)]); }
    public function update(Request $request,string $resource,int $id) { $meta=$this->resource($resource); $data=$this->validated($request,$meta,$id); $this->validateProfessorModule($meta, $data); $this->validateSemesterBelongsToProgram($meta,$data); DB::table($meta['table'])->where('id',$id)->update(array_merge($data,['updated_at'=>now()])); return redirect()->route('crud.index',$resource)->with('success','Enregistrement modifié.'); }
    public function destroy(string $resource,int $id) { $meta=$this->resource($resource); if($meta['table']==='users' && auth()->id()===$id) return back()->with('error','Impossible de supprimer votre propre compte.'); try { DB::table($meta['table'])->where('id',$id)->delete(); return back()->with('success','Enregistrement supprimé.'); } catch (\Throwable) { return back()->with('error','Suppression impossible : cet élément est utilisé par une autre donnée.'); } }
    private function choices(array $meta): array { $all=[]; foreach($meta['selects']??[] as $field=>[$table,$label]) { if ($field==='semester_id') { /* Semesters are filtered client-side by the chosen program; all are returned as options. */ $all[$field]=DB::table($table)->orderBy('program_id')->orderBy('number')->pluck($label,'id'); } else { $all[$field]=DB::table($table)->orderBy($label)->pluck($label,'id'); } } return $all; }
    /** Semesters available for the group's program, used to scope the semester select. */
    private function programSemestersFor(array $meta, object $row): array { if (($meta['table']??'')!=='student_groups') return []; $programId=$row->program_id??(request()->query('program_id')??null); if (!$programId) return []; return DB::table('semesters')->where('program_id',$programId)->orderBy('number')->pluck('name','id')->all(); }
    /** Filter values applied to the index listing (program_id for groups). */
    private function resolveFilter(?Request $request,string $resource,array $meta): array { $req=$request??request(); $filter=['program_id'=>null,'where'=>[]]; if ($meta['table']==='student_groups' && ($pid=(int)$req->query('program_id'))) { $filter['program_id']=$pid; $filter['where'][]=['program_id',$pid]; } return $filter; }
    /** Ensure the chosen semester belongs to the chosen program. */
    private function validateSemesterBelongsToProgram(array $meta, array $data): void { if ($meta['table']!=='student_groups') return; if (!empty($data['program_id']) && !empty($data['semester_id'])) { $ok=DB::table('semesters')->where('id',$data['semester_id'])->where('program_id',$data['program_id'])->exists(); abort_unless($ok,422,'Le semestre sélectionné n’appartient pas à la filière choisie.'); } }
    private function validateProfessorModule(array $meta, array $data): void { if ($meta['table'] === 'teaching_sessions') app(ProfessorModuleEligibility::class)->validateTeachingSession($data); }
    private function validated(Request $request,array $meta,?int $id=null): array { $rules=[]; foreach($meta['fields'] as $field=>$label) { $rules[$field]=($field==='password'&&$id?'nullable':'required').'|max:255'; if(in_array($meta['types'][$field]??'', ['number']))$rules[$field]='required|integer|min:0'; if(in_array($field, ['max_weekly_hours','max_daily_minutes'], true))$rules[$field]='nullable|integer|min:0'; if($field==='email')$rules[$field]='required|email|unique:users,email'.($id?','.$id:''); if(isset($meta['selects'][$field]))$rules[$field]='required|integer'; }
                $data=Validator::make($request->all(),$rules)->validate();
        // Subjects are no longer tied directly to a semester. The form still
        // includes this legacy field while existing installations transition.
        foreach($meta['types']??[] as $field=>$type) if($type==='checkbox')$data[$field]=$request->boolean($field); if($meta['table']==='users'){ if(empty($data['password']))unset($data['password']); else $data['password']=Hash::make($data['password']); } return $data; }

    public function showGroupConditions(Request $request, string $resource)
    {
        if ($resource !== 'groupes') abort(404);
        $groupId = (int) $request->route('id');
        $group = \DB::table('student_groups')->find($groupId);
        if (!$group) abort(404);
        $rows = \DB::table('group_study_conditions')->where('student_group_id', $group->id)->orderBy('day_of_week')->get();
        return view('crud.group-conditions', compact('group', 'rows'));
    }

    public function storeGroupCondition(Request $request, string $resource)
    {
        if ($resource !== 'groupes') abort(404);
        $groupId = (int) $request->route('id');
        $group = \DB::table('student_groups')->find($groupId);
        if (!$group) abort(404);
        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'start_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'end_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'max_daily_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);
        if ($data['end_minute'] <= $data['start_minute']) {
            return redirect()->back()->with('error', "L'heure de fin doit être après l'heure de début.");
        }
        \DB::table('group_study_conditions')->updateOrInsert(
            ['student_group_id' => $groupId, 'day_of_week' => $data['day_of_week']],
            $data + ['created_at' => now(), 'updated_at' => now()],
        );
        return redirect()->route('crud.group-conditions', ['groupes', $groupId])->with('success', 'Condition enregistrée.');
    }

    public function destroyGroupCondition(Request $request, string $resource, int $conditionId)
    {
        if ($resource !== 'groupes') abort(404);
        $groupId = (int) $request->route('id');
        \DB::table('group_study_conditions')->where('id', $conditionId)->where('student_group_id', $groupId)->delete();
        return redirect()->route('crud.group-conditions', ['groupes', $groupId])->with('success', 'Condition supprimée.');
    }
}
