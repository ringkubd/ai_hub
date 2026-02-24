<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class WorkDetails extends Model
{
    use HasFactory, SoftDeletes, Searchable;

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
     * Modify the query used to retrieve models when making all the models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function makeAllSearchableUsing($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->with('bill', 'section', 'part');
    }

    public function getTotalOldAttribute(){
        $quantity = !is_numeric($this->quantity) ? 1 : (double) $this->quantity;
        return ((double) $this->unit_rate) * $quantity;
    }

    public function getQuantityUnitAttribute(){
        $quantity = !is_numeric($this->quantity) ? 1 : (double) $this->quantity;
        return $quantity;
    }

    public function getSlfAttribute(){
        return explode('.', $this->sl_no)[0];
    }

    public function getTotalHistoryQuantityAttribute(){
        return $this->work_history->where('is_final','!=',2)->sum('quantity');
    }
    public function getTotalDonePriceAttribute(){
        return $this->work_history->where('is_billable', 1)->where('is_final','!=',2)->sum('value');
    }


    public function getExcessAmountAttribute1(){
        $excess = $this->total_done_price - $this->total;
        return $excess > 0 ? $excess : 0;
    }
    public function getUnaccomplishedAmountAttribute(){
        $unaccomplished = $this->total - $this->total_done_price;
        return $unaccomplished > 0 ? $unaccomplished : 0;
    }

    public function bill()                                              {
        return $this->belongsTo(Bill::class);
    }

    public function section(){
        return $this->belongsTo(Section::class);
    }

    public function part(){
        return $this->belongsTo(Part::class);
    }

    public function work_history(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkHistory::class)->latest();
    }
    public function work_history2(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkHistory::class)->latest();
    }

    public function rates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BOQRate::class);
    }

    public function invoiceWorkHistory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceWorkHistory::class);
    }

    public function getRateAttribute(){
        $today = today()->toDateString();
        return $this->rates
            ->where('start_date', '<=', "$today")
            ->where(function ($q) use ($today){
                $q->where('end_date', '>=', "$today")->orWhereNull('end_date');
            })
            ->first()?->uni_rate;
    }
}
