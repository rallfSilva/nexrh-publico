<?php

namespace App\Http\Controllers;

use App\Servidores\FichaPdf;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** O próprio servidor consulta a ficha dele e baixa o PDF. */
class MinhaFichaController extends Controller
{
    public function show(Request $request): View
    {
        $servidor = $request->user()->servidor;

        if ($servidor === null) {
            return view('servidores.sem-ficha');
        }

        $servidor->load(['unidade.chefe', 'vaga.cargo', 'declaracoes', 'eventos']);

        return view('servidores.show', [
            'servidor' => $servidor,
            'completo' => true,
            'propria' => true,
            'pendencias' => $servidor->pendencias(),
        ]);
    }

    public function pdf(Request $request, FichaPdf $ficha): Response
    {
        $servidor = $request->user()->servidor ?? abort(404);

        Auditoria::registrar('download', $servidor, 'Ficha cadastral (PDF) baixada pelo próprio servidor');

        return $ficha->baixar($servidor);
    }
}
