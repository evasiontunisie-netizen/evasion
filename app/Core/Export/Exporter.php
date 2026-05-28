<?php

declare(strict_types=1);

namespace App\Core\Export;

final class Exporter
{
    public static function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
        }
        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    public static function xls(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Export" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Table>';
        if ($rows !== []) {
            $xml .= self::row(array_keys($rows[0]));
            foreach ($rows as $row) {
                $xml .= self::row(array_values($row));
            }
        }

        return $xml . '</Table></Worksheet></Workbook>';
    }

    public static function pdf(string $title, array $rows): string
    {
        $text = $title . "\n\n";
        foreach (array_slice($rows, 0, 80) as $row) {
            $text .= implode(' | ', array_map(static fn ($value): string => (string) $value, $row)) . "\n";
        }

        $stream = "BT /F1 10 Tf 40 780 Td (" . self::pdfText($text) . ") Tj ET";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 595 842] /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length ' . strlen($stream) . ' >> stream ' . $stream . ' endstream endobj',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        return $pdf . "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private static function row(array $cells): string
    {
        $xml = '<Row>';
        foreach ($cells as $cell) {
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $cell, ENT_XML1) . '</Data></Cell>';
        }

        return $xml . '</Row>';
    }

    private static function pdfText(string $value): string
    {
        return str_replace(["\\", '(', ')', "\r", "\n"], ['\\\\', '\(', '\)', '', '\n'], substr($value, 0, 3500));
    }
}
