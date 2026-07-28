<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Semester extends Model { protected $guarded = []; public function program() { return $this->belongsTo(Program::class); } public function modules() { return $this->hasMany(Module::class); } public function sessions() { return $this->hasMany(TeachingSession::class); } public function groups() { return $this->hasMany(StudentGroup::class); } }
