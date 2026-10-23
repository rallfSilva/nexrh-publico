<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use App\Support\Cpf;
use App\Support\FichaSegad;
use Database\Factories\ServidorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Servidor do órgão, com os campos da Ficha de Cadastro da SEGAD/RR.
 *
 * Não é o mesmo que usuário: o servidor pode existir sem acesso ao sistema.
 * Quando tem acesso, user_id liga os dois e ele vê a própria ficha.
 */
#[Fillable([
    'orgao_id', 'user_id', 'unidade_id', 'vaga_id', 'situacao',
    'decreto_nomeacao', 'data_decreto', 'doe_numero', 'data_doe', 'efeitos_a_partir_de', 'processo_sei', 'matricula_comissionado', 'carga_horaria',
    'nome', 'cpf', 'sexo', 'estado_civil', 'raca_cor', 'pcd', 'tipo_deficiencia', 'molestia_grave', 'nacionalidade', 'naturalidade', 'data_nascimento', 'tipo_sanguineo', 'nome_mae', 'nome_pai',
    'pis_pasep', 'rg_numero', 'rg_orgao_uf', 'rg_data_expedicao',
    'reservista_numero', 'reservista_orgao_uf', 'reservista_data_expedicao', 'reservista_categoria', 'reservista_ano',
    'titulo_numero', 'titulo_zona', 'titulo_secao', 'titulo_data_expedicao', 'titulo_cidade', 'titulo_uf',
    'ctps_numero', 'ctps_serie', 'ctps_uf', 'ctps_data_expedicao',
    'cnh_numero', 'cnh_uf', 'cnh_categoria', 'cnh_data_expedicao', 'cnh_data_validade', 'cnh_data_primeira',
    'estrangeiro_data_chegada', 'estrangeiro_ano_chegada', 'estrangeiro_data_limite', 'estrangeiro_rne',
    'grau_instrucao', 'curso', 'instituicao_curso', 'ano_inicio_primeiro_emprego', 'ano_fim_primeiro_emprego', 'vaga_pcd_ou_reabilitacao',
    'servidor_efetivo', 'matricula_efetiva', 'cargo_efetivo', 'servidor_federal', 'matricula_siape',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf', 'celular', 'email',
    'banco_codigo', 'banco_nome', 'agencia', 'conta_corrente',
    'data_desligamento', 'ato_desligamento', 'motivo_desligamento',
])]
#[Hidden(['cpf', 'cpf_hash', 'pis_pasep', 'rg_numero', 'reservista_numero', 'titulo_numero', 'ctps_numero', 'cnh_numero', 'estrangeiro_rne', 'agencia', 'conta_corrente'])]
class Servidor extends Model
{
    /** @use HasFactory<ServidorFactory> */
    use Auditable, BelongsToOrgao, HasFactory;

    protected $table = 'servidores';

    /** @var array<string, string> */
    public const SITUACOES = ['ativo' => 'Ativo', 'desligado' => 'Desligado'];

    public const MOTIVOS_DESLIGAMENTO = [
        'Exoneração a pedido',
        'Exoneração de ofício',
        'Fim da cessão ou requisição',
        'Aposentadoria',
        'Falecimento',
        'Outro',
    ];

    /** Documentos e dados bancários: criptografados no banco. */
    public const CAMPOS_CRIPTOGRAFADOS = ['cpf', 'pis_pasep', 'rg_numero', 'reservista_numero', 'titulo_numero', 'ctps_numero', 'cnh_numero', 'estrangeiro_rne', 'agencia', 'conta_corrente'];

    /** Dados sensíveis da LGPD (art. 11): só RH e administração do órgão veem. */
    public const CAMPOS_SENSIVEIS_LGPD = ['raca_cor', 'pcd', 'tipo_deficiencia', 'molestia_grave', 'tipo_sanguineo'];

