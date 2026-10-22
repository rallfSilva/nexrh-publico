<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Linha do tempo funcional: ingresso, nomeação, mudança de lotação, desligamento. */
#[Fillable(['orgao_id', 'servidor_id', 'data', 'tipo', 'descricao', 'detalhe', 'user_id'])]
class ServidorEvento extends Model
{
    use BelongsToOrgao;

    public const UPDATED_AT = null;

    protected $table = 'servidor_eventos';

    /** @var array<string, string> */
    public const TIPOS = [
        'ingresso' => 'Ingresso no órgão',
        'nomeacao' => 'Nomeação',
        'lotacao' => 'Lotação',
        'desligamento' => 'Desligamento',
        'readmissao' => 'Readmissão',
    ];

    protected function casts(): array
    {
        return ['data' => 'date'];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
