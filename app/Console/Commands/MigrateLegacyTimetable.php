<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time bridge migration from the legacy minute-based system
 * (TeachingSession -> Schedule -> TimetableEntry) to the unified
 * day/timeslot system (Subject/Section/Teacher -> TimetableSession).
 *
 * Safe by design:
 *  - Runs inside a DB transaction.
 *  - Defaults to --dry-run: prints exactly what WOULD happen, writes nothing.
 *  - Only auto-migrates entries whose start/end minutes match an existing
 *    timeslot EXACTLY. Anything else is reported as "needs manual review"
 *    rather than guessed at, since this touches real saved data.
 *  - Run it against a staging copy of the database first.
 */
class MigrateLegacyTimetable extends Command
{
    protected $signature = 'timetable:migrate-legacy
        {--schedule= : Only migrate entries from this schedules.id}
        {--commit : Actually write changes. Without this flag, nothing is written.}';

    protected $description = 'Migrate legacy TimetableEntry rows into the unified TimetableSession model.';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $this->info($commit ? 'Running in COMMIT mode: changes will be written.' : 'Running in DRY-RUN mode: no changes will be written (use --commit to apply).');

        $entriesQuery = DB::table('timetable_entries as e')
            ->join('teaching_sessions as ts', 'ts.id', '=', 'e.teaching_session_id')
            ->join('modules as m', 'm.id', '=', 'ts.module_id')
            ->join('student_groups as sg', 'sg.id', '=', 'ts.student_group_id')
            ->join('semesters as sem', 'sem.id', '=', 'ts.semester_id')
            ->join('users as u', 'u.id', '=', 'ts.professor_id')
            ->select('e.*', 'ts.semester_id', 'ts.module_id', 'ts.professor_id', 'ts.student_group_id',
                'm.name as module_name', 'm.code as module_code', 'm.weekly_hours',
                'sg.name as group_name', 'sg.student_count', 'sem.program_id',
                'u.name as prof_name', 'u.email as prof_email');

        if ($scheduleId = $this->option('schedule')) {
            $entriesQuery->where('e.schedule_id', $scheduleId);
        }

        $entries = $entriesQuery->get();
        if ($entries->isEmpty()) {
            $this->warn('No legacy timetable_entries found for the given criteria.');
            return self::SUCCESS;
        }

        $days = DB::table('days')->get()->keyBy('position');
        $timeslots = DB::table('timeslots')->get();

        $created = 0;
        $skipped = [];
        $teacherCache = [];   // user_id => teacher_id
        $sectionCache = [];   // program_id|group_name => section_id
        $subjectCache = [];   // semester_id|module_code => subject_id

