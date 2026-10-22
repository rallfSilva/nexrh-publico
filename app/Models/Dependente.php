<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use App\Support\Cpf;
use App\Support\FichaSegad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dependente do servidor, com o código de parentesco da ficha e as finalidades
 * (imposto de renda, previdência e salário-família).
 *
 * Não é apagado: quando deixa de ser dependente, é encerrado com data e motivo.
 */
#[Fillable([
    'orgao_id', 'servidor_id', 'nome', 'cpf', 'data_nascimento', 'sexo', 'parentesco',
    'declarar_ir', 'declarar_previdencia', 'salario_familia', 'pcd', 'tipo_deficiencia', 'universitario',
    'encerrado_em', 'motivo_encerramento',
])]
#[Hidden(['cpf', 'cpf_hash'])]
class Dependente extends Model
{
    use Auditable, BelongsToOrgao;

    /** Filhos, enteados e equiparados: entram nos limites de idade. */
    public const PARENTESCOS_FILHO = ['03', '04', '06', '07', '10'];

    /** Cônjuge ou companheiro(a): só um ativo por servidor. */
    public const PARENTESCOS_CONJUGE = ['01', '02'];

    public const PARENTESCOS_UNIVERSITARIO = ['04', '07'];

    public const MOTIVOS_ENCERRAMENTO = [
        'Atingiu o limite de idade',
        'Concluiu o curso',
        'Separação ou divórcio',
        'Fim da guarda, tutela ou curatela',
        'Passou a ter renda própria',
        'Falecimento',
        'Cadastrado por engano',
        'Outro',
    ];

    protected function casts(): array
    {
        return [
            'cpf' => 'encrypted',
            'data_nascimento' => 'date',
            'encerrado_em' => 'date',
            'declarar_ir' => 'boolean',
            'declarar_previdencia' => 'boolean',
            'salario_familia' => 'boolean',
            'pcd' => 'boolean',
            'universitario' => 'boolean',
            'tipo_deficiencia' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Dependente $d) {
            $d->parentesco = str_pad((string) $d->parentesco, 2, '0', STR_PAD_LEFT);

            if ($d->isDirty('cpf')) {
                $d->cpf = Cpf::somenteDigitos($d->cpf);
                $d->cpf_hash = Cpf::hash($d->cpf);
            }
        });
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->whereNull('encerrado_em');
    }

    public function ativo(): bool
    {
        return $this->encerrado_em === null;
    }

    public function idade(): int
    {
        return (int) $this->data_nascimento->diffInYears(now());
    }

    public function rotuloParentesco(): string
    {
        return FichaSegad::PARENTESCOS[$this->parentesco] ?? $this->parentesco;
    }

    /** Rótulo curto para listas: "Filho(a) ou enteado(a)" sem o detalhe depois da vírgula. */
    public function parentescoCurto(): string
    {
        return match ($this->parentesco) {
            '02' => 'Companheiro(a)',
            '04' => 'Filho(a) universitário(a)',
            '06' => 'Irmão(ã) ou neto(a) sob guarda',
            '07' => 'Irmão(ã) ou neto(a) sob guarda, universitário(a)',
            '11' => 'Incapaz sob tutela ou curatela',
            default => $this->rotuloParentesco(),
        };
    }

    /** @return list<string> */
    public function finalidades(): array
    {
        return array_keys(array_filter([
            'Imposto de renda' => $this->declarar_ir,
            'Previdência' => $this->declarar_previdencia,
            'Salário-família' => $this->salario_familia,
        ]));
    }

    public function cpfMascarado(): ?string
    {
        return Cpf::mascarar($this->cpf);
    }

    public function cpfFormatado(): ?string
    {
        return Cpf::formatar($this->cpf);
    }

    /**
     * Situações que pedem revisão do RH, pelas regras de idade de cada finalidade.
     * Dependente com deficiência não tem limite de idade.
     *
     * IR: filho ou equiparado até 21 anos, ou até 24 se universitário (Lei nº 9.250/95, art. 35).
     * Salário-família: filho ou equiparado até 14 anos. Previdência: filho até 21 anos.
     *
     * @return list<string>
     */
    public function alertas(): array
    {
        if (! $this->ativo() || $this->pcd || ! in_array($this->parentesco, self::PARENTESCOS_FILHO, true)) {
            return [];
        }

        $idade = $this->idade();
        $limiteIr = $this->universitario ? 24 : 21;
        $a = [];

        if ($this->declarar_ir && $idade >= $limiteIr) {
            $a[] = "Tem {$idade} anos: passou do limite de {$limiteIr} anos para o imposto de renda".($this->universitario ? '.' : ' (24 se for universitário).');
        }
        if ($this->salario_familia && $idade >= 14) {
            $a[] = "Tem {$idade} anos: o salário-família vai só até os 14 anos.";
        }
        if ($this->declarar_previdencia && $idade >= 21) {
            $a[] = "Tem {$idade} anos: como dependente previdenciário, o filho vai até os 21 anos.";
        }

        return $a;
    }

    protected function camposSensiveisAuditoria(): array
    {
        return ['cpf_hash'];
    }

    protected function camposProtegidosAuditoria(): array
    {
        return ['cpf', 'pcd', 'tipo_deficiencia'];
    }

    protected function rotuloAuditoria(): string
    {
        return 'Dependente '.$this->nome.' de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id);
    }
}
