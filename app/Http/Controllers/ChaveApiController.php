<?php

namespace App\Http\Controllers;

use App\Models\ChaveApi;
use App\Models\Orgao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Chaves de acesso à API de consulta, criadas e revogadas pela administração do órgão. */
class ChaveApiController extends Controller
{
    public function index(Request $request): View
    {
        return view('api.chaves', [
            'chaves' => ChaveApi::with(['orgao', 'criadaPor'])->orderByRaw('revogada_em is not null')->latest('id')->get(),
            'orgaos' => $request->user()->orgao_id === null ? Orgao::where('ativo', true)->orderBy('sigla')->pluck('sigla', 'id')->all() : [],
            'criada' => session('chave_criada'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'escopos' => ['required', 'array', 'min:1'],
            'escopos.*' => [Rule::in(array_keys(ChaveApi::ESCOPOS))],
            'expira_em' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'orgao_id' => [$user->orgao_id === null ? 'required' : 'prohibited', 'nullable', Rule::exists('orgaos', 'id')->where('ativo', true)],
        ], [
            'escopos.required' => 'Escolha ao menos o que a chave pode consultar.',
            'orgao_id.required' => 'Escolha o órgão: cada chave enxerga um órgão só.',
        ]);

        $gerada = ChaveApi::gerar();
        $chave = ChaveApi::create([
            'orgao_id' => $user->orgao_id ?? $dados['orgao_id'],
            'nome' => $dados['nome'],
            'prefixo' => $gerada['prefixo'],
            'hash' => $gerada['hash'],
            'escopos' => array_values(array_intersect(array_keys(ChaveApi::ESCOPOS), $dados['escopos'])),
            'expira_em' => $dados['expira_em'] ?? null,
            'criada_por' => $user->id,
        ]);

        // A chave em texto vai só para a próxima tela: não fica no banco nem na trilha.
        return redirect()->route('api.chaves')->with('chave_criada', ['id' => $chave->id, 'texto' => $gerada['texto']]);
    }

    public function revogar(Request $request, ChaveApi $chave): RedirectResponse
    {
        abort_if($chave->revogada_em !== null, 409);

        $chave->forceFill(['revogada_em' => now(), 'revogada_por' => $request->user()->id])->save();

        return back()->with('sucesso', 'Chave "'.$chave->nome.'" revogada. Quem a usava passa a receber "acesso negado".');
    }
}
