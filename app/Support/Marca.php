<?php

namespace App\Support;

/**
 * Logo do NexRH Público para os documentos gerados: o PDF (DomPDF, sem acesso
 * a arquivos externos) e a impressão pelo navegador recebem o símbolo embutido.
 */
final class Marca
{
    public const SIMBOLO = 'img/marca/simbolo.svg';

    /** Símbolo em PNG: o DomPDF desenha imagens PNG com mais fidelidade que SVG. */
    public const SIMBOLO_PNG = 'img/marca/simbolo-512.png';

    public static function simboloPng(): string
    {
        return 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path(self::SIMBOLO_PNG)));
    }

    /** Símbolo em SVG já com o tamanho em pixels, para usar em `content: url(...)` no CSS. */
    public static function simboloSvg(int $tamanho): string
    {
        $svg = str_replace('<svg ', '<svg width="'.$tamanho.'" height="'.$tamanho.'" ', (string) file_get_contents(public_path(self::SIMBOLO)));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Texto como string CSS, com tudo que não for letra, número ou espaço escapado. */
    public static function textoCss(string $texto): string
    {
        $escapado = preg_replace_callback(
            '/[^\p{L}\p{N} .,:\/·-]/u',
            fn (array $m) => '\\'.dechex(mb_ord($m[0])).' ',
            $texto,
        );

        return '"'.$escapado.'"';
    }
}
