<?php

namespace App\Importacao;

use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/** Lê um arquivo .xlsx inteiro como [nome da aba => linhas], com textos já aparados. */
class LeitorPlanilha
{
    /** @return array<string, list<list<mixed>>> */
    public function ler(string $caminho): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $reader->open($caminho);
        } catch (\Throwable $e) {
            throw new RuntimeException('Não foi possível abrir a planilha. Confira se é um arquivo .xlsx válido.', previous: $e);
        }

        $abas = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $linhas = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $valores = [];
                    for ($c = 0; $c < $row->getNumCells(); $c++) {
                        $cell = $row->cells[$c] ?? null;
                        // Fórmulas: vale o resultado guardado no arquivo, não a fórmula.
                        $v = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell?->getValue();
                        $valores[] = is_string($v) ? trim(preg_replace('/\s+/u', ' ', $v)) : $v;
                    }
                    $linhas[] = $valores;
                }
                $abas[$sheet->getName()] = $linhas;
            }
        } finally {
            $reader->close();
        }

        return $abas;
    }
}
