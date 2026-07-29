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
     * Estado do ponto: 'critical', 'alert', 'normal' ou 'unknown'.
     *
     * 'unknown' cobre tudo que impede afirmar algo sobre o rio — sem leitura,
     * leitura velha, ou estação sem cota publicada. Nenhum desses casos pode
     * ser apresentado como normal.
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

        $alertLevel = $this->alertLevel();

        if ($alertLevel !== null && $reading->value >= $alertLevel) {
            return 'alert';
        }

        return $alertLevel === null && $this->critical_level === null ? 'unknown' : 'normal';
    }

    /**
     * O SGB publica "atenção" abaixo de "alerta"; para o mapa são o mesmo aviso.
     * Vale o limiar mais baixo: avisar antes é mais seguro que avisar depois.
     */
    public function alertLevel(): ?float
    {
        $levels = array_filter(
            [$this->attention_level, $this->alert_level],
            fn (?float $level): bool => $level !== null,
        );

        return $levels === [] ? null : min($levels);
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
