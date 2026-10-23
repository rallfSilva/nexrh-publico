<?php

namespace App\Http\Middleware;

use App\Models\ChaveApi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a chamada à API pela chave (cabeçalho "Authorization: Bearer nxr_...")
 * e confere o escopo pedido. A chave só enxerga o próprio órgão.
 */
class AutenticarChaveApi
{
    public function handle(Request $request, Closure $next, ?string $escopo = null): Response
    {
        $chave = $request->attributes->get('chave_api');

        if ($chave === null) {
            $texto = (string) $request->bearerToken();
            $chave = $texto !== '' ? ChaveApi::porTexto($texto) : null;

            if ($chave === null || ! $chave->valida() || ! $chave->orgao?->ativo) {
                return response()->json(['erro' => 'Chave de acesso ausente, inválida, revogada ou expirada.'], 401, ['WWW-Authenticate' => 'Bearer']);
            }

            ChaveApi::whereKey($chave->id)->update(['ultimo_uso_em' => now(), 'ultimo_ip' => $request->ip(), 'usos' => $chave->usos + 1]);
            Auth::setUser($chave->usuarioTecnico());
            $request->attributes->set('chave_api', $chave);
        }

        if ($escopo !== null && ! $chave->permite($escopo)) {
            return response()->json(['erro' => "Esta chave não tem acesso a \"{$escopo}\". Peça ao administrador do órgão para incluir o escopo."], 403);
        }

        $resposta = $next($request);
        $resposta->headers->set('Cache-Control', 'no-store, private');

        return $resposta;
    }
}