        DB::transaction(function () use (
            $entries, $days, $timeslots, $commit, &$created, &$skipped,
            &$teacherCache, &$sectionCache, &$subjectCache
        ) {
            foreach ($entries as $row) {
                // 1) Day: legacy day_of_week (1=Lundi..5=Vendredi) matches days.position exactly.
                $day = $days->get($row->day_of_week);
                if (!$day) {
                    $skipped[] = "Entry #{$row->id}: no 'days' row with position={$row->day_of_week}.";
                    continue;
                }

                // 2) Timeslot: only an EXACT start/end match is auto-migrated.
                $timeslot = $timeslots->first(function ($t) use ($row) {
                    return $this->minutesFromTime($t->starts_at) === (int) $row->start_minute
                        && $this->minutesFromTime($t->ends_at) === (int) $row->end_minute;
                });
                if (!$timeslot) {
                    $start = sprintf('%02d:%02d', intdiv($row->start_minute, 60), $row->start_minute % 60);
                    $end = sprintf('%02d:%02d', intdiv($row->end_minute, 60), $row->end_minute % 60);
                    $skipped[] = "Entry #{$row->id} ({$row->module_name} / {$row->group_name}, {$start}-{$end}): "
                        . "no timeslot matches this exact range. Create a matching timeslot or adjust manually, then re-run.";
                    continue;
                }

                // 3) Teacher: match existing teachers.user_id, else teachers.email, else create.
                $teacherKey = $row->professor_id;
                if (!isset($teacherCache[$teacherKey])) {
                    $teacher = DB::table('teachers')->where('user_id', $row->professor_id)->first()
                        ?? DB::table('teachers')->where('email', $row->prof_email)->first();
                    if ($teacher) {
                        $teacherId = $teacher->id;
                        if ($commit && !$teacher->user_id) {
                            DB::table('teachers')->where('id', $teacherId)->update(['user_id' => $row->professor_id]);
                        }
                    } else {
                        $teacherId = $commit ? DB::table('teachers')->insertGetId([
                            'user_id' => $row->professor_id, 'name' => $row->prof_name, 'email' => $row->prof_email,
                            'created_at' => now(), 'updated_at' => now(),
                        ]) : -1; // placeholder id in dry-run
                    }
                    $teacherCache[$teacherKey] = $teacherId;
                }
                $teacherId = $teacherCache[$teacherKey];

                // 4) Section: match by (program_id, name) derived from the semester's program.
                $sectionKey = $row->program_id . '|' . $row->group_name;
                if (!isset($sectionCache[$sectionKey])) {
                    $section = DB::table('sections')->where('program_id', $row->program_id)->where('name', $row->group_name)->first();
                    $sectionCache[$sectionKey] = $section?->id ?? ($commit ? DB::table('sections')->insertGetId([
                        'program_id' => $row->program_id, 'name' => $row->group_name, 'capacity' => $row->student_count,
                        'created_at' => now(), 'updated_at' => now(),
                    ]) : -1);
                }
                $sectionId = $sectionCache[$sectionKey];

                // 5) Subject: match by (semester_id, code) derived from the module.
                $subjectKey = $row->semester_id . '|' . $row->module_code;
                if (!isset($subjectCache[$subjectKey])) {
                    $subject = DB::table('subjects')->where('semester_id', $row->semester_id)->where('code', $row->module_code)->first();
                    $subjectCache[$subjectKey] = $subject?->id ?? ($commit ? DB::table('subjects')->insertGetId([
                        'semester_id' => $row->semester_id, 'teacher_id' => $teacherId,
                        'name' => $row->module_name, 'code' => $row->module_code,
                        'sessions_per_week' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ]) : -1);
                }
                $subjectId = $subjectCache[$subjectKey];

                // 6) Skip if this exact TimetableSession already exists (idempotent re-runs).
                $exists = DB::table('timetable_sessions')
                    ->where(['subject_id' => $subjectId, 'teacher_id' => $teacherId, 'section_id' => $sectionId,
                        'day_id' => $day->id, 'timeslot_id' => $timeslot->id])
                    ->exists();

                if ($exists) {
                    $this->line("Entry #{$row->id}: already migrated, skipping.");
                    continue;
                }

                if ($commit) {
                    DB::table('timetable_sessions')->insert([
                        'subject_id' => $subjectId, 'teacher_id' => $teacherId, 'classroom_id' => $row->classroom_id,
                        'section_id' => $sectionId, 'semester_id' => $row->semester_id,
                        'day_id' => $day->id, 'timeslot_id' => $timeslot->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $created++;
            }

            if (!$commit) {
                // Dry-run: always roll back, even though we only issued SELECTs above.
                DB::rollBack();
            }
        });

        $this->info("{$created} session(s) " . ($commit ? 'migrated.' : 'would be migrated.'));
        if ($skipped) {
            $this->warn(count($skipped) . ' entrie(s) need manual review:');
            foreach ($skipped as $line) {
                $this->line(' - ' . $line);
            }
        }
        return self::SUCCESS;
    }

    private function minutesFromTime(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return $h * 60 + $m;
    }
}
