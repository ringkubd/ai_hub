<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = ['id'];


    /**
     * @return void
     */

    public function tenderPackage()
    {
        return $this->belongsTo(TenderPackage::class, 'tender_package_id');
    }

    public function tag()
    {
        return $this->belongsToMany(Tag::class, 'question_tag', 'question_id', 'tag_id');
    }

    public function answer()
    {
        return $this->hasMany(Answer::class, 'question_id')->with('team');
    }

    public function questionSeen()
    {
        return $this->belongsToMany(User::class, 'question_seen');
    }

    public function questionSeenMe()
    {
        return $this->belongsToMany(User::class, 'question_seen')->where('users.id', auth()->user()?->id);
    }


    public function creator()
    {
        return $this->belongsTo(User::class)->with('team');
    }

    public function attachments()
    {
        return $this->belongsToMany(Attachment::class, 'question_attachment');
    }

    public function team()
    {
        return $this->belongsToMany(Team::class, 'question_team')->withPivot(['recommended_by', 'recommended_by_name']);
    }
}
