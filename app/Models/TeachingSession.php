<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeachingSession extends Model { protected $guarded = []; public function professor() { return $this->belongsTo(User::class, 'professor_id'); } public function group() { return $this->belongsTo(StudentGroup::class, 'student_group_id'); } public function module() { return $this->belongsTo(Module::class); } }
