<?php

namespace App\Http\Controllers;

use App\Relatorios\Catalogo;
use App\Relatorios\Exportador;
use App\Relatorios\Relatorio;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RelatorioController extends Controller
{
    /** A tela mostra até este número de linhas; a exportação leva todas. */
    private const LIMITE_TELA = 300;

    public function index(Request $request): View
    {
        return view('relatorios.index', ['relatorios' => Catalogo::para($request->user())]);
    }

    public function show(Request $request, string $chave): View
    {
        $relatorio = $this->relatorio($request, $chave);
        $user = $request->user();
        $filtros = $relatorio->valoresDosFiltros($user, $request->query());
        $linhas = $relatorio->linhas($user, $filtros);

        return view('relatorios.show', [
            'relatorio' => $relatorio,
            'definicoes' => $relatorio->filtros($user),
            'filtros' => $filtros,
            'colunas' => $relatorio->colunas($user),
            'numericas' => $relatorio->numericas(),
            'linhas' => $linhas->take(self::LIMITE_TELA),
            'total' => $linhas->count(),
            'limite' => self::LIMITE_TELA,
            'outros' => Catalogo::para($user),
        ]);
    }

    public function exportar(Request $request, string $chave, string $formato, Exportador $exportador): Response
    {
        $relatorio = $this->relatorio($request, $chave);
        $user = $request->user();
        $filtros = $relatorio->valoresDosFiltros($user, $request->query());
        $linhas = $relatorio->linhas($user, $filtros);

        return $formato === 'pdf'
            ? $exportador->pdf($relatorio, $user, $filtros, $linhas)
            : $exportador->excel($relatorio, $user, $filtros, $linhas);
    }

    /** Relatório que não existe ou que o usuário não pode ver responde 404. */
    private function relatorio(Request $request, string $chave): Relatorio
    {
        $relatorio = Catalogo::encontrar($chave);
        abort_if($relatorio === null || ! $relatorio->permitido($request->user()), 404);

        return $relatorio;
    }
}
