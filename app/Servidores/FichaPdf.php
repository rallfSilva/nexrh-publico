<?php

namespace App\Servidores;

use App\Models\Servidor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Ficha de Cadastro no layout da SEGAD, com as declarações, pronta para assinar. */
class FichaPdf
{
    public function baixar(Servidor $servidor): Response
    {
        $servidor->loadMissing(['orgao', 'unidade', 'vaga.cargo', 'declaracoes']);

        $pdf = Pdf::loadView('pdf.ficha', [
            'servidor' => $servidor,
            'parentesco' => $servidor->ultimaDeclaracao('parentesco'),
            'acumulacao' => $servidor->ultimaDeclaracao('acumulacao'),
            'bens' => $servidor->ultimaDeclaracao('bens'),
            'geradoEm' => now(),
        ])->setPaper('a4');

        $pdf->getDomPDF()->getOptions()->setIsRemoteEnabled(false);

        $resposta = $pdf->download('ficha-cadastral-'.Str::slug($servidor->nome).'.pdf');
        $resposta->headers->set('Cache-Control', 'no-store, private');

        return $resposta;
    }
}
