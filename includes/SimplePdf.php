<?php
declare(strict_types=1);

/**
 * Gerador de PDF mínimo, sem bibliotecas externas.
 *  - Fontes padrão do PDF (Helvetica e Helvetica-Bold), codificação WinAnsi (acentos do português).
 *  - Coordenadas com origem no canto superior esquerdo, em pontos (1 pt = 1/72 pol).
 *  - Logo opcional em PNG RGB de 8 bits, sem entrelaçamento (assets/img/logo_pdf.png).
 */
final class SimplePdf
{
    public float $w;
    public float $h;

    private array $pages = [];
    private string $cur = '';
    private ?array $img = null;     // [w, h, idat]
    private array $cw = [];         // largura (em 1/1000 em) por byte, Helvetica
    private string $title;

    public function __construct(string $title = 'Documento', bool $landscape = true)
    {
        $this->title = $title;
        [$this->w, $this->h] = $landscape ? [841.89, 595.28] : [595.28, 841.89];
        $this->buildWidths();
    }

    /* ---------- Página ---------- */
    public function addPage(): void
    {
        if ($this->cur !== '' || $this->pages) $this->pages[] = $this->cur;
        $this->cur = '';
    }

    /* ---------- Desenho ---------- */
    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return sprintf('%.3F %.3F %.3F', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255);
    }

    public function rect(float $x, float $y, float $w, float $h, ?string $fill, ?string $stroke = null, float $lw = 0.5): void
    {
        $op = '';
        if ($fill !== null)   $op .= self::rgb($fill) . " rg\n";
        if ($stroke !== null) $op .= self::rgb($stroke) . " RG\n" . sprintf('%.2F w', $lw) . "\n";
        $paint = $fill !== null && $stroke !== null ? 'B' : ($fill !== null ? 'f' : 'S');
        $this->cur .= $op . sprintf('%.2F %.2F %.2F %.2F re %s', $x, $this->h - $y - $h, $w, $h, $paint) . "\n";
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $hex = '#000000', float $lw = 0.5): void
    {
        $this->cur .= self::rgb($hex) . " RG\n" . sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $lw, $x1, $this->h - $y1, $x2, $this->h - $y2) . "\n";
    }

    /** $y é a linha de base do texto, medida a partir do topo. */
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, string $hex = '#000000'): void
    {
        $this->cur .= sprintf("BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            self::rgb($hex), $bold ? 'F2' : 'F1', $size, $x, $this->h - $y, $this->esc($this->toWin($s)));
    }

    public function loadLogo(string $path): bool
    {
        $d = is_file($path) ? @file_get_contents($path) : false;
        if ($d === false || substr($d, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;
        $pos = 8; $idat = ''; $meta = null;
        while ($pos + 8 <= strlen($d)) {
            $len  = unpack('N', substr($d, $pos, 4))[1];
            $type = substr($d, $pos + 4, 4);
            $body = substr($d, $pos + 8, $len);
            if ($type === 'IHDR') $meta = unpack('Nw/Nh/Cbd/Cct/Ccm/Cfm/Cil', $body);
            elseif ($type === 'IDAT') $idat .= $body;
            elseif ($type === 'IEND') break;
            $pos += 12 + $len;
        }
        // Somente PNG RGB, 8 bits, sem entrelaçamento (repassado ao PDF sem recodificar).
        if (!$meta || $meta['bd'] !== 8 || $meta['ct'] !== 2 || $meta['il'] !== 0 || $idat === '') return false;
        $this->img = [$meta['w'], $meta['h'], $idat];
        return true;
    }

    public function image(float $x, float $y, float $w, float $h): void
    {
        if (!$this->img) return;
        $this->cur .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $w, $h, $x, $this->h - $y - $h);
    }

    /* ---------- Texto: medição e quebra de linha ---------- */
    public function textWidth(string $s, float $size, bool $bold = false): float
    {
        $s = $this->toWin($s);
        $t = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) $t += $this->cw[ord($s[$i])] ?? 556;
        return $t * $size / 1000 * ($bold ? 1.08 : 1.0) * 1.02; // margem de segurança
    }

    /** Quebra o texto em linhas que cabem em $width (quebra palavras muito longas). */
    public function wrap(string $s, float $width, float $size, bool $bold = false): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $s) ?: [''] as $para) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($para), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($this->textWidth($try, $size, $bold) <= $width) { $line = $try; continue; }
                if ($line !== '') { $lines[] = $line; $line = ''; }
                while ($this->textWidth($word, $size, $bold) > $width && $this->strlen($word) > 1) {
                    $cut = $this->strlen($word) - 1;
                    while ($cut > 1 && $this->textWidth($this->substr($word, 0, $cut), $size, $bold) > $width) $cut--;
                    $lines[] = $this->substr($word, 0, $cut);
                    $word = $this->substr($word, $cut);
                }
                $line = $word;
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /* ---------- Saída ---------- */
    /** @param callable|null $footer function(SimplePdf $pdf, int $page, int $total): void */
    public function output(?callable $footer = null): string
    {
        $this->pages[] = $this->cur;
        $this->cur = '';
        $total = count($this->pages);
        if ($footer) {
            foreach ($this->pages as $i => $content) {
                $this->cur = '';
                $footer($this, $i + 1, $total);
                $this->pages[$i] = $content . $this->cur;
            }
            $this->cur = '';
        }

        $objs = [];                                   // índice = número do objeto - 1
        $objs[0] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[1] = '';                                // preenchido depois (Pages)
        $objs[2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< >>';                           // objeto 5: imagem (ou vazio, mantendo a numeração)
        $imgRef = '';
        if ($this->img) {
            [$iw, $ih, $idat] = $this->img;
            $objs[4] = "<< /Type /XObject /Subtype /Image /Width $iw /Height $ih /ColorSpace /DeviceRGB /BitsPerComponent 8 "
                . "/Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns $iw >> /Length "
                . strlen($idat) . " >>\nstream\n" . $idat . "\nendstream";
            $imgRef = '/XObject << /Im1 5 0 R >>';
        }
        $kids = [];
        foreach ($this->pages as $content) {
            $stream = $content;
            $filter = '';
            if (function_exists('gzcompress')) { $stream = gzcompress($content, 6); $filter = ' /Filter /FlateDecode'; }
            $objs[] = "<< /Length " . strlen($stream) . "$filter >>\nstream\n" . $stream . "\nendstream";
            $contentNo = count($objs);
            $objs[] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Contents %d 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> %s >> >>',
                $this->w, $this->h, $contentNo, $imgRef);
            $kids[] = count($objs) . ' 0 R';
        }
        $objs[1] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $info = count($objs) + 1;

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offs = [];
        foreach ($objs as $i => $body) {
            $offs[$i] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $offs[] = strlen($pdf);
        $pdf .= "$info 0 obj\n<< /Title (" . $this->esc($this->ascii($this->title)) . ") /Creator (OBSIDIAN EXPRESS) /Producer (SimplePdf) >>\nendobj\n";
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($info + 1) . "\n0000000000 65535 f \n";
        foreach ($offs as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        $pdf .= "trailer\n<< /Size " . ($info + 1) . " /Root 1 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }

    /* ---------- Auxiliares ---------- */
    private function esc(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    private function ascii(string $s): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', strtr($s, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ç'=>'C'])) ?? '';
    }

    /** UTF-8 -> Windows-1252 (o que a fonte WinAnsi espera). */
    private function toWin(string $s): string
    {
        if (function_exists('mb_convert_encoding')) return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        if (function_exists('iconv')) { $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s); if ($r !== false) return $r; }
        return $this->ascii($s);
    }

    private function strlen(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); }
    private function substr(string $s, int $a, ?int $l = null): string
    {
        return function_exists('mb_substr') ? mb_substr($s, $a, $l) : ($l === null ? substr($s, $a) : substr($s, $a, $l));
    }

    /** Larguras da Helvetica (métrica AFM padrão); acentuados seguem a letra-base. */
    private function buildWidths(): void
    {
        $ascii = [32=>278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
            556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,
            667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,
            278,278,278,469,556,333,
            556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,
            334,260,334,584];
        foreach ($ascii as $i => $w) $this->cw[$i] = $w;
        $ranges = [[192,197,667],[198,198,1000],[199,199,722],[200,203,667],[204,207,278],[208,209,722],[210,214,778],[215,215,584],
            [216,216,778],[217,220,722],[221,222,667],[223,223,611],[224,229,556],[230,230,889],[231,231,500],[232,235,556],
            [236,239,278],[240,246,556],[247,247,584],[248,248,611],[249,252,556],[253,253,500],[254,254,556],[255,255,500]];
        foreach ($ranges as [$a, $b, $w]) for ($i = $a; $i <= $b; $i++) $this->cw[$i] = $w;
    }
}

