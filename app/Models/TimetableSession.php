<?php
namespace App\Models;
use App\Services\SessionConflictChecker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSession extends Model {
    protected $guarded = [];

    /**
     * Garantie Laravel-only : à chaque création, le modèle refuse toute session
     * dont la salle, le professeur ou le groupe chevauche (horaires réels) une
     * session existante le même jour. Aucun chevauchement possible, y compris
     * entre créneaux qui se chevauchent partiellement (timeslot_id différents).
     */
    protected static function booted(): void
    {
        static::creating(function (self $session) {
            $required = ['module_id', 'semester_id', 'professor_id', 'classroom_id', 'student_group_id', 'day_id', 'timeslot_id'];
            foreach ($required as $field) {
                if (blank($session->{$field})) return;
            }
            // Le générateur et le formulaire créent leurs sessions après contrôle ;
            // ce hook est la dernière ligne de défense (inserts massés, commandes,
            // tinker...) : il lève une exception si un chevauchement réel existe.
            $checker = app(SessionConflictChecker::class);
            $checker->validate($session->only($required));
        });
    }
    
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
