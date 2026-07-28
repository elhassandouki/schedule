<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudentGroup extends Model { protected $guarded = []; public function semester() { return $this->belongsTo(Semester::class); } }
