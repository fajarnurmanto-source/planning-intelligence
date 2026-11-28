<?php
declare(strict_types=1);
namespace App\Services\Pi\Development;
use RuntimeException;

/** Bounded physical row evidence. Header and agency signals are not certifications. */
final class AdaptivePlanningTableEvidence
{
    private const CODE='/^\h*(?:\d{1,3}\.\h+)?(\d\.\d{2}\.\d{2}(?:\.\d\.\d{2}(?:\.\d{4})?)?)(?=\h|$)/u';
    public function tokens(string $line): array
    {
        // Whole tokens only: never parse a substring of a code or mixed-locale number.
        preg_match_all('/(?<![\d.,])(?:-?\d{1,3}(?:\.\d{3})+(?:,\d{2})?|-?\d{1,3}(?:,\d{3})+(?:\.\d{2})?|-?\d+,\d{2})(?![\d.,])/u',$line,$m,PREG_OFFSET_CAPTURE);
        $out=[];
        foreach($m[0] as [$raw,$offset]){
            $kind=str_contains($raw,',')&&str_contains($raw,'.')?(strrpos($raw,',')>strrpos($raw,'.')?'ID_DECIMAL':'EN_DECIMAL'):
                (str_contains($raw,'.')?'ID_GROUPED_INTEGER':(preg_match('/,\d{2}$/D',$raw)?'DECIMAL_COMMA':'EN_GROUPED_INTEGER'));
            $out[]=['raw'=>$raw,'format'=>$kind,'offset'=>mb_strlen(substr($line,0,$offset),'UTF-8')];
        }
        return $out;
    }
    public function scan(string $text,int $first,int $last,array $baseline,array $anchors): array
    {
        $pages=explode("\f",$text);
        if(array_pop($pages)!==''||count($pages)!==$last-$first+1||$first<1||strlen($text)>20_000_000||!preg_match('//u',$text))throw new RuntimeException('PI_RDI03B_BATCH_HOLD');
        $rows=[];$counts=[];$samples=[];
        foreach($pages as $i=>$page){
            $p=$first+$i;$hash=strtoupper(hash('sha256',$page));
            if(($baseline[$p]??null)!==$hash)throw new RuntimeException('PI_RDI03B_PAGE_DRIFT');
            $lines=preg_split('/\r\n|\n|\r/u',$page);$header=[];$agency=[];
            foreach($lines as $n=>$raw){
                if(preg_match('/^\h*(?:BAB\s+[IVXLCDM\d]+|LAMPIRAN\b|DAFTAR\s+ISI\b|Tabel\s+\d+)/iu',$raw)){$header=[];$agency=[];}
                if(preg_match('/\b(?:indikator|target|pagu|pendanaan|kode|satuan)\b/iu',$raw)&&preg_match('/\b(?:202[5-9]|2030|indikator|target|pagu|pendanaan|satuan)\b/iu',$raw)&&!preg_match(self::CODE,$raw)){
                    $header[]=$this->line($raw,$n+1);if(count($header)>12)array_shift($header);
                }
                if(preg_match('/^\h*(?:Perangkat\h+Daerah|Organisasi|SKPD)\h*:\h*\S/iu',$raw))$agency=[$this->line($raw,$n+1)];
                $anchor=$anchors[$p][$n+1]??null;if($anchor===null)continue;
                if(!preg_match(self::CODE,$raw,$m)||$m[1]!==$anchor['code']||strtoupper(hash('sha256',$raw))!==$anchor['window'][0]['sha256'])throw new RuntimeException('PI_RDI03B_ANCHOR_DRIFT');
                $window=[];$tokenRows=[];$stop='PAGE_END';$limit=min(count($lines),$n+32);
                for($k=$n;$k<$limit;$k++){
                    $line=$lines[$k];
                    if($k>$n&&(preg_match(self::CODE,$line)||preg_match('/^\h*(?:BAB\s+[IVXLCDM\d]+|LAMPIRAN\b|DAFTAR\s+ISI\b|Tabel\s+\d+|Halaman\b|Jumlah\h+Total\b|Perangkat\h+Daerah\h*:|Organisasi\h*:|SKPD\h*:)/iu',$line))){$stop='STRUCTURAL_BOUNDARY';break;}
                    // A blank is evidence, not a reason to skip an entire continuation.
                    $window[]=$this->line($line,$k+1);
                    $body=$k===$n?substr($line,strlen($m[0])):$line;
                    $tokens=$this->tokens($body);
                    if($tokens)$tokenRows[]=['line'=>$k+1,'tokens'=>$tokens,'offset_basis'=>$k===$n?'AFTER_CODE':'FULL_LINE'];
                }
                if($k===$limit&&$limit<count($lines))$stop='LIMIT_HOLD';
                $five=count(array_filter($tokenRows,static fn($r)=>count($r['tokens'])===5));
                $six=count(array_filter($tokenRows,static fn($r)=>count($r['tokens'])===6));
                $shape=match(true){!$tokenRows=>'NO_TOKEN_SIGNAL',$five+$six>1=>'REPEATED_POSITIONAL_GROUPS_HOLD',$five===1=>'FIVE_TOKEN_GROUP_CANDIDATE',$six===1=>'SIX_TOKEN_GROUP_CANDIDATE',default=>'OTHER_TOKEN_SHAPE_HOLD'};
                $row=['page'=>$p,'line'=>$n+1,'page_sha256'=>$hash,'code'=>$anchor['code'],'window'=>$window,'token_rows'=>$tokenRows,'header_signals'=>$header,'agency_heading_signals'=>$agency,'boundary'=>$stop,'shape'=>$shape,'column_semantics'=>'NOT_CERTIFIED','agency_identity'=>'UNRESOLVED','numeric_comparison'=>'BLOCKED'];
                $rows[]=$row;$counts[$shape]=($counts[$shape]??0)+1;
                $counts['BOUNDARY_'.$stop]=($counts['BOUNDARY_'.$stop]??0)+1;
                if(!isset($samples[$shape]))$samples[$shape]=$row;
            }
        }
        return ['records'=>$rows,'counts'=>$counts,'samples'=>$samples,'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
    private function line(string $s,int $n): array
    {
        return ['line'=>$n,'sha256'=>strtoupper(hash('sha256',$s)),'text'=>mb_substr($s,0,1200,'UTF-8'),'truncated'=>mb_strlen($s,'UTF-8')>1200];
    }
}
