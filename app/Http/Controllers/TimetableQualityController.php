<?php

namespace App\Http\Controllers;

use App\Services\TimetableQualityAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetableQualityController extends Controller
{
    public function show(Request $request, int $semesterId)
    {
        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) {
            abort(404, 'Semester not found');
        }
        
        // Authorization
        $user = $request->user();
        if ($user->role === 'prof') {
            // Prof can view quality report but only for semesters where they teach
            $hasAccess = DB::table('timetable_sessions')
                ->join('subjects', 'subjects.id', '=', 'timetable_sessions.subject_id')
                ->where('timetable_sessions.semester_id', $semesterId)
                ->where('subjects.teacher_id', DB::table('teachers')->where('user_id', $user->id)->value('id'))
                ->exists();
            
            abort_if(!$hasAccess, 403, 'Not authorized to view this quality report');
        } elseif ($user->role === 'chef_departement') {
            // Chef can view semesters in their department
            $hasAccess = DB::table('semesters')
                ->join('programs', 'programs.id', '=', 'semesters.program_id')
                ->where('semesters.id', $semesterId)
                ->where('programs.department_id', $user->department_id)
                ->exists();
            
            abort_if(!$hasAccess, 403, 'Not authorized to view this quality report');
        } elseif ($user->role === 'chef_filiere') {
            // Chef filière can view their programs
            $hasAccess = DB::table('semesters')
                ->where('id', $semesterId)
                ->where('program_id', $user->program_id)
                ->exists();
            
            abort_if(!$hasAccess, 403, 'Not authorized to view this quality report');
        }
        // Super admin and sous admin can view everything
        
        // Get quality report
        $analyzer = new TimetableQualityAnalyzer();
        $quality = $analyzer->analyze($semesterId);
        
        // Filter sensitive data if user is prof
        if ($user->role === 'prof') {
            $teacherId = DB::table('teachers')->where('user_id', $user->id)->value('id');
            
            // Filter workload: only show own workload
            $quality['workload']['teachers'] = collect($quality['workload']['teachers'])
                ->filter(fn($t) => $t['teacher_id'] === $teacherId)
                ->values()
                ->toArray();
            
            // Filter student groups: only show groups where prof teaches
            $quality['workload']['student_groups'] = collect($quality['workload']['student_groups'] ?? [])
                ->filter(function ($s) use ($semesterId, $teacherId) {
                    return DB::table('timetable_sessions')
                        ->where('student_group_id', $s['student_group_id'])
                        ->where('semester_id', $semesterId)
                        ->where('teacher_id', $teacherId)
                        ->exists();
                })
                ->values()
                ->toArray();
            
            // Filter warnings: only show warnings relevant to prof
            $quality['soft_warnings'] = collect($quality['soft_warnings'])
                ->filter(function ($w) use ($teacherId) {
                    if ($w['type'] === 'teacher_overload' || $w['type'] === 'long_consecutive') {
                        return isset($w['teacher']) && DB::table('teachers')
                            ->where('id', $teacherId)
                            ->where('name', $w['teacher'])
                            ->exists();
                    }
                    return true;
                })
                ->values()
                ->toArray();
        }
        
        $program = DB::table('programs')->find($semester->program_id);
        
        return view('timetable.quality', [
            'semester' => $semester,
            'program' => $program,
            'quality' => $quality,
            'user' => $user,
        ]);
    }
    
    /**
     * API endpoint for dashboard widget
     */
    public function summary(Request $request, int $semesterId)
    {
        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) {
            return response()->json(['error' => 'Semester not found'], 404);
        }
        
        $user = $request->user();
        
        // Check authorization (same logic as show)
        if ($user->role === 'prof') {
            $hasAccess = DB::table('timetable_sessions')
                ->join('subjects', 'subjects.id', '=', 'timetable_sessions.subject_id')
                ->where('timetable_sessions.semester_id', $semesterId)
                ->where('subjects.teacher_id', DB::table('teachers')->where('user_id', $user->id)->value('id'))
                ->exists();
            
            if (!$hasAccess) {
                return response()->json(['error' => 'Not authorized'], 403);
            }
        }
        
        $analyzer = new TimetableQualityAnalyzer();
        $quality = $analyzer->analyze($semesterId);
        
        return response()->json([
            'quality_score' => $quality['quality_score'],
            'quality_rating' => $quality['quality_rating'],
            'generated_sessions' => $quality['generated_sessions'],
            'required_sessions' => $quality['required_sessions'],
            'skipped_sessions' => $quality['skipped_sessions'],
            'conflict_count' => $quality['conflict_count'],
            'warning_count' => $quality['warning_count'],
        ]);
    }
}
