<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;
use RuntimeException;

/** Per-page planning signals, never assert hierarchy, targets or cross-document equivalence. */
final class PlanningPageProfile
{
    public function profile(string $text,int $first,int $last): array
    {
        $pages=explode("\f",$text);
        if(array_pop($pages)!==''||count($pages)!==$last-$first+1||$first<1||$last<$first||strlen($text)>20_000_000||!preg_match('//u',$text))throw new RuntimeException('PI_RDI02_BATCH_BOUNDARY_HOLD');
        $out=[];$counts=[];$samples=[];
        $patterns=[
            'CHAPTER'=>'/^\h*BAB\h+[IVXLCDM\d]+\b/iu',
            'TABLE'=>'/^\h*Tabel\h+\d+(?:[.-]\d+)*\b/iu',
            'SUBACTIVITY_CODE'=>'/(?<![\d.])\d\.\d{2}\.\d{2}\.\d\.\d{2}\.\d{4}(?![\d.])/u',
            'ACTIVITY_CODE'=>'/(?<![\d.])\d\.\d{2}\.\d{2}\.\d\.\d{2}(?![\d.])/u',
            'PROGRAM_CODE'=>'/(?<![\d.])\d\.\d{2}\.\d{2}(?![\d.])/u',
            'AGENCY'=>'/^\h*(?:\d{1,2}[.)]\h+)?(?:Perangkat\h+Daerah|Organisasi|SKPD|Unit\h+Organisasi)\h*:/iu',
            'INDICATOR'=>'/\b(?:indikator\h+(?:kinerja|tujuan|sasaran|program|kegiatan)|indikator\h+utama)\b/iu',
            'TARGET'=>'/\b(?:target\h+kinerja|target\h+capaian|target\h+tahunan)\b/iu',
            'FUNDING'=>'/\b(?:pagu\h+indikatif|pendanaan|alokasi\h+anggaran)\b/iu',
            'STRATEGY'=>'/^\h*(?:\d+(?:\.\d+)*\.?\h+)?(?:visi|misi|tujuan|sasaran|strategi|arah\h+kebijakan|isu\h+strategis)\b/iu',
        ];
        foreach($pages as $i=>$page){
            $p=$first+$i;$pc=[];
            foreach(preg_split('/\r\n|\n|\r/u',$page) as $n=>$line){
                foreach($patterns as $kind=>$regex){
                    if(!preg_match($regex,$line,$m))continue;
                    $pc[$kind]=($pc[$kind]??0)+1;$counts[$kind]=($counts[$kind]??0)+1;
                    if(!isset($samples[$kind]))$samples[$kind]=['page'=>$p,'line'=>$n+1,'sha256'=>strtoupper(hash('sha256',$line)),'excerpt'=>mb_substr(trim($line),0,240,'UTF-8'),'matched'=>$m[0]];
                }
            }
            $out[]=['page'=>$p,'text_sha256'=>strtoupper(hash('sha256',$page)),'state'=>trim($page)===''?'NO_TEXT':($pc?'SIGNALS':'UNCLASSIFIED'),'signal_counts'=>(object)$pc];
        }
        return ['pages'=>$out,'counts'=>$counts,'samples'=>$samples,'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
}
