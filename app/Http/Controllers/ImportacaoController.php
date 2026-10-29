<?php

namespace App\Http\Controllers;

use App\Importacao\AnalisadorAfastamentos;
use App\Importacao\AnalisadorDependentes;
use App\Importacao\AnalisadorFerias;
use App\Importacao\AnalisadorPonto;
use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AnalisadorServidores;
use App\Importacao\AplicadorAfastamentos;
use App\Importacao\AplicadorDependentes;
use App\Importacao\AplicadorFerias;
use App\Importacao\AplicadorPonto;
use App\Importacao\AplicadorQuadroCargos;
use App\Importacao\AplicadorServidores;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use League\Flysystem\FilesystemException;
use RuntimeException;

class ImportacaoController extends Controller
{
    public function index(Request $request): View
    {
        return view('importacoes.index', [
            'importacoes' => Importacao::with(['usuario', 'orgao'])
                ->whereIn('tipo', $this->tiposPermitidos($request))
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $tipos = $this->tiposPermitidos($request);
        $tipo = in_array($request->input('tipo'), $tipos, true) ? $request->input('tipo') : $tipos[0];

        return view('importacoes.create', [
            'tipo' => $tipo,
            'tipos' => $tipos,
            'orgaos' => $request->user()->orgao_id === null ? Orgao::where('ativo', true)->orderBy('sigla')->get() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->mergeIfMissing(['tipo' => $this->tiposPermitidos($request)[0] ?? null]);

        // O relógio de ponto gera um arquivo de texto (AFD); o resto são planilhas.
        $ponto = $request->input('tipo') === 'ponto';
        $request->validate([
            'tipo' => ['required', Rule::in($this->tiposPermitidos($request))],
            'arquivo' => ['required', 'file', $ponto ? 'mimes:txt' : 'mimes:xlsx', 'max:10240'],
            'orgao_id' => [$user->orgao_id === null ? 'required' : 'nullable', 'exists:orgaos,id'],
        ], [
            'arquivo.mimes' => $ponto ? 'Envie o arquivo AFD do relógio de ponto (texto, .txt).' : 'Envie a planilha no formato .xlsx (Excel).',
            'arquivo.max' => 'O arquivo pode ter no máximo 10 MB.',
            'orgao_id.required' => 'Escolha o órgão.',
        ]);

        $tipo = $request->input('tipo');
        $orgaoId = $user->orgao_id ?? (int) $request->input('orgao_id');
        $arquivo = $request->file('arquivo');
        try {
            $caminho = $arquivo->storeAs("importacoes/{$orgaoId}", Str::uuid().($ponto ? '.txt' : '.xlsx'), 'local');
        } catch (FilesystemException $e) {
            report($e);
            $caminho = false;
        }
        if ($caminho === false) {
            return back()->withErrors(['arquivo' => 'O sistema não conseguiu guardar o arquivo no servidor. Peça à TI para conferir as permissões da pasta storage (Guia da TI, Problemas comuns).']);
        }
        $completo = Storage::disk('local')->path($caminho);

        try {
            $resultado = match ($tipo) {
                'servidores' => app(AnalisadorServidores::class)->analisar($completo, $orgaoId),
                'dependentes' => app(AnalisadorDependentes::class)->analisar($completo, $orgaoId),
                'ferias' => app(AnalisadorFerias::class)->analisar($completo, $orgaoId),
                'afastamentos' => app(AnalisadorAfastamentos::class)->analisar($completo, $orgaoId),
                'ponto' => app(AnalisadorPonto::class)->analisar($completo, $orgaoId),
                default => app(AnalisadorQuadroCargos::class)->analisar($completo),
            };
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($caminho);

            return back()->withErrors(['arquivo' => $e->getMessage()]);
        }

        $bloqueios = array_filter($resultado['divergencias'], fn ($d) => $d['nivel'] === 'bloqueia');

        $importacao = Importacao::create([
            'orgao_id' => $orgaoId,
            'user_id' => $user->id,
            'tipo' => $tipo,
            'formato' => $resultado['formato'],
            'arquivo_nome' => mb_substr($arquivo->getClientOriginalName(), 0, 255),
            'arquivo_caminho' => $caminho,
            'arquivo_hash' => hash_file('sha256', $completo),
            'situacao' => $bloqueios === [] ? 'analisada' : 'recusada',
            'resumo' => $resultado['resumo'],
            'divergencias' => $resultado['divergencias'],
            'dados' => $bloqueios === [] ? [$tipo => $resultado[$tipo]] : null,
        ]);

        Auditoria::registrar('importacao', $importacao, ($ponto ? 'Arquivo do relógio de ponto' : 'Planilha')." \"{$importacao->arquivo_nome}\" ".($ponto ? 'enviado' : 'enviada').' para análise ('.count($resultado['divergencias']).' divergência(s))');

        return redirect()->route('importacoes.show', $importacao);
    }

    public function show(Request $request, Importacao $importacao): View
    {
        $this->autorizar($request, $importacao);
        $previa = $importacao->situacao === 'analisada' ? ($importacao->dados[$importacao->tipo] ?? []) : [];

        return view('importacoes.show', ['importacao' => $importacao, 'previa' => $previa]);
    }

    public function aplicar(Request $request, Importacao $importacao): RedirectResponse
    {
        $this->autorizar($request, $importacao);

        if (! $importacao->podeAplicar()) {
            return back()->with('erro', 'Esta importação não pode mais ser aplicada.');
        }

        if ($importacao->tipo === 'ferias') {
            $r = app(AplicadorFerias::class)->aplicar($importacao);

            return redirect()->route('ferias.escala')->with('sucesso', sprintf(
                'Férias importadas: %d nova(s) e %d atualizada(s). Elas aparecem na escala do setor e na aba Férias de cada ficha.',
                $r['criadas'], $r['atualizadas'],
            ));
        }

        if ($importacao->tipo === 'ponto') {
            $r = app(AplicadorPonto::class)->aplicar($importacao);

            return redirect()->route('frequencia.index')->with('sucesso', sprintf(
                'Relógio de ponto importado: %d marcação(ões) de %d servidor(es). Elas aparecem no espelho de cada um, com a indicação "R".',
                $r['marcacoes'], $r['servidores'],
            ));
        }

        if ($importacao->tipo === 'afastamentos') {
            $r = app(AplicadorAfastamentos::class)->aplicar($importacao);

            return redirect()->route('ferias.escala')->with('sucesso', sprintf(
                'Afastamentos importados: %d novo(s) e %d atualizado(s). Eles entram na conta do mínimo de presentes de cada setor.',
                $r['criados'], $r['atualizados'],
            ));
        }

        if ($importacao->tipo === 'dependentes') {
            $r = app(AplicadorDependentes::class)->aplicar($importacao);

            return redirect()->route('servidores.index')->with('sucesso', sprintf(
                'Dependentes importados: %d novo(s) e %d atualizado(s). Eles aparecem na aba Dependentes de cada ficha.',
                $r['criados'], $r['atualizados'],
            ));
        }

        if ($importacao->tipo === 'servidores') {
            $r = app(AplicadorServidores::class)->aplicar($importacao);

            return redirect()->route('servidores.index')->with('sucesso', sprintf(
                'Servidores importados: %d novo(s), %d atualizado(s), %d com vaga vinculada e %d declaração(ões). O relatório continua no histórico de importações.',
                $r['criados'], $r['atualizados'], $r['com_vaga'], $r['declaracoes'],
            ));
        }

        $fichas = $request->boolean('criar_fichas') && $request->user()->can('servidores.gerenciar');
        $r = app(AplicadorQuadroCargos::class)->aplicar($importacao, $fichas);

        if ($r['fichas']) {
            return redirect()->route('servidores.index')->with('sucesso', sprintf(
                'Quadro importado: %d cargo(s) novo(s), %d atualizado(s), %d vaga(s). Servidores: %d ficha(s) criada(s) e %d ligada(s) à vaga%s. As fichas novas ficam com a pendência "Ficha incompleta" até o RH completar.',
                $r['criados'], $r['atualizados'], $r['vagas'], $r['fichas']['criadas'], $r['fichas']['vinculadas'],
                $r['fichas']['ignoradas'] ? '; '.count($r['fichas']['ignoradas']).' vaga(s) sem ficha por CPF inválido ou repetido (veja as divergências da importação)' : '',
            ));
        }

        return redirect()->route('cargos.index', ['orgao' => $importacao->orgao_id])->with('sucesso', sprintf(
            'Quadro importado: %d cargo(s) novo(s), %d atualizado(s), %d vaga(s). O relatório de divergências continua disponível no histórico de importações.',
            $r['criados'], $r['atualizados'], $r['vagas'],
        ));
    }

    public function cancelar(Request $request, Importacao $importacao): RedirectResponse
    {
        $this->autorizar($request, $importacao);

        if ($importacao->situacao !== 'analisada') {
            return back()->with('erro', 'Esta importação não pode mais ser cancelada.');
        }

        $importacao->forceFill(['situacao' => 'cancelada', 'dados' => null])->save();
        Storage::disk('local')->delete($importacao->arquivo_caminho);
        Auditoria::registrar('importacao', $importacao, "Importação de \"{$importacao->arquivo_nome}\" cancelada");

        return redirect()->route('importacoes.index')->with('sucesso', 'Importação cancelada. Nada foi alterado.');
    }

    /** Cargos para quem gerencia cargos; servidores para quem gerencia servidores; dependentes para quem gerencia o dossiê; férias e afastamentos para quem gerencia férias; relógio de ponto para quem gerencia a frequência. */
    private function tiposPermitidos(Request $request): array
    {
        $user = $request->user();

        return array_values(array_filter([
            $user->can('cargos.gerenciar') ? 'cargos' : null,
            $user->can('servidores.gerenciar') ? 'servidores' : null,
            $user->can('gerenciar-dossie') ? 'dependentes' : null,
            $user->can('ferias.gerenciar') ? 'ferias' : null,
            $user->can('ferias.gerenciar') ? 'afastamentos' : null,
            $user->can('frequencia.gerenciar') ? 'ponto' : null,
        ]));
    }

    private function autorizar(Request $request, Importacao $importacao): void
    {
        abort_unless(in_array($importacao->tipo, $this->tiposPermitidos($request), true), 404);
    }
}
