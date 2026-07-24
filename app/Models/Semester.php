<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Semester extends Model { protected $guarded = []; public function sessions() { return $this->hasMany(TeachingSession::class); } public function groups() { return $this->hasMany(StudentGroup::class); } }
