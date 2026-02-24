<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = ['id'];


    protected function getCreatorNameAttribute(){
        return $this->creator->name;
    }

    protected function getUpdatorNameAttribute(){
        return $this->updator->name;
    }

    protected function getTeamNameAttribute(){
        return $this->team->name;
    }

    public function conversation(){
        return $this->hasMany(WorkScheduleConversation::class);
    }

    public function team(){
        return $this->belongsTo(Team::class, 'concern_team_id');
    }

    public function attachments(){
        return $this->hasMany(WorkScheduleAttachment::class);
    }

    public function creator(){
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updator(){
        return $this->belongsTo(User::class, 'updated_by');
    }
}
