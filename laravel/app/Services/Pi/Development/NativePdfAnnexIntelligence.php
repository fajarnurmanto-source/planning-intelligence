<?php
namespace App\Services\Pi\Development;

use RuntimeException;

/**
 * R106 deterministic, read-only native PDF annex extraction and fiscal
 * account reconstruction, bound to the owner-verified R104/R105 byte pins.
 * No OCR, network, DB, cloud AI, approval, or document writes.
 */
final class NativePdfAnnexIntelligence
{
    public const SUBACTIVITY = '2.11.11.2.01.0004';

    public function review(string $basePdf, string $changePdf, ?string $extractor = null): array
    {
        $base = $this->read($basePdf, 'E4AAA45F33B7254FDB7BBE3A0BC456999FD2CE3E19D8C07B874C640A5E1DEE24', '74', '2025', false, $extractor);
        $change = $this->read($changePdf, '4E5194E6987468E30406911B277BACCA73506B3BF93BE413C0C19784AE3825EA', '12', '2026', true, $extractor);
        return $this->reconcileParsed($base, $change);
    }

    /** Test seam: parsed text does not bypass actual-PDF SHA guard in review(). */
    public function parseLayout(string $text, string $number, string $year, bool $amendment): array
    {
        $first = substr($text, 0, 10000);
        if (!preg_match('/PERATURAN\s+WALI\s*KOTA\s+YOGYAKARTA\s+NOMOR\s+'.preg_quote($number, '/').'\s+TAHUN\s+'.preg_quote($year, '/').'/iu', $first)) {
            throw new RuntimeException('PI_R106_PDF_LEGAL_HEADING_IDENTITY_HOLD');
        }
        if ($amendment && !preg_match('/PERUBAHAN\s+KETIGA/iu', $first)) {
            throw new RuntimeException('PI_R106_EXPECTED_THIRD_AMENDMENT_HOLD');
        }
        if (strlen($text) > 20000000 || $text === '' || !preg_match('//u', $text)) {
            throw new RuntimeException('PI_R106_TEXT_LAYER_UNREADABLE_OR_TOO_LARGE');
        }
        $pages = explode("\f", str_replace("\r\n", "\n", $text));
        if (end($pages) === '') { array_pop($pages); }
        if (count($pages) < 2 || count($pages) > 2200) {
            throw new RuntimeException('PI_R106_PAGE_DELIMITERS_MISSING');
        }
        $needle = '/Sub\s*Kegiatan\s*:\s*'.preg_quote(self::SUBACTIVITY, '/').'\b/u';
        $hits = [];
        foreach ($pages as $index => $page) {
            if (preg_match($needle, $page, $m, PREG_OFFSET_CAPTURE)) {
                $hits[] = [$index, $m[0][1]];
            }
        }
        if (count($hits) !== 1) {
            throw new RuntimeException('PI_R106_SUBACTIVITY_MISSING_OR_DUPLICATE_HOLD');
        }
        [$pageIndex, $offset] = $hits[0];
        $selected = substr($pages[$pageIndex], $offset);
        if (isset($pages[$pageIndex + 1])) {
            $selected .= "\f".$pages[$pageIndex + 1];
        }
        $startLineEnd = strpos($selected, "\n");
        if ($startLineEnd === false) { throw new RuntimeException('PI_R106_ANNEX_SECTION_MALFORMED'); }
        if (preg_match('/\bSub\s*Kegiatan\s*:\s*\d+(?:\.\d+){4}\b/u', $selected, $match, PREG_OFFSET_CAPTURE, $startLineEnd + 1)) {
            $selected = substr($selected, 0, $match[0][1]);
        }
        // Preserve page boundaries, but make new-page account starts addressable by ^ in multiline regex.
        $selected = str_replace("\f", "\f\n", $selected);
        $rows = $amendment ? $this->splitAccountRows($selected, $pageIndex + 1) : $this->baseAccountRows($selected, $pageIndex + 1);
        if (count($rows) !== 6) { throw new RuntimeException('PI_R106_SIX_LEAF_ACCOUNTS_NOT_RESOLVED_HOLD'); }
        preg_match('/\bHalaman\s+(\d+)\b/u', $pages[$pageIndex], $printed);
        return [
            'instrument' => 'PERWAL-'.$number.'-'.$year,
            'source_pdf_page' => $pageIndex + 1,
            'printed_appendix_page' => isset($printed[1]) ? (int)$printed[1] : null,
            'pdf_page_count' => count($pages),
            'subactivity_code' => self::SUBACTIVITY,
            'rows' => $rows,
            'text_layer' => 'AVAILABLE_LOCAL',
            'legal_identity' => 'HEADER_TEXT_MATCH_ONLY_NOT_AUTHENTICATED',
        ];
    }

