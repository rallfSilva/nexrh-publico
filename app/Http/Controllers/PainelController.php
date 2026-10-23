<?php

namespace App\Http\Controllers;

use App\Ferias\Periodos;
use App\Models\Afastamento;
use App\Models\Documento;
use App\Models\Ferias;
use App\Models\Orgao;
use App\Models\RegistroAuditoria;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Painel de cada perfil: só entram os blocos que o usuário pode ver, sempre
 * restritos aos servidores visíveis para ele (o órgão todo ou a equipe da chefia).
 */
class PainelController extends Controller
{
    public function __invoke(Request $request, Periodos $periodos): View
    {
        $user = $request->user();
        $usuarios = User::visiveisPara($user);
        $hoje = today();

        $veServidores = $user->can('listar-servidores');
        $veEscala = $user->can('ver-escala');
        $equipe = fn () => Servidor::visiveisPara($user)->where('situacao', 'ativo');
        $daEquipe = fn ($q) => $q->whereHas('servidor', fn ($s) => $s->visiveisPara($user)->where('situacao', 'ativo'));

        return view('painel', [
            'chefia' => ! $user->can('servidores.ver') && $user->can('servidores.ver_equipe'),
            'totais' => [
                'orgaos' => $user->orgao_id === null ? Orgao::count() : null,
                'unidades' => Unidade::where('ativo', true)->count(),
                'usuarios' => $user->can('usuarios.ver') ? (clone $usuarios)->where('ativo', true)->count() : null,
                'semDoisFatores' => (clone $usuarios)->where('ativo', true)->whereNull('two_factor_confirmed_at')->count(),
                'acessosHoje' => (clone $usuarios)->where('ultimo_acesso_em', '>=', $hoje)->count(),
            ],
            'servidores' => $veServidores ? [
                'ativos' => $equipe()->count(),
                'efetivos' => $equipe()->where('servidor_efetivo', true)->count(),
            ] : null,
            'hoje' => $veEscala ? [
                'ferias' => Ferias::where('situacao', 'aprovada')->where('inicio', '<=', $hoje)->where('fim', '>=', $hoje)->where($daEquipe)->count(),
                'afastados' => Afastamento::validos()->entre($hoje, $hoje)->where($daEquipe)->count(),
            ] : null,
            'aAprovar' => $user->can('aprovar-ferias') ? Ferias::aAprovarPor($user)->count() : null,
            'documentosAValidar' => $user->can('gerenciar-dossie') ? Documento::where('situacao', 'pendente')->count() : null,
            'cargos' => $user->can('cargos.ver') ? [
                'ocupadas' => Vaga::where('situacao', 'ocupada')->count(),
                'vagas' => Vaga::where('situacao', 'vaga')->count(),
            ] : null,
            'porUnidade' => $veServidores ? $this->porUnidade($equipe()) : collect(),
            'manual' => route('ajuda.index'),
            'feriasPorMes' => $veEscala ? $this->feriasPorMes($daEquipe) : [],
            'proximas' => $veEscala
                ? Ferias::whereIn('situacao', ['aprovada', 'programada'])->where($daEquipe)->entre($hoje, $hoje->copy()->addDays(30))
                    ->with('servidor.unidade')->orderBy('inicio')->limit(10)->get()
                : collect(),
            'pendencias' => $user->can('servidores.ver') ? $this->pendencias($equipe) : [],
            'unidadesSemChefe' => $user->can('servidores.ver') || $user->can('unidades.gerenciar') ? Unidade::where('ativo', true)->whereNull('chefe_user_id')->orderBy('sigla')->limit(5)->get() : collect(),
            'ultimosEventos' => $user->can('auditoria.ver') ? RegistroAuditoria::with('usuario')->latest('id')->limit(6)->get() : collect(),
            'minha' => $this->minhaSituacao($user, $periodos),
        ]);
    }

    /** Servidores ativos por unidade, do maior setor para o menor. */
    private function porUnidade($servidores)
    {
        $contagem = $servidores->whereNotNull('unidade_id')->selectRaw('unidade_id, count(*) as total')->groupBy('unidade_id')->pluck('total', 'unidade_id');
        $unidades = Unidade::whereIn('id', $contagem->keys())->get()->keyBy('id');

        return $contagem->map(fn ($total, $id) => ['unidade' => $unidades[$id], 'total' => (int) $total])
            ->sortByDesc('total')->values();
    }

