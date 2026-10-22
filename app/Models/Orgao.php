<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\OrgaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'sigla', 'cnpj', 'esfera', 'uf', 'municipio', 'ativo'])]
class Orgao extends Model
{
    /** @use HasFactory<OrgaoFactory> */
    use Auditable, HasFactory;

    protected $table = 'orgaos';

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    protected function rotuloAuditoria(): string
    {
        return 'Órgão '.$this->sigla;
    }
}
