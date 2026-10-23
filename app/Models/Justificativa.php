<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Justificativa de uma ocorrência do ponto (falta, atraso, saída antecipada ou
 * registro esquecido), de um dia ou de alguns dias seguidos (atestado).
 *
 * Enviada pelo servidor, fica em análise até a chefia (ou o RH) abonar ou recusar,
 * com motivo. Lançada pelo RH, já entra abonada. Dia abonado conta como trabalhado
 * no espelho. Nunca é apagada: o servidor retira enquanto está em análise.
 */
#[Fillable([
    'orgao_id', 'servidor_id', 'inicio', 'fim', 'tipo', 'ocorrencia', 'horarios', 'motivo', 'situacao',
    'arquivo_caminho', 'arquivo_nome', 'arquivo_mime', 'arquivo_tamanho', 'arquivo_hash',
    'enviado_por', 'enviado_pelo_servidor', 'analisado_por', 'analisado_em', 'motivo_decisao',
])]
#[Hidden(['arquivo_caminho'])]
class Justificativa extends Model
{
    use Auditable, BelongsToOrgao;

    /** tipo => [rótulo, exige anexo] */
    public const TIPOS = [
        'atestado' => ['Atestado médico', true],
        'declaracao' => ['Declaração de comparecimento', true],
        'servico_externo' => ['Serviço externo', false],
        'esquecimento' => ['Esqueci de registrar', false],
        'outro' => ['Outro motivo', false],
    ];

    /** situação => [rótulo, classe do selo] */
    public const SITUACOES = [
        'pendente' => ['Em análise', 'b-info'],
        'abonada' => ['Abonada', 'b-ok'],
        'recusada' => ['Recusada', 'b-err'],
        'retirada' => ['Retirada', 'b-mute'],
    ];

    /** Situações do dia no espelho que pedem justificativa. */
    public const OCORRENCIAS = ['falta', 'incompleto', 'atencao'];

    /** Atestado de vários dias: acima disso, é licença, registrada pelo RH. */
    public const MAXIMO_DIAS = 15;

    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
            'enviado_pelo_servidor' => 'boolean',
            'analisado_em' => 'datetime',
            'arquivo_tamanho' => 'integer',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function analisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analisado_por');
    }

    /** Em análise ou abonadas: as que valem (uma só por dia). */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->whereIn('situacao', ['pendente', 'abonada']);
    }

    public function scopeEntre(Builder $query, $inicio, $fim): Builder
    {
        return $query->where('inicio', '<=', $fim)->where('fim', '>=', $inicio);
    }

    /** Em análise que o usuário pode decidir: as da equipe (ou do órgão, para o RH), nunca as próprias. */
    public function scopeAAnalisarPor(Builder $query, User $usuario): Builder
    {
        return $query->where('situacao', 'pendente')
            ->whereHas('servidor', fn ($q) => $q->visiveisPara($usuario)->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $usuario->id)));
    }

    public function rotuloTipo(): string
    {
        return self::TIPOS[$this->tipo][0] ?? $this->tipo;
    }

    /** @return array{0: string, 1: string} rótulo e classe do selo */
    public function selo(): array
    {
        return self::SITUACOES[$this->situacao] ?? [$this->situacao, 'b-mute'];
    }

    public function periodoTexto(): string
    {
        return $this->inicio->equalTo($this->fim)
            ? $this->inicio->format('d/m/Y')
            : $this->inicio->format('d/m/Y').' a '.$this->fim->format('d/m/Y');
    }

    public function temArquivo(): bool
    {
        return $this->arquivo_caminho !== null;
    }

    public function ehImagem(): bool
    {
        return str_starts_with((string) $this->arquivo_mime, 'image/');
    }

    /** O motivo e o anexo podem trazer dado de saúde: a trilha registra a mudança, não o conteúdo. */
    protected function camposProtegidosAuditoria(): array
    {
        return ['motivo', 'arquivo_caminho', 'arquivo_nome', 'arquivo_hash'];
    }

    protected function generoAuditoria(): string
    {
        return 'a';
    }

    protected function rotuloAuditoria(): string
    {
        return 'Justificativa de ponto de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id).' ('.$this->periodoTexto().')';
    }
}
