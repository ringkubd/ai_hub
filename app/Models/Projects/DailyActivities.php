<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyActivities extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];

    public function equipment(){
        return $this->hasOne(Equipment::class);
    }
    public function attendance(){
        return $this->hasMany(Attendance::class);
    }
    public function safety_security(){
        return $this->hasOne(SaftySecurity::class);
    }
    public function concrete_testing(){
        return $this->hasMany(ConcreteTesting::class);
    }
    public function created_by(){
        return $this->belongsTo(User::class, 'created_by');
    }
    public function added_by(){
        return $this->belongsTo(User::class, 'created_by');
    }
    public function approved_by(){
        return $this->belongsTo(User::class, 'created_by');
    }
}
