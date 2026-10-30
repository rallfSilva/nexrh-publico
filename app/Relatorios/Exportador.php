<?php

namespace App\Relatorios;

use App\Models\User;
use App\Support\Auditoria;
use App\Support\Marca;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gera o relatório em Excel ou PDF. O conteúdo é o mesmo da tela (CPF mascarado)
 * e cada exportação fica na trilha de auditoria, com os filtros usados.
 */
class Exportador
{
    public function excel(Relatorio $r, User $user, array $filtros, Collection $linhas): BinaryFileResponse
    {
        $colunas = $r->colunas($user);
        $numericas = $r->numericas();
        $caminho = tempnam(sys_get_temp_dir(), 'rel').'.xlsx';

        $options = new Options(properties: new Properties(title: $r->titulo(), application: 'NexRH Público', creator: 'NexRH Público', lastModifiedBy: $user->name));
        $i = 1;
        foreach ($colunas as $chave => $rotulo) {
            $maior = max(mb_strlen($rotulo), $linhas->take(300)->max(fn ($l) => mb_strlen((string) ($l[$chave] ?? ''))) ?? 0);
            $options->setColumnWidth(min(60, max(8, $maior + 2)), $i++);
        }

        $writer = new Writer($options);
        $writer->openToFile($caminho);

        $titulo = (new Style)->withFontBold(true)->withFontSize(14)->withFontColor('1E5470');
        $cinza = (new Style)->withFontColor('5B7383')->withFontSize(10);
        $cabecalho = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('1E5470');

        $writer->addRow(new Row([Cell::fromValue($r->titulo().' · '.($user->orgao?->sigla ?? 'Todos os órgãos'), $titulo)]));
        $writer->addRow(new Row([Cell::fromValue($r->textoDosFiltros($user, $filtros).' · gerado em '.now()->format('d/m/Y H:i').' por '.$user->name.' · CPF mascarado', $cinza)]));
        $writer->addRow(new Row([]));
        $writer->addRow(new Row(array_values(array_map(fn ($rotulo) => Cell::fromValue($rotulo, $cabecalho), $colunas))));

        foreach ($linhas as $linha) {
            $celulas = [];
            foreach (array_keys($colunas) as $chave) {
                $v = $linha[$chave] ?? null;
                $celulas[] = Cell::fromValue(in_array($chave, $numericas, true) && is_numeric($v) ? (int) $v : ($v === null ? '' : (string) $v));
            }
            $writer->addRow(new Row($celulas));
        }
        $writer->close();

        $this->auditar($r, $user, $filtros, $linhas->count(), 'Excel');

        return response()->download($caminho, $this->nomeArquivo($r, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend();
    }

    public function pdf(Relatorio $r, User $user, array $filtros, Collection $linhas): Response
    {
        $pdf = Pdf::loadView('pdf.relatorio', [
            'relatorio' => $r,
            'colunas' => $r->colunas($user),
            'numericas' => $r->numericas(),
            'linhas' => $linhas,
            'filtros' => $r->textoDosFiltros($user, $filtros),
            'usuario' => $user,
            'geradoEm' => now(),
            'logo' => Marca::simboloPng(),
        ])->setPaper('a4', count($r->colunas($user)) > 5 ? 'landscape' : 'portrait');
        $pdf->getDomPDF()->getOptions()->setIsRemoteEnabled(false)->setIsFontSubsettingEnabled(true);

        $this->auditar($r, $user, $filtros, $linhas->count(), 'PDF');

        $resposta = $pdf->download($this->nomeArquivo($r, 'pdf'));
        $resposta->headers->set('Cache-Control', 'no-store, private');

        return $resposta;
    }

    private function auditar(Relatorio $r, User $user, array $filtros, int $total, string $formato): void
    {
        Auditoria::registrar('exportacao', null, "Relatório \"{$r->titulo()}\" exportado em {$formato} ({$total} linhas)", null, [
            'relatorio' => $r->chave(),
            'formato' => $formato,
            'filtros' => array_filter($filtros, fn ($v) => $v !== null),
            'linhas' => $total,
        ], $user);
    }

    private function nomeArquivo(Relatorio $r, string $extensao): string
    {
        return 'nexrh-'.Str::slug($r->titulo()).'-'.now()->format('Y-m-d').'.'.$extensao;
    }
}
