<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reference extends Model
{
    use HasFactory;

    protected $connection = 'project';

    protected $guarded = [''];

    public function creator(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function constructor(){
        return $this->belongsTo(Constructor::class);
    }

    public function tenderPackage(){
        return $this->belongsTo(TenderPackage::class);
    }

    public function refType(){
        return $this->belongsTo(ReferenceType::class, 'reference_type_id');
    }
}
