<?php

namespace App\Relatorios;

use App\Models\User;
use Illuminate\Support\Collection;

class Pendencias extends Relatorio
{
    public function chave(): string
    {
        return 'pendencias';
    }

    public function titulo(): string
    {
        return 'Pendências dos servidores';
    }

    public function descricao(): string
    {
        return 'O que falta em cada ficha: declarações, vaga, documentos do dossiê e dependentes a revisar.';
    }

    public function icone(): string
    {
        return 'alerta';
    }

    public function permitido(User $user): bool
    {
        return $user->can('servidores.ver');
    }

    public function filtros(User $user): array
    {
        $tipos = ['ficha' => 'Ficha e declarações', 'ferias' => 'Férias'];
        if ($user->can('gerenciar-dossie')) {
            $tipos['dossie'] = 'Dossiê e dependentes';
        }

        return [
            'unidade' => $this->filtroUnidade($user),
            'tipo' => ['rotulo' => 'Tipo', 'opcoes' => $tipos, 'vazio' => 'Todos os tipos'],
        ];
    }

    public function colunas(User $user): array
    {
        return ['servidor' => 'Servidor', 'unidade' => 'Setor', 'tipo' => 'Tipo', 'pendencia' => 'Pendência', 'detalhe' => 'O que fazer'];
    }

    public function linhas(User $user, array $filtros): Collection
    {
        $dossie = $user->can('gerenciar-dossie');
        $tipos = $filtros['tipo'] ? [$filtros['tipo']] : ['ficha', 'ferias', 'dossie'];

        $servidores = $this->servidores($user, $filtros)
            ->where('situacao', 'ativo')
            ->with(['unidade', 'declaracoes', 'periodosAquisitivos.ferias', 'ferias'])
            ->when($dossie, fn ($q) => $q->with(['documentos', 'dependentes']))
            ->orderByRaw('unaccent(nome)')
            ->get();

        return $servidores->flatMap(function ($s) use ($tipos, $dossie) {
            $grupos = [
                'ficha' => in_array('ficha', $tipos, true) ? $s->pendencias() : [],
                'ferias' => in_array('ferias', $tipos, true) ? $s->pendenciasDeFerias() : [],
                'dossie' => $dossie && in_array('dossie', $tipos, true) ? $s->pendenciasDoDossie() : [],
            ];
            $rotulos = ['ficha' => 'Ficha', 'ferias' => 'Férias', 'dossie' => 'Dossiê'];

            return collect($grupos)->flatMap(fn ($lista, $tipo) => array_map(fn ($p) => [
                'servidor' => $s->nome,
                'unidade' => $s->unidade?->sigla,
                'tipo' => $rotulos[$tipo],
                'pendencia' => $p['titulo'],
                'detalhe' => $p['detalhe'],
            ], $lista));
        })->values();
    }
}
