<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use App\Support\Cpf;
use Database\Factories\VagaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['orgao_id', 'cargo_id', 'numero', 'base_legal', 'situacao', 'orgao_remanejamento', 'ocupante_nome', 'ocupante_cpf', 'ato_numero', 'ato_data', 'efeito_data', 'observacao'])]
#[Hidden(['ocupante_cpf'])]
class Vaga extends Model
{
    /** @use HasFactory<VagaFactory> */
    use Auditable, BelongsToOrgao, HasFactory;

    protected $table = 'vagas';

    /** @var array<string, string> */
    public const SITUACOES = [
        'ocupada' => 'Ocupada',
        'vaga' => 'Vaga',
        'remanejada' => 'Remanejada para outro órgão',
        'devolvida' => 'Devolvida ao órgão de origem',
    ];

    /** Situações em que a vaga não está disponível para o órgão. */
    public const FORA_DO_ORGAO = ['remanejada', 'devolvida'];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'ocupante_cpf' => 'encrypted',
            'ato_data' => 'date',
            'efeito_data' => 'date',
        ];
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function cpfMascarado(): ?string
    {
        return Cpf::mascarar($this->ocupante_cpf);
    }

    public function rotuloSituacao(): string
    {
        return self::SITUACOES[$this->situacao] ?? $this->situacao;
    }

    /** O CPF é guardado criptografado e nunca vai para a trilha de auditoria. */
    protected function camposSensiveisAuditoria(): array
    {
        return ['ocupante_cpf'];
    }

    protected function generoAuditoria(): string
    {
        return 'a';
    }

    protected function rotuloAuditoria(): string
    {
        return 'Vaga nº '.$this->numero.' de '.($this->cargo?->denominacao ?? 'cargo #'.$this->cargo_id);
    }
}
