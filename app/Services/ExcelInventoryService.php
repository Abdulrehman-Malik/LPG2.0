<?php
namespace App\Services;

use RuntimeException;

class ExcelInventoryService
{
    private const REQUIRED_HEADERS = [
        'cylinder type',
        'quantity',
        'max gas capacity',
        'available gas',
    ];

    public function readOpeningInventory(string $path): array
    {
        if (!is_file($path) || filesize($path) <= 0) {
            throw new RuntimeException('The uploaded Excel file is empty or could not be read.');
        }
        if (filesize($path) > 5 * 1024 * 1024) {
            throw new RuntimeException('The Excel file is too large. Maximum allowed size is 5 MB.');
        }

        $archive = $this->openArchive($path);
        try {
            $sheetPath = $this->resolveFirstSheetPath($archive);
            $sheetXml = $this->readEntry($archive, $sheetPath);
            $sharedStrings = $this->readSharedStrings($archive);
        } finally {
            if ($archive instanceof \ZipArchive) {
                $archive->close();
            }
        }

        $rowMatches = [];
        preg_match_all('/<row\b[^>]*>(.*?)<\/row>/si', $sheetXml, $rowMatches);
        if (count($rowMatches[1]) < 1) {
            throw new RuntimeException('The Excel sheet does not contain any rows.');
        }

        $headers = null;
        $data = [];
        foreach ($rowMatches[1] as $rowIndex => $rowXml) {
            $excelRow = $rowIndex + 1;
            $cells = $this->readCells($rowXml, $sharedStrings);
            if (!$cells) {
                continue;
            }

            if ($headers === null) {
                $headers = [];
                for ($i = 1; $i <= 4; $i++) {
                    $headers[] = $this->normalizeHeader($cells[$i] ?? '');
                }
                if ($headers !== self::REQUIRED_HEADERS) {
                    throw new RuntimeException('Invalid Excel headers. Required columns are: Cylinder Type, Quantity, Max GAS Capacity, Available Gas.');
                }
                continue;
            }

            $nonBlank = array_filter($cells, static fn($value) => trim((string) $value) !== '');
            if (!$nonBlank) {
                continue;
            }

            foreach ($cells as $column => $value) {
                if ($column > 4 && trim((string) $value) !== '') {
                    throw new RuntimeException('Row ' . $excelRow . ' contains data outside the four required columns.');
                }
            }

            $data[] = [
                'excel_row' => $excelRow,
                'cylinder_type' => trim((string) ($cells[1] ?? '')),
                'quantity' => trim((string) ($cells[2] ?? '')),
                'max_gas_capacity' => trim((string) ($cells[3] ?? '')),
                'available_gas' => trim((string) ($cells[4] ?? '')),
            ];

            if (count($data) > 5000) {
                throw new RuntimeException('The Excel file contains more than 5,000 data rows.');
            }
        }

        if ($headers === null) {
            throw new RuntimeException('The Excel sheet is missing the required header row.');
        }
        if (!$data) {
            throw new RuntimeException('The Excel sheet does not contain any inventory rows.');
        }

        return $data;
    }

    public function readCustomers(string $path): array
    {
        if (!is_file($path) || filesize($path) <= 0) throw new RuntimeException('The uploaded Excel file is empty or could not be read.');
        if (filesize($path) > 5 * 1024 * 1024) throw new RuntimeException('The Excel file is too large. Maximum allowed size is 5 MB.');
        $archive=$this->openArchive($path);
        try { $sheetPath=$this->resolveFirstSheetPath($archive); $sheetXml=$this->readEntry($archive,$sheetPath); $sharedStrings=$this->readSharedStrings($archive); }
        finally { if($archive instanceof \ZipArchive) $archive->close(); }
        $rowMatches=[]; preg_match_all('/<row\b[^>]*>(.*?)<\/row>/si',$sheetXml,$rowMatches);
        $headers=null; $data=[];
        $required=['code','name','phone','city','address','vehicle no','credit limit','allow credit sale','opening balance','active'];
        foreach($rowMatches[1] as $rowIndex=>$rowXml){
            $excelRow=$rowIndex+1; $cells=$this->readCells($rowXml,$sharedStrings); if(!$cells) continue;
            if($headers===null){
                $headers=[]; for($i=1;$i<=10;$i++) $headers[]=$this->normalizeHeader($cells[$i]??'');
                if($headers!==$required) throw new RuntimeException('Invalid Excel headers. Required columns are: Code, Name, Phone, City, Address, Vehicle No, Credit Limit, Allow Credit Sale, Opening Balance, Active.');
                continue;
            }
            $nonBlank=array_filter($cells,static fn($v)=>trim((string)$v)!==''); if(!$nonBlank) continue;
            foreach($cells as $column=>$value) if($column>10 && trim((string)$value)!=='') throw new RuntimeException('Row '.$excelRow.' contains data outside the ten required columns.');
            $data[]=['excel_row'=>$excelRow,'code'=>trim((string)($cells[1]??'')),'name'=>trim((string)($cells[2]??'')),'phone'=>trim((string)($cells[3]??'')),'city'=>trim((string)($cells[4]??'')),'address'=>trim((string)($cells[5]??'')),'vehicle_no'=>trim((string)($cells[6]??'')),'credit_limit'=>trim((string)($cells[7]??'')),'allow_credit_sale'=>trim((string)($cells[8]??'')),'opening_balance'=>trim((string)($cells[9]??'')),'is_active'=>trim((string)($cells[10]??''))];
            if(count($data)>5000) throw new RuntimeException('The Excel file contains more than 5,000 data rows.');
        }
        if($headers===null) throw new RuntimeException('The Excel sheet is missing the required header row.');
        if(!$data) throw new RuntimeException('The Excel sheet does not contain any customer rows.');
        return $data;
    }

