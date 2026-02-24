<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Widget extends Model
{
    use HasFactory;

    protected $connection = 'project';

    protected $guarded = ['id'];


    public function getStatusAttribute($value){
        return $value ? "Active" : "Draft";
    }

    public function content(){
        return $this->hasMany(WidgetContent::class)->latest();
    }

    public function teams(){
        return $this->belongsToMany(Team::class, 'widget_team');
    }

}
