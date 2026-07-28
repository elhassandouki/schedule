<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TimetableSession extends Model {
    protected $guarded = [];
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function section(): BelongsTo { return $this->belongsTo(Section::class); }
    public function timeslot(): BelongsTo { return $this->belongsTo(Timeslot::class); }
    public function day(): BelongsTo { return $this->belongsTo(SchoolDay::class, 'day_id'); }
}
