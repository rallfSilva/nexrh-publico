# NexRH Público: convenções do repositório

- Laravel 13, PHP 8.3, PostgreSQL 16. Interface e textos em **português do Brasil**; nomes de classes, tabelas e métodos de domínio também em português (`Unidade`, `registros_auditoria`, `idsDescendentes`).
- Modelos usam os atributos `#[Fillable]` / `#[Hidden]` e o método `casts()`.
- Todo modelo com dados de órgão usa `BelongsToOrgao` e `Auditable`. Nunca consultar dados de órgão com `withoutGlobalScopes()` em código de tela.
- Permissões: rotas protegidas com `can:<permissao>`; permissões e perfis ficam em `Database\Seeders\PerfisSeeder` (idempotente).
- Telas: componentes `x-layouts.app`, `x-campo`, `x-icone`, classes CSS em `resources/css/app.css` (paleta #1E5470, #34729C, #6CB1DA, #6EC1D1, #D1ECFF, #CBEAEC).
- **Dados fictícios** em seeders, testes e demonstrações. Nunca nomes, CPFs ou salários reais.
- Antes de commitar: `vendor/bin/pint` e `php artisan test` (banco `nexrh_test` no PostgreSQL).
