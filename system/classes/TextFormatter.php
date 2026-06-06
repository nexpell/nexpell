<?php

class TextFormatter
{
    public static function htmlNormalize(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function format(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function formatHtml(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function render(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function renderHtml(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function parse(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function parseHtml(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function sanitize(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function sanitizeHtml(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function output(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function __invoke(?string $text = ''): string
    {
        return self::normalizeValue($text);
    }

    public function __call(string $name, array $arguments): string
    {
        $text = $arguments[0] ?? '';
        return self::normalizeValue(is_string($text) ? $text : '');
    }

    public static function __callStatic(string $name, array $arguments): string
    {
        $text = $arguments[0] ?? '';
        return self::normalizeValue(is_string($text) ? $text : '');
    }

    private static function normalizeValue(?string $text = ''): string
    {
        $value = (string)$text;
        return trim($value) === '' ? '' : $value;
    }
}
