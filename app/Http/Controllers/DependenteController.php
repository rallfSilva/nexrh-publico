<?php

namespace App\Http\Controllers;

use App\Models\Dependente;
use App\Models\Servidor;
use App\Servidores\RegrasDependente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Dependentes do servidor: cadastro, alteração, encerramento e reativação (nunca exclusão). */
class DependenteController extends Controller
{
    public function create(Servidor $servidor): View
    {
        return view('dependentes.form', ['servidor' => $servidor, 'dependente' => new Dependente(['pcd' => false])]);
    }

    public function store(Request $request, Servidor $servidor): RedirectResponse
    {
        $dados = $this->validar($request, $servidor);

        $dependente = Dependente::create([...$dados, 'orgao_id' => $servidor->orgao_id, 'servidor_id' => $servidor->id]);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'dependentes'])
            ->with('sucesso', "{$dependente->nome} cadastrado(a) como dependente.");
    }

    public function edit(Servidor $servidor, Dependente $dependente): View
    {
        return view('dependentes.form', ['servidor' => $servidor, 'dependente' => $dependente]);
    }

    public function update(Request $request, Servidor $servidor, Dependente $dependente): RedirectResponse
    {
        $dependente->update($this->validar($request, $servidor, $dependente));

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'dependentes'])
            ->with('sucesso', "Cadastro de {$dependente->nome} atualizado.");
    }

    public function encerrar(Request $request, Servidor $servidor, Dependente $dependente): RedirectResponse
    {
        $dados = $request->validate([
            'encerrado_em' => ['required', 'date', 'after_or_equal:'.$dependente->data_nascimento->format('Y-m-d'), 'before_or_equal:today'],
            'motivo_encerramento' => ['required', Rule::in(Dependente::MOTIVOS_ENCERRAMENTO)],
        ], [
            'encerrado_em.required' => 'Informe a data.',
            'encerrado_em.before_or_equal' => 'A data não pode ser no futuro.',
            'encerrado_em.after_or_equal' => 'A data não pode ser antes do nascimento.',
            'motivo_encerramento.required' => 'Escolha o motivo.',
            'motivo_encerramento.in' => 'Escolha um motivo da lista.',
        ]);

        abort_unless($dependente->ativo(), 409);

        // Não sai das finalidades: o histórico mostra para que ele valia até a data.
        $dependente->update($dados);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'dependentes'])
            ->with('sucesso', "{$dependente->nome} deixou de ser dependente em {$dependente->encerrado_em->format('d/m/Y')}.");
    }

    public function reativar(Servidor $servidor, Dependente $dependente): RedirectResponse
    {
        abort_if($dependente->ativo(), 409);

        if (in_array($dependente->parentesco, Dependente::PARENTESCOS_CONJUGE, true)
            && $servidor->dependentes()->ativos()->whereIn('parentesco', Dependente::PARENTESCOS_CONJUGE)->exists()) {
            return back()->with('erro', 'Já existe um cônjuge ou companheiro(a) ativo. Encerre aquele cadastro antes de reativar este.');
        }

        $dependente->update(['encerrado_em' => null, 'motivo_encerramento' => null]);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'dependentes'])
            ->with('sucesso', "{$dependente->nome} voltou a ser dependente.");
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, Servidor $servidor, ?Dependente $dependente = null): array
    {
        $dados = RegrasDependente::normalizar($request->only(['nome', 'cpf', 'data_nascimento', 'sexo', 'parentesco', ...RegrasDependente::LOGICOS, 'tipo_deficiencia']));

        // Na tela, a pergunta do salário-família some quando o parentesco não é de filho.
        if (! in_array($dados['parentesco'] ?? null, Dependente::PARENTESCOS_FILHO, true)) {
            $dados['salario_familia'] = false;
        }

        return Validator::make($dados, RegrasDependente::regras($servidor, $dados, $dependente), RegrasDependente::mensagens())->validate();
    }
}
