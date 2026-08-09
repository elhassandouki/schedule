<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentGroup extends Model {
    protected $guarded = [];
    protected $table = 'student_groups';
    
    public function semester(): BelongsTo {
        return $this->belongsTo(Semester::class);
    }
    
    public function timetableSessions(): HasMany {
        return $this->hasMany(TimetableSession::class, 'student_group_id');
    }
}
