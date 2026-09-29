<?php

namespace App\Support;

/**
 * A minimal one-page PDF (Helvetica text only), for seeded placeholder documents without a PDF library.
 */
class SimplePdf
{
    /**
     * @param  list<string>  $lines  first line is the title; long lines are wrapped
     */
    public static function make(array $lines): string
    {
        $escape = fn (string $text) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $wrapped = [];

        foreach ($lines as $i => $line) {
            foreach ($i === 0 ? [$line] : explode("\n", wordwrap($line, 90)) as $part) {
                $wrapped[] = $escape($part);
            }
        }

        $title = array_shift($wrapped);
        $stream = "BT /F1 18 Tf 72 720 Td ({$title}) Tj /F1 11 Tf 0 -30 Td 15 TL";

        foreach ($wrapped as $line) {
            $stream .= " ({$line}) Tj T*";
        }

        $stream .= ' ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