    public function createCustomerTemplate(string $targetPath): void
    {
        $dir=dirname($targetPath); if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)) throw new RuntimeException('Unable to create the Excel template directory.');
        $base=$dir.DIRECTORY_SEPARATOR.'customer_template_'.bin2hex(random_bytes(8)); $tarPath=$base.'.tar'; $zipPath=$base.'.xlsx';
        try{
            $archive=new \PharData($tarPath);
            foreach($this->customerTemplateFiles() as $name=>$content) $archive[$name]=$content;
            unset($archive); $tar=new \PharData($tarPath); $tar->convertToData(\Phar::ZIP,null,'xlsx');
            if(!is_file($zipPath)) throw new RuntimeException('Unable to generate the Excel template.');
            if(!rename($zipPath,$targetPath)) throw new RuntimeException('Unable to prepare the Excel template for download.');
        }finally{@unlink($tarPath);@unlink($zipPath);}
    }

    private function customerTemplateFiles(): array
    {
        return [
            '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Customers" sheetId="1" r:id="rId1"/><sheet name="Instructions" sheetId="2" r:id="rId2"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml'=>$this->worksheetXml([
                ['Code','Name','Phone','City','Address','Vehicle No','Credit Limit','Allow Credit Sale','Opening Balance','Active'],
                ['CUST001','Sample Customer','03001234567','Lahore','Sample address','ABC-123', '50000','1','0','1'],
                ['CUST002','Another Customer','03009876543','Islamabad','Sample address','XYZ-456','0','0','1500','1'],
            ]),
            'xl/worksheets/sheet2.xml'=>$this->worksheetXml([
                ['Customer Excel Upload Instructions'],
                ['Code','Optional but must be unique when supplied. Existing customer codes are rejected; import never overwrites existing customers.'],
                ['Name','Required.'],
                ['Allow Credit Sale','Use 1 for allowed, 0 for cash only.'],
                ['Credit Limit','Must be 0 or greater.'],
                ['Opening Balance','Must be 0 or greater.'],
                ['Active','Use 1 for active, 0 for inactive.'],
                ['Other fields','Phone, City, Address and Vehicle No are optional.'],
            ]),
        ];
    }

    public function createTemplate(string $targetPath): void
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create the Excel template directory.');
        }

        $base = $dir . DIRECTORY_SEPARATOR . 'opening_inventory_template_' . bin2hex(random_bytes(8));
        $tarPath = $base . '.tar';
        $zipPath = $base . '.xlsx';

        try {
            $archive = new \PharData($tarPath);
            foreach ($this->templateFiles() as $name => $content) {
                $archive[$name] = $content;
            }
            unset($archive);

            $tar = new \PharData($tarPath);
            $tar->convertToData(\Phar::ZIP, null, 'xlsx');

            if (!is_file($zipPath)) {
                throw new RuntimeException('Unable to generate the Excel template.');
            }
            if (!rename($zipPath, $targetPath)) {
                throw new RuntimeException('Unable to prepare the Excel template for download.');
            }
        } finally {
            @unlink($tarPath);
            @unlink($zipPath);
        }
    }

    private function openArchive(string $path): object
    {
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                throw new RuntimeException('The uploaded file is not a valid Excel workbook.');
            }
            return $zip;
        }

        try {
            return new \PharData($path);
        } catch (\Throwable) {
            throw new RuntimeException('Excel support requires PHP ZIP/PharData support. Please enable the PHP ZIP extension and try again.');
        }
    }

    private function readEntry(object $archive, string $path): string
    {
        if ($archive instanceof \ZipArchive) {
            $content = $archive->getFromName($path);
        } else {
            $archivePath = method_exists($archive, 'getPath') ? (string) $archive->getPath() : '';
            $content = $archivePath !== ''
                ? @file_get_contents('phar://' . $archivePath . '/' . ltrim($path, '/'))
                : false;

            if ($content === false && isset($archive[$path])) {
                $content = $archive[$path]->getContent();
            }
        }

        if ($content === false || $content === null) {
            throw new RuntimeException('The Excel workbook is missing required worksheet data.');
        }

        return (string) $content;
    }

    private function resolveFirstSheetPath(object $archive): string
    {
        $workbook = $this->readEntry($archive, 'xl/workbook.xml');
        $rels = $this->readEntry($archive, 'xl/_rels/workbook.xml.rels');

        if (!preg_match('/<sheet\b[^>]*r:id=["\']([^"\']+)["\'][^>]*>/si', $workbook, $sheetMatch)) {
            throw new RuntimeException('The Excel workbook does not contain a worksheet.');
        }

        $rid = $sheetMatch[1];
        $pattern = '/<Relationship\b[^>]*\bId=["\']' . preg_quote($rid, '/') . '["\'][^>]*\bTarget=["\']([^"\']+)["\'][^>]*\/>/si';
        if (!preg_match($pattern, $rels, $relMatch)) {
            throw new RuntimeException('The Excel workbook worksheet relationship is invalid.');
        }

        $target = str_replace('\\', '/', $relMatch[1]);
        return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . ltrim($target, '/');
    }

    private function readSharedStrings(object $archive): array
    {
        try {
            $xml = $this->readEntry($archive, 'xl/sharedStrings.xml');
        } catch (\Throwable) {
            return [];
        }

        $strings = [];
        preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $xml, $matches);
        foreach ($matches[1] as $item) {
            $parts = [];
            preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $item, $textMatches);
            foreach ($textMatches[1] as $part) {
                $parts[] = html_entity_decode($part, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function readCells(string $rowXml, array $sharedStrings): array
    {
        $cells = [];
        $matches = [];
        preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/si', $rowXml, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attrs = $this->attributes($match[1]);
            $ref = $attrs['r'] ?? '';
            if (!preg_match('/^([A-Z]+)\d+$/i', $ref, $refMatch)) {
                continue;
            }

            $column = $this->columnNumber($refMatch[1]);
            $body = $match[2] ?? '';

            if (preg_match('/<f\b[^>]*>/si', $body)) {
                $rowNumber = preg_replace('/\D+/', '', $ref);
                throw new RuntimeException('Excel formulas are not allowed. Row ' . $rowNumber . ' contains a formula. Enter fixed values instead.');
            }

            $type = strtolower($attrs['t'] ?? '');
            if ($type === 'inlinestr') {
                $text = [];
                preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $body, $textMatches);
                foreach ($textMatches[1] as $part) {
                    $text[] = html_entity_decode($part, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                $cells[$column] = implode('', $text);
            } elseif ($type === 's') {
                preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $v);
                $cells[$column] = $sharedStrings[(int) ($v[1] ?? -1)] ?? '';
            } elseif ($type === 'b') {
                preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $v);
                $cells[$column] = (($v[1] ?? '0') === '1') ? '1' : '0';
            } elseif ($type === 'e') {
                throw new RuntimeException('Excel contains an error value in cell ' . $ref . '.');
            } else {
                preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $v);
                $cells[$column] = html_entity_decode($v[1] ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        return $cells;
    }

    private function attributes(string $raw): array
    {
        $result = [];
        preg_match_all('/([A-Za-z_:][A-Za-z0-9_.:-]*)=["\']([^"\']*)["\']/s', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $result[$m[1]] = html_entity_decode($m[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return $result;
    }

    private function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $number = ($number * 26) + ord($letter) - 64;
        }
        return $number;
    }

    private function normalizeHeader(string $header): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($header))) ?? '';
    }

    private function templateFiles(): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Opening Inventory" sheetId="1" r:id="rId1"/><sheet name="Instructions" sheetId="2" r:id="rId2"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => $this->worksheetXml([
                ['Cylinder Type', 'Quantity', 'Max GAS Capacity', 'Available Gas'],
                ['C6', '10', '6', '6'],
                ['C6', '2', '6', '4'],
                ['C10', '5', '10', '10'],
            ]),
            'xl/worksheets/sheet2.xml' => $this->worksheetXml([
                ['Opening Inventory Excel Upload Instructions'],
                ["Opening date", "Today's date is applied automatically. Do not enter a date in Excel."],
                ['Same cylinder type', 'Multiple rows remain separate physical-cylinder batches.'],
                ['Capacity mismatch', 'Existing cylinder types with a different Excel capacity are flagged and require confirmation; the existing database capacity is retained.'],
                ['Gas validation', 'Available Gas must be 0 or greater and cannot exceed Max GAS Capacity.'],
                ['Physical stock', "Each row creates the requested number of physical cylinders with the row's gas quantity."],
            ]),
        ];
    }

    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $xml .= '<row r="' . $rowNumber . '">';
            foreach ($row as $columnIndex => $value) {
                $cell = $this->columnLetters($columnIndex + 1) . $rowNumber;
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    private function columnLetters(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $letters = chr(65 + $remainder) . $letters;
            $number = intdiv($number - 1, 26);
        }
        return $letters;
    }
}
