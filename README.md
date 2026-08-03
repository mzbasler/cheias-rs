# Cheias RS

Sistema de monitoramento de nível de rios e alerta de cheias no Rio Grande do Sul.

> ⚠️ **Em migração.** O protótipo estático anterior (`index.html`, cobrindo os 4 pontos de
> medição de Eldorado do Sul via SIGDC) foi descontinuado nesta branch. O projeto está
> sendo reconstruído como aplicação Laravel — nome do app: **`cheias-rs`** — com escopo
> ampliado para o estado inteiro.

## O que é um ponto no mapa

Cada ponto é uma **estação fluviométrica** — mede o rio, não a chuva. É um lugar na
margem onde existe uma régua linimétrica e, na maioria dos casos, um sensor que
transmite a leitura por telemetria.

O que a estação mede é a **cota**: a altura da lâmina d'água em metros a partir do zero
da régua, um referencial local arbitrário fixado na instalação. Cota de estações
diferentes não se compara — 4,20 m em Encantado e 4,20 m em Montenegro não dizem a mesma
coisa. O que dá sentido ao valor são as **cotas de referência** publicadas pela fonte
(atenção, alerta e inundação); é a comparação com elas que define a cor do ponto.

Estação sem leitura, com leitura mais velha que 3 h, ou sem cota publicada aparece como
_sem leitura_ — nunca como normal. Sensor mudo durante uma cheia não pode parecer rio
calmo.

## Fontes de dados

Retrato do banco em 2 de agosto de 2026 — 280 estações:

| Fonte | Estações | Papel |
| --- | ---: | --- |
| SNIRH / Hidroweb (ANA) | 238 | Inventário: catálogo das estações de rio, telemétricas e em operação no RS. Entra como localização e nome, sem leitura. |
| SACE (SGB, antigo CPRM) | 38 | Sistema de Alerta de Eventos Críticos. De onde vem a maior parte das leituras e das cotas de referência. Não publica API: é raspagem da página do mapa de níveis e de um CSV por estação. |
| SIGDC (Defesa Civil RS) | 4 | Pontos de monitoramento com leitura. |

Só as estações com leitura registrada vão ao mapa — 55 na data acima. As demais ficam de
fora: estação catalogada sem medição viraria ruído sobre as que informam.

O SACE mede as mesmas estações que o inventário da ANA já catalogou; quando o código
coincide, a leitura entra na estação existente em vez de criar uma duplicata a poucos
metros dela no mapa.

## Stack

Laravel 13 · PHP 8.3 · SQLite (desenvolvimento). O planejamento interno — catálogo de
fontes de dados (ANA, SGB/SACE, CEMADEN, SEMA, INMET, Open-Meteo, SIGDC) e plano de
migração — fica em `docs/`, fora do versionamento.

## Rodar localmente

```sh
composer install
npm install
composer run dev
```

## Licença

Domínio público (CC0). Os dados pertencem aos respectivos órgãos públicos (ANA, SGB/CPRM,
Defesa Civil RS, INMET, SEMA, entre outros).
