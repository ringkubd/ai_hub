<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $connection = 'project';

    protected $guarded = [];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
