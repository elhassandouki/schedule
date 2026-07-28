<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Program extends Model { protected $guarded = []; public function department(): BelongsTo { return $this->belongsTo(Department::class); } public function semesters(): HasMany { return $this->hasMany(Semester::class); } public function modules(): HasMany { return $this->hasMany(Module::class); } public function groups(): HasMany { return $this->hasMany(Section::class, 'program_id'); } }
