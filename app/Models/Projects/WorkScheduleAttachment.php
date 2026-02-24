<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkScheduleAttachment extends Model
{
    use HasFactory;

    protected $connection = 'project';
}