    /**
     * Dias de férias (aprovadas e aguardando aprovação) em cada um dos próximos 12 meses.
     *
     * @return list<array{mes: Carbon, aprovadas: int, pedidas: int}>
     */
    private function feriasPorMes(callable $daEquipe): array
    {
        $inicio = today()->startOfMonth();
        $fim = $inicio->copy()->addMonths(12)->subDay();
        $meses = [];
        for ($m = $inicio->copy(); $m->lte($fim); $m->addMonth()) {
            $meses[$m->format('Y-m')] = ['mes' => $m->copy(), 'aprovadas' => 0, 'pedidas' => 0];
        }

        foreach (Ferias::ocupam()->where($daEquipe)->entre($inicio, $fim)->get(['inicio', 'fim', 'situacao']) as $f) {
            $tipo = $f->situacao === 'programada' ? 'pedidas' : 'aprovadas';
            for ($d = $f->inicio->copy()->max($inicio); $d->lte($f->fim->copy()->min($fim)); $d->addDay()) {
                $meses[$d->format('Y-m')][$tipo]++;
            }
        }

        return array_values($meses);
    }

    /**
     * Pendências que pedem ação do RH, contadas no órgão todo.
     *
     * @return list<array{total: int, titulo: string, detalhe: string, link: ?string}>
     */
    private function pendencias(callable $equipe): array
    {
        $ano = (int) now()->year;
        $semMinimo = Unidade::where('ativo', true)->where(fn ($q) => $q->whereNull('minimo_presentes_ferias')->orWhere('minimo_presentes_ferias', 0))->count();

        return array_values(array_filter([
            ['total' => $equipe()->whereNull('vaga_id')->count(), 'titulo' => 'servidores sem vaga vinculada', 'detalhe' => 'Escolha a vaga do quadro na ficha.', 'link' => route('servidores.index')],
            ['total' => $equipe()->whereDoesntHave('declaracoes', fn ($q) => $q->where('tipo', 'bens')->where('ano_referencia', '>=', $ano))->count(), 'titulo' => "servidores sem declaração de bens de {$ano}", 'detalhe' => 'Renovação anual (Lei nº 8.429/92, art. 13).', 'link' => route('relatorios.show', 'pendencias')],
            ['total' => $equipe()->whereDoesntHave('declaracoes', fn ($q) => $q->where('tipo', 'parentesco'))->count(), 'titulo' => 'servidores sem declaração de parentesco', 'detalhe' => 'Exigida na posse.', 'link' => route('relatorios.show', 'pendencias')],
            ['total' => $semMinimo, 'titulo' => $semMinimo === 1 ? 'unidade sem mínimo de presentes' : 'unidades sem mínimo de presentes', 'detalhe' => 'Sem o mínimo, o bloqueio de férias pelo setor não se aplica.', 'link' => route('unidades.index')],
        ], fn ($p) => $p['total'] > 0));
    }

    /** Resumo da própria ficha, para quem é servidor e tem acesso. */
    private function minhaSituacao(User $user, Periodos $periodos): ?array
    {
        $servidor = $user->servidor;
        if ($servidor === null || ! $servidor->ativo()) {
            return null;
        }

        $periodos->sincronizar($servidor);
        $servidor->load(['periodosAquisitivos.ferias', 'ferias', 'declaracoes', 'documentos', 'dependentes']);

        return [
            'servidor' => $servidor,
            'saldo' => $servidor->periodosAquisitivos->sum(fn ($p) => $p->saldo()),
            'proximas' => $servidor->ferias->filter(fn ($f) => in_array($f->situacao, ['programada', 'aprovada'], true) && $f->fim->gte(today()))->sortBy('inicio')->first(),
            'pendencias' => [...$servidor->pendencias(), ...$servidor->pendenciasDoDossie(), ...$servidor->pendenciasDeFerias()],
        ];
    }
}
