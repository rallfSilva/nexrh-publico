<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma planilha enviada para importação: primeiro é analisada (prévia e divergências),
 * depois aplicada ou cancelada. Os dados lidos ficam criptografados até a aplicação.
 */
#[Fillable(['orgao_id', 'user_id', 'tipo', 'formato', 'arquivo_nome', 'arquivo_caminho', 'arquivo_hash', 'situacao', 'resumo', 'divergencias', 'dados', 'aplicada_em'])]
#[Hidden(['dados'])]
class Importacao extends Model
{
    use BelongsToOrgao;

    protected $table = 'importacoes';

    /** @var array<string, string> */
    public const FORMATOS = [
        'estrutura_selc' => 'Planilha de estrutura de cargos (formato usado pela SELC)',
        'modelo_nexrh' => 'Modelo padrão do NexRH (02_modelo_cargos_e_vagas)',
        'modelo_servidores' => 'Modelo padrão do NexRH (03_modelo_servidores)',
    ];

    /** @var array<string, string> */
    public const TIPOS = [
        'cargos' => 'Quadro de cargos',
        'servidores' => 'Servidores',
    ];

    /** @var array<string, string> */
    public const SITUACOES = [
        'analisada' => 'Aguardando confirmação',
        'aplicada' => 'Aplicada',
        'cancelada' => 'Cancelada',
        'recusada' => 'Recusada (erros impedem a importação)',
    ];

    protected function casts(): array
    {
        return [
            'resumo' => 'array',
            'divergencias' => 'array',
            'dados' => 'encrypted:array',
            'aplicada_em' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return list<array{nivel: string, cargo: ?string, mensagem: string}> */
    public function divergenciasDoNivel(string $nivel): array
    {
        return array_values(array_filter($this->divergencias ?? [], fn ($d) => $d['nivel'] === $nivel));
    }

    public function podeAplicar(): bool
    {
        return $this->situacao === 'analisada' && $this->divergenciasDoNivel('bloqueia') === [];
    }
}
