<?php

namespace App\Models;

use App\Models\Concerns\TracksActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes, TracksActivity;

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'default_tax_rate' => 'decimal:2',
        'trial_ends_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function subscription(): HasMany
    {
        return $this->hasMany(StoreSubscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(StoreSubscription::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->whereIn('status', ['active', 'trial'])
        );
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(StoreCapability::class);
    }

    public function isActive(): bool
    {
        if (in_array($this->status, ['suspended', 'expired'], true)) {
            return false;
        }

        if ($this->status === 'trial' && $this->trial_ends_at?->isPast()) {
            return false;
        }

        return in_array($this->status, ['active', 'trial'], true);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function expireTrialIfDue(): void
    {
        if ($this->status !== 'trial' || ! $this->trial_ends_at?->isPast()) {
            return;
        }

        $this->update(['status' => 'expired']);

        StoreSubscription::query()
            ->where('store_id', $this->id)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'expired']);
    }
}
