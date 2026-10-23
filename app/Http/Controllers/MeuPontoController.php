<?php

namespace App\Http\Controllers;

use App\Frequencia\Espelho;
use App\Frequencia\Justificador;
use App\Frequencia\Marcador;
use App\Models\Justificativa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** O próprio servidor registra entrada e saída, confere o espelho e justifica as ocorrências. */
class MeuPontoController extends Controller
{
    public function show(Request $request, Marcador $marcador, Justificador $justificador): View
    {
        $servidor = $request->user()->servidor;

        if ($servidor === null) {
            return view('servidores.sem-ficha');
        }

        $mes = FrequenciaController::mesPedido($request);
        $espelho = (new Espelho)->carregar(collect([$servidor]), $mes);
        $hojeEspelho = $mes->isSameMonth(today()) ? $espelho : (new Espelho)->carregar(collect([$servidor]), today());
        $hoje = collect($hojeEspelho->calcular($servidor, $request->user())['dias'])->first(fn ($d) => $d['data']->isToday());

        return view('frequencia.meu-ponto', [
            'servidor' => $servidor,
            'mes' => $mes,
            'calculo' => $espelho->calcular($servidor, $request->user()),
            'fechamento' => $espelho->fechamentoDe($servidor),
            'hoje' => $hoje,
            'impedimento' => $marcador->impedimento($servidor, $request->ip(), $hojeEspelho),
            'jornadaHoje' => $hojeEspelho->jornadaEm($servidor, today()),
            // Prazo para justificar: o mês atual e o anterior.
            'ocorrencias' => $justificador->ocorrencias($servidor, today()->subMonthNoOverflow()->startOfMonth(), today()->subDay(), $request->user())->sortByDesc('data')->values(),
            'justificativas' => Justificativa::with('analisadoPor:id,name')->where('servidor_id', $servidor->id)->where('situacao', '!=', 'retirada')
                ->latest('id')->limit(6)->get(),
        ]);
    }

    public function registrar(Request $request, Marcador $marcador): RedirectResponse
    {
        $servidor = $request->user()->servidor ?? abort(404);
        $registro = $marcador->registrar($servidor, $request->user(), $request->ip());

        return redirect()->route('meu-ponto')->with('sucesso', 'Ponto registrado às '.$registro->momento->format('H:i').' de '.$registro->momento->format('d/m/Y').'.');
    }
}
