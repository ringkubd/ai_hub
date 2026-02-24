<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceWorkHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];


    public function bill(){
        return $this->belongsTo(Bill::class);
    }

    public function section(){
        return $this->belongsTo(Section::class);
    }

    public function part(){
        return $this->belongsTo(Part::class);
    }

    public function work_details(){
        return $this->belongsTo(WorkDetails::class);
    }

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }
}
