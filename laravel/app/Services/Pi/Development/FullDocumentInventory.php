<?php
declare(strict_types=1);

namespace App\Services\Pi\Development;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * RDI-01A: inventory every physical PDF page, NOT a legal structure parser.
 * Candidate markers retain physical page/line and raw extracted line hashes.
 * Never interprets a table cell, consolidates an amendment, or admits evidence.
 */
final class FullDocumentInventory
{
    public function read(string $file, string $sha, int $expectedPages, ?string $extractor = null, bool $layout = false, ?bool $multilineAmendment = null): array
    {
        if (!is_file($file) || is_link($file) || !is_readable($file)
            || filesize($file) < 128 || filesize($file) > 80 * 1024 * 1024
            || !preg_match('/\A[A-F0-9]{64}\z/D', $sha)
            || strtoupper((string)hash_file('sha256', $file)) !== $sha) {
            throw new RuntimeException('PI_RDI_SOURCE_PIN_HOLD');
        }
        $native = new NativePdfAnnexIntelligence;
        foreach ($native->extractorCommands($file, $extractor) as $candidate) {
            // Same established executable discovery, bounded Symfony process.
            try {
                $process = new Process($candidate['argv']);
                $process->setTimeout(180);
                $body='';
                $process->run(static function (string $type, string $chunk) use (&$body): void {
                    if ($type !== Process::OUT) { return; }
                    if (strlen($body)+strlen($chunk)>60_000_000) {
                        throw new RuntimeException('PI_RDI_TEXT_SIZE_HOLD');
                    }
                    $body.=$chunk;
                });
                if (!$process->isSuccessful()) { continue; }
                $result = $this->inventory($body, $expectedPages);
                if ($layout) { $result['layout_candidates'] = (new DocumentLayoutReconstructor)->reconstruct($body, $expectedPages); }
                if ($multilineAmendment !== null) {
                    $result['multiline_fiscal'] = (new MultilineFiscalTableReconstructor)->reconstruct($body, $expectedPages, $multilineAmendment);
                    $contextEngine = new FiscalOrganizationContext;
                    $result['fiscal_context'] = $contextEngine->resolve($body, $result['multiline_fiscal']);
                    $result['fiscal_discovery'] = $contextEngine->enrich($body, $result['multiline_fiscal'], $result['fiscal_context'], dirname(__DIR__,4).'/resources/pi/affairs-nomenclature-seed-r87.json');
                }
                if (strtoupper((string)hash_file('sha256', $file)) !== $sha) {
                    throw new RuntimeException('PI_RDI_SOURCE_DRIFT');
                }
                return ['source_sha256'=>$sha,'engine'=>$candidate['label']] + $result;
            } catch (\Exception $e) {
                if ($e->getMessage() === 'PI_RDI_SOURCE_DRIFT') { throw $e; }
            }
        }
        throw new RuntimeException('PI_RDI_EXTRACTOR_OR_PAGE_COUNT_HOLD');
    }

