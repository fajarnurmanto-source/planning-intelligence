<?php
declare(strict_types=1);
namespace App\Services\Pi\Regulatory;

use RuntimeException;

/** Distinct publication bytes are never promoted to the canonical pin. */
final class RegulatoryManifestationIntake
{
    public function stage(string $id,array $discovery,array $source,string $body,string $root,string $pin): array
    {
        if(!preg_match('/^(UU|PERMENDAGRI)-[1-9][0-9]{0,3}-(?:19|20)[0-9]{2}$/D',$id)
            ||!preg_match('/^[A-F0-9]{64}$/D',$pin)||!is_dir($root)||is_link($root))throw new RuntimeException('PI_RAI_MANIFESTATION_INPUT_HOLD');
        if(($discovery['state']??null)!=='LINK_CANDIDATES_FOUND'
            ||($discovery['metadata_url']??null)!==(new RegulatoryAlternativeSourceDiscovery)->metadataUrl($id)
            ||!preg_match('/^[A-F0-9]{64}$/D',$discovery['metadata_sha256']??'')
            ||!in_array($source,$discovery['sources']??[],true))throw new RuntimeException('PI_RAI_MANIFESTATION_DISCOVERY_HOLD');
        $url=$source['url']??'';$p=parse_url($url);
        if(($source['adapter']??null)!=='DITJEN_PP'||!is_array($p)||($p['scheme']??null)!=='https'
            ||!in_array($p['host']??null,['peraturan.go.id','www.peraturan.go.id'],true)
            ||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['query'])||isset($p['fragment'])
            ||!preg_match('~^/files/[A-Za-z0-9%_.-]+\.pdf$~D',$p['path']??''))throw new RuntimeException('PI_RAI_MANIFESTATION_URL_HOLD');
        if(strlen($body)<128||strlen($body)>83886080||!str_starts_with($body,'%PDF-')||!str_contains(substr($body,-4096),'%%EOF'))throw new RuntimeException('PI_RAI_MANIFESTATION_PDF_HOLD');
        $sha=strtoupper(hash('sha256',$body));
        $role=$source['component_role']??'UNSPECIFIED_CANDIDATE';
        if(!in_array($role,['BODY_CANDIDATE','EXPLANATION_CANDIDATE','ANNEX_CANDIDATE','UNSPECIFIED_CANDIDATE'],true))throw new RuntimeException('PI_RAI_MANIFESTATION_ROLE_HOLD');
        $receipt=['schema'=>'PI_RAI_MANIFESTATION_CANDIDATE_V1','instrument_id'=>$id,'canonical_pin'=>$pin,
            'observed_sha256'=>$sha,'source_url'=>$url,'metadata_url'=>$discovery['metadata_url'],'metadata_sha256'=>$discovery['metadata_sha256'],
            'component_role'=>$role,'component_identity'=>'METADATA_AND_FILENAME_CANDIDATE_ONLY',
            'byte_relation'=>$sha===$pin?'IDENTICAL_BYTES':'DISTINCT_BYTES',
            'coverage'=>'NOT_VERIFIED','content_equivalence'=>'NOT_ASSESSED','version_relation'=>'NOT_ASSESSED',
            'legal_effect'=>'NOT_ASSESSED','production_admission'=>'DENIED'];
        // Separate directory, never overwrite or adopt as canonical.
        $path=rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$sha.'.pdf';
        $this->immutableWrite($path,$body);
        $json=json_encode($receipt,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $this->immutableWrite($path.'.'.hash('sha256',$json).'.receipt.json',$json);
        return $receipt+['state'=>'MANIFESTATION_QUARANTINED'];
    }

    private function immutableWrite(string $path,string $bytes): void
    {
        if(is_link($path))throw new RuntimeException('PI_RAI_MANIFESTATION_SYMLINK_HOLD');
        if(is_file($path)){
            if(hash_file('sha256',$path)!==hash('sha256',$bytes))throw new RuntimeException('PI_RAI_MANIFESTATION_EXISTING_DRIFT');
            return;
        }
        $f=@fopen($path,'xb');
        if(!$f)throw new RuntimeException('PI_RAI_MANIFESTATION_WRITE_HOLD');
        try{$n=fwrite($f,$bytes);}finally{fclose($f);}
        if($n!==strlen($bytes)||hash_file('sha256',$path)!==hash('sha256',$bytes)){
            @unlink($path);throw new RuntimeException('PI_RAI_MANIFESTATION_WRITE_HOLD');
        }
    }
}
