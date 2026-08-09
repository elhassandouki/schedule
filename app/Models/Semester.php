<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Semester extends Model { 
    protected $guarded = []; 
    public function program() { return $this->belongsTo(Program::class); } 
    public function modules() { return $this->hasMany(Module::class); } 
    public function studentGroups() { return $this->hasMany(StudentGroup::class); }
    public function timetableSessions() { return $this->hasMany(TimetableSession::class); } 
}
