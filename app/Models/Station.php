<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Station extends Model
{
    /**
     * Leitura mais velha que isto não representa mais o rio agora. Os sensores
     * reportam a cada 15–30 min; 3 h cobre falhas curtas sem mascarar um sensor
     * mudo durante uma cheia.
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
     * Estado do ponto.
     *
     * Distingue "não temos leitura desta estação" de "a transmissão parou": a
     * primeira é entrada de catálogo, a segunda é sensor mudo durante cheia.
     * Tratar as duas com o mesmo símbolo esconde a que pede atenção.
     */
    public function status(): string
    {
        $reading = $this->latestReading;

        if ($reading === null) {
            return 'unmonitored';
        }

        if ($reading->isStale()) {
            return 'stale';
        }

        if ($this->critical_level !== null && $reading->value >= $this->critical_level) {
            return 'critical';
        }

        if ($this->alert_level !== null && $reading->value >= $this->alert_level) {
            return 'alert';
        }

        if ($this->attention_level !== null && $reading->value >= $this->attention_level) {
            return 'attention';
        }

        // Há leitura fresca, mas sem cota publicada não dá para dizer que está normal.
        $hasReferenceLevel = $this->attention_level !== null
            || $this->alert_level !== null
            || $this->critical_level !== null;

        return $hasReferenceLevel ? 'normal' : 'unrated';
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'attention_level' => 'float',
            'alert_level' => 'float',
            'critical_level' => 'float',
        ];
    }
}
