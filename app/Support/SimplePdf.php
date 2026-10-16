<?php
// app/Support/SimplePdf.php

namespace App\Support;

/**
 * A tiny dependency-free PDF writer (A4, built-in Helvetica fonts, text + lines + boxes).
 * It exists because receipts/invoices are simple documents and this avoids pulling in a
 * large library. y coordinates are measured from the TOP of the page, in points.
 * The peso sign is not in the PDF base fonts, so callers write "PHP 1,500.00".
 */
class SimplePdf
{
    public const WIDTH = 595.28;
    public const HEIGHT = 841.89;

    /** Helvetica glyph widths (per 1000 em) for ASCII 32..126. */
    private const WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var string[] content stream per page */
    private array $pages = [''];

    private int $current = 0;

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->current = count($this->pages) - 1;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function textWidth(string $text, float $size, bool $bold = false): float
    {
        $width = 0;

        foreach (str_split($this->encode($text)) as $char) {
            $code = ord($char);
            $width += self::WIDTHS[$code - 32] ?? 556;
        }

        return $width * $size / 1000 * ($bold ? 1.06 : 1);
    }

    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, array $rgb = [0.12, 0.16, 0.22], string $align = 'left'): void
    {
        if ($align === 'right') {
            $x -= $this->textWidth($text, $size, $bold);
        } elseif ($align === 'center') {
            $x -= $this->textWidth($text, $size, $bold) / 2;
        }

        $this->pages[$this->current] .= sprintf(
            "BT /%s %s Tf %s rg %s %s Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1',
            $this->n($size),
            $this->color($rgb),
            $this->n($x),
            $this->n(self::HEIGHT - $y),
            $this->escape($this->encode($text))
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5, array $rgb = [0.85, 0.85, 0.85]): void
    {
        $this->pages[$this->current] .= sprintf(
            "%s w %s RG %s %s m %s %s l S\n",
            $this->n($width), $this->color($rgb),
            $this->n($x1), $this->n(self::HEIGHT - $y1), $this->n($x2), $this->n(self::HEIGHT - $y2)
        );
    }

    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $strokeWidth = 0.8): void
    {
        $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $cmd = '';
        if ($fill) {
            $cmd .= $this->color($fill) . " rg\n";
        }
        if ($stroke || ! $fill) {
            $cmd .= $this->color($stroke ?? [0, 0, 0]) . ' RG ' . $this->n($strokeWidth) . " w\n";
        }

        $this->pages[$this->current] .= $cmd . sprintf(
            "%s %s %s %s re %s\n",
            $this->n($x), $this->n(self::HEIGHT - $y - $h), $this->n($w), $this->n($h), $op
        );
    }

    /** Assemble the complete PDF file. */
    public function output(): string
    {
        $objects = [];
        $pageCount = count($this->pages);

        // 1 catalog, 2 page tree, 3/4 fonts, then (page, content) pairs
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $i => $content) {
            $pageId = 5 + $i * 2;
            $contentId = $pageId + 1;
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->n(self::WIDTH), $this->n(self::HEIGHT), $contentId
            );
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref
0 ' . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . 'trailer
<< /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>
startxref
' . $xref . "\n%%EOF";
    }

    /** UTF-8 -> Windows-1252 (the encoding the base fonts use). */
    private function encode(string $text): string
    {
        $text = str_replace(['₱', '–', '—', '·', '‘', '’', '“', '”'], ['PHP ', '-', '-', '-', "'", "'", '"', '"'], $text);

        return iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text) ?: '';
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $text);
    }

    private function color(array $rgb): string
    {
        return implode(' ', array_map(fn ($c) => $this->n($c), $rgb));
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