    private function read(string $path, string $expectedSha, string $number, string $year, bool $amendment, ?string $extractor): array
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)
            || filesize($path) < 128 || filesize($path) > 80 * 1024 * 1024) {
            throw new RuntimeException('PI_R106_SOURCE_FILE_UNSAFE');
        }
        if (strtoupper((string)hash_file('sha256', $path)) !== $expectedSha) {
            throw new RuntimeException('PI_R106_OWNER_SOURCE_SHA256_HOLD');
        }
        $parsed = $this->parseLayout($this->extract($path, $extractor), $number, $year, $amendment);
        $parsed['sha256'] = $expectedSha;
        $parsed['pdf_bytes'] = filesize($path);
        return $parsed;
    }

    /**
     * Native deterministic Poppler lookup, no installations or network.
     * Supports WinGet, Scoop, PATH and WSL default before named distros.
     * Exposes only bounded backend/exit diagnostics, never PDF contents.
     *
     * @return list<array{label:string,argv:list<string>}>
     */
    public function extractorCommands(string $file, ?string $extractor = null, ?string $os = null): array
    {
        $os ??= PHP_OS_FAMILY;
        if ($extractor !== null && (trim($extractor) === '' || str_contains($extractor, "\0"))) {
            throw new RuntimeException('PI_R106_EXPLICIT_EXTRACTOR_UNSAFE');
        }
        $commands = [];
        $args = ['-layout', '-enc', 'UTF-8', $file, '-'];
        if ($extractor !== null) {
            return [['label' => 'EXPLICIT_POPPLER', 'argv' => array_merge([$extractor], $args)]];
        }
        $commands[] = ['label' => 'NATIVE_PATH_POPPLER', 'argv' => array_merge(['pdftotext'], $args)];
        if ($os !== 'Windows') {
            return $commands;
        }

        $possible = [];
        $local = getenv('LOCALAPPDATA');
        $user = getenv('USERPROFILE');
        if (is_string($local) && $local !== '') {
            $base = str_replace('\\', '/', $local);
            $possible[] = $base.'/Microsoft/WinGet/Links/pdftotext.exe';
            $possible[] = $base.'/Microsoft/WinGet/Packages/oschwartz10612.Poppler*/poppler*/Library/bin/pdftotext.exe';
            $possible[] = $base.'/Microsoft/WinGet/Packages/oschwartz10612.Poppler*/Library/bin/pdftotext.exe';
        }
        if (is_string($user) && $user !== '') {
            $base = str_replace('\\', '/', $user);
            $possible[] = $base.'/scoop/apps/poppler/current/Library/bin/pdftotext.exe';
            $possible[] = $base.'/scoop/apps/poppler/current/bin/pdftotext.exe';
        }
        foreach ([getenv('ProgramFiles'), getenv('ProgramFiles(x86)')] as $base) {
            if (!is_string($base) || $base === '') { continue; }
            $base = str_replace('\\', '/', $base);
            $possible[] = $base.'/poppler/Library/bin/pdftotext.exe';
            $possible[] = $base.'/poppler/bin/pdftotext.exe';
        }
        $seen = [];
        foreach ($possible as $glob) {
            foreach (glob($glob) ?: [] as $exe) {
                if (is_file($exe) && !is_link($exe) && is_readable($exe) && !isset($seen[$exe])) {
                    $seen[$exe] = true;
                    $commands[] = ['label'=>'NATIVE_INSTALLED_POPPLER',
                        'argv'=>array_merge([$exe], $args)];
                }
            }
        }
        if (!preg_match('~^([a-zA-Z]):[\\\\/](.+)$~', $file, $parts)) {
            return $commands;
        }
        $wslFile = '/mnt/'.strtolower($parts[1]).'/'.str_replace('\\', '/', $parts[2]);
        $tail = ['pdftotext', '-layout', '-enc', 'UTF-8', $wslFile, '-'];
        $commands[] = ['label'=>'WSL_DEFAULT', 'argv'=>array_merge(['wsl.exe','--exec'], $tail)];
        foreach (['Ubuntu', 'Ubuntu-24.04'] as $name) {
            $commands[] = ['label'=>'WSL_'.$name,
                'argv'=>array_merge(['wsl.exe','--distribution',$name,'--exec'], $tail)];
        }
        return $commands;
    }

    private function extract(string $file, ?string $extractor): string
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('PI_R106_PHP_PROCESS_EXECUTION_DISABLED');
        }
        $attempts = [];
        foreach ($this->extractorCommands($file, $extractor) as $candidate) {
            $pipes = [];
            $process = null;
            try {
                $process = @proc_open($candidate['argv'],
                    [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
                    $pipes, null, null, ['bypass_shell'=>true]);
                if (!is_resource($process)) {
                    $attempts[] = $candidate['label'].'_NOT_FOUND';
                    continue;
                }
                fclose($pipes[0]);
                $body = stream_get_contents($pipes[1], 20_000_001);
                fclose($pipes[1]);
                stream_get_contents($pipes[2], 4096);
                fclose($pipes[2]);
                $code = proc_close($process);
                $process = null;
                if ($code !== 0) {
                    $attempts[] = $candidate['label'].'_EXIT_'.$code;
                    continue;
                }
                if (!is_string($body) || $body === ''
                    || strlen($body) > 20_000_000
                    || !preg_match('//u', $body) || !str_contains($body, "\f")) {
                    $attempts[] = $candidate['label'].'_INVALID_TEXT_LAYER';
                    continue;
                }
                return $body;
            } catch (\Throwable) {
                $attempts[] = $candidate['label'].'_PROCESS_ERROR';
            } finally {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) { @fclose($pipe); }
                }
                if (is_resource($process)) {
                    @proc_terminate($process);
                    @proc_close($process);
                }
            }
        }
        throw new RuntimeException('PI_R106_PDF_ENGINE_HOLD:'.implode(',', $attempts));
    }

    private function baseAccountRows(string $section, int $firstPage): array
    {
        preg_match_all('/(?m)^\h*(5(?:\.\d+){4}\.\d{5})(?!\d)\h*[^\r\n]*/u',
            $section, $matches, PREG_OFFSET_CAPTURE);
        $rows = [];
        foreach ($matches[1] as $i => $codeMatch) {
            $code = $codeMatch[0];
            $begin = $matches[0][$i][1];
            $end = $matches[0][$i + 1][1] ?? strlen($section);
            $chunk = substr($section, $begin, $end - $begin);
            $money = $this->money($chunk);
            if (count($money) !== 1 || isset($rows[$code])) {
                throw new RuntimeException('PI_R106_BASE_AMOUNT_OR_DUPLICATE_HOLD');
            }
            $rows[$code] = ['code' => $code, 'amount_idr' => $money[0],
                'page' => $firstPage + substr_count(substr($section, 0, $begin), "\f"),
                'excerpt' => $this->excerpt($chunk)];
        }
        return $rows;
    }

    private function splitAccountRows(string $section, int $firstPage): array
    {
        preg_match_all('/(?m)^\h*(5(?:\.\d+){4}\.\d{3})(?!\d)\h*[^\r\n]*/u',
            $section, $matches, PREG_OFFSET_CAPTURE);
        $rows = [];
        foreach ($matches[1] as $i => $prefixMatch) {
            $begin = $matches[0][$i][1];
            $end = $matches[0][$i + 1][1] ?? strlen($section);
            $chunk = substr($section, $begin, $end - $begin);
            $money = $this->money($chunk);
            if (count($money) === 0) { continue; }
            if (count($money) !== 3 || !preg_match('/(?m)^\h*(\d{2})(?:\h|$)/u', $chunk, $suffix)) {
                throw new RuntimeException('PI_R106_SPLIT_ACCOUNT_OR_THREE_COLUMNS_HOLD');
            }
            $code = $prefixMatch[0].$suffix[1];
            if (isset($rows[$code]) || $money[2] !== $money[1] - $money[0]) {
                throw new RuntimeException('PI_R106_DUPLICATE_OR_DELTA_MISMATCH_HOLD');
            }
            $rows[$code] = ['code' => $code, 'before_idr' => $money[0],
                'after_idr' => $money[1], 'delta_idr' => $money[2],
                'page' => $firstPage + substr_count(substr($section, 0, $begin), "\f"),
                'excerpt' => $this->excerpt($chunk)];
        }
        return $rows;
    }

    private function money(string $text): array
    {
        preg_match_all('/(?<![\d.])\-?\d{1,3}(?:\.\d{3})*,\d{2}(?!\d)/u', $text, $m);
        return array_map(static fn($value) => (int)str_replace(['.', ',00'], '', $value), $m[0]);
    }

    private function excerpt(string $chunk): string
    {
        return substr(trim(preg_replace('/\s+/u', ' ', $chunk) ?? ''), 0, 340);
    }

    public function reconcileParsed(array $base, array $change): array
    {
        $codes = array_keys($base['rows']); sort($codes);
        $changeCodes = array_keys($change['rows']); sort($changeCodes);
        if ($codes !== $changeCodes) {
            throw new RuntimeException('PI_R106_BASE_CHANGE_ACCOUNT_SET_MISMATCH_HOLD');
        }
        $rows = []; $totalBase = 0; $totalBefore = 0; $totalAfter = 0;
        foreach ($codes as $code) {
            $a = $base['rows'][$code]; $b = $change['rows'][$code];
            if ($a['amount_idr'] !== $b['before_idr'] || $b['before_idr'] !== $b['after_idr']
                || $b['delta_idr'] !== 0) {
                throw new RuntimeException('PI_R106_BASE_CHANGE_AMOUNTS_NOT_EQUAL_HOLD');
            }
            $totalBase += $a['amount_idr'];
            $totalBefore += $b['before_idr'];
            $totalAfter += $b['after_idr'];
            $rows[] = [
                'account_code' => $code, 'base_idr' => $a['amount_idr'],
                'third_amendment_before_idr' => $b['before_idr'],
                'third_amendment_after_idr' => $b['after_idr'],
                'third_amendment_delta_idr' => $b['delta_idr'],
                'base_page' => $a['page'],
                'change_page' => $b['page'],
                'base_excerpt' => $a['excerpt'], 'change_excerpt' => $b['excerpt'],
                'row_review' => 'TEXT_LAYER_CANDIDATE_NEEDS_VISUAL_REVIEW',
            ];
        }
        if ($totalBase !== 1020366250 || $totalBefore !== $totalBase
            || $totalAfter !== $totalBase) {
            throw new RuntimeException('PI_R106_CANDIDATE_TOTAL_UNEXPECTED_HOLD');
        }
        return [
            'schema' => 'PI_R106_NATIVE_PDF_ANNEX_REVIEW_V1',
            'case_id' => 'YOG-2026-PERSAMPAHAN',
            'subactivity_code' => self::SUBACTIVITY,
            'source_documents' => ['base' => $base, 'third_amendment' => $change],
            'rows' => $rows, 'row_count' => count($rows),
            'derived_leaf_total_idr' => $totalAfter,
            'reconciliation' => 'SIX_LEAF_ACCOUNTS_AND_NUMBERS_MATCH_ONLY',
            'origin_signature_and_complete_annex' => 'REVIEW_REQUIRED',
            'later_amendments_13_15_2026' => 'NOT_RECONCILED',
            'rka_dpa_realization' => 'NOT_VERIFIED',
            'human_review' => 'NOT_REQUESTED',
            '2027_indicator_equivalence' => 'ABSTAIN',
            'numeric_admission' => 0, 'production_admission' => 'DENIED',
            'writes_performed' => false,
        ];
    }
}
