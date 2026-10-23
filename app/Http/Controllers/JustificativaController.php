<?php

namespace App\Http\Controllers;

use App\Frequencia\Fechamento;
use App\Frequencia\Justificador;
use App\Models\Documento;
use App\Models\Justificativa;
use App\Models\Servidor;
use App\Models\User;
use App\Servidores\ArquivoDossie;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Justificativas de ocorrências do ponto: o servidor envia (com anexo), a chefia
 * ou o RH abonam ou recusam, e o RH também abona direto pelo espelho.
 */
class JustificativaController extends Controller
{
    public function __construct(private Justificador $justificador, private ArquivoDossie $arquivos) {}

    /** Formulário do servidor, já com o dia da ocorrência. */
    public function create(Request $request): View
    {
        $servidor = $request->user()->servidor ?? abort(404);
        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('data')) ? Carbon::parse($request->input('data')) : today()->subDay();
        $ocorrencia = $this->justificador->ocorrencias($servidor, $data, $data, $request->user())->first();

        return view('frequencia.justificar', [
            'servidor' => $servidor,
            'data' => $data,
            'ocorrencia' => $ocorrencia,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $servidor = $request->user()->servidor ?? abort(404);
        $dados = $this->validar($request);
        $j = $this->justificador->registrar($servidor, $request->user(), $dados, $request->file('arquivo'), false);

        return redirect()->route('meu-ponto', ['mes' => $j->inicio->format('Y-m')])
            ->with('sucesso', 'Justificativa de '.$j->periodoTexto().' enviada. Ela fica em análise até a chefia decidir.');
    }

    public function retirar(Request $request, Justificativa $justificativa): RedirectResponse
    {
        abort_unless($justificativa->servidor_id === $request->user()->servidor?->id, 404);
        abort_unless($justificativa->situacao === 'pendente', 409);
        if ($fechado = Fechamento::primeiroFechadoEntre($justificativa->servidor, $justificativa->inicio, $justificativa->fim)) {
            throw Fechamento::erroMesFechado('fechamento', $fechado);
        }

        $justificativa->update(['situacao' => 'retirada']);

        return redirect()->route('meu-ponto', ['mes' => $justificativa->inicio->format('Y-m')])
            ->with('sucesso', 'Justificativa de '.$justificativa->periodoTexto().' retirada.');
    }

    /** Fila da chefia e do RH: em análise e as decididas nos últimos 60 dias. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $aba = $request->input('aba') === 'decididas' ? 'decididas' : 'analise';
        $query = Justificativa::with(['servidor.unidade', 'analisadoPor:id,name', 'enviadoPor:id,name']);

        $lista = $aba === 'analise'
            ? $query->aAnalisarPor($user)->orderBy('inicio')->get()
            : $query->whereIn('situacao', ['abonada', 'recusada'])->where('analisado_em', '>=', now()->subDays(60))
                ->whereHas('servidor', fn ($q) => $q->visiveisPara($user))->latest('analisado_em')->limit(200)->get();

        return view('frequencia.justificativas', [
            'aba' => $aba,
            'lista' => $lista,
            'emAnalise' => $aba === 'analise' ? $lista->count() : Justificativa::aAnalisarPor($user)->count(),
        ]);
    }

    public function abonar(Request $request, Justificativa $justificativa): RedirectResponse
    {
        $this->podeDecidir($request->user(), $justificativa);
        $motivo = $request->validate(['motivo_decisao' => ['nullable', 'string', 'max:255']])['motivo_decisao'] ?? null;

        $justificativa->update(['situacao' => 'abonada', 'analisado_por' => $request->user()->id, 'analisado_em' => now(), 'motivo_decisao' => $motivo]);

        return back()->with('sucesso', 'Justificativa de '.$justificativa->servidor->nome.' ('.$justificativa->periodoTexto().') abonada.');
    }

    public function recusar(Request $request, Justificativa $justificativa): RedirectResponse
    {
        $this->podeDecidir($request->user(), $justificativa);
        $motivo = $request->validate(['motivo_decisao' => ['required', 'string', 'max:255']], ['motivo_decisao.required' => 'Diga por que a justificativa foi recusada. O servidor vai ver o motivo.'])['motivo_decisao'];

        $justificativa->update(['situacao' => 'recusada', 'analisado_por' => $request->user()->id, 'analisado_em' => now(), 'motivo_decisao' => $motivo]);

        return back()->with('sucesso', 'Justificativa de '.$justificativa->servidor->nome.' ('.$justificativa->periodoTexto().') recusada.');
    }

    /** Abono lançado pelo RH no espelho: já entra abonado. */
    public function abonarDireto(Request $request, Servidor $servidor): RedirectResponse
    {
        $dados = $this->validar($request, true);
        $j = $this->justificador->registrar($servidor, $request->user(), $dados, $request->file('arquivo'), true);

        return redirect()->route('frequencia.espelho', [$servidor, 'mes' => $j->inicio->format('Y-m')])
            ->with('sucesso', 'Abono de '.$j->periodoTexto().' lançado no espelho de '.$servidor->nome.'.');
    }

    /** Anexo: o próprio servidor, quem pode decidir e o RH. Cada acesso fica na trilha. */
    public function arquivo(Request $request, Justificativa $justificativa): Response
    {
        $user = $request->user();
        $proprio = $justificativa->servidor_id === $user->servidor?->id;
        $analisa = $user->can('analisar-justificativas') && Servidor::visiveisPara($user)->whereKey($justificativa->servidor_id)->exists();
        abort_unless($justificativa->temArquivo() && ($proprio || $analisa), 404);

        $baixar = $request->boolean('baixar');
        Auditoria::registrar($baixar ? 'download' : 'acesso', $justificativa, ($baixar ? 'Download' : 'Visualização').' do anexo da justificativa de '.$justificativa->servidor->nome.' ('.$justificativa->periodoTexto().')');

        return $this->arquivos->resposta($justificativa, $baixar);
    }

    private function podeDecidir(User $user, Justificativa $justificativa): void
    {
        abort_unless(Justificativa::aAnalisarPor($user)->whereKey($justificativa->id)->exists(), $justificativa->situacao === 'pendente' ? 403 : 409);
        if ($fechado = Fechamento::primeiroFechadoEntre($justificativa->servidor, $justificativa->inicio, $justificativa->fim)) {
            throw Fechamento::erroMesFechado('fechamento', $fechado);
        }
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, bool $peloRh = false): array
    {
        $tiposComAnexo = array_keys(array_filter(Justificativa::TIPOS, fn ($t) => $t[1]));

        return $request->validate([
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d'],
            'tipo' => ['required', Rule::in(array_keys(Justificativa::TIPOS))],
            'horarios' => ['nullable', 'string', 'max:60'],
            'motivo' => ['required', 'string', 'max:1000'],
            'arquivo' => [$peloRh ? 'nullable' : 'required_if:tipo,'.implode(',', $tiposComAnexo), 'nullable', 'file', 'max:'.Documento::TAMANHO_MAXIMO_KB, function ($atributo, $valor, $falhar) {
                if (! array_key_exists(ArquivoDossie::tipoReal($valor), Documento::MIMES)) {
                    $falhar('Envie PDF, JPG ou PNG.');
                }
            }],
        ], [
            'motivo.required' => 'Conte o que aconteceu.',
            'arquivo.required_if' => 'Anexe o documento (PDF, JPG ou PNG).',
            'arquivo.max' => 'O arquivo pode ter até 10 MB.',
        ]);
    }
}
