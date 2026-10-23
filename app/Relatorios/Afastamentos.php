<?php

namespace App\Relatorios;

use App\Models\Afastamento;
use App\Models\User;
use Illuminate\Support\Collection;

class Afastamentos extends Relatorio
{
    public function chave(): string
    {
        return 'afastamentos';
    }

    public function titulo(): string
    {
        return 'Licenças e afastamentos';
    }

    public function descricao(): string
    {
        return 'Licenças e afastamentos do ano. O tipo só aparece para quem vê dados pessoais.';
    }

    public function icone(): string
    {
        return 'calendario';
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
            'situacao' => ['rotulo' => 'Situação', 'opcoes' => ['validos' => 'Registrados', 'cancelados' => 'Cancelados'], 'padrao' => 'validos'],
        ];
    }

    public function colunas(User $user): array
    {
        $colunas = ['servidor' => 'Servidor', 'unidade' => 'Setor', 'tipo' => 'Tipo', 'inicio' => 'Início', 'fim' => 'Fim', 'dias' => 'Dias'];

        return $user->can('servidores.dados_pessoais') ? $colunas + ['documento' => 'Documento'] : $colunas;
    }

    public function numericas(): array
    {
        return ['dias'];
    }

    public function linhas(User $user, array $filtros): Collection
    {
        $ano = (int) $filtros['ano'];
        $pessoais = $user->can('servidores.dados_pessoais');

        return Afastamento::with('servidor.unidade')
            ->whereIn('servidor_id', $this->servidores($user, $filtros)->select('id'))
            ->entre("{$ano}-01-01", "{$ano}-12-31")
            ->when($filtros['situacao'] === 'cancelados', fn ($q) => $q->whereNotNull('cancelado_em'), fn ($q) => $q->whereNull('cancelado_em'))
            ->orderBy('inicio')
            ->get()
            ->map(fn (Afastamento $a) => [
                'servidor' => $a->servidor->nome,
                'unidade' => $a->servidor->unidade?->sigla,
                'tipo' => $a->tipoPara($user),
                'inicio' => $this->data($a->inicio),
                'fim' => $this->data($a->fim),
                'dias' => $a->dias(),
            ] + ($pessoais ? ['documento' => $a->documento] : []));
    }
}
