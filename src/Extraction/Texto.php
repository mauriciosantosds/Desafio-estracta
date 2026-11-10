<?php

declare(strict_types=1);

namespace ProvaSpider\Extraction;

/**
 * Normalização de textos extraídos.
 */
final class Texto
{
    /**
     * Troca &nbsp; (U+00A0) e qualquer sequência de espaços por um espaço simples.
     * Texto vazio vira null.
     */
    public static function normalizar(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        $texto = str_replace("\u{00A0}", ' ', $texto);
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

        return $texto === '' ? null : $texto;
    }

    /**
     * Remove a máscara mantendo os dígitos como string (zeros à esquerda preservados).
     */
    public static function apenasDigitos(?string $texto): ?string
    {
        $digitos = preg_replace('/\D+/', '', $texto ?? '') ?? '';

        return $digitos === '' ? null : $digitos;
    }
}
