<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'project';

    protected $guarded = [];


    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function workDetails(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WorkDetails::class);
    }

    public function getTotalAttribute()
    {
        return $this->is_billable != 0 && $this->is_final != 2 ? $this->value : 0;
    }
    public function getNonTenderTotalAttribute()
    {
        return $this->part->non_tender == 1 ? $this->value : 0;
    }

    public function getWithoutNonTenderTotalAttribute()
    {
        return $this->part->non_tender == 0 ? $this->value : 0;
    }

    public function part(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'work_details', 'id');
    }

    public function getExcessAmountAttribute()
    {
        $excess = $this->total  - $this->workDetails->total;
        return max($excess, 0);
    }
    public function getUnaccomplishedAmountAttribute()
    {
        return $this->is_final == 1 && $this->total > $this->workDetails->total ? 0 :  $this->workDetails->total - $this->total;
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the total value in BDT (with currency conversion applied)
     *
     * @return float
     */
    public function getConvertedValueAttribute(): float
    {
        if (!$this->currency_id || !$this->exchange_rate) {
            return $this->value; // No conversion needed
        }

        // Apply exchange rate to convert to BDT
        return $this->value * $this->exchange_rate;
    }

    /**
     * Get converted total with the right formatting
     *
     * @return string
     */
    public function getFormattedValueAttribute(): string
    {
        if (!$this->currency_id || !$this->exchange_rate) {
            return number_format($this->value, 2) . ' BDT'; // Default currency
        }

        $originalValue = $this->value;
        $convertedValue = $this->converted_value;
        $currencySymbol = $this->currency ? $this->currency->symbol : '';
        $currencyCode = $this->currency ? $this->currency->code : '';

        if ($currencyCode === 'BDT' || $this->exchange_rate == 1) {
            return number_format($originalValue, 2) . ' ' . $currencyCode;
        }

        return number_format($originalValue, 2) . ' ' . $currencyCode . ' (' . number_format($convertedValue, 2) . ' BDT)';
    }
}
