<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuickMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];

    /**
     * @return void
     */

    public function creator(){
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function attachments(){
        return $this->hasMany(QuickMessageAttachment::class);
    }

    public function replays(){
        return $this->hasMany(QuickMessageReplay::class);
    }
}
