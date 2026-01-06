<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParcelJourney extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['rider_name', 'rider_mobile'];

    public function notifications(): ParcelJourney|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ParcelJourneyNotification::class);
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Get the rider's real name (cleaned, no prefix)
     */
    public function getRiderNameAttribute()
    {
        // If DB column exists, use it
        if (! empty($this->attributes['rider_name'])) {
            return $this->stripRiderPrefix($this->attributes['rider_name']);
        }

        // Fallback: extract from note
        if (preg_match('/【.*?】sprinter【(.+?) :/u', $this->note, $m)) {
            return $this->stripRiderPrefix(trim($m[1]));
        }

        return null;
    }

    /**
     * Get the rider mobile number
     */
    public function getRiderMobileAttribute()
    {
        if (! empty($this->attributes['rider_mobile'])) {
            return $this->attributes['rider_mobile'];
        }

        if (preg_match('/ :\s*([0-9+]+)】/u', $this->note, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Strip internal prefix like AR_, OCW_, DR_ from a rider name
     */
    private function stripRiderPrefix(string $name): string
    {
        // Regex: match common prefixes followed by underscore
        return preg_replace('/^(AR_|OCW_|DR_)/', '', $name);
    }
}
