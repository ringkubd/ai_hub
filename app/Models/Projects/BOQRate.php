<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BOQRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];

}
