<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenderPackageContent extends Model
{
    use HasFactory;

    protected $connection = 'project';
    protected $table = 'tender_packag_contents';
    protected $guarded = [];


    public function content(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function package(): \Illuminate\Database\Eloquent\Relations\BelongsTo{
        return  $this->belongsTo(TenderPackage::class);
    }
}
