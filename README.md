# Cheias RS

Sistema de monitoramento de nível de rios e alerta de cheias no Rio Grande do Sul.

> ⚠️ **Em migração.** O protótipo estático anterior (`index.html`, cobrindo os 4 pontos de
> medição de Eldorado do Sul via SIGDC) foi descontinuado nesta branch. O projeto está
> sendo reconstruído como aplicação Laravel — nome do app: **`cheias-rs`** — com escopo
> ampliado para o estado inteiro.

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
