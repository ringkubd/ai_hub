<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkDetailsArchive extends Model
{
    use HasFactory;

    protected $connection = 'project';

    protected $guarded = [];
    /**
     * Get the name of the index associated with the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'work_details';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $array = $this->toArray();
        $array['section'] = $this->section;
        $array['part'] = $this->part;
        $array['bill'] = $this->bill;
        return $array;
    }

    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function makeAllSearchableUsing($query)
    {
        return $query->with('bill', 'section', 'part');
    }

    public function getTotalAttribute()
    {
        $quantity = !is_numeric($this->quantity) ? 1 : (float) $this->quantity;
        return ((float) $this->unit_rate) * $quantity;
    }

    public function getQuantityUnitAttribute()
    {
        $quantity = !is_numeric($this->quantity) ? 1 : (float) $this->quantity;
        return $quantity;
    }

    public function getSlfAttribute()
    {
        return explode('.', $this->sl_no)[0];
    }

    public function getTotalHistoryQuantityAttribute()
    {
        return $this->work_history->sum('quantity');
    }
    public function getTotalDonePriceAttribute()
    {
        return $this->work_history->where('is_billable', 1)->sum('value');
    }

    public function getExcessAmountAttribute()
    {
        $excess = $this->total_done_price - $this->total;
        return $excess > 0 ? $excess : 0;
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function work_history()
    {
        return $this->hasMany(WorkHistory::class, 'work_details_id', 'original_id')->latest();
    }
}
