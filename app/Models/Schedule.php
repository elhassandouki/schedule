<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Schedule extends Model { protected $guarded = []; public function entries() { return $this->hasMany(TimetableEntry::class); } }
