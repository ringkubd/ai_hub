<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasFactory;

    protected $connection = 'project';

    protected $guarded = ['id'];

    /**
     * @return void
     */

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function authorizedRepresentative(){
        return $this->hasOne(User::class, 'id', 'authorized_representative');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function contact(){
        return $this->hasMany(TeamContact::class, 'team_id', 'id')->orderBy('is_mobile');
    }

    public function children(){
        return $this->hasMany(Team::class, 'id', 'parent_id');
    }

    public function parent(){
        return $this->hasOne(Team::class, 'parent_id');
    }

    public function member(){
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsToMany
     */
    public function widgets(): BelongsToMany
    {
        return $this->belongsToMany(Widget::class, 'widget_team');
    }

    /**
     * @return BelongsToMany
     */
    public function package(): BelongsToMany
    {
        return $this->belongsToMany(TenderPackage::class, 'tender_package_teams');
    }

    public function tender(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(TenderPackage::class, TenderPackageContent::class);
    }

    public function tenderPackage(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(TenderPackage::class, 'tenderable','tender_packag_contents');
    }

}