    protected function casts(): array
    {
        $casts = array_fill_keys(self::CAMPOS_CRIPTOGRAFADOS, 'encrypted');

        foreach (['data_decreto', 'data_doe', 'efeitos_a_partir_de', 'data_nascimento', 'rg_data_expedicao', 'reservista_data_expedicao', 'titulo_data_expedicao', 'ctps_data_expedicao', 'cnh_data_expedicao', 'cnh_data_validade', 'cnh_data_primeira', 'estrangeiro_data_chegada', 'estrangeiro_data_limite', 'data_desligamento'] as $data) {
            $casts[$data] = 'date';
        }

        foreach (['pcd', 'molestia_grave', 'vaga_pcd_ou_reabilitacao', 'servidor_efetivo', 'servidor_federal'] as $logico) {
            $casts[$logico] = 'boolean';
        }

        foreach (['estado_civil', 'raca_cor', 'tipo_deficiencia', 'reservista_categoria', 'reservista_ano', 'grau_instrucao', 'ano_inicio_primeiro_emprego', 'ano_fim_primeiro_emprego', 'estrangeiro_ano_chegada'] as $inteiro) {
            $casts[$inteiro] = 'integer';
        }

        return $casts;
    }

    protected static function booted(): void
    {
        static::saving(function (Servidor $servidor) {
            if ($servidor->isDirty('cpf')) {
                $servidor->cpf = Cpf::somenteDigitos($servidor->cpf);
                $servidor->cpf_hash = Cpf::hash($servidor->cpf);
            }

            // Liga o acesso ao sistema pelo CPF: quem tem usuário passa a ver a própria ficha.
            if ($servidor->user_id === null && $servidor->cpf) {
                $servidor->user_id = User::where('orgao_id', $servidor->orgao_id)
                    ->where('cpf', $servidor->cpf)
                    ->whereDoesntHave('servidor')
                    ->value('id');
            }
        });
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function vaga(): BelongsTo
    {
        return $this->belongsTo(Vaga::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function declaracoes(): HasMany
    {
        return $this->hasMany(Declaracao::class)->orderByDesc('data_declaracao')->orderByDesc('id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function dependentes(): HasMany
    {
        return $this->hasMany(Dependente::class)->orderBy('data_nascimento')->orderBy('id');
    }

    public function periodosAquisitivos(): HasMany
    {
        return $this->hasMany(PeriodoAquisitivo::class)->orderBy('inicio');
    }

    public function ferias(): HasMany
    {
        return $this->hasMany(Ferias::class)->orderBy('inicio')->orderBy('id');
    }

    public function afastamentos(): HasMany
    {
        return $this->hasMany(Afastamento::class)->orderByDesc('inicio')->orderByDesc('id');
    }

    public function jornadas(): HasMany
    {
        return $this->hasMany(Jornada::class)->orderByDesc('inicio')->orderByDesc('id');
    }

    public function registrosPonto(): HasMany
    {
        return $this->hasMany(RegistroPonto::class)->orderBy('momento');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(ServidorEvento::class)->orderByDesc('data')->orderByDesc('id');
    }

    /**
     * Servidores que o usuário pode ver: todo o órgão para RH, administração e auditoria;
     * só a própria equipe (unidades que chefia e as subordinadas) para a chefia.
     */
    public function scopeVisiveisPara(Builder $query, User $usuario): Builder
    {
        if ($usuario->can('servidores.ver')) {
            return $query;
        }

        if ($usuario->can('servidores.ver_equipe')) {
            return $query->whereIn('unidade_id', $usuario->idsUnidadesChefiadas());
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeBusca(Builder $query, ?string $termo): Builder
    {
        $termo = trim((string) $termo);

        if ($termo === '') {
            return $query;
        }

        $digitos = Cpf::somenteDigitos($termo);
        if (strlen($digitos) === 11) {
            return $query->where('cpf_hash', Cpf::hash($digitos));
        }

        return $query->where(fn (Builder $q) => $q
            ->whereRaw('unaccent(nome) ilike unaccent(?)', ['%'.$termo.'%'])
            ->orWhere('matricula_comissionado', 'ilike', '%'.$termo.'%')
            ->orWhere('matricula_efetiva', 'ilike', '%'.$termo.'%')
            ->orWhere('decreto_nomeacao', 'ilike', '%'.$termo.'%'));
    }

    public static function porCpf(string $cpf): Builder
    {
        return static::query()->where('cpf_hash', Cpf::hash($cpf));
    }

    public function cpfMascarado(): ?string
    {
        return Cpf::mascarar($this->cpf);
    }

    public function cpfFormatado(): ?string
    {
        return Cpf::formatar($this->cpf);
    }

    public function iniciais(): string
    {
        $partes = array_values(array_filter(explode(' ', $this->nome), fn ($p) => mb_strlen($p) > 2));

        return mb_strtoupper(mb_substr($partes[0] ?? $this->nome, 0, 1).mb_substr($partes[count($partes) - 1] ?? '', 0, 1));
    }

    public function ativo(): bool
    {
        return $this->situacao === 'ativo';
    }

    /** Matrícula exibida: a do comissionado ou, na falta, a efetiva. */
    public function matricula(): ?string
    {
        return $this->matricula_comissionado ?: $this->matricula_efetiva;
    }

    public function rotuloVinculo(): string
    {
        return match (true) {
            $this->servidor_federal => 'Federal cedido',
            $this->servidor_efetivo && $this->vaga_id !== null => 'Efetivo + comissão',
            $this->servidor_efetivo => 'Efetivo',
            default => 'Comissionado',
        };
    }

    public function rotulo(string $lista, int|string|null $codigo): ?string
    {
        return FichaSegad::comCodigo(constant(FichaSegad::class.'::'.$lista), $codigo);
    }

    /** Declaração mais recente de um tipo. */
    public function ultimaDeclaracao(string $tipo): ?Declaracao
    {
        return $this->relationLoaded('declaracoes')
            ? $this->declaracoes->firstWhere('tipo', $tipo)
            : $this->declaracoes()->where('tipo', $tipo)->first();
    }

    /**
     * O que falta para a ficha ficar em dia.
     *
     * @return list<array{titulo: string, detalhe: string}>
     */
    public function pendencias(): array
    {
        if (! $this->ativo()) {
            return [];
        }

        $p = [];
        $ano = (int) now()->year;

        if ($this->vaga_id === null) {
            $p[] = ['titulo' => 'Sem vaga vinculada', 'detalhe' => 'Escolha a vaga do quadro que este servidor ocupa.'];
        }
        if ($this->ultimaDeclaracao('parentesco') === null) {
            $p[] = ['titulo' => 'Declaração de parentesco pendente', 'detalhe' => 'Exigida na posse (Súmula Vinculante nº 13).'];
        }
        if ($this->ultimaDeclaracao('acumulacao') === null) {
            $p[] = ['titulo' => 'Declaração de acumulação pendente', 'detalhe' => 'Exigida na posse e sempre que mudar.'];
        }
        $bens = $this->ultimaDeclaracao('bens');
        if ($bens === null || $bens->ano_referencia < $ano) {
            $p[] = ['titulo' => "Declaração de bens de {$ano} pendente", 'detalhe' => $bens ? "A última é de {$bens->ano_referencia}. Renovação anual (Lei nº 8.429/92, art. 13)." : 'Exigida na posse e todo ano (Lei nº 8.429/92, art. 13).'];
        }
        if ($this->sexo === 'M' && $this->reservista_numero === null && $this->data_nascimento?->diffInYears(now()) < 46) {
            $p[] = ['titulo' => 'Reservista não informado', 'detalhe' => 'Obrigatório para o sexo masculino.'];
        }
        if ($this->cnh_data_validade !== null && $this->cnh_data_validade->isPast()) {
            $p[] = ['titulo' => 'CNH vencida', 'detalhe' => 'Venceu em '.$this->cnh_data_validade->format('d/m/Y').'.'];
        }

        return $p;
    }

    /**
     * Pendências do dossiê e dos dependentes. Ficam à parte porque só quem vê
     * esses dados (RH, administração e o próprio servidor) deve recebê-las.
     *
     * @return list<array{titulo: string, detalhe: string}>
     */
    public function pendenciasDoDossie(): array
    {
        if (! $this->ativo()) {
            return [];
        }

        $p = [];
        $documentos = $this->documentos->where('situacao', '!=', 'arquivado');

        if (($n = $documentos->where('situacao', 'pendente')->count()) > 0) {
            $p[] = ['titulo' => $n === 1 ? '1 documento aguardando validação' : "{$n} documentos aguardando validação", 'detalhe' => 'Enviado(s) pelo servidor. O RH confere e valida ou recusa.'];
        }
        foreach ($documentos->where('situacao', 'recusado') as $d) {
            $p[] = ['titulo' => 'Documento recusado: '.$d->titulo(), 'detalhe' => $d->motivo ? 'Motivo: '.$d->motivo : 'Envie de novo.'];
        }
        foreach ($documentos->where('situacao', 'validado')->filter->vencido() as $d) {
            $p[] = ['titulo' => $d->titulo().' vencido', 'detalhe' => 'Venceu em '.$d->validade->format('d/m/Y').'. Peça a versão atual.'];
        }
        if (($faltam = $this->documentosDaPosseFaltando()) !== []) {
            $p[] = ['titulo' => 'Dossiê da posse incompleto', 'detalhe' => count($faltam).' de '.count(Documento::EXIGIDOS_POSSE).' documentos faltando: '.implode(', ', array_slice($faltam, 0, 3)).(count($faltam) > 3 ? ' e outros.' : '.')];
        }
        foreach ($this->dependentes->filter->ativo() as $dep) {
            foreach ($dep->alertas() as $alerta) {
                $p[] = ['titulo' => 'Revisar dependente '.$dep->nome, 'detalhe' => $alerta];
            }
        }

        return $p;
    }

    /**
     * Pendências de férias: pedidos aguardando aprovação e períodos vencidos com saldo
     * (acumulados). Espera períodos, férias e afastamentos já carregados.
     *
     * @return list<array{titulo: string, detalhe: string}>
     */
    public function pendenciasDeFerias(): array
    {
        if (! $this->ativo()) {
            return [];
        }

        $p = [];
        $aguardando = $this->ferias->where('situacao', 'programada');
        if ($aguardando->isNotEmpty()) {
            $p[] = ['titulo' => 'Férias aguardando aprovação', 'detalhe' => $aguardando->map->periodoTexto()->implode('; ').'. A chefia ou o RH aprova.'];
        }

        $regra = RegraFerias::doOrgao($this->orgao_id);
        $acumulados = $this->periodosAquisitivos->filter(fn (PeriodoAquisitivo $pa) => $pa->completo() && $pa->saldo() > 0);
        if ($acumulados->count() >= $regra->max_periodos_acumulados) {
            $p[] = [
                'titulo' => 'Férias acumuladas: '.$acumulados->count().' períodos com saldo',
                'detalhe' => 'Períodos '.$acumulados->map->rotulo()->implode(', ').'. O limite é de '.$regra->max_periodos_acumulados.' períodos acumulados: marque as férias do mais antigo.',
            ];
        }

        return $p;
    }

    /**
     * Documentos exigidos na posse que ainda não estão validados no dossiê.
     *
     * @return list<string> rótulos
     */
    public function documentosDaPosseFaltando(): array
    {
        $validados = $this->documentos->where('situacao', 'validado')->pluck('tipo')->all();
        $todos = Documento::todosOsTipos();

        return array_values(array_map(
            fn ($tipo) => $todos[$tipo],
            array_diff(array_keys(Documento::EXIGIDOS_POSSE), $validados),
        ));
    }

    /** Data de ingresso no órgão: efeitos do ato de nomeação ou, na falta, o cadastro. */
    public function ingresso(): ?Carbon
    {
        return $this->efeitos_a_partir_de ?? $this->created_at;
    }

    protected function camposSensiveisAuditoria(): array
    {
        return ['cpf_hash'];
    }

    protected function camposProtegidosAuditoria(): array
    {
        return [...self::CAMPOS_CRIPTOGRAFADOS, ...self::CAMPOS_SENSIVEIS_LGPD];
    }

    protected function rotuloAuditoria(): string
    {
        return 'Servidor '.$this->nome;
    }
}
