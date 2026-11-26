<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use DOMDocument;
use DOMXPath;
use RuntimeException;

/** Exact-identity official metadata link discovery, not legal or byte verification. */
final class RegulatoryAlternativeSourceDiscovery
{
    public function metadataUrl(string $id): string
    {
        if(!preg_match('/^(UU|PERMENDAGRI)-([1-9][0-9]{0,3})-((?:19|20)[0-9]{2})$/D',$id,$m))throw new RuntimeException('PI_RAI_DISCOVERY_ID_HOLD');
        return 'https://peraturan.go.id/id/'.strtolower($m[1]).'-no-'.$m[2].'-tahun-'.$m[3];
    }
    public function discover(string $id,callable $fetch): array
    {
        $url=$this->metadataUrl($id);
        $result=['metadata_url'=>$url,'metadata_sha256'=>null,'sources'=>[],'legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
        try{$r=$fetch($url);}catch(\Throwable){return $result+['state'=>'METADATA_UNAVAILABLE'];}
        $html=$r['body']??null;
        if(($r['status']??0)!==200||!is_string($html)||strlen($html)>5242880||$html==='')return $result+['state'=>'METADATA_UNAVAILABLE'];
        $dom=new DOMDocument();
        if(!@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING))return $result+['state'=>'METADATA_PARSE_HOLD'];
        $xp=new DOMXPath($dom);$heading=trim((string)$xp->query('//h1')?->item(0)?->textContent);
        preg_match('/^(UU|PERMENDAGRI)-(\d+)-(\d+)$/D',$id,$m);
        $kind=$m[1]==='UU'?'(?:Undang[\s-]+Undang|UU)':'(?:Peraturan\s+Menteri\s+Dalam\s+Negeri|Permendagri)';
        if(!preg_match('/^'.$kind.'\s+(?:Nomor|No\.?)\s*'.$m[2].'\s+Tahun\s+'.$m[3].'\b/iu',$heading))return $result+['state'=>'METADATA_IDENTITY_HOLD'];
        $result['metadata_sha256']=strtoupper(hash('sha256',$html));
        foreach($xp->query('//a[@href]') as $a){
            $href=trim($a->getAttribute('href'));
            if(str_starts_with($href,'/files/'))$href='https://peraturan.go.id'.$href;
            $p=parse_url($href);
            if(!is_array($p)||($p['scheme']??null)!=='https'||!in_array($p['host']??null,['peraturan.go.id','www.peraturan.go.id'],true)||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['query'])||isset($p['fragment'])||!preg_match('~^/files/[A-Za-z0-9%_.-]+\.pdf$~D',$p['path']??''))continue;
            $name=strtolower(rawurldecode(basename($p['path'])));
            // A link in the document page may also refer to a different instrument.
            $k=strtolower($m[1]);
            if(!preg_match('/^'.preg_quote($k,'/').'[-_ ]*(?:no[-_ ]*)?'.$m[2].'[-_ ]*(?:tahun[-_ ]*)?'.$m[3].'(?:[-_ ]*(bt|pjl|lampiran))?\.pdf$/D',$name,$part))continue;
            $role=match($part[1]??''){'bt'=>'BODY_CANDIDATE','pjl'=>'EXPLANATION_CANDIDATE','lampiran'=>'ANNEX_CANDIDATE',default=>'UNSPECIFIED_CANDIDATE'};
            $result['sources'][$href]=['adapter'=>'DITJEN_PP','url'=>$href,'component_role'=>$role];
            if(count($result['sources'])>=4)break;
        }
        $result['sources']=array_values($result['sources']);
        return $result+['state'=>$result['sources']?'LINK_CANDIDATES_FOUND':'NO_EXACT_PDF_LINK'];
    }
}
