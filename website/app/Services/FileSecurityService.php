<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Scans uploaded files for security threats.
 *
 * Validates file signatures (magic bytes), performs server-side MIME detection
 * via finfo, blocks dangerous extensions, scans text-based files for embedded
 * code/scripts, and detects formula injection in CSV/XLSX files.
 */
class FileSecurityService
{
    private array $dangerousExtensions = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif',
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash',
        'asp', 'aspx', 'jsp', 'jspx', 'cfm',
        'htaccess', 'htpasswd', 'env',
    ];

    private array $magicBytes = [
        'application/pdf' => ['%PDF'],
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG"],
        'image/gif' => ['GIF87a', 'GIF89a'],
        'application/zip' => ["PK"],
    ];

    /**
     * Allowed MIME types mapped to their expected file extensions.
     * Used to cross-validate finfo detection against client-reported extension.
     */
    private array $allowedMimeExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt', 'csv', 'log'],
        'text/csv' => ['csv'],
        'text/html' => ['html', 'htm'],
        'text/xml' => ['xml'],
        'application/json' => ['json'],
        'application/xml' => ['xml'],
        'application/zip' => ['zip', 'xlsx', 'docx', 'pptx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/msword' => ['doc'],
    ];

    private array $dangerousPatterns = [
        '/<\?php/i',
        '/<\?=/i',
        '/<script[\s>]/i',
        '/javascript\s*:/i',
        '/on(load|error|click|mouseover|submit|focus|blur)\s*=/i',
        '/eval\s*\(/i',
        '/exec\s*\(/i',
        '/system\s*\(/i',
        '/passthru\s*\(/i',
        '/shell_exec\s*\(/i',
        '/base64_decode\s*\(/i',
    ];

    /**
     * CSV/text formula injection: cells starting with these characters can
     * trigger formula execution when opened in Excel/Sheets.
     *
     * Covers: =, +, -, @, tab, carriage return, and pipe-prefixed formulas.
     * Also catches cells that use DDE commands like =cmd|'/C calc.exe'!A0.
     */
    private array $formulaInjectionPatterns = [
        '/(?:^|,|;|\t)[\s]*[=+\-@\t\r|]/',              // Cell starting with dangerous char
        '/(?:^|,|;|\t)[\s]*0x[0-9a-fA-F]/i',             // Hex-encoded values
        '/\bDDE\b/i',                                      // DDE keyword
        '/=\s*cmd\s*\|/i',                                // =cmd| DDE attack
        '/=\s*MSEXCEL\s*\|/i',                            // =MSEXCEL| DDE attack
        '/=\s*\w+\s*\(.*\|/i',                            // =FUNC()|cmd patterns
        '/@SUM\s*\(/i',                                    // @SUM( legacy formula
        '/\+\s*cmd\s*\|/i',                                // +cmd| DDE attack
        '/=\s*HYPERLINK\s*\(/i',                           // =HYPERLINK() payload
        '/=\s*IMPORTXML\s*\(/i',                           // =IMPORTXML() data exfil
        '/=\s*IMPORTDATA\s*\(/i',                          // =IMPORTDATA() data exfil
        '/=\s*IMPORTFEED\s*\(/i',                          // =IMPORTFEED() data exfil
        '/=\s*IMPORTHTML\s*\(/i',                          // =IMPORTHTML() data exfil
        '/=\s*IMPORTRANGE\s*\(/i',                         // =IMPORTRANGE() data exfil
        '/=\s*IMAGE\s*\(/i',                               // =IMAGE() tracking pixel
        '/=\s*WEBSERVICE\s*\(/i',                          // =WEBSERVICE() SSRF
    ];

    public function scan(UploadedFile $file, int $maxSizeMB = 10): FileSecurityResult
    {
        // 1. Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, $this->dangerousExtensions)) {
            return new FileSecurityResult(false, "File type .{$extension} is not allowed for security reasons.");
        }

        // 2. Check file size
        if ($file->getSize() > $maxSizeMB * 1024 * 1024) {
            return new FileSecurityResult(false, "File exceeds maximum size of {$maxSizeMB}MB.");
        }

        // 3. Server-side MIME detection via finfo (do NOT trust client-reported type)
        $realPath = $file->getRealPath();
        $finfoMime = null;
        if ($realPath && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $finfoMime = finfo_file($finfo, $realPath);
                finfo_close($finfo);
            }
        }

        // Cross-validate: if finfo detected a MIME type, verify the extension matches
        if ($finfoMime && $finfoMime !== 'application/octet-stream') {
            $expectedExtensions = $this->allowedMimeExtensions[$finfoMime] ?? null;

            // If we know this MIME type, enforce that the extension matches
            if ($expectedExtensions !== null && !in_array($extension, $expectedExtensions)) {
                return new FileSecurityResult(
                    false,
                    "File content type ({$finfoMime}) does not match its extension (.{$extension})."
                );
            }
        }

        // 4. Validate magic bytes (file signature) for known types
        $mimeType = $finfoMime ?: $file->getMimeType();
        if (isset($this->magicBytes[$mimeType])) {
            $header = file_get_contents($realPath, false, null, 0, 10);
            $valid = false;
            foreach ($this->magicBytes[$mimeType] as $magic) {
                if (str_starts_with($header, $magic)) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                return new FileSecurityResult(false, 'File content does not match its declared type.');
            }
        }

        // 5. Scan content for dangerous patterns (text-based files only)
        $textExtensions = ['csv', 'txt', 'svg', 'xml', 'json', 'html', 'htm'];
        if (str_starts_with($mimeType ?? '', 'text/') || in_array($extension, $textExtensions)) {
            $content = file_get_contents($realPath);
            foreach ($this->dangerousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    return new FileSecurityResult(false, 'File contains potentially dangerous content.');
                }
            }

            // 5a. CSV formula injection scan
            if ($extension === 'csv' || $mimeType === 'text/csv') {
                $formulaResult = $this->scanForFormulaInjection($content);
                if (!$formulaResult->passed) {
                    return $formulaResult;
                }
            }
        }

        // 6. XLSX formula injection scan (ZIP-based XML inspection)
        if ($extension === 'xlsx') {
            $xlsxResult = $this->scanXlsxForFormulaInjection($realPath);
            if (!$xlsxResult->passed) {
                return $xlsxResult;
            }
        }

        // 7. Check for double extensions (e.g., file.php.jpg)
        $originalName = $file->getClientOriginalName();
        $parts = explode('.', $originalName);
        if (count($parts) > 2) {
            foreach (array_slice($parts, 0, -1) as $part) {
                if (in_array(strtolower($part), $this->dangerousExtensions)) {
                    return new FileSecurityResult(false, 'File name contains suspicious double extension.');
                }
            }
        }

        return new FileSecurityResult(true, 'File passed security scan.', $this->sanitizeFilename($originalName));
    }

    /**
     * Scan CSV/text content for formula injection patterns.
     */
    private function scanForFormulaInjection(string $content): FileSecurityResult
    {
        foreach ($this->formulaInjectionPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return new FileSecurityResult(
                    false,
                    'File contains potential formula injection. Cells must not start with =, +, -, @, or | characters.'
                );
            }
        }

        return new FileSecurityResult(true, 'No formula injection detected.');
    }

    /**
     * Scan XLSX files for formula injection by inspecting the shared strings
     * and sheet XML inside the ZIP archive.
     */
    private function scanXlsxForFormulaInjection(string $filePath): FileSecurityResult
    {
        if (!class_exists(\ZipArchive::class)) {
            // If ZipArchive is not available, skip XLSX-specific scanning
            return new FileSecurityResult(true, 'ZipArchive not available; skipped XLSX formula scan.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return new FileSecurityResult(false, 'Unable to read XLSX file. It may be corrupted.');
        }

        try {
            // Scan shared strings (xl/sharedStrings.xml)
            $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedStrings !== false) {
                $result = $this->scanXmlContentForFormulas($sharedStrings);
                if (!$result->passed) {
                    return $result;
                }
            }

            // Scan individual sheets (xl/worksheets/sheet*.xml)
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name !== false && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheetContent = $zip->getFromName($name);
                    if ($sheetContent !== false) {
                        $result = $this->scanXmlContentForFormulas($sheetContent);
                        if (!$result->passed) {
                            return $result;
                        }
                    }
                }
            }
        } finally {
            $zip->close();
        }

        return new FileSecurityResult(true, 'XLSX passed formula injection scan.');
    }

    /**
     * Scan XML content extracted from XLSX for formula injection patterns.
     */
    private function scanXmlContentForFormulas(string $xmlContent): FileSecurityResult
    {
        // Extract text values from <t> tags and <f> (formula) tags
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            return new FileSecurityResult(true, 'Could not parse XML; skipping.');
        }

        // Register namespaces for XPath
        $xml->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        // Check <f> formula elements for DDE attacks
        $formulas = $xml->xpath('//s:f') ?: [];
        foreach ($formulas as $formula) {
            $formulaText = (string) $formula;
            if (preg_match('/^\s*(cmd|MSEXCEL)\s*\|/i', $formulaText) ||
                preg_match('/DDE/i', $formulaText) ||
                preg_match('/HYPERLINK\s*\(\s*["\']https?:.*cmd/i', $formulaText)) {
                return new FileSecurityResult(
                    false,
                    'XLSX file contains potentially dangerous formulas (DDE/command injection).'
                );
            }
        }

        // Check <t> text elements for formula-like content
        $textNodes = $xml->xpath('//s:t') ?: [];
        foreach ($textNodes as $text) {
            $value = trim((string) $text);
            if ($value === '') {
                continue;
            }
            // Check if text value starts with formula injection characters
            if (preg_match('/^[=+\-@|]/', $value)) {
                // Only flag if it looks like an actual formula attempt
                if (preg_match('/^[=+\-@]\s*\w+\s*\(/', $value) ||
                    preg_match('/^[=+\-]\s*cmd\s*\|/i', $value) ||
                    preg_match('/^@SUM\s*\(/i', $value)) {
                    return new FileSecurityResult(
                        false,
                        'XLSX file contains potential formula injection in cell values.'
                    );
                }
            }
        }

        return new FileSecurityResult(true, 'XML content passed formula scan.');
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(['../', '..\\', '/', '\\', "\0"], '', $filename);
        $info = pathinfo($filename);
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $info['filename']);
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $info['extension'] ?? '');
        return $name . ($ext ? '.' . $ext : '');
    }
}
