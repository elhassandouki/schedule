<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Section extends Model { protected $guarded = []; public function students(): HasMany { return $this->hasMany(Student::class); } public function timetableSessions(): HasMany { return $this->hasMany(TimetableSession::class); } }
