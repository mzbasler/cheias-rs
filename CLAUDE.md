# cheias-rs

Monitoramento de nível de rios e alerta de cheias no RS. Laravel 13, PHP 8.3.

O planejamento (fontes de dados, fases de migração) fica em `docs/` — pasta local,
fora do versionamento.

## Minimalismo

Regras decidíveis: dá para olhar o diff e dizer se foi violada.

- **Nenhuma abstração antes do segundo caso de uso real.** Interface com uma única
  implementação é ruído. Generalizar por antecipação é dívida, não previdência.
- **Sem camada cerimonial:** nada de Repository por cima do Eloquent, DTO onde uma
  Collection resolve, ou Service que só repassa uma chamada.
- **Arquivo novo se justifica.** Se o método cabe no Model ou no Command, não nasce
  uma classe para ele.
- **`config/` é para o que varia entre ambientes.** Constante de domínio mora no código.
- **Código morto se apaga.** Não se comenta "para depois" — o histórico do git guarda.
- **Comentário explica _por quê_.** Se explica _o quê_, o código está ruim: arruma o
  código em vez de narrá-lo.

## Idioma do framework

Usar Laravel como Laravel, não como Java com sotaque.

- Eloquent antes de query builder cru. Form Request para validação. Artisan Command +
  Scheduler para ingestão. Cache e Http client nativos.
- Seguir o skeleton do framework: **sem `declare(strict_types=1)`** — o Laravel não usa,
  e consistência com o ecossistema vale mais que preferência pessoal.
- Tipagem explícita em toda assinatura pública (parâmetros e retorno).
- **Identificadores em inglês** (classe, método, coluna, rota) — convenção PHP/Laravel.
  Texto de usuário, comentário, documentação e commit em português.

## Dado de cheia é dado crítico

Erro aqui não é bug de UI: é risco durante enchente.

- **Leitura carrega sempre o instante da medição.** Valor sem timestamp não circula.
- **Proveniência explícita:** todo dado normalizado sabe de que fonte veio.
- **Fonte indisponível é estado modelado e visível** — nunca `catch` silencioso, nunca
  valor inventado para preencher lacuna.
- **Nunca interpolar ou estimar** leitura que a fonte não forneceu.
- **Relato de cidadão não se mistura com telemetria oficial** — nem no banco, nem na
  resposta da API, nem na interface.

## Pronto significa

- `vendor/bin/pint` limpo. Formatação é trabalho de ferramenta, não pauta de revisão.
- Teste cobrindo o caminho que importa, não o trivial.
- Sem TODO pendente, sem código comentado, sem arquivo órfão.
