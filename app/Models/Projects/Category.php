<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = ['id'];

    public function post(){
        return $this->belongsToMany(Post::class, 'post_category');
    }

    public function parentCategory(){
        return $this->hasOne(Category::class, 'id', 'parent_id');
    }

    public function childCategory(){
        return $this->hasMany(Category::class, 'id', 'parent_id');
    }
}
