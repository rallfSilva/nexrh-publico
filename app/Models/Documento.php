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
 * Documento do dossiê digital do servidor.
 *
 * Enviado pelo RH já entra validado; enviado pelo próprio servidor fica aguardando
 * validação até o RH aceitar ou recusar (com motivo). Nunca é apagado: o RH arquiva,
 * também com motivo. O arquivo fica criptografado no disco privado (ver ArquivoDossie).
 */
#[Fillable([
    'orgao_id', 'servidor_id', 'categoria', 'tipo', 'descricao', 'data_documento', 'validade', 'situacao', 'motivo',
    'arquivo_caminho', 'arquivo_nome', 'arquivo_mime', 'arquivo_tamanho', 'arquivo_hash',
    'enviado_por', 'enviado_pelo_servidor', 'analisado_por', 'analisado_em',
])]
#[Hidden(['arquivo_caminho'])]
class Documento extends Model
{
    use Auditable, BelongsToOrgao;

    /** @var array<string, string> */
    public const CATEGORIAS = [
        'identificacao' => 'Identificação',
        'escolaridade' => 'Escolaridade',
        'admissao' => 'Admissão',
        'atos' => 'Atos funcionais',
        'capacitacao' => 'Capacitação',
        'dependentes' => 'Dependentes',
        'saude' => 'Saúde',
        'outros' => 'Outros',
    ];

    /** @var array<string, array<string, string>> categoria => [tipo => rótulo] */
    public const TIPOS = [
        'identificacao' => [
            'rg' => 'Carteira de identidade (RG)',
            'cpf' => 'CPF',
            'certidao_civil' => 'Certidão de nascimento ou casamento',
            'titulo_eleitor' => 'Título de eleitor e quitação eleitoral',
            'reservista' => 'Certificado de reservista',
            'pis_pasep' => 'Comprovante de PIS/PASEP',
            'cnh' => 'Carteira de motorista (CNH)',
            'ctps' => 'Carteira de trabalho (CTPS)',
            'comprovante_residencia' => 'Comprovante de residência',
            'comprovante_bancario' => 'Comprovante de conta bancária',
            'foto' => 'Foto 3x4',
        ],
        'escolaridade' => [
            'diploma' => 'Diploma',
            'historico' => 'Histórico escolar',
            'certificado_conclusao' => 'Certificado de conclusão',
            'registro_profissional' => 'Registro em conselho profissional',
        ],
        'admissao' => [
            'termo_posse' => 'Termo de posse',
            'aso' => 'Atestado de saúde ocupacional (ASO)',
            'ficha_assinada' => 'Ficha cadastral assinada',
            'declaracoes_assinadas' => 'Declarações assinadas (parentesco, acumulação e bens)',
            'certidoes_negativas' => 'Certidões negativas (criminal e cível)',
        ],
        'atos' => [
            'decreto_nomeacao' => 'Decreto de nomeação',
            'publicacao_doe' => 'Publicação no Diário Oficial',
            'portaria' => 'Portaria',
            'decreto_exoneracao' => 'Decreto de exoneração',
            'cessao' => 'Ato de cessão ou requisição',
        ],
        'capacitacao' => [
            'certificado_curso' => 'Certificado de curso',
        ],
        'dependentes' => [
            'documento_dependente' => 'Documento do dependente',
            'certidao_nascimento_dependente' => 'Certidão de nascimento do dependente',
            'comprovante_matricula' => 'Comprovante de matrícula (universitário)',
            'termo_guarda' => 'Termo de guarda, tutela ou curatela',
        ],
        'saude' => [
            'atestado' => 'Atestado médico',
            'laudo' => 'Laudo médico',
        ],
        'outros' => [
            'outro' => 'Outro documento',
        ],
    ];

    /**
     * Documentos exigidos na posse: o dossiê mostra quantos já estão validados.
     *
     * @var array<string, string> tipo => categoria
     */
    public const EXIGIDOS_POSSE = [
        'rg' => 'identificacao',
        'cpf' => 'identificacao',
        'certidao_civil' => 'identificacao',
        'titulo_eleitor' => 'identificacao',
        'pis_pasep' => 'identificacao',
        'comprovante_residencia' => 'identificacao',
        'diploma' => 'escolaridade',
        'aso' => 'admissao',
        'termo_posse' => 'admissao',
        'decreto_nomeacao' => 'atos',
    ];

    /** @var array<string, string> */
    public const SITUACOES = [
        'pendente' => 'Aguardando validação',
        'validado' => 'Validado',
        'recusado' => 'Recusado',
        'arquivado' => 'Arquivado',
    ];

    /** Tipos aceitos, conferidos pelo conteúdo do arquivo (não pela extensão). */
    public const MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public const TAMANHO_MAXIMO_KB = 10240;

    protected function casts(): array
    {
        return [
            'data_documento' => 'date',
            'validade' => 'date',
            'analisado_em' => 'datetime',
            'enviado_pelo_servidor' => 'boolean',
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

    /** Os que valem no dossiê: tudo que não foi arquivado. */
    public function scopeEmUso(Builder $query): Builder
    {
        return $query->where('situacao', '!=', 'arquivado');
    }

    /** Todos os tipos numa lista só: tipo => rótulo. */
    public static function todosOsTipos(): array
    {
        return array_merge(...array_values(self::TIPOS));
    }

    public function rotuloTipo(): string
    {
        return self::TIPOS[$this->categoria][$this->tipo] ?? $this->tipo;
    }

    public function titulo(): string
    {
        return $this->descricao ?: $this->rotuloTipo();
    }

    public function rotuloSituacao(): string
    {
        return self::SITUACOES[$this->situacao] ?? $this->situacao;
    }

    public function vencido(): bool
    {
        return $this->validade !== null && $this->validade->isPast() && ! $this->validade->isToday();
    }

    /** Vence nos próximos 30 dias. */
    public function vencendo(): bool
    {
        return $this->validade !== null && ! $this->vencido() && $this->validade->lte(now()->addDays(30));
    }

    /** Selo da lista: [texto, classe]. */
    public function selo(): array
    {
        return match (true) {
            $this->situacao === 'pendente' => ['Aguardando validação', 'b-warn'],
            $this->situacao === 'recusado' => ['Recusado', 'b-err'],
            $this->situacao === 'arquivado' => ['Arquivado', 'b-mute'],
            $this->vencido() => ['Vencido em '.$this->validade->format('d/m/Y'), 'b-err'],
            $this->vencendo() => ['Vence em '.$this->validade->format('d/m/Y'), 'b-warn'],
            default => ['Validado', 'b-ok'],
        };
    }

    public function tamanhoLegivel(): string
    {
        return $this->arquivo_tamanho >= 1048576
            ? number_format($this->arquivo_tamanho / 1048576, 1, ',', '.').' MB'
            : max(1, (int) round($this->arquivo_tamanho / 1024)).' KB';
    }

    public function ehImagem(): bool
    {
        return str_starts_with($this->arquivo_mime, 'image/');
    }

    protected function rotuloAuditoria(): string
    {
        return 'Documento "'.$this->titulo().'" de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id);
    }
}
