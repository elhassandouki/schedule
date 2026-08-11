<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimetableSessionRequest;
use App\Models\{Classroom, Module, Program, SchoolDay, Semester, Timeslot, TimetableSession, StudentGroup, User};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableSessionController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = TimetableSession::with(['module', 'professor', 'classroom', 'studentGroup', 'timeslot', 'day'])
            ->when($request->program_id, fn ($q, $id) => $q->whereHas('semester', fn ($semester) => $semester->where('program_id', $id)))
            ->when($request->semester_id, fn ($q, $id) => $q->where('semester_id', $id))
            ->when($request->student_group_id, fn ($q, $id) => $q->where('student_group_id', $id))
            ->when($request->professor_id, fn ($q, $id) => $q->where('professor_id', $id))
            ->when($request->classroom_id, fn ($q, $id) => $q->where('classroom_id', $id))->get();

        return view('timetable.index', $this->formData() + compact('sessions'));
    }

    public function create(): View
    {
        return view('timetable.form', $this->formData() + ['timetableSession' => new TimetableSession]);
    }

    public function store(StoreTimetableSessionRequest $request): RedirectResponse
    {
        TimetableSession::create($request->validated());

        return redirect()->route('timetable.index')->with('success', 'Session créée.');
    }

    public function edit(TimetableSession $timetableSession): View
    {
        return view('timetable.form', $this->formData() + compact('timetableSession'));
    }

    public function update(StoreTimetableSessionRequest $request, TimetableSession $timetableSession): RedirectResponse
    {
        $timetableSession->update($request->validated());

        return redirect()->route('timetable.index')->with('success', 'Session mise à jour.');
    }

    public function destroy(TimetableSession $timetableSession): RedirectResponse
    {
        $timetableSession->delete();

        return back()->with('success', 'Session supprimée.');
    }

    private function formData(): array
    {
        return [
            'programs' => Program::orderBy('name')->get(),
            'semesters' => Semester::with('program')->orderBy('number')->get(),
            'modules' => Module::with('semester')->orderBy('name')->get(),
            'professors' => User::where('role', 'prof')->orderBy('name')->get(),
            'classrooms' => Classroom::orderBy('name')->get(),
            'studentGroups' => StudentGroup::with('semester')->orderBy('name')->get(),
            'timeslots' => Timeslot::orderBy('position')->get(),
            'days' => SchoolDay::orderBy('position')->get(),
        ];
    }
}
