<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];



    public function work_details(){
        return $this->belongsTo(WorkDetails::class);
    }

    public function work_history(){
        return $this->belongsTo(WorkHistory::class);
    }

    public function preparedBy(){
        return $this->belongsTo(User::class, 'prepared_by');
    }

}
