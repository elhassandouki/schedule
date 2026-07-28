<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Module extends Model {
    protected $guarded = [];
    public function program() { return $this->belongsTo(Program::class); }
    public function semester() { return $this->belongsTo(Semester::class); }
    public function professors() { return $this->belongsToMany(User::class, 'professor_module', 'module_id', 'professor_id')->withTimestamps(); }
}
