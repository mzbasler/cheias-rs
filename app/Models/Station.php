<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * O filtro de métrica vai dentro do `ofMany`: encadeado por fora, ele só
     * seria aplicado depois de a subconsulta já ter escolhido a linha mais
     * recente — e uma vazão do mesmo instante roubaria o lugar do nível.
     */
    public function latestReading(): HasOne
    {
        return $this->hasOne(Reading::class)->ofMany(
            ['measured_at' => 'max'],
            fn (Builder $query) => $query->where('metric', Reading::METRIC_LEVEL),
        );
    }

    /**
     * Vazão estimada pelo modelo para hoje. Não substitui a medição de nível:
     * é outra grandeza, de outra natureza, e só existe porque cobre as estações
     * cujas leituras ainda não temos.
     */
    public function todayDischarge(): HasOne
    {
        return $this->hasOne(Reading::class)
            ->where('metric', Reading::METRIC_DISCHARGE)
            ->whereDate('measured_at', now()->toDateString());
    }

    public function dischargeForecast(): HasMany
    {
        return $this->hasMany(Reading::class)
            ->where('metric', Reading::METRIC_DISCHARGE)
            ->where('measured_at', '>=', now()->startOfDay())
            ->orderBy('measured_at');
    }

    /**
     * Estado do ponto.
     *
     * Distingue "não temos feed desta estação" de "o feed calou": a primeira é
     * uma entrada de catálogo, a segunda é um sensor mudo durante cheia. Tratar
     * as duas com o mesmo símbolo transforma o mapa em ruído e esconde a que
     * realmente pede atenção.
     */
    public function status(): string
    {
        $reading = $this->latestReading;

        if ($reading === null) {
            // Sem nível medido, mas com vazão modelada: há o que mostrar, desde
            // que rotulado como estimativa e nunca como leitura da estação.
            return $this->todayDischarge === null ? 'unmonitored' : 'modeled';
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

        // Há leitura fresca, mas sem cota publicada não dá para dizer que está normal.
        return $this->alert_level === null && $this->critical_level === null
            ? 'unrated'
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
