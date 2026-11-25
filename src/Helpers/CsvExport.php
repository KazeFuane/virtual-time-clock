<?php
/**
 * CSV downloads that open cleanly in Excel.
 *
 * Files are written as UTF-16LE with a byte order mark and tab separators.
 * Excel opens that format straight into columns whatever the Windows region
 * (comma or semicolon list separator), and keeps accented names readable.
 * Google Sheets and LibreOffice open it too.
 */
class CsvExport
{
    /** @var resource */
    private $out;

    public function __construct(string $filename)
    {
        header('Content-Type: text/csv; charset=UTF-16LE');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        $this->out = fopen('php://output', 'w');
        fwrite($this->out, "\xFF\xFE");
    }

    public function row(array $cells): void
    {
        $line = [];
        foreach ($cells as $cell) {
            $v = (string) ($cell ?? '');
            $v = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $v);
            // Stop spreadsheets from treating text as a formula.
            if ($v !== '' && strpbrk($v[0], '=+@') !== false) {
                $v = "'" . $v;
            }
            if (strpbrk($v, '"') !== false) {
                $v = '"' . str_replace('"', '""', $v) . '"';
            }
            $line[] = $v;
        }
        fwrite($this->out, mb_convert_encoding(implode("\t", $line) . "\r\n", 'UTF-16LE', 'UTF-8'));
    }

    public function blank(): void
    {
        $this->row(['']);
    }

    public function close(): void
    {
        fclose($this->out);
    }

    /** Seconds as H:MM:SS (hours can go past 24), with a minus sign when negative. */
    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '';
        }
        $sign = $seconds < 0 ? '-' : '';
        $s = abs($seconds);
        return sprintf('%s%d:%02d:%02d', $sign, intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
    }
}
