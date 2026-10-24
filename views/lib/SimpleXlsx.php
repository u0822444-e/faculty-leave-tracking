<?php
class SimpleXlsx
{
    private $sheets = [];
    private $sheetIndex = 0;

    public function sheet(string $name, array $rows, array $colWidths = []): void
    {
        $name = preg_replace('/[\/\\\\\?\*\[\]:]/', '', $name);
        $name = substr($name, 0, 31) ?: 'Sheet' . ($this->sheetIndex + 1);
        $this->sheets[] = ['name' => $name, 'rows' => $rows, 'widths' => $colWidths];
        $this->sheetIndex++;
    }

    public function download(string $filename): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create XLSX archive.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->worksheet($sheet['rows'], $sheet['widths']));
        }
        $zip->close();

        if (ob_get_level())
            ob_end_clean();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: max-age=0');

        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function contentTypes(): string
    {
        $o = '';
        foreach ($this->sheets as $i => $_) {
            $o .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $o . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(): string
    {
        $x = '';
        foreach ($this->sheets as $i => $s) {
            $x .= '<sheet name="' . htmlspecialchars($s['name'], ENT_XML1) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $x . '</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $r = '';
        $n = count($this->sheets);
        for ($i = 0; $i < $n; $i++) {
            $r .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $r .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $r . '</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // ORDER MATTERS: numFmts must come FIRST
            . '<numFmts count="1">'
            . '<numFmt numFmtId="164" formatCode="&quot;₱&quot;#,##0.00"/>'
            . '</numFmts>'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F5E3D"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function worksheet(array $rows, array $widths = []): string
    {
        $cols = '';
        if ($widths) {
            $cols = '<cols>';
            foreach ($widths as $i => $w) {
                $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $w . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }
        $data = '<sheetData>';
        foreach ($rows as $ri => $row) {
            $rowNum = $ri + 1;
            $data .= '<row r="' . $rowNum . '">';
            $ci = 0;
            foreach ($row as $cell) {
                $ci++;
                $ref = $this->colLetter($ci) . $rowNum;
                $style = $ri === 0 ? 1 : 0;
                if (is_array($cell) && array_key_exists('v', $cell)) {
                    $v = $cell['v'];
                    $s = $cell['style'] ?? null;
                    if ($s === 'currency')
                        $style = 2;
                    elseif ($s === 'date')
                        $style = 3;
                    elseif ($s === 'bold')
                        $style = 4;
                    elseif ($s === 'total')
                        $style = 5;
                } else {
                    $v = $cell;
                }
                if (is_numeric($v)) {
                    $data .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $this->num($v) . '</v></c>';
                } else {
                    $data .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</t></is></c>';
                }
            }
            $data .= '</row>';
        }
        $data .= '</sheetData>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $cols . $data . '</worksheet>';
    }

    private function num($v): string
    {
        if (is_int($v) || (is_string($v) && ctype_digit($v)))
            return (string) $v;
        $f = (float) $v;
        if (floor($f) == $f)
            return (string) (int) $f;
        return rtrim(rtrim(number_format($f, 4, '.', ''), '0'), '.');
    }

    private function colLetter(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26);
        }
        return $s;
    }
}