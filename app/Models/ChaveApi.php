<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Chave de acesso à API de consulta, sempre de um órgão só. A chave em si
 * (nxr_...) aparece uma única vez, quando é criada; o banco guarda só o SHA-256.
 */
#[Fillable(['orgao_id', 'nome', 'prefixo', 'hash', 'escopos', 'expira_em', 'criada_por'])]
#[Hidden(['hash'])]
class ChaveApi extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'chaves_api';

    /** O que cada chave pode consultar. */
    public const ESCOPOS = [
        'unidades' => 'Unidades e organograma',
        'cargos' => 'Cargos e vagas (totais por cargo)',
        'servidores' => 'Servidores: nome, matrícula, cargo, setor e vínculo (sem dados pessoais)',
        'ferias' => 'Escala de férias e ausências (sem o tipo de licença)',
        'frequencia' => 'Boletim de frequência dos meses fechados pelo RH (faltas, abonos e horas, sem motivos nem anexos)',
    ];

    protected function casts(): array
    {
        return [
            'escopos' => 'array',
            'expira_em' => 'date',
            'ultimo_uso_em' => 'datetime',
            'revogada_em' => 'datetime',
            'usos' => 'integer',
        ];
    }

    public function criadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por');
    }

    /** Gera a chave: devolve o texto que o integrador vai usar, que não fica guardado. */
    public static function gerar(): array
    {
        $segredo = Str::random(40);

        return ['texto' => 'nxr_'.$segredo, 'prefixo' => 'nxr_'.substr($segredo, 0, 6), 'hash' => hash('sha256', 'nxr_'.$segredo)];
    }

    public static function porTexto(string $texto): ?self
    {
        return str_starts_with($texto, 'nxr_') ? static::where('hash', hash('sha256', $texto))->first() : null;
    }

    public function valida(): bool
    {
        return $this->revogada_em === null && ($this->expira_em === null || $this->expira_em->gte(today()));
    }

    public function permite(string $escopo): bool
    {
        return in_array($escopo, $this->escopos ?? [], true);
    }

    public function situacao(): array
    {
        return match (true) {
            $this->revogada_em !== null => ['Revogada', 'b-mute'],
            $this->expira_em !== null && $this->expira_em->lt(today()) => ['Expirada', 'b-mute'],
            default => ['Ativa', 'b-ok'],
        };
    }

    /**
     * Usuário técnico que representa a chave durante a chamada: não é gravado e
     * só serve para o isolamento por órgão valer na API como vale nas telas.
     */
    public function usuarioTecnico(): User
    {
        $user = new User(['name' => 'API: '.$this->nome]);
        $user->orgao_id = $this->orgao_id;

        return $user;
    }

    protected function camposSensiveisAuditoria(): array
    {
        return ['hash', 'ultimo_ip'];
    }

    protected function generoAuditoria(): string
    {
        return 'a';
    }

    protected function rotuloAuditoria(): string
    {
        return 'Chave de API "'.$this->nome.'"';
    }
}
