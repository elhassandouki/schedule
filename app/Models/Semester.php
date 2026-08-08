<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Semester extends Model { 
    protected $guarded = []; 
    public function program() { return $this->belongsTo(Program::class); } 
    public function modules() { return $this->hasMany(Module::class); } 
    public function subjects() { return $this->hasMany(Subject::class); } 
    public function timetableSessions() { return $this->hasMany(TimetableSession::class); } 
}
