<?php

namespace App\Relatorios;

use App\Models\Cargo;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Support\Collection;

class QuadroDeCargos extends Relatorio
{
    public function chave(): string
    {
        return 'cargos';
    }

    public function titulo(): string
    {
        return 'Quadro de cargos e vagas';
    }

    public function descricao(): string
    {
        return 'Cada vaga do quadro, com situação, ocupante e ato de nomeação.';
    }

    public function icone(): string
    {
        return 'cargos';
    }

    public function permitido(User $user): bool
    {
        return $user->can('cargos.ver');
    }

    public function filtros(User $user): array
    {
        return [
            'natureza' => ['rotulo' => 'Natureza', 'opcoes' => Cargo::NATUREZAS, 'vazio' => 'Todas as naturezas'],
            'situacao' => ['rotulo' => 'Situação da vaga', 'opcoes' => Vaga::SITUACOES, 'vazio' => 'Todas as situações'],
        ];
    }

    public function colunas(User $user): array
    {
        return ['cargo' => 'Cargo', 'simbolo' => 'Símbolo', 'natureza' => 'Natureza', 'vaga' => 'Vaga', 'situacao' => 'Situação', 'ocupante' => 'Ocupante', 'cpf' => 'CPF', 'ato' => 'Ato', 'efeitos' => 'Efeitos a partir de'];
    }

    public function numericas(): array
    {
        return ['vaga'];
    }

    public function linhas(User $user, array $filtros): Collection
    {
        return Vaga::with(['cargo', 'servidor'])
            ->whereHas('cargo', fn ($q) => $q->where('ativo', true)->when($filtros['natureza'], fn ($c, $n) => $c->where('natureza', $n)))
            ->when($filtros['situacao'], fn ($q, $s) => $q->where('situacao', $s))
            ->join('cargos', 'cargos.id', '=', 'vagas.cargo_id')
            ->orderByRaw('unaccent(cargos.denominacao)')->orderBy('cargos.simbolo')->orderBy('vagas.numero')
            ->select('vagas.*')
            ->get()
            ->map(fn (Vaga $v) => [
                'cargo' => $v->cargo->denominacao,
                'simbolo' => $v->cargo->simbolo,
                'natureza' => $v->cargo->rotuloNatureza(),
                'vaga' => $v->numero,
                'situacao' => $v->rotuloSituacao(),
                'ocupante' => $v->servidor?->nome ?? $v->ocupante_nome,
                'cpf' => $v->servidor?->cpfMascarado() ?? $v->cpfMascarado(),
                'ato' => trim(($v->ato_numero ?? '').($v->ato_data ? ' de '.$this->data($v->ato_data) : '')) ?: null,
                'efeitos' => $this->data($v->efeito_data),
            ]);
    }
}
