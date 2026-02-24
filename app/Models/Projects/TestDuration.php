<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestDuration extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];

    public function material_test(){
        return $this->belongsTo(MaterialTest::class);
    }

    public function update_by(){
        return $this->belongsTo(User::class, 'updated_by');
    }
}
