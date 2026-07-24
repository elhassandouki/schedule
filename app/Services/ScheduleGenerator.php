<?php
namespace App\Services;

use App\Models\Schedule;
use App\Models\TeachingSession;
use Illuminate\Support\Facades\DB;

class ScheduleGenerator
{
    /** Generates a conflict-free weekly timetable. Days are 1-5, slots are 08:00-18:00. */
    public function generate(int $semesterId, string $name): array
    {
        $schedule = Schedule::create(['semester_id' => $semesterId, 'name' => $name, 'status' => 'generating']);
        $sessions = TeachingSession::with(['group', 'professor'])->where('semester_id', $semesterId)->get()
            ->sortByDesc(fn ($s) => $s->group->student_count * $s->occurrences_per_week);
        $rooms = DB::table('classrooms')->orderBy('capacity')->get();
        $placed = [];
        $unplaced = [];

        foreach ($sessions as $session) {
            for ($occurrence = 1; $occurrence <= $session->occurrences_per_week; $occurrence++) {
                $done = false;
                foreach (range(1, 5) as $day) {
                    foreach (range(480, 960, 60) as $start) {
                        $end = $start + $session->duration_minutes;
                        if ($end > 1080 || !$this->professorAvailable($session->professor_id, $day, $start, $end)) continue;
                        foreach ($rooms as $room) {
                            if ($room->capacity < $session->group->student_count || ($room->type !== 'mixte' && $room->type !== $session->type)) continue;
                            if ($this->conflicts($placed, $session, $room->id, $day, $start, $end)) continue;
                            $placed[] = compact('session', 'room', 'day', 'start', 'end', 'occurrence'); $done = true; break 3;
                        }
                    }
                }
                if (!$done) $unplaced[] = ['session_id' => $session->id, 'module' => $session->module->name, 'group' => $session->group->name, 'occurrence' => $occurrence];
            }
        }
        foreach ($placed as $p) DB::table('timetable_entries')->insert(['schedule_id'=>$schedule->id,'teaching_session_id'=>$p['session']->id,'classroom_id'=>$p['room']->id,'day_of_week'=>$p['day'],'start_minute'=>$p['start'],'end_minute'=>$p['end'],'occurrence'=>$p['occurrence'],'created_at'=>now(),'updated_at'=>now()]);
        $schedule->update(['status' => empty($unplaced) ? 'generated' : 'partial']);
        return [$schedule, $unplaced];
    }
    private function professorAvailable($professor, $day, $start, $end): bool { $blocked = DB::table('professor_availabilities')->where('professor_id',$professor)->where('day_of_week',$day)->where('available',false)->where('start_minute','<',$end)->where('end_minute','>',$start)->exists(); return !$blocked; }
    private function conflicts(array $placed, $session, $room, $day, $start, $end): bool { foreach ($placed as $p) { if ($p['day'] !== $day || $p['end'] <= $start || $p['start'] >= $end) continue; if ($p['room']->id === $room || $p['session']->professor_id === $session->professor_id || $p['session']->student_group_id === $session->student_group_id) return true; } return false; }
}
