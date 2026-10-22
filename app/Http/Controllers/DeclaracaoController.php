<?php

namespace App\Http\Controllers;

use App\Models\Declaracao;
use App\Models\Servidor;
use App\Servidores\RegrasDeclaracao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class DeclaracaoController extends Controller
{
    public function create(Servidor $servidor, string $tipo): View
    {
        $anterior = $servidor->ultimaDeclaracao($tipo);

        // Parte da última declaração do mesmo tipo: em geral só muda o ano ou um item.
        $valores = $anterior?->conteudo ?? [];
        if ($tipo === 'parentesco' && $anterior === null) {
            $valores['cargo_comissao'] = $servidor->vaga?->cargo?->denominacao;
            $valores['cargo_efetivo'] = $servidor->cargo_efetivo;
            $valores['vinculo_funcional'] = $servidor->servidor_efetivo
                ? 'Efetivo ocupante de função de confiança ou cargo em comissão'
                : 'Ocupante de função de confiança ou cargo em comissão';
        }

        return view('declaracoes.form', [
            'servidor' => $servidor,
            'tipo' => $tipo,
            'valores' => $valores,
            'anterior' => $anterior,
            'ano' => $tipo === 'bens' ? max((int) now()->year, ($anterior?->ano_referencia ?? 0) + 1) : null,
        ]);
    }

    public function store(Request $request, Servidor $servidor, string $tipo): RedirectResponse
    {
        $dados = RegrasDeclaracao::normalizar($request->except('_token'));
        $validados = Validator::make($dados, RegrasDeclaracao::regras($tipo), RegrasDeclaracao::mensagens())->validate();

        Declaracao::create([
            'orgao_id' => $servidor->orgao_id,
            'servidor_id' => $servidor->id,
            'tipo' => $tipo,
            'data_declaracao' => $validados['data_declaracao'],
            'ano_referencia' => $validados['ano_referencia'] ?? null,
            'conteudo' => RegrasDeclaracao::montar($tipo, $validados),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'declaracoes'])
            ->with('sucesso', Declaracao::TIPOS[$tipo].' registrada.');
    }
}