    /** expectedPages must be independently observed from PDF metadata, not text. */
    public function inventory(string $text, int $expectedPages): array
    {
        if ($expectedPages < 1 || $expectedPages > 10000 || strlen($text)>60_000_000
            || !preg_match('//u',$text) || !str_ends_with($text,"\f")) {
            throw new RuntimeException('PI_RDI_TEXT_BOUNDARY_HOLD');
        }
        $pages = explode("\f", $text);
        array_pop($pages); // only final delimiter sentinel; retain empty physical pages
        if (count($pages) !== $expectedPages) {
            throw new RuntimeException('PI_RDI_PAGE_COUNT_HOLD');
        }
        $records=[]; $counts=[]; $blank=0; $unclassified=0;
        $patterns=[
            'EXPLANATION_HEADING'=>'/^\h*P\h*E\h*N\h*J\h*E\h*L\h*A\h*S\h*A\h*N(?:\h+ATAS)?\h*$/iu',
            'ANNEX_HEADING'=>'/^\h*LAMPIRAN(?:\h+[IVXLCDM\d]+)?\h*$/iu',
            'CONTENTS_HEADING'=>'/^\h*DAFTAR\h+ISI\h*$/iu',
            'CHAPTER'=>'/^\h*BAB\h+[IVXLCDM\d]+(?:\h+.*)?$/iu',
            'PART'=>'/^\h*Bagian\h+\p{L}+(?:\h+\p{L}+)?\h*$/iu',
            'PARAGRAPH_HEADING'=>'/^\h*Paragraf\h+\d+\h*$/iu',
            'ARTICLE'=>'/^\h*Pasal\h+(?:\d+[A-Z]?|[IVXLCDM]+)\h*$/iu',
            'PARAGRAPH_MARKER'=>'/^\h*\(\d+[a-z]?\)\h+\S/iu',
            'LETTER_MARKER'=>'/^\h*[a-z][.)]\h+\S/u',
            'NUMBER_MARKER'=>'/^\h*\d+[.)]\h+\S/u',
            'EXPLANATION_DETAIL'=>'/^\h*(?:Ayat\h*\(\d+[a-z]?\)|Huruf\h+[a-z]|Angka\h+\d+)\h*$/iu',
            'PREAMBLE_MARKER'=>'/^\h*(?:Menimbang|Mengingat|Memutuskan|Menetapkan)\h*:/iu',
            'SUBACTIVITY_MARKER'=>'/^\h*Sub\h*Kegiatan\h*:\h*\d+(?:\.\d+){5}(?![\d.])/iu',
            'ACCOUNT_FULL_MARKER'=>'/^\h*\d(?:\.\d+){4}\.\d{5}(?!\d)/u',
            'ACCOUNT_SPLIT_MARKER'=>'/^\h*\d(?:\.\d+){4}\.\d{3}(?!\d)/u',
            'TABLE_LAYOUT_SIGNAL'=>'/\S\h{3,}\S\h{3,}\S/u',
        ];
        $zone='UNRESOLVED';
        foreach ($pages as $i=>$page) {
            $markers=[]; $pageCounts=[]; $hasText=trim($page)!=='';
            if (!$hasText) { $blank++; }
            foreach (preg_split('/\r\n|\n|\r/u',$page) as $lineNo=>$line) {
                if (trim($line)==='') { continue; }
                foreach ($patterns as $kind=>$regex) {
                    if (!preg_match($regex,$line)) { continue; }
                    // Zone is only a heading signal; not legal or structural scope.
                    if (in_array($kind,['EXPLANATION_HEADING','ANNEX_HEADING','CONTENTS_HEADING'],true)) {
                        $zone=$kind;
                    }
                    $pageCounts[$kind]=($pageCounts[$kind]??0)+1;
                    $counts[$kind]=($counts[$kind]??0)+1;
                    $markers[]=['kind'=>$kind,'line'=>$lineNo+1,'zone_signal'=>$zone,
                        'line_sha256'=>strtoupper(hash('sha256',$line)),
                        'excerpt'=>mb_substr(trim($line),0,180,'UTF-8')];
                    break;
                }
            }
            // Native text can contain only a footer or fragmented glyphs on a visual form.
            $content=preg_replace('/^.*(?:www\.peraturan\.go\.id|\d{4},\h*No\.\d+).*$/mu','',$page);
            preg_match_all('/(?<![\p{L}\p{N}])\p{L}{3,}(?![\p{L}\p{N}])/u',$content,$words);
            $wordCount=count($words[0]);
            $sparse=$wordCount<6&&!isset($pageCounts['ARTICLE']);
            if ($hasText && !$markers) { $unclassified++; }
            $records[]=['pdf_page'=>$i+1,'text_bytes'=>strlen($page),
                'text_sha256'=>strtoupper(hash('sha256',$page)),
                'native_text_quality'=>['lexical_words'=>$wordCount,'state'=>$sparse?'SPARSE_OR_FRAGMENTED_REQUIRES_VISUAL':'UNVERIFIED_TEXT_AVAILABLE'],
                'state'=>!$hasText?'NO_TEXT_OCR_OR_VISUAL_REQUIRED':(!$markers?'TEXT_UNCLASSIFIED':'TEXT_WITH_CANDIDATE_MARKERS'),
                'marker_counts'=>$pageCounts,'markers'=>$markers];
        }
        return ['schema'=>'PI_RDI_01A_PAGE_INVENTORY_V2','expected_pages'=>$expectedPages,
            'inventoried_pages'=>count($records),'text_pages'=>count($records)-$blank,
            'no_text_pages'=>$blank,'unclassified_text_pages'=>$unclassified,
            'marker_counts'=>$counts,'pages'=>$records,
            'structure_verification'=>'NOT_PERFORMED','table_reconstruction'=>'NOT_PERFORMED',
            'legal_harmonization'=>'NOT_ASSESSED','numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
}
