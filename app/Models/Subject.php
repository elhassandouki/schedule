<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model { 
    protected $guarded = []; 
    
    public function teacher() { 
        return $this->belongsTo(Teacher::class); 
    } 
    
    public function timetableSessions(): HasMany { 
        return $this->hasMany(TimetableSession::class); 
    } 
}
