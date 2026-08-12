<?php

namespace App\Http\Controllers;

use App\Models\{Module, Program, Semester, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfessorController extends Controller
{
    public function index()
    {
        return view('professors.index', ['professors' => User::where('role', 'prof')->with('modules')->orderBy('name')->paginate(15)]);
    }

    public function create()
    {
        return view('professors.form', $this->formData() + ['professor' => new User]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $professor = User::create($data['user'] + ['role' => 'prof']);
        $professor->modules()->sync($data['module_ids']);
        return redirect()->route('professors.index')->with('success', 'Professeur ajouté.');
    }

    public function edit(User $professor)
    {
        abort_unless($professor->role === 'prof', 404);
        return view('professors.form', $this->formData() + compact('professor'));
    }

    public function update(Request $request, User $professor)
    {
        abort_unless($professor->role === 'prof', 404);
        $data = $this->validated($request, $professor);
        $professor->update($data['user']);
        $professor->modules()->sync($data['module_ids']);
        return redirect()->route('professors.index')->with('success', 'Professeur modifié.');
    }

    private function formData(): array
    {
        return [
            'programs' => Program::orderBy('name')->get(),
            'semesters' => Semester::with('program')->orderBy('number')->get(),
            'modules' => Module::with('semester')->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?User $professor = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($professor ? ','.$professor->id : '')],
            'max_weekly_hours' => ['nullable', 'integer', 'min:0'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['integer', 'exists:modules,id'],
        ];
        $rules['password'] = $professor ? ['nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'];
        $data = $request->validate($rules);
        $user = collect($data)->only(['name', 'email', 'max_weekly_hours'])->all();
        if (!empty($data['password'])) $user['password'] = Hash::make($data['password']);
        return ['user' => $user, 'module_ids' => $data['module_ids'] ?? []];
    }

    public function availabilities(User $professor)
    {
        abort_unless($professor->role === 'prof', 404);
        $rows = \Illuminate\Support\Facades\DB::table('professor_availabilities')
            ->where('professor_id', $professor->id)->orderBy('day_of_week')->get();
        $defined = $rows->isNotEmpty();
        return view('professors.availabilities', compact('professor', 'rows', 'defined'));
    }

    public function updateAvailabilities(Request $request, User $professor)
    {
        abort_unless($professor->role === 'prof', 404);
        $data = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'start_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'end_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'available' => ['nullable', 'boolean'],
        ]);
        $data['available'] = !empty($data['available']);
        if ($data['end_minute'] <= $data['start_minute']) {
            return redirect()->back()->with('error', "L'heure de fin doit être après l'heure de début.");
        }
        \Illuminate\Support\Facades\DB::table('professor_availabilities')->updateOrInsert(
            ['professor_id' => $professor->id, 'day_of_week' => $data['day_of_week']],
            $data + ['created_at' => now(), 'updated_at' => now()],
        );
        return redirect()->route('professors.availabilities', $professor)->with('success', 'Disponibilité enregistrée.');
    }

    public function deleteAvailability(User $professor, $availabilityId)
    {
        abort_unless($professor->role === 'prof', 404);
        \Illuminate\Support\Facades\DB::table('professor_availabilities')
            ->where('id', $availabilityId)->where('professor_id', $professor->id)->delete();
        return redirect()->route('professors.availabilities', $professor)->with('success', 'Disponibilité supprimée.');
    }
}
