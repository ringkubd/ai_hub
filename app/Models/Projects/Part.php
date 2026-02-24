<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Part extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $connection = 'project';

    protected $guarded = [];


    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function work_details()
    {
        return $this->hasMany(WorkDetails::class)->with('work_history');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
