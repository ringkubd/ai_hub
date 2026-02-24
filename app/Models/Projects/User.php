<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $connection = 'project';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];


    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function team()
    {
        return $this->hasOne(Team::class, 'id', 'team_id');
    }

    public function authorizedRepresentative()
    {
        return $this->hasOne(Team::class, 'authorized_representative');
    }

    /**
     * Get the activity timeline for the user.
     *
     * @return mixed
     */
    public function activity()
    {
        return $this->hasMany(Activity::class)
            ->with(['user', 'subject'])
            ->latest();
    }

    public function question()
    {
        $this->belongsToMany(Question::class, 'question_team');
    }

    public function tenderPackage(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(TenderPackage::class, 'tender_package_users');
    }

    /**
     * Get the user's chat messages
     */
    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function hasPackage($package): bool
    {
        if ($package instanceof TenderPackage) {
            return $package->users->contains($this->id);
        }
        if (is_string($package)) {
            return $this->tenderPackage()->where('tender_packages.name', $package)->count();
        }
        if (is_array($package)) {
            return $this->tenderPackage()->whereIn('tender_packages.name', $package)->count();
        }
        if (is_int($package)) {
            return $this->tenderPackage()->where('tender_packages.id', $package)->count();
        }
        return false;
    }
}
