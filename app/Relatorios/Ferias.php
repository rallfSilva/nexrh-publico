<?php

namespace App\Relatorios;

use App\Models\Ferias as FeriasModel;
use App\Models\User;
use Illuminate\Support\Collection;

class Ferias extends Relatorio
{
    public function chave(): string
    {
        return 'ferias';
    }

    public function titulo(): string
    {
        return 'Férias';
    }

    public function descricao(): string
    {
        return 'Etapas de férias do ano, com período aquisitivo, datas e situação.';
    }

    public function icone(): string
    {
        return 'sol';
    }

    public function permitido(User $user): bool
    {
        return $user->can('ver-escala');
    }

    public function filtros(User $user): array
    {
        return [
            'ano' => ['rotulo' => 'Ano', 'opcoes' => $this->anos(), 'padrao' => (string) now()->year],
            'unidade' => $this->filtroUnidade($user),
            'situacao' => ['rotulo' => 'Situação', 'opcoes' => ['aprovada' => 'Aprovadas', 'programada' => 'Aguardando aprovação', 'interrompida' => 'Interrompidas', 'cancelada' => 'Canceladas', 'recusada' => 'Recusadas'], 'vazio' => 'Todas as situações'],
        ];
    }

    public function colunas(User $user): array
    {
        return ['servidor' => 'Servidor', 'unidade' => 'Setor', 'periodo' => 'Período aquisitivo', 'etapa' => 'Etapa', 'inicio' => 'Início', 'fim' => 'Fim', 'dias' => 'Dias', 'situacao' => 'Situação', 'sei' => 'Processo SEI'];
    }

    public function numericas(): array
    {
        return ['etapa', 'dias'];
    }

    public function linhas(User $user, array $filtros): Collection
    {
        $ano = (int) $filtros['ano'];

        return FeriasModel::with(['servidor.unidade', 'periodo'])
            ->whereIn('servidor_id', $this->servidores($user, $filtros)->select('id'))
            ->entre("{$ano}-01-01", "{$ano}-12-31")
            ->when($filtros['situacao'], fn ($q, $s) => $q->where('situacao', $s))
            ->orderBy('inicio')
            ->get()
            ->map(fn (FeriasModel $f) => [
                'servidor' => $f->servidor->nome,
                'unidade' => $f->servidor->unidade?->sigla,
                'periodo' => $f->periodo?->rotulo(),
                'etapa' => $f->etapa,
                'inicio' => $this->data($f->inicio),
                'fim' => $this->data($f->fim),
                'dias' => $f->dias,
                'situacao' => $f->selo()[0],
                'sei' => $f->processo_sei,
            ]);
    }
}
