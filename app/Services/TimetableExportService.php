<?php

namespace App\Services;

use App\Models\Semester;
use Illuminate\Support\Facades\DB;

/**
 * Collects the data needed for timetable exports (PDF / Excel),
 * ordered by day and timeslot position and grouped per group
 * to also produce the per-group sheets in Excel.
 */
class TimetableExportService
{
    /**
     * @return array{semester: \App\Models\Semester, program: string, academicYear: string, slug: string, entries: \Illuminate\Support\Collection, counts: array{byGroup: \Illuminate\Support\Collection, byDay: \Illuminate\Support\Collection}}
     */
    public function collect(Semester $semester, $user): array
    {
        $semester->load('program');

        $isAdminOrChef = in_array($user->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere'], true);

        $query = DB::table('timetable_sessions as ts')
            ->join('modules as m', 'm.id', '=', 'ts.module_id')
            ->join('student_groups as sg', 'sg.id', '=', 'ts.student_group_id')
            ->join('users as professor', 'professor.id', '=', 'ts.professor_id')
            ->join('classrooms as c', 'c.id', '=', 'ts.classroom_id')
            ->join('days as d', 'd.id', '=', 'ts.day_id')
            ->join('timeslots as slot', 'slot.id', '=', 'ts.timeslot_id')
            ->where('ts.semester_id', $semester->id);

        if (!$isAdminOrChef) {
            $query->where('ts.professor_id', $user->id);
        }

        $entries = $query
            ->orderBy('d.position')
            ->orderBy('slot.position')
            ->orderBy('sg.name')
            ->select(
                'ts.id',
                'ts.day_id',
                'ts.timeslot_id',
                'm.name as module',
                'sg.name as groupe',
                'professor.name as professeur',
                'c.name as salle',
                'd.name as day_name',
                'slot.name as timeslot_name',
                'slot.starts_at',
                'slot.ends_at',
            )
            ->get();

        return [
            'semester' => $semester,
            'program' => $semester->program ? $semester->program->name : '—',
            'academicYear' => (string) (DB::table('academic_years')->where('id', $semester->academic_year_id)->value('name') ?? '—'),
            'slug' => $this->slug($semester->name ?? $semester->id, $semester->program_id),
            'entries' => $entries,
            'counts' => [
                'byGroup' => $entries->groupBy('groupe')->map(fn ($rows) => count($rows)),
                'byDay' => $entries->groupBy('day_name')->map(fn ($rows) => count($rows)),
            ],
        ];
    }

    /**
     * Translates day names stored in English (Monday...) to French,
     * because the `days` table is seeded in English while the UI is French.
     */
    public function translateDay(string $englishDay): string
    {
        return static::DAY_FR[$englishDay] ?? $englishDay;
    }

    private const DAY_FR = [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche',
    ];

    private function slug(string $semesterName, $programId): string
    {
        $base = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $semesterName);
        $base = preg_replace('/[^a-z0-9]+/', '_', $base);

        return substr(trim($base, '_'), 0, 30) . '_' . $programId;
    }
}
