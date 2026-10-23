<?php

namespace App\Http\Controllers;

use App\Models\RegraFerias;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Manual do sistema, por perfil. Aberto a todos que entram; os trechos do perfil de quem lê vêm marcados. */
class AjudaController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('ajuda.index', [
            'perfil' => $user->getRoleNames()->first(),
            'perfis' => User::PERFIS,
            'regra' => $user->orgao_id ? RegraFerias::doOrgao($user->orgao_id) : new RegraFerias(RegraFerias::PADRAO),
            'imagem' => fn (string $nome) => is_file(public_path("img/ajuda/{$nome}.jpg")) ? asset("img/ajuda/{$nome}.jpg") : null,
        ]);
    }
}
