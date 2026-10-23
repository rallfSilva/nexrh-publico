<?php

namespace App\Frequencia;

use App\Models\Justificativa;
use App\Models\Servidor;
use App\Models\User;
use App\Servidores\ArquivoDossie;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Envio de justificativas pelo servidor e abono direto pelo RH: confere o período,
 * se há ocorrência para justificar, se o dia já não tem justificativa valendo, e
 * guarda o anexo criptografado, como os documentos do dossiê.
 */
class Justificador
{
    public function __construct(private ArquivoDossie $arquivos) {}

    /**
     * Dias com ocorrência (falta, atraso, saída antecipada, registro incompleto) no
     * período, já descontados os que têm justificativa em análise ou abonada.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ocorrencias(Servidor $servidor, Carbon $inicio, Carbon $fim, ?User $quem = null): Collection
    {
        $dias = collect();
        $fim = $fim->copy()->min(today()->subDay());
        for ($mes = $inicio->copy()->startOfMonth(); $mes->lte($fim); $mes->addMonth()) {
            $calculo = (new Espelho)->carregar(collect([$servidor]), $mes->copy())->calcular($servidor, $quem);
            $dias = $dias->merge(collect($calculo['dias'])->filter(fn ($d) => $d['justificavel'] && $d['data']->between($inicio, $fim)));
        }

        return $dias->values();
    }

    /**
     * @param  array{inicio: string, fim?: ?string, tipo: string, motivo: string, horarios?: ?string}  $dados
     */
    public function registrar(Servidor $servidor, User $quem, array $dados, ?UploadedFile $arquivo, bool $peloRh): Justificativa
    {
        $inicio = Carbon::parse($dados['inicio'])->startOfDay();
        $fim = Carbon::parse($dados['fim'] ?? $dados['inicio'])->startOfDay();

        if ($fim->lt($inicio)) {
            throw ValidationException::withMessages(['fim' => 'O último dia não pode ser antes do primeiro.']);
        }
        if ($inicio->diffInDays($fim) + 1 > Justificativa::MAXIMO_DIAS) {
            throw ValidationException::withMessages(['fim' => 'No máximo '.Justificativa::MAXIMO_DIAS.' dias. Afastamentos maiores são registrados pelo RH como licença.']);
        }
        if ($dados['tipo'] !== 'atestado' && ! $inicio->equalTo($fim)) {
            throw ValidationException::withMessages(['fim' => 'Só o atestado médico vale para mais de um dia. Para outros motivos, envie uma justificativa por dia.']);
        }
        if (! $peloRh && $inicio->gte(today())) {
            throw ValidationException::withMessages(['inicio' => 'Justifique a partir do dia seguinte, quando a ocorrência já aparece no espelho.']);
        }
        if (! $peloRh && $inicio->lt(today()->subMonthNoOverflow()->startOfMonth())) {
            throw ValidationException::withMessages(['inicio' => 'O prazo para justificar vai até o mês anterior. Para dias mais antigos, fale com o RH.']);
        }

        $ativa = Justificativa::ativas()->where('servidor_id', $servidor->id)->entre($inicio, $fim)->first();
        if ($ativa) {
            throw ValidationException::withMessages(['inicio' => 'Já há uma justificativa '.($ativa->situacao === 'pendente' ? 'em análise' : 'abonada').' para '.$ativa->periodoTexto().'.']);
        }

        $ocorrencias = $this->ocorrencias($servidor, $inicio, $fim, $quem);
        if ($ocorrencias->isEmpty() && ! ($peloRh && $fim->gte(today()))) {
            throw ValidationException::withMessages(['inicio' => 'Nesse período não há falta, atraso nem registro incompleto para justificar.']);
        }

        if (Justificativa::TIPOS[$dados['tipo']][1] && $arquivo === null && ! $peloRh) {
            throw ValidationException::withMessages(['arquivo' => 'Anexe o '.mb_strtolower(Justificativa::TIPOS[$dados['tipo']][0]).' (PDF, JPG ou PNG).']);
        }

        return Justificativa::create([
            'orgao_id' => $servidor->orgao_id,
            'servidor_id' => $servidor->id,
            'inicio' => $inicio,
            'fim' => $fim,
            'tipo' => $dados['tipo'],
            'ocorrencia' => $this->descrever($ocorrencias),
            'horarios' => $dados['horarios'] ?? null,
            'motivo' => $dados['motivo'],
            'situacao' => $peloRh ? 'abonada' : 'pendente',
            'enviado_por' => $quem->id,
            'enviado_pelo_servidor' => ! $peloRh,
            'analisado_por' => $peloRh ? $quem->id : null,
            'analisado_em' => $peloRh ? now() : null,
            ...($arquivo ? $this->arquivos->guardar($servidor, $arquivo, 'frequencia') : []),
        ]);
    }

    /** "05/10: Sem registro; 08/10: Entrada 32 min depois do horário" (até 255 caracteres). */
    public function descrever(Collection $ocorrencias): ?string
    {
        if ($ocorrencias->isEmpty()) {
            return null;
        }

        $texto = $ocorrencias->map(fn ($d) => $d['data']->format('d/m').': '.($d['alertas'] ? implode(', ', $d['alertas']) : Espelho::SITUACOES[$d['situacao']][0]))->implode('; ');

        return mb_strlen($texto) > 255 ? mb_substr($texto, 0, 252).'...' : $texto;
    }
}
