<?php
declare(strict_types=1);

namespace App\Services\Pi\Development;

use RuntimeException;

/** Layout-space observations, not physical bounding boxes or certified legal text. */
final class DocumentLayoutReconstructor
{
    public function reconstruct(string $text, int $pages): array
    {
        $parts=explode("\f",$text);
        if(array_pop($parts)!=='' || count($parts)!==$pages || !preg_match('//u',$text)){
            throw new RuntimeException('PI_RDI_LAYOUT_PAGE_BOUNDARY_HOLD');
        }
        $zone='UNRESOLVED';$article=null;$ayat=null;$letter=null;$nodes=[];$issues=[];$tables=[];$seen=[];$amendment=false;
        foreach($parts as $i=>$page){
            $p=$i+1;$rows=[];$subactivity=null;
            if(trim($page)===''){$article=$ayat=$letter=null;$zone='UNRESOLVED';}
            foreach(preg_split('/\r\n|\n|\r/u',$page) as $n=>$raw){
                $line=trim($raw);
                if($line==='')continue;
                preg_match('/^ */',$raw,$indentMatch);
                $indent=strlen($indentMatch[0]);
                $loc=['page'=>$p,'line'=>$n+1,'line_sha256'=>strtoupper(hash('sha256',$raw)),'layout_indent'=>$indent];
                if(preg_match('/^\s*(?:P\s*E\s*N\s*J\s*E\s*L\s*A\s*S\s*A\s*N)(?:\s+ATAS)?\s*$/iu',$line)){
                    $zone='EXPLANATION';$article=$ayat=$letter=null;continue;
                }
                if(preg_match('/^LAMPIRAN(?:\h+[IVXLCDM\d]+)?(?:\h*[:.]|\h*$)/iu',$line)){
                    $zone='ANNEX';$article=$ayat=$letter=null;continue;
                }
                if(preg_match('/^DAFTAR\h+ISI$/iu',$line)){
                    $zone='CONTENTS';$article=$ayat=$letter=null;continue;
                }
                if(preg_match('/^MEMUTUSKAN\s*:/iu',$line)){
                    $zone='BODY_CANDIDATE';$article=$ayat=$letter=null;continue;
                }
                if(preg_match('/\b(?:ketentuan\s+pasal|di\s+antara\s+pasal|diubah\s+sehingga|dihapus|disisipkan)\b/iu',$line)&&$zone==='BODY_CANDIDATE'){
                    $amendment=true;
                    $issues[]=$loc+['reason'=>'AMENDMENT_OR_QUOTATION_CONTEXT','excerpt'=>mb_substr($line,0,180)];
                }
                if(preg_match('/^Sub\h*Kegiatan\h*:\h*(\d+(?:\.\d+){5})(?![\d.])/iu',$line,$m)){
                    $subactivity=$m[1];
                    // A fiscal context signal is sufficient to stop legal parent inference.
                    $zone='FISCAL_LAYOUT_CANDIDATE';$article=$ayat=$letter=null;
                }
                if(preg_match('/^(\d(?:\.\d+){4}\.(?:\d{5}|\d{3}))(?!\d)/u',$line,$m)){
                    $segments=preg_split('/\h{2,}/u',$line);
                    preg_match_all('/(?<![\d.])-?\d{1,3}(?:\.\d{3})*,\d{2}(?!\d)/u',$line,$money);
                    $rows[]=$loc+['code_fragment'=>$m[1],'subactivity_same_page'=>$subactivity,
                        'code_state'=>strlen(substr(strrchr($m[1],'.'),1))===5?'FULL_MARKER':'SPLIT_UNRESOLVED',
                        'column_fragments'=>$segments,'money_tokens'=>$money[0],'state'=>'UNVERIFIED_ROW_FRAGMENT'];
                    continue;
                }
                if($zone!=='BODY_CANDIDATE')continue;
                $kind=null;$label=null;$parent=null;
                if(preg_match('/^Pasal\h+(\d+[A-Z]?|[IVXLCDM]+)$/iu',$line,$m)){
                    $kind='PASAL';$label=strtoupper($m[1]);$ayat=$letter=null;
                }elseif(preg_match('/^\((\d+[a-z]?)\)\h+\S/iu',$line,$m)){
                    $kind='AYAT';$label=$m[1];$parent=$article;$letter=null;
                }elseif(preg_match('/^([a-z])[.)]\h+\S/u',$line,$m)){
                    $kind='HURUF';$label=$m[1];$parent=$ayat??$article;
                }elseif(preg_match('/^(\d+)[.)]\h+\S/u',$line,$m)){
                    $kind='ANGKA';$label=$m[1];$parent=$letter;
                }
                if($kind===null)continue;
                $id='p'.$p.'l'.($n+1);
                $reason=null;
                if($amendment)$reason='AMENDMENT_CONTEXT_UNRESOLVED';
                elseif($kind!=='PASAL'&&$parent===null)$reason='MISSING_PARENT';
                elseif(in_array($kind,['HURUF','ANGKA'],true)&&$parent!==null&&$indent<=$nodes[$parent]['layout_indent'])$reason='INDENT_NOT_DEEPER';
                $key=($parent??'ROOT').'|'.$kind.'|'.$label;
                if(isset($seen[$key])){
                    $reason='DUPLICATE_IDENTITY';
                    $old=$seen[$key];$nodes[$old]['state']='AMBIGUOUS';$nodes[$old]['parent_candidate']=null;
                    $issues[]=['node'=>$old,'reason'=>$reason];
                }
                if($parent!==null&&$nodes[$parent]['state']!=='CANDIDATE')$reason='PARENT_UNRESOLVED';
                $nodes[$id]=$loc+['id'=>$id,'kind'=>$kind,'label'=>$label,
                    'parent_candidate'=>$reason===null?$parent:null,'state'=>$reason===null?'CANDIDATE':'AMBIGUOUS'];
                if($reason!==null)$issues[]=['node'=>$id,'reason'=>$reason];
                $seen[$key]=$id;
                if($kind==='PASAL')$article=$id;
                if($kind==='AYAT')$ayat=$id;
                if($kind==='HURUF')$letter=$id;
            }
            if($rows)$tables[]=['page'=>$p,'rows'=>$rows];
        }
        // Invalidate descendants of any parent subsequently found duplicate.
        foreach($nodes as $id=>&$node){
            $parent=$node['parent_candidate'];
            if($parent!==null&&$nodes[$parent]['state']!=='CANDIDATE'){
                $node['state']='AMBIGUOUS';$node['parent_candidate']=null;
                $issues[]=['node'=>$id,'reason'=>'ANCESTOR_UNRESOLVED'];
            }
        }
        unset($node);
        return ['schema'=>'PI_RDI_LAYOUT_CANDIDATES_V2','pages'=>$pages,
            'hierarchy_nodes'=>array_values($nodes),'ambiguity_records'=>$issues,
            'ambiguous_node_count'=>count(array_filter($nodes,static fn($n)=>$n['state']==='AMBIGUOUS')),
            'account_fragment_pages'=>$tables,'account_fragments'=>array_sum(array_map(static fn($p)=>count($p['rows']),$tables)),
            'coordinate_system'=>'POPPLER_LAYOUT_CHARACTER_SPACES_NOT_PDF_COORDINATES',
            'legal_verification'=>'NOT_PERFORMED','table_reconstruction'=>'PARTIAL_FRAGMENTS_ONLY',
            'numeric_admission'=>0,'production_admission'=>'DENIED'];
    }
}
