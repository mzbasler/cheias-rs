<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    /** Nível do rio em metros, medido por sensor. */
    public const METRIC_LEVEL = 'level';

    /** Vazão em m³/s estimada pelo modelo GloFAS — não é medição. */
    public const METRIC_DISCHARGE = 'discharge';

    protected $guarded = [];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function isStale(): bool
    {
        return $this->measured_at->lt(now()->subHours(Station::STALE_AFTER_HOURS));
    }

    public function unit(): string
    {
        return $this->metric === self::METRIC_DISCHARGE ? 'm³/s' : 'm';
    }

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'measured_at' => 'datetime',
        ];
    }
}
