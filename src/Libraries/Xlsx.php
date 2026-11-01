<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * A small .xlsx reader and writer for one sheet of text and numbers (the users export and import). It needs only
 * ZipArchive and DOM; no spreadsheet library. Text is written as inline strings, so a value that starts with '='
 * stays text and is never a formula.
 */
final class Xlsx
{
    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_PKG  = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const XML     = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

    /**
     * @param list<list<string|int|float>> $rows
     * @return string the .xlsx file content
     */
    public static function write(array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($tmp === false) {
            throw new RuntimeException('Cannot create a temporary file for the Excel file.');
        }

        $zip = new ZipArchive();

        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the Excel file.');
        }

        $zip->addFromString('[Content_Types].xml', self::XML
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', self::XML
            . '<Relationships xmlns="' . self::NS_PKG . '">'
            . '<Relationship Id="rId1" Type="' . self::NS_REL . '/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::XML
            . '<workbook xmlns="' . self::NS_MAIN . '" xmlns:r="' . self::NS_REL . '">'
            . '<sheets><sheet name="Users" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::XML
            . '<Relationships xmlns="' . self::NS_PKG . '">'
            . '<Relationship Id="rId1" Type="' . self::NS_REL . '/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');

        $sheet = self::XML . '<worksheet xmlns="' . self::NS_MAIN . '"><sheetData>';

        foreach (array_values($rows) as $i => $row) {
            $number = $i + 1;
            $sheet .= '<row r="' . $number . '">';

            foreach (array_values($row) as $j => $value) {
                $ref = self::column($j) . $number;

                if (is_int($value) || is_float($value)) {
                    $sheet .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                        . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
                }
            }

            $sheet .= '</row>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet . '</sheetData></worksheet>');
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        unlink($tmp);

        return $bytes;
    }

    /**
     * Reads the first sheet of an .xlsx file into rows of strings. Empty cells in between are returned as ''.
     *
     * @return list<list<string>>
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The file is not a valid Excel (.xlsx) file.');
        }

        $sheetXml   = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedXml  = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The Excel file has no first sheet.');
        }

        $shared = [];

        if ($sharedXml !== false) {
            $doc = self::load($sharedXml);

            foreach ((new DOMXPath($doc))->query('/x:sst/x:si') ?: [] as $si) {
                $shared[] = self::text($si);
            }
        }

        $doc   = self::load($sheetXml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('x', self::NS_MAIN);
        $rows = [];

        foreach ($xpath->query('//x:sheetData/x:row') ?: [] as $row) {
            $cells = [];

            foreach ($xpath->query('x:c', $row) ?: [] as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $index = self::columnIndex($cell->getAttribute('r'));
                $type  = $cell->getAttribute('t');

                if ($type === 's') {
                    $value = $shared[(int) self::childText($cell, 'v')] ?? '';
                } elseif ($type === 'inlineStr') {
                    $is    = $cell->getElementsByTagNameNS(self::NS_MAIN, 'is')->item(0);
                    $value = $is instanceof DOMElement ? self::text($is) : '';
                } else {
                    $value = self::childText($cell, 'v');
                }

                $cells[$index] = $value;
            }

            if ($cells === []) {
                $rows[] = [];
                continue;
            }

            $width = max(array_keys($cells)) + 1;
            $line  = [];

            for ($i = 0; $i < $width; $i++) {
                $line[] = $cells[$i] ?? '';
            }

            $rows[] = $line;
        }

        return $rows;
    }

    /** Loads XML without external entities or network access. */
    private static function load(string $xml): DOMDocument
    {
        $doc = new DOMDocument();

        if (! $doc->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The Excel file is damaged.');
        }

        return $doc;
    }

    /** The text of a shared string or inline string: its <t> elements, including rich-text runs. */
    private static function text(\DOMNode $node): string
    {
        $out = '';

        foreach ($node->getElementsByTagNameNS(self::NS_MAIN, 't') as $t) {
            $out .= $t->textContent;
        }

        return $out;
    }

    private static function childText(DOMElement $cell, string $name): string
    {
        $node = $cell->getElementsByTagNameNS(self::NS_MAIN, $name)->item(0);

        return $node instanceof DOMElement ? $node->textContent : '';
    }

    /** 0-based column number of a cell reference such as 'C7'. */
    private static function columnIndex(string $ref): int
    {
        $letters = strtoupper((string) preg_replace('/\d+$/', '', $ref));
        $index   = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /** Column letters for a 0-based index: 0 → A, 25 → Z, 26 → AA. */
    private static function column(int $index): string
    {
        $letters = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + (($n - 1) % 26)) . $letters;
        }

        return $letters;
    }
}
