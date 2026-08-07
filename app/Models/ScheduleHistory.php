<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleHistory extends Model
{
    protected $table = 'schedule_histories';
    
    protected $fillable = [
        'semester_id',
        'name',
        'status',
        'generated_sessions_count',
        'skipped_sessions_count',
        'generated_by_user_id',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function sessions()
    {
        return $this->hasMany(TimetableSession::class, 'semester_id', 'semester_id')
            ->whereDate('created_at', '>=', $this->created_at)
            ->whereDate('created_at', '<=', $this->updated_at);
    }
}
