<?php

namespace App\Support;

use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class Auditoria
{
    /**
     * @param  array<string, mixed>|null  $antigos
     * @param  array<string, mixed>|null  $novos
     */
    public static function registrar(
        string $evento,
        ?Model $alvo,
        string $descricao,
        ?array $antigos = null,
        ?array $novos = null,
        ?User $usuario = null,
    ): RegistroAuditoria {
        $usuario ??= Auth::hasUser() ? Auth::user() : null;

        $orgaoId = $alvo?->getAttribute('orgao_id') ?? $usuario?->orgao_id;

        if ($alvo instanceof Orgao) {
            $orgaoId = $alvo->getKey();
        }

        return RegistroAuditoria::create([
            'orgao_id' => $orgaoId,
            'user_id' => $usuario?->getKey(),
            'evento' => $evento,
            'auditable_type' => $alvo?->getMorphClass(),
            'auditable_id' => $alvo?->getKey(),
            'descricao' => mb_substr($descricao, 0, 255),
            'valores_antigos' => $antigos,
            'valores_novos' => $novos,
            'ip' => app()->runningInConsole() ? null : Request::ip(),
            'user_agent' => app()->runningInConsole() ? 'console' : mb_substr((string) Request::userAgent(), 0, 500),
        ]);
    }
}
