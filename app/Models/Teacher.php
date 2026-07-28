<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Teacher extends Model { protected $guarded = []; public function subjects(): HasMany { return $this->hasMany(Subject::class); } public function timetableSessions(): HasMany { return $this->hasMany(TimetableSession::class); } }
