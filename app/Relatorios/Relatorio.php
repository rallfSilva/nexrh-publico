<?php

namespace App\Relatorios;

use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Um relatório: quem pode ver, os filtros, as colunas e as linhas. As linhas já
 * vêm prontas para mostrar (texto), com o CPF sempre mascarado. Os servidores
 * são sempre os visíveis para quem pede: o órgão todo ou a equipe da chefia.
 */
abstract class Relatorio
{
    abstract public function chave(): string;

    abstract public function titulo(): string;

    abstract public function descricao(): string;

    abstract public function permitido(User $user): bool;

    /** @return array<string, string> chave => rótulo */
    abstract public function colunas(User $user): array;

    /**
     * @param  array<string, string|null>  $filtros
     * @return Collection<int, array<string, scalar|null>>
     */
    abstract public function linhas(User $user, array $filtros): Collection;

    public function icone(): string
    {
        return 'relatorio';
    }

    /** Colunas numéricas: alinhadas à direita e gravadas como número no Excel. */
    public function numericas(): array
    {
        return [];
    }

    /**
     * Filtros do relatório: nome => [rotulo, opcoes, vazio]. Sem "vazio", o filtro é obrigatório
     * e começa no "padrao".
     *
     * @return array<string, array{rotulo: string, opcoes: array<string, string>, vazio?: string, padrao?: string}>
     */
    public function filtros(User $user): array
    {
        return ['unidade' => $this->filtroUnidade($user)];
    }

    /**
     * Valores aceitos dos filtros: o que não está nas opções é ignorado.
     *
     * @param  array<string, mixed>  $entrada
     * @return array<string, string|null>
     */
    public function valoresDosFiltros(User $user, array $entrada): array
    {
        $valores = [];
        foreach ($this->filtros($user) as $nome => $f) {
            $v = isset($entrada[$nome]) ? (string) $entrada[$nome] : null;
            $valores[$nome] = $v !== null && array_key_exists($v, $f['opcoes']) ? $v : ($f['padrao'] ?? null);
        }

        return $valores;
    }

    /** Descrição dos filtros usados, para o cabeçalho do PDF e da planilha. */
    public function textoDosFiltros(User $user, array $valores): string
    {
        $partes = [];
        foreach ($this->filtros($user) as $nome => $f) {
            if (($valores[$nome] ?? null) !== null) {
                $partes[] = $f['rotulo'].': '.$f['opcoes'][$valores[$nome]];
            }
        }

        return $partes ? implode(' · ', $partes) : 'Sem filtros';
    }

    protected function filtroUnidade(User $user): array
    {
        $unidades = Unidade::where('ativo', true)
            ->when(! $user->can('servidores.ver') && ! $user->can('ferias.ver'), fn ($q) => $q->whereIn('id', $user->idsUnidadesChefiadas()))
            ->orderBy('sigla')->get();

        return ['rotulo' => 'Setor', 'opcoes' => $unidades->mapWithKeys(fn ($u) => [(string) $u->id => $u->sigla.' · '.$u->nome])->all(), 'vazio' => 'Todos os setores'];
    }

    /** Servidores visíveis para o usuário, no setor escolhido. */
    protected function servidores(User $user, array $filtros): Builder
    {
        return Servidor::visiveisPara($user)
            ->when($filtros['unidade'] ?? null, fn ($q, $u) => $q->where('unidade_id', $u));
    }

    protected function anos(): array
    {
        $atual = (int) now()->year;

        return collect(range($atual + 1, $atual - 4))->mapWithKeys(fn ($a) => [(string) $a => (string) $a])->all();
    }

    protected function data($data): ?string
    {
        return $data?->format('d/m/Y');
    }
}
