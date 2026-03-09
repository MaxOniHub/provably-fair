<?php
declare(strict_types=1);

namespace Provably;

class Printer
{
    public const int TIMES = 80;

    public static function section(string $title): void
    {
        echo '<pre>';
        echo PHP_EOL . str_repeat('=', self::TIMES) . PHP_EOL;
        echo "  > {$title}" . PHP_EOL;
        echo str_repeat('=', self::TIMES) . PHP_EOL;
        echo '</pre>';
    }

    public static function row(string $label, string $value): void
    {
        echo '<pre>';
        echo sprintf("  %-26s %s\n", $label . ':', $value);
        echo '</pre>';
    }

    public static function text(string $text): void
    {
        echo '<pre>' . $text . '</pre>';
    }
}
