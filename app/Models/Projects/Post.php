<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = ['id'];

    /**
     * @return void
     */

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */

    public function categories(){
        return $this->belongsToMany(Category::class, 'post_category')->whereNull('parent_id')->with('childCategory');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */

    public function author(){
        return $this->hasOne(User::class, 'id', 'author_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags(){
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function metas(){
        return $this->hasMany(PostMeta::class);
    }

}
