<?php

class TextFormatter
{
    /* ==========================
       PLAIN-TEXT (Textarea etc.)
    ========================== */

    public static function plainToHtml(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    public static function normalizeNewlines(?string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text ?? '');
    }

    /* ==========================
       HTML (CKEditor etc.)
    ========================== */

    public static function htmlNormalize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // entfernt <br />\r\n, <br>\n etc.
        $html = preg_replace('~<br\s*/?>\s*[\r\n]+~i', '<br>', $html);

        return trim($html);
    }

    /* ==========================
       OUTPUT ONLY
    ========================== */

    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

