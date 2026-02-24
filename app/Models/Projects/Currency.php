<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $fillable = ['name', 'code', 'symbol', 'exchange_rate', 'is_default'];

    public function parts()
    {
        return $this->hasMany(Part::class);
    }
}
