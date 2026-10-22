<?php

namespace App\Http\Controllers;

use App\Models\Vaga;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VagaController extends Controller
{
    public function edit(Vaga $vaga): View
    {
        return view('vagas.form', ['vaga' => $vaga->load(['cargo', 'servidor'])]);
    }

    public function update(Request $request, Vaga $vaga): RedirectResponse
    {
        // Vaga de servidor cadastrado: ocupante e ato vêm da ficha dele.
        if ($vaga->servidor()->exists()) {
            $vaga->update($request->validate([
                'base_legal' => ['nullable', 'string', 'max:255'],
                'observacao' => ['nullable', 'string', 'max:2000'],
            ]));

            return redirect()->route('cargos.show', $vaga->cargo_id)->with('sucesso', "Vaga nº {$vaga->numero} atualizada.");
        }

        $situacao = $request->input('situacao');
        $ocupada = $situacao === 'ocupada';
        $fora = in_array($situacao, Vaga::FORA_DO_ORGAO, true);

        $request->merge(['ocupante_cpf' => $request->filled('ocupante_cpf') ? Cpf::somenteDigitos($request->input('ocupante_cpf')) : null]);

        $dados = $request->validate([
            'situacao' => ['required', Rule::in(array_keys(Vaga::SITUACOES))],
            'ocupante_nome' => [Rule::requiredIf($ocupada), 'nullable', 'string', 'max:255'],
            'ocupante_cpf' => [Rule::requiredIf($ocupada), 'nullable', function ($attr, $valor, $fail) {
                if ($valor !== null && ! Cpf::valido($valor)) {
                    $fail('CPF inválido. Confira os números.');
                }
            }],
            'orgao_remanejamento' => [Rule::requiredIf($fora), 'nullable', 'string', 'max:255'],
            'ato_numero' => ['nullable', 'string', 'max:60'],
            'ato_data' => ['nullable', 'date'],
            'efeito_data' => ['nullable', 'date'],
            'base_legal' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'ocupante_nome.required' => 'Informe o nome de quem ocupa a vaga.',
            'ocupante_cpf.required' => 'Informe o CPF de quem ocupa a vaga.',
            'orgao_remanejamento.required' => 'Informe para qual órgão a vaga foi remanejada ou devolvida.',
        ]);

        if (! $ocupada) {
            $dados['ocupante_nome'] = null;
            $dados['ocupante_cpf'] = null;
        }
        if (! $fora) {
            $dados['orgao_remanejamento'] = null;
        }

        $vaga->update($dados);

        return redirect()->route('cargos.show', $vaga->cargo_id)->with('sucesso', "Vaga nº {$vaga->numero} atualizada.");
    }
}
