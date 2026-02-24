<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialTest extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];

    public function test_duration(){
        return $this->hasMany(TestDuration::class);
    }

    public function created_by(){
        return $this->belongsTo(User::class, 'created_by');
    }
}
