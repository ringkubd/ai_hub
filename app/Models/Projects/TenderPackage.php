<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TenderPackage extends Model
{
    use HasFactory;

    protected $connection = 'project';

    protected $guarded = [];

    public function constructor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Constructor::class);
    }

    /**
     * Get the users that belong to the TenderPackage
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tender_package_users');
    }
}
