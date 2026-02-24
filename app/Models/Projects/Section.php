<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Section extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $connection = 'project';

    protected $fillable = ['tender_package_id', 'bill_id', 'sl', 'title', 'description'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'currency_totals' => 'array',
    ];

    /**
     * Boot the model.
     *
     * @return void
     */

    /**
     * Get the parts for the section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function part()
    {
        return $this->hasMany(Part::class);
    }

    /**
     * Get the work details for the section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function work_details()
    {
        return $this->hasMany(WorkDetails::class);
    }
    /**
     * Get the work details for the section without nontender.
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function work_detailsWithoutNontender(): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkDetails::class)->whereHas('part', function ($q) {
            $q->where('non_tender', '!=', 1);
        });
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function work_history()
    {
        return $this->hasManyThrough(WorkHistory::class, WorkDetails::class);
    }
    public function work_history_non_tender()
    {
        return $this->hasManyThrough(WorkHistory::class, WorkDetails::class)->whereHas('workDetails.part', function ($q) {
            $q->where('non_tender', 1);
        });
    }

    public function section_view(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SectionView::class);
    }

    public function getExcessAmountAttribute() {}
}
