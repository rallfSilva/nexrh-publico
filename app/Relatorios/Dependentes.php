<?php

namespace App\Relatorios;

use App\Models\Dependente;
use App\Models\User;
use Illuminate\Support\Collection;

class Dependentes extends Relatorio
{
    public function chave(): string
    {
        return 'dependentes';
    }

    public function titulo(): string
    {
        return 'Dependentes';
    }

    public function descricao(): string
    {
        return 'Dependentes por finalidade (imposto de renda, previdência e salário-família), com idade e alertas.';
    }

    public function icone(): string
    {
        return 'usuarios';
    }

    public function permitido(User $user): bool
    {
        return $user->can('gerenciar-dossie');
    }

    public function filtros(User $user): array
    {
        return [
            'unidade' => $this->filtroUnidade($user),
            'finalidade' => ['rotulo' => 'Finalidade', 'opcoes' => ['declarar_ir' => 'Imposto de renda', 'declarar_previdencia' => 'Previdência', 'salario_familia' => 'Salário-família'], 'vazio' => 'Todas as finalidades'],
            'situacao' => ['rotulo' => 'Situação', 'opcoes' => ['ativos' => 'Ativos', 'encerrados' => 'Encerrados'], 'padrao' => 'ativos'],
        ];
    }

    public function colunas(User $user): array
    {
        return ['titular' => 'Servidor', 'unidade' => 'Setor', 'dependente' => 'Dependente', 'cpf' => 'CPF', 'parentesco' => 'Parentesco', 'nascimento' => 'Nascimento', 'idade' => 'Idade', 'finalidades' => 'Finalidades', 'alertas' => 'Alertas'];
    }

    public function numericas(): array
    {
        return ['idade'];
    }

    public function linhas(User $user, array $filtros): Collection
    {
        return Dependente::with('servidor.unidade')
            ->whereIn('servidor_id', $this->servidores($user, $filtros)->where('situacao', 'ativo')->select('id'))
            ->when($filtros['situacao'] === 'encerrados', fn ($q) => $q->whereNotNull('encerrado_em'), fn ($q) => $q->ativos())
            ->when($filtros['finalidade'], fn ($q, $f) => $q->where($f, true))
            ->get()
            ->sortBy(fn ($d) => [$d->servidor->nome, $d->data_nascimento])
            ->map(fn (Dependente $d) => [
                'titular' => $d->servidor->nome,
                'unidade' => $d->servidor->unidade?->sigla,
                'dependente' => $d->nome,
                'cpf' => $d->cpfMascarado(),
                'parentesco' => $d->parentescoCurto(),
                'nascimento' => $this->data($d->data_nascimento),
                'idade' => $d->idade(),
                'finalidades' => implode(', ', $d->finalidades()) ?: null,
                'alertas' => implode(' ', $d->alertas()) ?: null,
            ])->values();
    }
}
