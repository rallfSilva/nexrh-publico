<?php

namespace App\Http\Controllers;

use App\Support\Auditoria;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModeloImportacaoController extends Controller
{
    public function index(): View
    {
        return view('modelos.index', ['modelos' => config('nexrh.modelos_importacao')]);
    }

    public function baixar(string $arquivo): BinaryFileResponse
    {
        // Só arquivos da lista: impede baixar qualquer outro arquivo do servidor.
        abort_unless(array_key_exists($arquivo, config('nexrh.modelos_importacao')), 404);

        Auditoria::registrar('download', null, "Baixou o modelo de planilha {$arquivo}");

        return response()->download(resource_path('modelos-importacao/'.$arquivo));
    }
}
