<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreTimetableSessionRequest;
use App\Models\{Classroom, SchoolDay, Section, Subject, Teacher, Timeslot, TimetableSession};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableSessionController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = TimetableSession::with(['subject', 'teacher', 'classroom', 'section', 'timeslot', 'day'])
            ->when($request->section_id, fn ($q, $id) => $q->where('section_id', $id))
            ->when($request->teacher_id, fn ($q, $id) => $q->where('teacher_id', $id))
            ->when($request->classroom_id, fn ($q, $id) => $q->where('classroom_id', $id))->get();
        return view('timetable.index', $this->formData() + compact('sessions'));
    }
    public function create(): View { return view('timetable.form', $this->formData() + ['timetableSession' => new TimetableSession]); }
    public function store(StoreTimetableSessionRequest $request): RedirectResponse { TimetableSession::create($request->validated()); return redirect()->route('timetable.index')->with('success', 'Session created.'); }
    public function edit(TimetableSession $timetableSession): View { return view('timetable.form', $this->formData() + compact('timetableSession')); }
    public function update(StoreTimetableSessionRequest $request, TimetableSession $timetableSession): RedirectResponse { $timetableSession->update($request->validated()); return redirect()->route('timetable.index')->with('success', 'Session updated.'); }
    public function destroy(TimetableSession $timetableSession): RedirectResponse { $timetableSession->delete(); return back()->with('success', 'Session deleted.'); }
    private function formData(): array { return ['subjects'=>Subject::orderBy('name')->get(), 'teachers'=>Teacher::orderBy('name')->get(), 'classrooms'=>Classroom::orderBy('name')->get(), 'sections'=>Section::orderBy('name')->get(), 'timeslots'=>Timeslot::orderBy('position')->get(), 'days'=>SchoolDay::orderBy('position')->get()]; }
}
