<?php

namespace App\Services;

use App\Models\{Classroom, SchoolDay, Semester, Timeslot, TimetableSession};
use Illuminate\Support\Facades\DB;

class SemesterTimetableGenerator
{
    /** @return array{created:int,unplaced:array<int, array{subject:string,group:string,reason:string}>} */
    public function generate(int $semesterId, bool $reset = false): array
    {
        $semester = Semester::with(['program.groups', 'subjects.teacher'])->findOrFail($semesterId);
        $days = SchoolDay::orderBy('position')->get();
        $slots = Timeslot::orderBy('position')->get();
        $rooms = Classroom::orderBy('capacity')->get();
        $created = 0; $unplaced = [];

        DB::transaction(function () use ($semester, $days, $slots, $rooms, $reset, &$created, &$unplaced) {
            if ($reset) TimetableSession::where('semester_id', $semester->id)->delete();
            $placed = TimetableSession::with('timeslot')->where('semester_id', $semester->id)->get()->all();

            foreach ($semester->program->groups as $group) {
                foreach ($semester->subjects as $subject) {
                    $alreadyScheduled = collect($placed)->where('section_id', $group->id)->where('subject_id', $subject->id)->count();
                    for ($occurrence = $alreadyScheduled; $occurrence < $subject->sessions_per_week; $occurrence++) {
                        $candidate = $this->findPlacement($placed, $group, $subject, $days, $slots, $rooms);
                        if (!$candidate) {
                            $unplaced[] = ['subject'=>$subject->name, 'group'=>$group->name, 'reason'=>'No conflict-free day, timeslot, and classroom were available.'];
                            continue;
                        }
                        $session = TimetableSession::create([
                            'semester_id'=>$semester->id, 'subject_id'=>$subject->id, 'teacher_id'=>$subject->teacher_id,
                            'section_id'=>$group->id, 'classroom_id'=>$candidate['room']->id,
                            'day_id'=>$candidate['day']->id, 'timeslot_id'=>$candidate['slot']->id,
                        ]);
                        $session->setRelation('timeslot', $candidate['slot']);
                        $placed[] = $session; $created++;
                    }
                }
            }
        });

        return compact('created', 'unplaced');
    }

    private function findPlacement(array $placed, $group, $subject, $days, $slots, $rooms): ?array
    {
        $candidates = [];
        foreach ($days as $day) foreach ($slots as $slot) {
            foreach ($rooms as $room) {
                if ($room->capacity < max(1, $group->capacity)) continue;
                if ($this->conflicts($placed, $subject->teacher_id, $group->id, $room->id, $day->id, $slot->id)) continue;
                $candidates[] = ['day'=>$day, 'slot'=>$slot, 'room'=>$room, 'score'=>$this->gapScore($placed, $group->id, $day->id, $slot->position)];
                break; // smallest suitable available room is sufficient for this slot
            }
        }
        if (!$candidates) return null;
        usort($candidates, fn ($a, $b) => [$a['score'], $a['day']->position, $a['slot']->position, $a['room']->capacity] <=> [$b['score'], $b['day']->position, $b['slot']->position, $b['room']->capacity]);
        return $candidates[0];
    }

    private function conflicts(array $placed, int $teacherId, int $groupId, int $roomId, int $dayId, int $slotId): bool
    {
        foreach ($placed as $session) if ($session->day_id === $dayId && $session->timeslot_id === $slotId && ($session->teacher_id === $teacherId || $session->section_id === $groupId || $session->classroom_id === $roomId)) return true;
        return false;
    }

    /** Prefer adjacent classes for a group, then days it is already attending. */
    private function gapScore(array $placed, int $groupId, int $dayId, int $position): int
    {
        $positions = collect($placed)->filter(fn ($s) => $s->section_id === $groupId && $s->day_id === $dayId)->map(fn ($s) => $s->timeslot->position)->all();
        if (!$positions) return 10;
        if (collect($positions)->contains(fn ($existing) => abs($existing - $position) === 1)) return 0;
        return 20 + min(array_map(fn ($existing) => abs($existing - $position), $positions));
    }
}
