<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Afastamento;
use App\Models\Cargo;
use App\Models\ChaveApi;
use App\Models\FechamentoFrequencia;
use App\Models\Ferias;
use App\Models\Servidor;
use App\Models\Unidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API de consulta (somente leitura), versão 1. Tudo vem do órgão da chave e sem
 * dados pessoais: CPF só mascarado, sem documentos, endereço, contato, banco nem
 * tipo de licença. Datas no formato AAAA-MM-DD.
 */
class ConsultaController extends Controller
{
    public function inicio(Request $request): JsonResponse
    {
        /** @var ChaveApi $chave */
        $chave = $request->attributes->get('chave_api');

        return $this->json([
            'sistema' => 'NexRH Público',
            'versao' => 'v1',
            'orgao' => ['sigla' => $chave->orgao->sigla, 'nome' => $chave->orgao->nome],
            'chave' => ['nome' => $chave->nome, 'escopos' => $chave->escopos, 'expira_em' => $chave->expira_em?->toDateString()],
            'consultas' => collect([
                'unidades' => '/api/v1/unidades',
                'cargos' => '/api/v1/cargos',
                'servidores' => '/api/v1/servidores',
                'ferias' => '/api/v1/ferias',
                'frequencia' => '/api/v1/frequencia?mes='.today()->subMonthNoOverflow()->format('Y-m'),
            ])->only($chave->escopos)->put('ausencias', $chave->permite('ferias') ? '/api/v1/ausencias' : null)->filter()->all(),
        ]);
    }

    public function unidades(): JsonResponse
    {
        $ativos = Servidor::where('situacao', 'ativo')->whereNotNull('unidade_id')->selectRaw('unidade_id, count(*) as total')->groupBy('unidade_id')->pluck('total', 'unidade_id');

        return $this->json(['dados' => Unidade::with('chefe')->orderBy('sigla')->get()->map(fn (Unidade $u) => [
            'id' => $u->id,
            'sigla' => $u->sigla,
            'nome' => $u->nome,
            'tipo' => $u->tipo,
            'unidade_superior_id' => $u->parent_id,
            'chefe' => $u->chefe?->name,
            'minimo_presentes_ferias' => $u->minimo_presentes_ferias,
            'servidores_ativos' => (int) ($ativos[$u->id] ?? 0),
            'ativa' => (bool) $u->ativo,
        ])]);
    }

    public function cargos(): JsonResponse
    {
        $cargos = Cargo::where('ativo', true)
            ->withCount(['vagas as ocupadas_count' => fn ($q) => $q->where('situacao', 'ocupada'), 'vagas as livres_count' => fn ($q) => $q->where('situacao', 'vaga'), 'vagas as remanejadas_count' => fn ($q) => $q->whereIn('situacao', ['remanejada', 'devolvida'])])
            ->orderBy('denominacao')->get();

        return $this->json(['dados' => $cargos->map(fn (Cargo $c) => [
            'id' => $c->id,
            'denominacao' => $c->denominacao,
            'simbolo' => $c->simbolo,
            'natureza' => $c->natureza,
            'base_legal' => $c->base_legal,
            'vagas' => $c->totais(),
        ])]);
    }

