<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\RecordsActivity;

class Answer extends Model
{
    use HasFactory, RecordsActivity;

    protected $guarded = ['id'];

    protected static function booted()
    {
        static::addGlobalScope("relation", function (Builder $builder) {
            $builder->with('answerSeen', 'creator', 'attachment')
                ->where('answers.tender_package_id', tenderPackage());
        });
        static::creating(function ($answer) {
            $answer->tender_package_id = tenderPackage();
        });
    }

    public function question(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function answerSeen(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'answer_seen');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'answer_team');
    }

    public function attachment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ResponseAttachment::class);
    }
}
