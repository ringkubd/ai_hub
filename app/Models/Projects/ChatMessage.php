<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{

    protected $connection = 'project';
    protected $fillable = [
        'user_id',
        'type',
        'content',
        'sources',
        'confidence',
        'query_id',
        'tender_package_id',
    ];

    protected $casts = [
        'sources' => 'array',
        'confidence' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to TenderPackage
     */
    public function tenderPackage()
    {
        return $this->belongsTo(TenderPackage::class);
    }

    /**
     * Scope to get chat history for a user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)->orderBy('created_at', 'asc');
    }

    /**
     * Scope to get chat for a specific tender package
     */
    public function scopeForTenderPackage($query, $tenderPackageId)
    {
        return $query->where('tender_package_id', $tenderPackageId);
    }
}