    public function servidores(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'unidade' => ['nullable', 'integer'],
            'situacao' => ['nullable', 'in:ativo,desligado'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pagina = Servidor::with(['unidade', 'vaga.cargo'])
            ->where('situacao', $filtros['situacao'] ?? 'ativo')
            ->when($filtros['unidade'] ?? null, fn ($q, $u) => $q->where('unidade_id', $u))
            ->orderBy('id')
            ->paginate($filtros['por_pagina'] ?? 50, pageName: 'pagina')
            ->withQueryString();

        return $this->json([
            'dados' => collect($pagina->items())->map(fn (Servidor $s) => $this->dadosDoServidor($s)),
            'paginacao' => ['pagina' => $pagina->currentPage(), 'por_pagina' => $pagina->perPage(), 'total' => $pagina->total(), 'ultima_pagina' => $pagina->lastPage(), 'proxima' => $pagina->nextPageUrl()],
        ]);
    }

    public function servidor(int $id): JsonResponse
    {
        $servidor = Servidor::with(['unidade', 'vaga.cargo'])->find($id);

        return $servidor
            ? $this->json(['dados' => $this->dadosDoServidor($servidor)])
            : $this->json(['erro' => 'Servidor não encontrado.'], 404);
    }

    private function dadosDoServidor(Servidor $servidor): array
    {
        return [
            'id' => $servidor->id,
            'nome' => $servidor->nome,
            'cpf_mascarado' => $servidor->cpfMascarado(),
            'matricula' => $servidor->matricula(),
            'cargo' => $servidor->vaga?->cargo ? ['denominacao' => $servidor->vaga->cargo->denominacao, 'simbolo' => $servidor->vaga->cargo->simbolo] : null,
            'unidade' => $servidor->unidade ? ['id' => $servidor->unidade->id, 'sigla' => $servidor->unidade->sigla] : null,
            'vinculo' => $servidor->rotuloVinculo(),
            'situacao' => $servidor->situacao,
            'ingresso' => $servidor->ingresso()?->toDateString(),
            'desligamento' => $servidor->data_desligamento?->toDateString(),
        ];
    }

    public function ferias(Request $request): JsonResponse
    {
        [$de, $ate, $unidade] = $this->intervalo($request);

        $ferias = Ferias::with(['servidor.unidade', 'periodo'])
            ->ocupam()
            ->entre($de, $ate)
            ->whereHas('servidor', fn ($q) => $q->when($unidade, fn ($s) => $s->where('unidade_id', $unidade)))
            ->orderBy('inicio')->get();

        return $this->json([
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'dados' => $ferias->map(fn (Ferias $f) => [
                'id' => $f->id,
                'servidor' => ['id' => $f->servidor_id, 'nome' => $f->servidor->nome],
                'unidade' => $f->servidor->unidade?->sigla,
                'periodo_aquisitivo' => $f->periodo?->rotulo(),
                'etapa' => $f->etapa,
                'inicio' => $f->inicio->toDateString(),
                'fim' => $f->fim->toDateString(),
                'dias' => $f->dias,
                'situacao' => $f->situacao === 'programada' ? 'aguardando_aprovacao' : $f->situacao,
            ]),
        ]);
    }

    /** Quem está fora em cada dia do intervalo: férias e licenças, sem dizer o tipo da licença. */
    public function ausencias(Request $request): JsonResponse
    {
        [$de, $ate, $unidade] = $this->intervalo($request, 31);
        $daUnidade = fn ($q) => $q->when($unidade, fn ($s) => $s->where('unidade_id', $unidade));

        $ferias = Ferias::with('servidor.unidade')->where('situacao', 'aprovada')->entre($de, $ate)->whereHas('servidor', $daUnidade)->get()
            ->map(fn ($f) => ['servidor' => $f->servidor, 'inicio' => $f->inicio, 'fim' => $f->fim, 'motivo' => 'ferias']);
        $afastamentos = Afastamento::with('servidor.unidade')->validos()->entre($de, $ate)->whereHas('servidor', $daUnidade)->get()
            ->map(fn ($a) => ['servidor' => $a->servidor, 'inicio' => $a->inicio, 'fim' => $a->fim, 'motivo' => 'licenca_ou_afastamento']);

        return $this->json([
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'dados' => $ferias->concat($afastamentos)->sortBy(fn ($a) => $a['inicio']->toDateString().$a['servidor']->nome)->values()->map(fn ($a) => [
                'servidor' => ['id' => $a['servidor']->id, 'nome' => $a['servidor']->nome],
                'unidade' => $a['servidor']->unidade?->sigla,
                'inicio' => $a['inicio']->toDateString(),
                'fim' => $a['fim']->toDateString(),
                'motivo' => $a['motivo'],
            ]),
        ]);
    }

    /**
     * Boletim de frequência de um mês, só dos setores que o RH já fechou (o que não
     * muda mais). Os setores ainda abertos vêm listados à parte, sem números.
     */
    public function frequencia(Request $request): JsonResponse
    {
        $f = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
            'unidade' => ['nullable', 'integer'],
        ], ['mes.required' => 'Informe o mês: ?mes=AAAA-MM.']);
        $mes = Carbon::createFromFormat('!Y-m', $f['mes']);

        $fechamentos = FechamentoFrequencia::with('unidade:id,sigla')->whereDate('mes', $mes)
            ->when($f['unidade'] ?? null, fn ($q, $u) => $q->where('unidade_id', $u))->get();
        $fechados = $fechamentos->filter->fechado();
        $setores = Servidor::where('situacao', 'ativo')->whereNotNull('unidade_id')->when($f['unidade'] ?? null, fn ($q, $u) => $q->where('unidade_id', $u))
            ->distinct()->pluck('unidade_id');
        $abertos = Unidade::whereIn('id', $setores->diff($fechados->pluck('unidade_id')))->orderBy('sigla')->pluck('sigla');

        return $this->json([
            'mes' => $mes->format('Y-m'),
            'setores_abertos' => $abertos->values(),
            'dados' => $fechados->sortBy(fn ($x) => $x->unidade?->sigla)->values()->map(fn (FechamentoFrequencia $x) => [
                'unidade' => ['id' => $x->unidade_id, 'sigla' => $x->unidade?->sigla],
                'fechado_em' => $x->fechado_em->toIso8601String(),
                'servidores' => collect($x->boletim)->map(fn (array $l) => [
                    'servidor' => ['id' => $l['servidor_id'], 'nome' => $l['servidor'], 'matricula' => $l['matricula']],
                    'vinculo' => $l['vinculo'],
                    'dias_de_trabalho' => $l['dias_trabalho'],
                    'dias_com_registro' => $l['com_registro'],
                    'faltas' => $l['faltas'],
                    'abonados' => $l['abonados'],
                    'incompletos' => $l['incompletos'],
                    'a_verificar' => $l['atencao'],
                    'dias_de_ferias' => $l['ferias'],
                    'dias_de_afastamento' => $l['afastamentos'],
                    'minutos_esperados' => $l['esperado'],
                    'minutos_trabalhados' => $l['trabalhado'],
                ])->values(),
            ]),
        ]);
    }

    /** JSON com acentos legíveis, como os sistemas do Estado costumam esperar. */
    private function json(array $dados, int $status = 200): JsonResponse
    {
        return response()->json($dados, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array{Carbon, Carbon, ?int} */
    private function intervalo(Request $request, int $padraoDias = 90): array
    {
        $f = $request->validate([
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:de'],
            'unidade' => ['nullable', 'integer'],
        ]);
        $de = isset($f['de']) ? Carbon::parse($f['de']) : today();
        $ate = isset($f['ate']) ? Carbon::parse($f['ate']) : $de->copy()->addDays($padraoDias);
        abort_if($de->diffInDays($ate) > 366, 422, 'O intervalo pode ter no máximo 366 dias.');

        return [$de, $ate, $f['unidade'] ?? null];
    }
}
