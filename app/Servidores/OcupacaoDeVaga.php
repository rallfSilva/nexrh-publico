<?php

namespace App\Servidores;

use App\Models\Servidor;
use App\Models\ServidorEvento;
use App\Models\Unidade;
use App\Models\Vaga;
use Illuminate\Support\Facades\Auth;

/**
 * Mantém o quadro de cargos coerente com o cadastro de servidores: a vaga do
 * servidor fica "ocupada" com o nome, o CPF e o ato dele, e volta a ficar livre
 * quando ele muda de vaga ou é desligado. Também escreve a linha do tempo.
 */
class OcupacaoDeVaga
{
    /**
     * Chamado depois de salvar o servidor. $antes são os valores originais
     * (vazio no cadastro novo).
     *
     * @param  array<string, mixed>  $antes
     */
    public function sincronizar(Servidor $servidor, array $antes = []): void
    {
        $novo = $antes === [];
        $vagaAnterior = $antes['vaga_id'] ?? null;

        if (! $novo && $vagaAnterior !== null && $vagaAnterior !== $servidor->vaga_id) {
            $this->liberar(Vaga::find($vagaAnterior), $servidor);
        }

        if ($servidor->ativo() && $servidor->vaga_id !== null) {
            $vaga = $servidor->vaga()->with('cargo')->first();
            $vaga->update([
                'situacao' => 'ocupada',
                'ocupante_nome' => $servidor->nome,
                'ocupante_cpf' => $servidor->cpf,
                'ato_numero' => $servidor->decreto_nomeacao ?? $vaga->ato_numero,
                'ato_data' => $servidor->data_decreto ?? $vaga->ato_data,
                'efeito_data' => $servidor->efeitos_a_partir_de ?? $vaga->efeito_data,
                'orgao_remanejamento' => null,
            ]);

            if ($novo || $vagaAnterior !== $servidor->vaga_id) {
                $this->evento($servidor, 'nomeacao', $servidor->efeitos_a_partir_de ?? $servidor->data_decreto,
                    'Nomeado(a) '.$vaga->cargo->denominacao.' ('.$vaga->cargo->simbolo.')',
                    $this->descricaoAto($servidor));
            }
        }

        if ($novo) {
            $this->evento($servidor, 'ingresso', $servidor->efeitos_a_partir_de ?? now(), 'Ingresso no órgão', 'Cadastro conforme a ficha da SEGAD');
        }

        if (! $novo && ($antes['unidade_id'] ?? null) !== $servidor->unidade_id && $servidor->unidade_id !== null) {
            $de = Unidade::find($antes['unidade_id'] ?? null)?->sigla;
            $para = $servidor->unidade?->sigla;
            $this->evento($servidor, 'lotacao', now(), $de ? "Lotação alterada de {$de} para {$para}" : "Lotado(a) em {$para}");
        }
    }

    public function desligar(Servidor $servidor, string $data, ?string $ato, ?string $motivo): void
    {
        $vaga = $servidor->vaga;

        $servidor->update([
            'situacao' => 'desligado',
            'vaga_id' => null,
            'data_desligamento' => $data,
            'ato_desligamento' => $ato,
            'motivo_desligamento' => $motivo,
        ]);

        $this->liberar($vaga, $servidor, 'Liberada pelo desligamento de '.$servidor->nome.' em '.date('d/m/Y', strtotime($data)).'.');
        $this->evento($servidor, 'desligamento', $data, 'Desligado(a)'.($motivo ? ': '.$motivo : ''), $ato ? 'Ato '.$ato : null);
    }

    public function readmitir(Servidor $servidor): void
    {
        $servidor->update(['situacao' => 'ativo', 'data_desligamento' => null, 'ato_desligamento' => null, 'motivo_desligamento' => null]);
        $this->evento($servidor, 'readmissao', $servidor->efeitos_a_partir_de ?? now(), 'Readmitido(a)', $this->descricaoAto($servidor));
    }

    /** Volta a vaga para "livre", se ainda estiver com este servidor. */
    private function liberar(?Vaga $vaga, Servidor $servidor, ?string $observacao = null): void
    {
        if ($vaga === null || $vaga->situacao !== 'ocupada' || $vaga->ocupante_cpf !== $servidor->cpf) {
            return;
        }

        $vaga->update([
            'situacao' => 'vaga',
            'ocupante_nome' => null,
            'ocupante_cpf' => null,
            'ato_numero' => null,
            'ato_data' => null,
            'efeito_data' => null,
            'observacao' => $observacao ?? $vaga->observacao,
        ]);
    }

    private function descricaoAto(Servidor $servidor): ?string
    {
        if (! $servidor->decreto_nomeacao) {
            return null;
        }

        return 'Decreto nº '.$servidor->decreto_nomeacao
            .($servidor->data_doe ? ', publicado em '.$servidor->data_doe->format('d/m/Y') : '');
    }

    private function evento(Servidor $servidor, string $tipo, mixed $data, string $descricao, ?string $detalhe = null): void
    {
        ServidorEvento::create([
            'orgao_id' => $servidor->orgao_id,
            'servidor_id' => $servidor->id,
            'data' => $data ?? now(),
            'tipo' => $tipo,
            'descricao' => mb_substr($descricao, 0, 255),
            'detalhe' => $detalhe !== null ? mb_substr($detalhe, 0, 255) : null,
            'user_id' => Auth::id(),
        ]);
    }
}
