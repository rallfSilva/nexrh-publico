<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Orgao;
use App\Models\Vaga;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CargoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $orgaos = $user->orgao_id === null ? Orgao::orderBy('sigla')->get() : collect();
        $orgaoId = $user->orgao_id ?? $request->integer('orgao') ?: $orgaos->first()?->id;

        $cargos = Cargo::query()
            ->where('orgao_id', $orgaoId)
            ->withCount([
                'vagas as ocupadas_count' => fn ($q) => $q->where('situacao', 'ocupada'),
                'vagas as livres_count' => fn ($q) => $q->where('situacao', 'vaga'),
                'vagas as remanejadas_count' => fn ($q) => $q->whereIn('situacao', Vaga::FORA_DO_ORGAO),
            ])
            ->when(! $request->boolean('inativos'), fn ($q) => $q->where('ativo', true))
            ->when($request->filled('natureza'), fn ($q) => $q->where('natureza', $request->string('natureza')))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = '%'.mb_strtolower($request->string('busca')).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(denominacao) like ?', [$termo])->orWhereRaw('lower(simbolo) like ?', [$termo]));
            })
            ->orderByDesc('remuneracao')
            ->orderBy('denominacao')
            ->get();

        if ($request->boolean('com_vagas')) {
            $cargos = $cargos->filter(fn (Cargo $c) => $c->livres_count > 0)->values();
        }

        $totais = ['total' => 0, 'remanejadas' => 0, 'disponiveis' => 0, 'ocupadas' => 0, 'vagas' => 0];
        foreach ($cargos as $cargo) {
            foreach ($cargo->totais() as $campo => $valor) {
                $totais[$campo] += $valor;
            }
        }

        return view('cargos.index', compact('cargos', 'totais', 'orgaos', 'orgaoId'));
    }

    public function show(Cargo $cargo): View
    {
        $cargo->load('vagas');

        return view('cargos.show', ['cargo' => $cargo, 'totais' => $cargo->totais()]);
    }

    public function create(Request $request): View
    {
        return view('cargos.form', [
            'cargo' => new Cargo(['natureza' => 'comissao', 'ativo' => true, 'quantidade_vagas' => 1]),
            'orgaos' => $request->user()->orgao_id === null ? Orgao::where('ativo', true)->orderBy('sigla')->get() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);

        $cargo = DB::transaction(function () use ($dados) {
            $cargo = Cargo::create($dados);
            foreach (range(1, $cargo->quantidade_vagas) as $numero) {
                $cargo->vagas()->create(['orgao_id' => $cargo->orgao_id, 'numero' => $numero, 'situacao' => 'vaga', 'base_legal' => $cargo->base_legal]);
            }

            return $cargo;
        });

        return redirect()->route('cargos.show', $cargo)->with('sucesso', "Cargo {$cargo->denominacao} cadastrado com {$cargo->quantidade_vagas} vaga(s).");
    }

    public function edit(Cargo $cargo): View
    {
        return view('cargos.form', ['cargo' => $cargo, 'orgaos' => collect()]);
    }

    public function update(Request $request, Cargo $cargo): RedirectResponse
    {
        $dados = $this->validar($request, $cargo);
        $novaQuantidade = $dados['quantidade_vagas'];

        DB::transaction(function () use ($cargo, $dados, $novaQuantidade) {
            $maiorNumero = (int) $cargo->vagas()->max('numero');

            if ($novaQuantidade < $cargo->vagas()->count()) {
                $excedentes = $cargo->vagas()->reorder('numero', 'desc')->limit($cargo->vagas()->count() - $novaQuantidade)->get();

                if ($excedentes->contains(fn (Vaga $v) => $v->situacao !== 'vaga')) {
                    throw ValidationException::withMessages([
                        'quantidade_vagas' => 'Só é possível reduzir a quantidade apagando vagas livres do fim da lista. Libere ou renumere as vagas antes.',
                    ]);
                }

                $excedentes->each->delete();
            }

            for ($n = $cargo->vagas()->count(); $n < $novaQuantidade; $n++) {
                $cargo->vagas()->create(['orgao_id' => $cargo->orgao_id, 'numero' => ++$maiorNumero, 'situacao' => 'vaga', 'base_legal' => $dados['base_legal']]);
            }

            $cargo->update($dados);
        });

        return redirect()->route('cargos.show', $cargo)->with('sucesso', 'Cargo atualizado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Cargo $cargo = null): array
    {
        $user = $request->user();
        $orgaoId = $cargo?->orgao_id ?? $user->orgao_id ?? $request->integer('orgao_id');

        if ($request->filled('remuneracao')) {
            $request->merge(['remuneracao' => str_replace(['.', ','], ['', '.'], (string) $request->input('remuneracao'))]);
        }
        $request->merge(['simbolo' => mb_strtoupper(trim((string) $request->input('simbolo')))]);

        $dados = $request->validate([
            'orgao_id' => [Rule::requiredIf($user->orgao_id === null && ! $cargo), 'nullable', Rule::exists('orgaos', 'id')],
            'denominacao' => ['required', 'string', 'max:255', Rule::unique('cargos')->where('orgao_id', $orgaoId)->where('simbolo', $request->input('simbolo'))->ignore($cargo)],
            'simbolo' => ['required', 'string', 'max:30'],
            'natureza' => ['required', Rule::in(array_keys(Cargo::NATUREZAS))],
            'remuneracao' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'quantidade_vagas' => ['required', 'integer', 'min:1', 'max:999'],
            'base_legal' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'denominacao.unique' => 'Já existe um cargo com essa denominação e símbolo neste órgão.',
            'remuneracao.numeric' => 'Informe a remuneração como número, por exemplo 4.472,58.',
        ]);

        $dados = [...['remuneracao' => null, 'base_legal' => null, 'observacao' => null], ...$dados];
        unset($dados['orgao_id']);
        $dados['orgao_id'] = $orgaoId;
        $dados['ativo'] = $request->boolean('ativo');

        return $dados;
    }
}
