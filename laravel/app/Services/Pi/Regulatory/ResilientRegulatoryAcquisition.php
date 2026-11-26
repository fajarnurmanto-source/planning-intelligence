<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use RuntimeException;

/** RAI local-first candidate resolver. No legal effect or production admission. */
final class ResilientRegulatoryAcquisition
{
    private const HOSTS=['BPK'=>'peraturan.bpk.go.id','DITJEN_PP'=>'peraturan.go.id'];
    private const MAX=83886080;

    public function resolve(array $document,string $root,callable $fetch): array
    {
        $id=$document['id']??null;$sha=$document['expected_sha256']??null;
        if(!is_string($id)||!preg_match('/^[A-Z0-9-]{4,100}$/D',$id)||!is_string($sha)||!preg_match('/^[A-F0-9]{64}$/D',$sha)
            ||!is_dir($root)||is_link($root)||!is_array($document['sources']??null)||count($document['sources'])>8)throw new RuntimeException('PI_RAI_INPUT_HOLD');
        $sources=[];
        foreach($document['sources'] as $s){
            $u=parse_url($s['url']??'');$adapter=$s['adapter']??'';
            if(!isset(self::HOSTS[$adapter])||!is_array($u)||($u['scheme']??null)!=='https'||($u['host']??null)!==self::HOSTS[$adapter]||isset($u['user'])||isset($u['pass'])||isset($u['port'])||isset($u['fragment'])||isset($u['query'])||!preg_match('~^/(?:Download/[0-9]+/[^/]+|files/[^/]+)\.pdf$~i',$u['path']??''))throw new RuntimeException('PI_RAI_SOURCE_HOLD');
            $sources[$s['url']]=$s;
        }
        $path=rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$sha.'.pdf';
        $result=['id'=>$id,'expected_sha256'=>$sha,'attempts'=>[],'freshness'=>'UNVERIFIED','legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED','analysis_scope'=>'CANDIDATE_HISTORICAL_ONLY'];
        if(file_exists($path)||is_link($path)){
            if(!$this->validFile($path,$sha))return $result+['state'=>'LOCAL_INTEGRITY_HOLD'];
            return $result+['state'=>'LOCAL_PINNED_CANDIDATE_AVAILABLE','source'=>'LOCAL'];
        }
        foreach($sources as $s){
            try{$r=$fetch($s['url']);}catch(\Throwable){$r=['status'=>0];}
            $status=$r['status']??0;
            $action=match($status){403=>'ALTERNATE_SOURCE',404=>'REDISCOVERY_REQUIRED',429=>'DEFER_RATE_LIMIT',503,0=>'DEFER_TRANSIENT',200=>'VALIDATE_BYTES',default=>'SOURCE_HOLD'};
            $attempt=['adapter'=>$s['adapter'],'url'=>$s['url'],'http_status'=>$status,'action'=>$action];
            if($status!==200){$result['attempts'][]=$attempt;continue;}
            $body=$r['body']??null;
            if(!is_string($body)||strlen($body)<128||strlen($body)>self::MAX||!str_starts_with($body,'%PDF-')||!str_contains(substr($body,-4096),'%%EOF')){
                $result['attempts'][]=array_replace($attempt,['action'=>'NON_PDF_HOLD']);continue;
            }
            if(strtoupper(hash('sha256',$body))!==$sha){$result['attempts'][]=array_replace($attempt,['action'=>'DIFFERENT_BYTES_HOLD']);continue;}
            // Exclusive creation: no replacement of an existing snapshot.
            $f=@fopen($path,'xb');
            if($f===false)return $result+['state'=>'REPOSITORY_WRITE_HOLD'];
            try{$written=fwrite($f,$body);}finally{fclose($f);}
            if($written!==strlen($body)||!$this->validFile($path,$sha)){@unlink($path);return $result+['state'=>'REPOSITORY_WRITE_HOLD'];}
            $result['attempts'][]=array_replace($attempt,['action'=>'PIN_MATCH']);
            return $result+['state'=>'REMOTE_PINNED_CANDIDATE_STORED','source'=>$s['adapter']];
        }
        return $result+['state'=>'ACQUISITION_PENDING','analysis_scope'=>'CANDIDATE_HISTORICAL_ONLY'];
    }

    public function importPinned(array $document,string $file,string $root): array
    {
        $sha=$document['expected_sha256']??'';
        if(!is_string($sha)||!preg_match('/^[A-F0-9]{64}$/D',$sha)||!$this->validFile($file,$sha))throw new RuntimeException('PI_RAI_IMPORT_PIN_HOLD');
        return $this->resolve($document,$root,static fn()=>['status'=>200,'body'=>file_get_contents($file)]);
    }

    private function validFile(string $file,string $sha): bool
    {
        if(is_link($file)||!is_file($file)||filesize($file)<128||filesize($file)>self::MAX)return false;
        $f=fopen($file,'rb');if(!$f)return false;
        try{$magic=fread($f,5);fseek($f,-min(4096,filesize($file)),SEEK_END);$tail=stream_get_contents($f);}finally{fclose($f);}
        return $magic==='%PDF-'&&str_contains($tail,'%%EOF')&&strtoupper(hash_file('sha256',$file))===$sha;
    }
}