/**
 * Monta o relatório diário de fretes em PDF.
 * Colunas: Nº, data, transportador, origem, destino, produtos e status.
 */
function build_daily_report_pdf(array $rows, string $date, string $logoPath): string
{
    $pdf = new SimplePdf('OBSIDIAN EXPRESS - Relatorio diario de fretes ' . $date, true);
    $hasLogo = $pdf->loadLogo($logoPath);

    $ml = 36.0; $mr = 36.0; $bottom = 40.0;
    $cols = [['Nº', 34], ['Data', 58], ['Transportador', 115], ['Origem', 152], ['Destino', 152], ['Produtos', 188], ['Status', 70]];
    $fs = 8.5; $lead = 11.0; $padX = 4.0; $padY = 4.0;
    $navy = '#101D3A'; $zebra = '#EAF0FB'; $ink = '#1A2238';

    $drawHeader = function (SimplePdf $p, bool $first) use ($hasLogo, $date, $rows, $ml, $navy): float {
        if ($first) {
            $p->rect(0, 0, $p->w, 64, '#000000');
            if ($hasLogo) $p->image($ml, 8, 48 * 720 / 337, 48);
            else $p->text($ml, 38, 'OBSIDIAN EXPRESS', 20, true, '#FFFFFF');
            $p->text($p->w - 36 - $p->textWidth('Relatório diário de fretes', 14, true), 30, 'Relatório diário de fretes', 14, true, '#FFFFFF');
            $sub = 'Data: ' . fmt_date($date) . '   |   Total de fretes: ' . count($rows);
            $p->text($p->w - 36 - $p->textWidth($sub, 9), 46, $sub, 9, false, '#82B6F5');
            $p->text($ml, 84, 'Gerado em ' . date('d/m/Y H:i'), 8.5, false, '#555555');
            return 96.0;
        }
        $p->rect(0, 0, $p->w, 22, '#000000');
        $p->text($ml, 15, 'OBSIDIAN EXPRESS  -  Relatório diário de fretes  -  ' . fmt_date($date), 8.5, true, '#FFFFFF');
        return 36.0;
    };

    $drawColumns = function (SimplePdf $p, float $y) use ($cols, $ml, $navy, $fs, $padX): float {
        $tw = array_sum(array_column($cols, 1));
        $p->rect($ml, $y, $tw, 20, $navy);
        $x = $ml;
        foreach ($cols as [$name, $w]) { $p->text($x + $padX, $y + 13.5, $name, $fs, true, '#FFFFFF'); $x += $w; }
        return $y + 20;
    };

    $pdf->addPage();
    $y = $drawColumns($pdf, $drawHeader($pdf, true));
    $tw = array_sum(array_column($cols, 1));

    if (!$rows) {
        $pdf->rect($ml, $y, $tw, 30, '#F4F6FB', '#C9D2E6');
        $pdf->text($ml + 10, $y + 19, 'Nenhum frete encontrado para esta data.', 10, false, $ink);
    }

    foreach ($rows as $n => $r) {
        $cells = [(string)$r['id'], fmt_date((string)$r['freight_date']), (string)$r['carrier'], (string)$r['origin'],
                  (string)$r['destination'], (string)($r['products'] ?? '') ?: '-', (string)$r['status']];
        $wrapped = []; $maxLines = 1;
        foreach ($cells as $i => $txt) {
            $wrapped[$i] = $pdf->wrap($txt, $cols[$i][1] - 2 * $padX, $fs);
            $maxLines = max($maxLines, count($wrapped[$i]));
        }
        $rowH = $maxLines * $lead + 2 * $padY;

        if ($y + $rowH > $pdf->h - $bottom) {
            $pdf->addPage();
            $y = $drawColumns($pdf, $drawHeader($pdf, false));
        }
        $pdf->rect($ml, $y, $tw, $rowH, $n % 2 ? $zebra : '#FFFFFF');
        $x = $ml;
        foreach ($wrapped as $i => $lines) {
            foreach ($lines as $k => $ln) $pdf->text($x + $padX, $y + $padY + $fs + $k * $lead - 1, $ln, $fs, false, $ink);
            $x += $cols[$i][1];
        }
        $pdf->line($ml, $y + $rowH, $ml + $tw, $y + $rowH, '#C9D2E6', 0.4);
        $y += $rowH;
    }

    return $pdf->output(function (SimplePdf $p, int $page, int $total): void {
        $label = 'Página ' . $page . ' de ' . $total;
        $p->text($p->w - 36 - $p->textWidth($label, 8), $p->h - 20, $label, 8, false, '#555555');
        $p->text(36, $p->h - 20, 'OBSIDIAN EXPRESS', 8, true, '#555555');
    });
}
