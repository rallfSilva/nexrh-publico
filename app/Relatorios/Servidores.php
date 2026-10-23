<?php

namespace App\Relatorios;

use App\Models\User;
use Illuminate\Support\Collection;

class Servidores extends Relatorio
{
    public function chave(): string
    {
        return 'servidores';
    }

    public function titulo(): string
    {
        return 'Servidores';
    }

    public function descricao(): string
    {
        return 'Quem está lotado em cada setor, com cargo, vínculo e data de ingresso.';
    }

    public function icone(): string
    {
        return 'servidor';
    }

    public function permitido(User $user): bool
    {
        return $user->can('listar-servidores');
    }

    public function filtros(User $user): array
    {
        return [
            'unidade' => $this->filtroUnidade($user),
            'situacao' => ['rotulo' => 'Situação', 'opcoes' => ['ativo' => 'Ativos', 'desligado' => 'Desligados'], 'padrao' => 'ativo'],
            'vinculo' => ['rotulo' => 'Vínculo', 'opcoes' => ['comissionado' => 'Só em comissão', 'efetivo' => 'Efetivos', 'federal' => 'Federais cedidos'], 'vazio' => 'Todos os vínculos'],
        ];
    }

    public function colunas(User $user): array
    {
        return ['nome' => 'Nome', 'cpf' => 'CPF', 'matricula' => 'Matrícula', 'cargo' => 'Cargo', 'simbolo' => 'Símbolo', 'unidade' => 'Setor', 'vinculo' => 'Vínculo', 'ingresso' => 'No órgão desde', 'situacao' => 'Situação'];
    }

    public function linhas(User $user, array $filtros): Collection
    {
        return $this->servidores($user, $filtros)
            ->with(['unidade', 'vaga.cargo'])
            ->where('situacao', $filtros['situacao'])
            ->when($filtros['vinculo'] === 'comissionado', fn ($q) => $q->where('servidor_efetivo', false)->where('servidor_federal', false))
            ->when($filtros['vinculo'] === 'efetivo', fn ($q) => $q->where('servidor_efetivo', true))
            ->when($filtros['vinculo'] === 'federal', fn ($q) => $q->where('servidor_federal', true))
            ->orderByRaw('unaccent(nome)')
            ->get()
            ->map(fn ($s) => [
                'nome' => $s->nome,
                'cpf' => $s->cpfMascarado(),
                'matricula' => $s->matricula(),
                'cargo' => $s->vaga?->cargo?->denominacao,
                'simbolo' => $s->vaga?->cargo?->simbolo,
                'unidade' => $s->unidade?->sigla,
                'vinculo' => $s->rotuloVinculo(),
                'ingresso' => $this->data($s->ingresso()),
                'situacao' => $s->situacao === 'ativo' ? 'Ativo' : 'Desligado em '.$this->data($s->data_desligamento),
            ]);
    }
}
