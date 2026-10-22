<?php

namespace App\Http\Controllers;

use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PainelController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $usuarios = User::visiveisPara($user);

        $ultimosEventos = $user->can('auditoria.ver')
            ? RegistroAuditoria::with('usuario')->latest('id')->limit(6)->get()
            : collect();

        return view('painel', [
            'totais' => [
                'orgaos' => $user->orgao_id === null ? Orgao::count() : null,
                'unidades' => Unidade::where('ativo', true)->count(),
                'usuarios' => (clone $usuarios)->where('ativo', true)->count(),
                'semDoisFatores' => (clone $usuarios)->where('ativo', true)->whereNull('two_factor_confirmed_at')->count(),
                'acessosHoje' => (clone $usuarios)->where('ultimo_acesso_em', '>=', today())->count(),
            ],
            'ultimosEventos' => $ultimosEventos,
            'cargos' => $user->can('cargos.ver') ? [
                'ocupadas' => Vaga::where('situacao', 'ocupada')->count(),
                'vagas' => Vaga::where('situacao', 'vaga')->count(),
            ] : null,
            'unidadesSemChefe' => Unidade::where('ativo', true)->whereNull('chefe_user_id')->orderBy('sigla')->limit(5)->get(),
        ]);
    }
}
