<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginActivity extends Model
{
    use HasFactory;

    protected $connection = 'project';

    protected $guarded = ['id'];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
