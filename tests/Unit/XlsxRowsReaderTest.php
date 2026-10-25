<?php

use App\Services\XlsxRowsReader;

test('it reads utf16 worksheet rows from xlsx files', function () {
    $path = tempnam(sys_get_temp_dir(), 'xlsx-reader-').'.xlsx';
    $zip = new \ZipArchive();

    expect($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE))->toBeTrue();

    $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);

    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);

    $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="4" uniqueCount="4">
  <si><t>Time</t></si>
  <si><t>Symbol</t></si>
  <si><t>2026.05.04 05:00:18</t></si>
  <si><t>XAUUSD-VIP</t></si>
</sst>
XML);

    $sheetXml = <<<'XML'
<?xml version="1.0" encoding="UTF-16"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>
    <row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2" t="s"><v>3</v></c><c r="C2"><v>4616.00</v></c></row>
  </sheetData>
</worksheet>
XML;

    $zip->addFromString('xl/worksheets/sheet1.xml', "\xFF\xFE".iconv('UTF-8', 'UTF-16LE', $sheetXml));
    $zip->close();

    try {
        $sheets = (new XlsxRowsReader())->sheets($path);

        expect($sheets['Sheet1'])->toHaveCount(2)
            ->and($sheets['Sheet1'][0])->toBe(['Time', 'Symbol'])
            ->and($sheets['Sheet1'][1])->toBe(['2026.05.04 05:00:18', 'XAUUSD-VIP', '4616.00']);
    } finally {
        @unlink($path);
    }
});
