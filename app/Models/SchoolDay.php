<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SchoolDay extends Model { protected $table = 'days'; protected $guarded = []; public function timetableSessions(): HasMany { return $this->hasMany(TimetableSession::class, 'day_id'); } }
