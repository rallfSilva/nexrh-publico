<?php

namespace App\Http\Controllers;

use App\Models\Orgao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrgaoController extends Controller
{
    public function index(): View
    {
        return view('orgaos.index', [
            'orgaos' => Orgao::withCount(['unidades', 'usuarios'])->orderBy('sigla')->get(),
        ]);
    }

    public function create(): View
    {
        return view('orgaos.form', ['orgao' => new Orgao(['esfera' => 'estadual', 'uf' => 'RR', 'municipio' => 'Boa Vista', 'ativo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $orgao = Orgao::create($this->validar($request));

        return redirect()->route('orgaos.index')->with('sucesso', "Órgão {$orgao->sigla} cadastrado.");
    }

    public function edit(Orgao $orgao): View
    {
        return view('orgaos.form', ['orgao' => $orgao]);
    }

    public function update(Request $request, Orgao $orgao): RedirectResponse
    {
        $orgao->update($this->validar($request, $orgao));

        return redirect()->route('orgaos.index')->with('sucesso', "Órgão {$orgao->sigla} atualizado.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Orgao $orgao = null): array
    {
        $request->merge(['sigla' => mb_strtoupper(trim((string) $request->input('sigla')))]);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:30', Rule::unique('orgaos', 'sigla')->ignore($orgao)],
            'cnpj' => ['nullable', 'string', 'regex:/^\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}$/', Rule::unique('orgaos', 'cnpj')->ignore($orgao)],
            'esfera' => ['required', Rule::in(['municipal', 'estadual', 'federal'])],
            'uf' => ['required', 'string', 'size:2'],
            'municipio' => ['required', 'string', 'max:255'],
        ], ['cnpj.regex' => 'Informe o CNPJ no formato 00.000.000/0000-00.']);

        $dados['uf'] = mb_strtoupper($dados['uf']);
        $dados['ativo'] = $request->boolean('ativo');

        return $dados;
    }
}
