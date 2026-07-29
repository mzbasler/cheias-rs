<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    protected $guarded = [];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function isStale(): bool
    {
        return $this->measured_at->lt(now()->subHours(Station::STALE_AFTER_HOURS));
    }

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'measured_at' => 'datetime',
        ];
    }
}
