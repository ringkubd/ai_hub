<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WidgetContent extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = ['id'];


    public function widget(){
        return $this->belongsTo(Widget::class);
    }

    public function author(){
        return $this->belongsTo(User::class, 'author_id');
    }
}
