<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamContact extends Model
{
    use HasFactory;

    protected $connection = 'project';

    protected $guarded = ['id'];
}
