# Monitoramento dos Rios — Eldorado do Sul

Página única, estática e sem dependências, para acompanhar o nível dos rios nos
**4 pontos de medição** da Defesa Civil de Eldorado do Sul/RS: Bairro Picada
(Condomínio Pier Jacuy), Balneário Sans Souci, Prainha do Itaí e IRGA.

Feita para moradores da região da Ilha e do Bairro Picada, com foco em celular.

> ## ⚠️ Página não oficial
>
> Este site **não é operado pela Prefeitura nem pela Defesa Civil de Eldorado do
> Sul**. Ele apenas reapresenta, de forma simplificada, dados públicos do SIGDC.
> Pode ficar desatualizado ou fora do ar sem aviso.
>
> **Em qualquer emergência, o que vale é a orientação oficial da Defesa Civil:**
> (51) 98595-1493 · Bombeiros 193 · <https://unigov.com.br/defesacivil/portal>

## Fonte dos dados

Consome, direto do navegador, o endpoint público de monitoramento do
**SIGDC — Sistema Integrado de Gestão da Defesa Civil**:

```
GET https://unigov.com.br/defesacivil/api/monitoring/points
```

Responde `Access-Control-Allow-Origin: *`, sem chave. Os 4 pontos de nível são
os de `metricClass: "water_level"`. Não há back-end nem banco: a página busca a
cada 5 minutos e guarda a última resposta no `localStorage` para continuar
legível sem internet.

**O endpoint não é documentado publicamente** — foi identificado a partir do
bundle do portal. Pode mudar ou ser fechado sem aviso, e nesse caso a página
passa a mostrar o estado de erro.

## O que vem da API e o que não vem

Vem da API, sem digitação manual: leitura atual, cotas de alerta e crítica,
histórico de 48 h, tendência, horário da última leitura, unidade, código do
aparelho, descrição do local e coordenadas.

Não vem da API, são decisões desta página:

- **Nome curto** de cada ponto no cabeçalho do card (versão abreviada e
  acentuada da descrição do local).
- **Escala do medidor** — a API informa as cotas, mas não o fundo nem o topo do
  rio. Usa-se 20 % acima da maior entre cota crítica, leitura atual e pico de 48 h.
- **Corte de 3 h** para considerar uma leitura velha e marcar o ponto como
  "sem dados". A cadência real dos sensores é de ~30 min.
- **Textos de orientação**, redigidos a partir da Central de Alertas do SIGDC.
- **Telefones**, obtidos de `/system-config` e fixados no HTML.

## Decisões de acessibilidade

- **Mobile first**: o CSS base é o layout de celular; o que cresce está em
  `@media (min-width:)`.
- **A cor nunca decide sozinha.** Cada estado carrega também forma de ícone
  própria (círculo com tique · triângulo · octógono · círculo tracejado), texto
  escrito e espessura de borda. Em impressão P&B e em `forced-colors`, hachuras
  substituem as cores.
- Contrastes conferidos contra a superfície: texto 19,17:1 · branco sobre
  crítico 4,80:1 · tinta sobre âmbar 10,73:1 · água 4,30:1. As linhas de cota
  levam halo da cor de superfície porque, sobre a água azul, vermelho mede
  1,09:1 — seria invisível.
- O gráfico de 24 h é **linha, não barra**: a variação é de poucos centímetros e
  barra com base truncada mentiria sobre a proporção. A faixa vertical usada vem
  declarada em texto sob o gráfico.
- Dado velho nunca é apresentado como se fosse ao vivo: o cache offline aparece
  sempre carimbado com a hora da medição.

## Rodar localmente

É um arquivo só. Abrir `index.html` direto costuma falhar no `fetch` por
restrição do navegador a `file://` — sirva por HTTP:

```sh
npx serve .
```

## Licença

Domínio público (CC0). Os dados pertencem à Defesa Civil de Eldorado do Sul.
