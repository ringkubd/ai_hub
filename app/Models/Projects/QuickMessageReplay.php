<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class QuickMessageReplay extends Model
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
        return $this->hasMany(QuickMessageReplayAttachment::class);
    }
}
