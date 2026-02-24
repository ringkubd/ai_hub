<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];


    public function work_history(){
        return $this->hasMany(InvoiceWorkHistory::class);
    }

    public function bill(){
        return $this->belongsToMany(Bill::class, 'invoice_work_histories')->limit(1);
    }

    public function creator(){
        return $this->belongsTo(User::class);
    }
}
