<?php

namespace App\Http\Controllers;

use App\Models\Afastamento;
use App\Models\Servidor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Licenças e afastamentos, registrados pelo RH. Não passam pelo mínimo do setor
 * (não são escolha do servidor), mas não podem coincidir com outro afastamento
 * nem com férias marcadas: nesse caso o RH interrompe ou cancela as férias antes.
 */
class AfastamentoController extends Controller
{
    public function create(Servidor $servidor): View
    {
        abort_unless($servidor->ativo(), 404);

        return view('ferias.afastamento', ['servidor' => $servidor, 'afastamento' => new Afastamento]);
    }

    public function store(Request $request, Servidor $servidor): RedirectResponse
    {
        abort_unless($servidor->ativo(), 404);

        $dados = $request->validate([
            'tipo' => ['required', Rule::in(Afastamento::TIPOS)],
            'inicio' => ['required', 'date_format:Y-m-d', 'after:2000-01-01'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio', 'before:2100-01-01'],
            'documento' => ['nullable', 'string', 'max:120'],
            'processo_sei' => ['nullable', 'string', 'max:40'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], [
            'tipo.required' => 'Escolha o tipo.',
            'tipo.in' => 'Escolha um tipo da lista.',
            'inicio.required' => 'Informe o primeiro dia.',
            'fim.required' => 'Informe o último dia.',
            'fim.after_or_equal' => 'O último dia não pode ser antes do primeiro.',
        ]);

        $inicio = Carbon::parse($dados['inicio']);
        $fim = Carbon::parse($dados['fim']);

        if ($outro = $servidor->afastamentos()->validos()->entre($inicio, $fim)->first()) {
            return back()->withInput()->withErrors(['inicio' => 'Já há afastamento registrado de '.$outro->periodoTexto().' que coincide com estas datas.']);
        }
        if ($ferias = $servidor->ferias()->ocupam()->entre($inicio, $fim)->first()) {
            return back()->withInput()->withErrors(['inicio' => 'Há férias marcadas de '.$ferias->periodoTexto().'. '.($ferias->podeInterromper() ? 'Interrompa as férias' : 'Cancele ou remarque as férias').' antes de registrar o afastamento.']);
        }

        Afastamento::create([...$dados, 'orgao_id' => $servidor->orgao_id, 'servidor_id' => $servidor->id, 'registrado_por' => $request->user()->id]);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'ferias'])
            ->with('sucesso', 'Afastamento de '.$servidor->nome.' registrado: '.$inicio->format('d/m/Y').' a '.$fim->format('d/m/Y').'.');
    }

    public function cancelar(Request $request, Servidor $servidor, Afastamento $afastamento): RedirectResponse
    {
        abort_unless($afastamento->valido(), 409);
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:255']], ['motivo.required' => 'Informe por que o afastamento foi cancelado.'])['motivo'];

        $afastamento->update(['cancelado_em' => now(), 'motivo_cancelamento' => $motivo]);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'ferias'])
            ->with('sucesso', 'Afastamento de '.$afastamento->periodoTexto().' cancelado. Continua no histórico.');
    }
}
