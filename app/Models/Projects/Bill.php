<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Bill extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $connection = 'project';
    
    protected $fillable = ['bill_no', 'title', 'description', 'tender_package_id'];


    public function section(){
        return $this->hasMany(Section::class);
    }

    public function part(){
        return $this->hasMany(Part::class);
    }

    public function workDetails(){
        return $this->hasMany(WorkDetails::class);
    }
}
