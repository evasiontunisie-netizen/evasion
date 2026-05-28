<?php

declare(strict_types=1);

namespace App\Core\Pdf;

final class DocumentPdf
{
    public static function make(string $title, array $sections): string
    {
        $lines = [[$title, 'H1'], ['Evasion ERP - document professionnel', 'SMALL'], [' ', 'P']];
        foreach ($sections as $sectionTitle => $rows) {
            $lines[] = [(string) $sectionTitle, 'H2'];
            foreach ((array) $rows as $row) {
                $lines[] = [is_array($row) ? implode('    ', array_map('strval', $row)) : (string) $row, 'P'];
            }
            $lines[] = [' ', 'P'];
        }

        return self::render($lines);
    }

    private static function render(array $lines): string
    {
        $pages = [];
        $current = [];
        $y = 790;
        foreach ($lines as [$text, $style]) {
            foreach (self::wrap($text, $style === 'P' ? 92 : 70) as $wrapped) {
                $size = $style === 'H1' ? 20 : ($style === 'H2' ? 14 : ($style === 'SMALL' ? 9 : 10));
                $leading = $style === 'H1' ? 30 : ($style === 'H2' ? 22 : 14);
                if ($y < 56) {
                    $pages[] = $current;
                    $current = [];
                    $y = 790;
                }
                $current[] = [$wrapped, $style, $size, $y];
                $y -= $leading;
            }
        }
        if ($current !== []) {
            $pages[] = $current;
        }

        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', 'PAGES', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
        $refs = [];
        foreach ($pages as $pageIndex => $page) {
            $stream = ["0.96 0.30 0.10 rg 0 812 612 30 re f", 'BT'];
            $stream[] = '/F2 8 Tf 470 820 Td (EVASION ERP) Tj';
            $stream[] = '/F1 8 Tf 54 28 Td (Page ' . ($pageIndex + 1) . '/' . count($pages) . ') Tj';
            foreach ($page as [$text, $style, $size, $y]) {
                $font = in_array($style, ['H1', 'H2'], true) ? '/F2' : '/F1';
                $stream[] = "{$font} {$size} Tf 54 {$y} Td (" . self::escape($text) . ') Tj';
            }
            $stream[] = 'ET';
            $content = implode("\n", $stream);
            $pageNumber = count($objects) + 1;
            $contentNumber = $pageNumber + 1;
            $refs[] = "{$pageNumber} 0 R";
            $objects[] = "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /MediaBox [0 0 612 842] /Contents {$contentNumber} 0 R >>";
            $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        }
        $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', $refs) . '] /Count ' . count($refs) . ' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private static function wrap(string $text, int $width): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?: '');
        if ($clean === '') {
            return [' '];
        }

        return explode("\n", wordwrap($clean, $width, "\n", false)) ?: [$clean];
    }

    private static function escape(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);
    }
}
