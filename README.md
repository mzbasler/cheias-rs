# Cheias RS

Sistema de monitoramento de nível de rios e alerta de cheias no Rio Grande do Sul.

**Em produção:** https://nivel-rios-eldorado-production.up.railway.app

## O que é um ponto no mapa

Cada ponto é uma **estação fluviométrica** — mede o rio, não a chuva. É um lugar na
margem onde existe uma régua linimétrica e, na maioria dos casos, um sensor que
transmite a leitura por telemetria.

O que a estação mede é a **cota**: a altura da lâmina d'água em metros a partir do zero
da régua, um referencial local arbitrário fixado na instalação. Cota de estações
diferentes não se compara — 4,20 m em Encantado e 4,20 m em Montenegro não dizem a mesma
coisa. O que dá sentido ao valor são as **cotas de referência** publicadas pela fonte
(atenção, alerta e inundação); é a comparação com elas que define a cor do ponto.

O status de uma estação é sempre um destes cinco:

| Status | Cor do ponto | Significa |
| --- | --- | --- |
| Inundação | vermelho | leitura ≥ cota de inundação |
| Alerta | amarelo | leitura ≥ cota de alerta |
| Normal | verde | leitura fresca, abaixo da cota de alerta |
| Sem cota de referência | azul, sem preenchimento | leitura fresca, mas nenhuma fonte publicou cota de atenção/alerta pra essa estação — mede certo, não dá pra classificar risco |
| Leitura desatualizada | cinza | sem medição há mais de 24 h — sensor mudo, nunca a mesma coisa que "rio normal" |

O ponto também traz uma seta mostrando se o nível subiu, desceu ou ficou estável nas
últimas 24 h. Por padrão, o mapa mostra só **Alerta** e **Inundação** — é um sistema de
alerta, não um inventário; os outros três status ficam ocultos, reexibíveis clicando na
legenda ou pela lista de estações.

Relato de morador (foto + localização, pelo botão de câmera no mapa) e câmera de
projeto independente (vídeo ao vivo de terceiro — hoje as do
[Nível do Rio](https://niveldorio.com) e [Nível Guaíba](https://nivelguaiba.com.br), no
Vale do Paranhana e nas bacias do Taquari e do Caí) são camadas completamente
separadas da telemetria oficial — marcador próprio pra cada uma, liga/desliga direto
pela legenda, nunca entram na mesma lista nem na mesma cor dos
status de estação.

## Fontes de dados

Retrato do banco em 4 de agosto de 2026 — 295 estações:

| Fonte | Estações | Papel |
| --- | ---: | --- |
| SNIRH / Hidroweb (ANA) | 238 | Inventário (`import:snirh`): catálogo das estações de rio, telemétricas e em operação no RS. Entra como localização e nome, sem leitura. |
| ANA — Hidroweb Service (`import:ana`) | — | Leitura oficial (`import:ana`): API autenticada da própria ANA, mede a cota das estações que ela já catalogou. Substitui a raspagem do SACE para quem tem código no inventário. |
| SACE (SGB, antigo CPRM) | 31 | Cobre só quem a ANA não cataloga (código próprio do SACE, sem correspondência no inventário) — e é uma das fontes das cotas de referência (atenção/alerta/inundação), que a API da ANA não publica. Não tem API própria: é raspagem da página do mapa de níveis e de um CSV por estação. Cobre só 4 bacias do RS (Taquari, Uruguai, Guaíba, Caí). |
| CEMADEN (`import:cemaden`) | 15 | Outra fonte de cota de referência — publica atenção/alerta/transbordamento por estação hidrológica via JSON público, cobrindo bacias fora do alcance do SACE. Catálogo só: a API não confirma o offset do sensor no nível bruto que devolve, então essas estações ainda não têm leitura. |
| SIGDC (Defesa Civil RS) | 4 | Pontos de monitoramento com leitura. |

Só as estações com leitura registrada vão ao mapa — a maioria das 238 do inventário, mais
as 31 do SACE e as 4 do SIGDC. As demais ficam de fora, incluindo as 15 do CEMADEN por
enquanto: estação catalogada sem medição viraria ruído sobre as que informam.

`import:ana` e o SACE medem, em parte, as mesmas estações que o inventário da ANA já
catalogou; quando o código coincide, a leitura entra na estação existente em vez de criar
uma duplicata a poucos metros dela no mapa — por isso a raspagem do SACE hoje só processa
quem não tem esse código.

Cota de referência (atenção/alerta/inundação) é definição da Defesa Civil **municipal**,
não federal nem estadual — por isso a cobertura é parcial e fragmentada: só existe onde
alguma prefeitura já fez e publicou esse levantamento. Não há hoje uma base pública única
e sistemática pra todas as ~280 estações catalogadas no RS.

Toda a ingestão roda sozinha em produção via `php artisan schedule:run`, disparado a cada
5 minutos por um serviço de cron dedicado no Railway (`routes/console.php` define a
cadência real de cada fonte — 15 min pra leitura, semanal pra catálogo).

## Painel de administração

Login em `/admin/login`. Cobre:

- **Estações** — editar nome, cotas de referência e coordenadas, inclusive as sem leitura.
- **Relatos de moradores** — aprovar/rejeitar, com a foto em tamanho maior antes e depois
  de decidir.
- **Saúde da ingestão** — quantas estações de cada fonte estão com leitura fresca ou
  atrasada.
- **Configurações** — chave Pix da doação, editável sem precisar de redeploy.

Usuário criado via `php artisan admin:create-user {name} {email} {password}` — sem
autorregistro público.

A lista de câmeras (tabela `cameras`) ainda não tem tela própria no painel — é curada à
mão via `php artisan tinker`, porque não existe nenhuma fonte que catalogue isso
automaticamente.

## Stack

Laravel 13 · PHP 8.3 · SQLite (desenvolvimento) · PostgreSQL (produção, Railway). Vanilla
JS + Leaflet no mapa, sem framework de frontend. O planejamento interno — catálogo de
fontes de dados e plano de migração — fica em `docs/`, fora do versionamento.

## Rodar localmente

```sh
composer install
npm install
composer run dev
```

## Licença

Domínio público (CC0). Os dados pertencem aos respectivos órgãos públicos (ANA, SGB/CPRM,
Defesa Civil RS, CEMADEN, entre outros).
