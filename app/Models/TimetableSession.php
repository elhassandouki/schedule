<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSession extends Model {
    protected $guarded = [];
    
    public function module(): BelongsTo {
        return $this->belongsTo(Module::class);
    }

    // Legacy relations are retained only so historic timetable rows can still
    // be read during the transition; new sessions use module/professor.
    public function subject(): BelongsTo {
        return $this->belongsTo(Subject::class);
    }
    
    public function semester(): BelongsTo { 
        return $this->belongsTo(Semester::class); 
    }
    
    public function professor(): BelongsTo {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function teacher(): BelongsTo {
        return $this->belongsTo(Teacher::class);
    }
    
    public function classroom(): BelongsTo { 
        return $this->belongsTo(Classroom::class); 
    }
    
    public function studentGroup(): BelongsTo { 
        return $this->belongsTo(StudentGroup::class, 'student_group_id'); 
    }
    
    public function timeslot(): BelongsTo { 
        return $this->belongsTo(Timeslot::class); 
    }
    
    public function day(): BelongsTo { 
        return $this->belongsTo(SchoolDay::class, 'day_id'); 
    }
}
