<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Station extends Model
{
    /**
     * Leitura mais velha que isto não representa mais o rio agora. Os sensores
     * do SIGDC reportam a cada ~30 min; 3 h cobre falhas curtas sem mascarar
     * um sensor mudo durante uma cheia.
     */
    public const STALE_AFTER_HOURS = 3;

    protected $guarded = [];

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(Reading::class)->latestOfMany('measured_at');
    }

    /**
     * Estado do ponto: 'critical', 'alert', 'normal' ou 'unknown'.
     *
     * 'unknown' cobre os três casos em que não dá para afirmar nada — sem
     * leitura, leitura velha, ou fonte que não informa as cotas. Nenhum deles
     * pode ser apresentado como "normal".
     */
    public function status(): string
    {
        $reading = $this->latestReading;

        if ($reading === null || $reading->isStale()) {
            return 'unknown';
        }

        if ($this->critical_level !== null && $reading->value >= $this->critical_level) {
            return 'critical';
        }

        if ($this->alert_level !== null && $reading->value >= $this->alert_level) {
            return 'alert';
        }

        return $this->alert_level === null && $this->critical_level === null
            ? 'unknown'
            : 'normal';
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'alert_level' => 'float',
            'critical_level' => 'float',
        ];
    }
}
