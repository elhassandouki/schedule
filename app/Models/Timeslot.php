<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Timeslot extends Model { protected $guarded = []; public function timetableSessions(): HasMany { return $this->hasMany(TimetableSession::class); } }
