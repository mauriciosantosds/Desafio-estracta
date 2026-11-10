<?php

declare(strict_types=1);

namespace ProvaSpider\Extraction;

/**
 * Converte o corpo HTML para UTF-8.
 *
 * Ordem de decisão do charset: header Content-Type, depois <meta> do documento.
 * Se o charset declarado for UTF-8 (ou nenhum) mas os bytes não forem UTF-8 válido,
 * assume Windows-1252, superconjunto prático do ISO-8859-1 usado pelo portal.
 */
final class HtmlDecoder
{
    public static function paraUtf8(string $bytes, ?string $contentType = null): string
    {
        $charset = self::charsetDeclarado($contentType ?? '')
            ?? self::charsetDeclarado(substr($bytes, 0, 2048));

        if ($charset === null || in_array($charset, ['utf-8', 'utf8'], true)) {
            if (mb_check_encoding($bytes, 'UTF-8')) {
                return $bytes;
            }
            $charset = 'windows-1252';
        }

        if (in_array($charset, ['iso-8859-1', 'latin1', 'us-ascii'], true)) {
            $charset = 'windows-1252';
        }

        $convertido = @mb_convert_encoding($bytes, 'UTF-8', $charset);

        return is_string($convertido) ? $convertido : mb_convert_encoding($bytes, 'UTF-8', 'windows-1252');
    }

    private static function charsetDeclarado(string $texto): ?string
    {
        if (preg_match('/charset\s*=\s*["\']?([a-z0-9_\-]+)/i', $texto, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }
}
