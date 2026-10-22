<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Orgao;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\Vaga;
use App\Servidores\FichaPdf;
use App\Servidores\OcupacaoDeVaga;
use App\Servidores\RegrasFicha;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ServidorController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $aba = $request->input('aba') === 'desligados' ? 'desligados' : 'ativos';
        $visiveis = fn () => Servidor::visiveisPara($user);

        $servidores = $visiveis()
            ->with(['unidade', 'vaga.cargo'])
            ->where('situacao', $aba === 'desligados' ? 'desligado' : 'ativo')
            ->busca($request->input('busca'))
            ->when($request->filled('unidade'), fn ($q) => $q->where('unidade_id', $request->integer('unidade')))
            ->when($request->filled('cargo'), fn ($q) => $q->whereHas('vaga', fn ($v) => $v->where('cargo_id', $request->integer('cargo'))))
            ->orderByRaw('unaccent(nome)')
            ->paginate(25)
            ->withQueryString();

        $ativos = $visiveis()->where('situacao', 'ativo');
        $ano = (int) now()->year;

        $kpis = [
            'ativos' => (clone $ativos)->count(),
            'efetivos' => (clone $ativos)->where('servidor_efetivo', true)->count(),
            'sem_vaga' => (clone $ativos)->whereNull('vaga_id')->count(),
            'bens_pendentes' => (clone $ativos)->whereDoesntHave('declaracoes', fn ($q) => $q->where('tipo', 'bens')->where('ano_referencia', '>=', $ano))->count(),
            'desligados' => $visiveis()->where('situacao', 'desligado')->count(),
        ];

        return view('servidores.index', [
            'servidores' => $servidores,
            'kpis' => $kpis,
            'aba' => $aba,
            'ano' => $ano,
            'unidades' => Unidade::where('ativo', true)->orderBy('sigla')->get(),
            'cargos' => Cargo::where('ativo', true)->orderBy('denominacao')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $orgaoId = $this->orgaoDoCadastro($request);

        if ($orgaoId === null) {
            return view('servidores.escolher-orgao', ['orgaos' => Orgao::where('ativo', true)->orderBy('sigla')->get()]);
        }

        $servidor = new Servidor([
            'orgao_id' => $orgaoId,
            'nacionalidade' => 'Brasileira',
            'pcd' => false,
            'molestia_grave' => false,
            'vaga_pcd_ou_reabilitacao' => false,
            'servidor_efetivo' => false,
            'servidor_federal' => false,
        ]);

        return view('servidores.form', $this->dadosDoFormulario($servidor, $request->integer('vaga') ?: null));
    }

    public function store(Request $request, OcupacaoDeVaga $ocupacao): RedirectResponse
    {
        $orgaoId = $this->orgaoDoCadastro($request) ?? abort(422, 'Escolha o órgão.');
        $dados = $this->validar($request, $orgaoId);

        $servidor = DB::transaction(function () use ($dados, $orgaoId, $ocupacao) {
            $servidor = Servidor::create([...$dados, 'orgao_id' => $orgaoId, 'situacao' => 'ativo']);
            $ocupacao->sincronizar($servidor);

            return $servidor;
        });

        return redirect()->route('servidores.show', $servidor)->with('sucesso', 'Servidor cadastrado e vaga marcada como ocupada. Registre agora as declarações de parentesco, acumulação e bens.');
    }

    public function show(Request $request, Servidor $servidor): View
    {
        // Dossiê e dependentes: só para quem vê os dados pessoais (RH e administração).
        $completo = $request->user()->can('servidores.dados_pessoais');
        $servidor->load(['unidade.chefe', 'vaga.cargo', 'usuario', 'declaracoes.registradaPor', 'eventos.usuario',
            ...($completo ? ['documentos.enviadoPor', 'documentos.analisadoPor', 'dependentes'] : [])]);

        Auditoria::registrar('acesso', $servidor, 'Ficha de '.$servidor->nome.' consultada');

        return view('servidores.show', [
            'servidor' => $servidor,
            'completo' => $completo,
            'pendencias' => [...$servidor->pendencias(), ...($completo ? $servidor->pendenciasDoDossie() : [])],
        ]);
    }

    public function edit(Servidor $servidor): View
    {
        return view('servidores.form', $this->dadosDoFormulario($servidor));
    }

    public function update(Request $request, Servidor $servidor, OcupacaoDeVaga $ocupacao): RedirectResponse
    {
        $dados = $this->validar($request, $servidor->orgao_id, $servidor);
        $antes = $servidor->getOriginal();
        $readmissao = ! $servidor->ativo() && ! empty($dados['vaga_id']);

        DB::transaction(function () use ($servidor, $dados, $antes, $readmissao, $ocupacao) {
            $servidor->update($dados);

            if ($readmissao) {
                $ocupacao->readmitir($servidor);
            }

            $ocupacao->sincronizar($servidor->refresh(), $antes);
        });

        return redirect()->route('servidores.show', $servidor)->with('sucesso', $readmissao ? 'Servidor readmitido.' : 'Cadastro atualizado.');
    }

    public function desligar(Request $request, Servidor $servidor, OcupacaoDeVaga $ocupacao): RedirectResponse
    {
        if (! $servidor->ativo()) {
            return back()->with('erro', 'Este servidor já está desligado.');
        }

        $dados = $request->validate([
            'data_desligamento' => ['required', 'date', 'after_or_equal:'.($servidor->efeitos_a_partir_de?->format('Y-m-d') ?? '1900-01-01')],
            'ato_desligamento' => ['nullable', 'string', 'max:60'],
            'motivo_desligamento' => ['required', Rule::in(Servidor::MOTIVOS_DESLIGAMENTO)],
        ], [
            'data_desligamento.required' => 'Informe a data do desligamento.',
            'data_desligamento.after_or_equal' => 'O desligamento não pode ser antes do ingresso.',
            'motivo_desligamento.required' => 'Escolha o motivo.',
        ]);

        DB::transaction(fn () => $ocupacao->desligar($servidor, $dados['data_desligamento'], $dados['ato_desligamento'] ?? null, $dados['motivo_desligamento']));

        return redirect()->route('servidores.show', $servidor)->with('sucesso', 'Desligamento registrado. A vaga voltou a ficar livre no quadro de cargos.');
    }

    public function pdf(Servidor $servidor, FichaPdf $ficha): Response
    {
        Auditoria::registrar('download', $servidor, 'Ficha cadastral (PDF) de '.$servidor->nome.' gerada');

        return $ficha->baixar($servidor);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, int $orgaoId, ?Servidor $servidor = null): array
    {
        $dados = RegrasFicha::normalizar($request->only(array_keys(RegrasFicha::regras($orgaoId))));
        $exigeVaga = $servidor === null || $servidor->ativo();

        return Validator::make($dados, RegrasFicha::regras($orgaoId, $servidor, $exigeVaga), RegrasFicha::mensagens(), RegrasFicha::nomes())->validate();
    }

    /** Órgão em que o cadastro é feito: o do usuário ou, para o administrador geral, o escolhido. */
    private function orgaoDoCadastro(Request $request): ?int
    {
        return $request->user()->orgao_id
            ?? (Orgao::whereKey($request->integer('orgao'))->value('id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosDoFormulario(Servidor $servidor, ?int $vagaSugerida = null): array
    {
        return [
            'servidor' => $servidor,
            'vagas' => $this->vagasDisponiveis($servidor),
            'vagaSugerida' => $vagaSugerida,
            'unidades' => Unidade::where('orgao_id', $servidor->orgao_id)->where('ativo', true)->orderBy('sigla')->get(),
        ];
    }

    /**
     * Vagas que podem ser escolhidas, agrupadas por cargo: as livres, as ocupadas
     * só na planilha (sem servidor cadastrado) e a vaga atual do servidor.
     *
     * @return Collection<string, Collection<int, Vaga>>
     */
    private function vagasDisponiveis(Servidor $servidor): Collection
    {
        return Vaga::query()
            ->where('orgao_id', $servidor->orgao_id)
            ->whereHas('cargo', fn ($q) => $q->where('ativo', true))
            ->where(fn ($q) => $q
                ->where('situacao', 'vaga')
                ->orWhere(fn ($o) => $o->where('situacao', 'ocupada')->whereDoesntHave('servidor'))
                ->when($servidor->vaga_id, fn ($a) => $a->orWhere('id', $servidor->vaga_id)))
            ->with('cargo')
            ->get()
            ->sortBy([fn ($a, $b) => strcmp($a->cargo->denominacao, $b->cargo->denominacao), ['numero', 'asc']])
            ->groupBy(fn (Vaga $v) => $v->cargo->denominacao.' ('.$v->cargo->simbolo.')');
    }
}
