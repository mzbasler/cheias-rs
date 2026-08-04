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

    /** Janela de histórico que o medidor usa para pico e variação. */
    public const HISTORY_HOURS = 48;

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
     * Janela que alimenta o medidor: define o pico usado como topo da escala e a
     * variação das últimas horas. Em ordem crescente de tempo.
     */
    public function recentReadings(): HasMany
    {
        return $this->hasMany(Reading::class)
            ->where('measured_at', '>=', now()->subHours(self::HISTORY_HOURS))
            ->orderBy('measured_at');
    }

    /**
     * Estado do ponto: 'critical', 'alert', 'normal', 'unclassified' ou 'unknown'.
     *
     * 'unknown' é ausência de dado — sem leitura, ou leitura velha demais para
     * valer. 'unclassified' é dado presente sem cota de referência publicada:
     * a estação mede o rio corretamente, só não há contra o que comparar. Os
     * dois nunca podem virar 'normal' por omissão.
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

        return $alertLevel === null && $this->critical_level === null ? 'unclassified' : 'normal';
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
