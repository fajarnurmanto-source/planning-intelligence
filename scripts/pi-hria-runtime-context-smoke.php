<?php
declare(strict_types=1);
// Isolated framework/vault smoke, no actual credential or outbound calls.
require dirname(__DIR__).'/laravel/vendor/autoload.php';
require __DIR__.'/pi-hria-runtime-context.php';
$root=sys_get_temp_dir().'/pi-hria-context-'.bin2hex(random_bytes(8));
mkdir($root.'/config',0700,true);
mkdir($root.'/storage/app/private',0700,true);
// Original runtime deliberately has no regulatory registry.
$qa=dirname(__DIR__).'/laravel';

file_put_contents($root.'/config/app.php','<?php return '.var_export(['key'=>'base64:'.base64_encode(random_bytes(32)),'cipher'=>'AES-256-CBC','previous_keys'=>[]],true).';');
file_put_contents($root.'/config/filesystems.php','<?php return '.var_export(['default'=>'local','disks'=>['local'=>['driver'=>'local','root'=>$root.'/storage/app/private','throw'=>true]]],true).';');
file_put_contents($root.'/config/pi.php','<?php return ["tenant_id"=>"ID-DIY-YOG"];');
$app=piHriaRuntimeContext($root,$qa);
$vault=$app->make(\App\Services\PlanningIntelligence\AiProviderCredentialVault::class);
$vault->save('ID-DIY-YOG','deepseek','synthetic-test-key','synthetic-model');
$r=$vault->resolve('ID-DIY-YOG','deepseek');
if($r['source']!=='encrypted_vault'||$r['api_key']!=='synthetic-test-key')throw new RuntimeException('HRIA_FIXTURE_VAULT_HOLD');
\Illuminate\Support\Facades\Http::preventStrayRequests();
\Illuminate\Support\Facades\Http::fake(['https://api.deepseek.com/chat/completions'=>\Illuminate\Support\Facades\Http::response(['choices'=>[['message'=>['content'=>'{"semantic_relation":"RELATED","semantic_confidence":"MEDIUM","explanation":"Synthetic only"}'],'finish_reason'=>'stop']]],200)]);
$provider=new \App\Services\Pi\Development\CloudSemanticCandidateProvider;
$out=$provider->propose(['scope'=>'PRIMARY_TEXT_LAYER_CANDIDATES_NOT_CERTIFIED_LEGAL_TEXT'],'deepseek',$r['model'],$r['api_key']);
if($out['semantic_relation']!=='RELATED')throw new RuntimeException('HRIA_FIXTURE_PROVIDER_HOLD');
\Illuminate\Support\Facades\Http::assertSentCount(1);
\Illuminate\Support\Facades\Http::assertSent(static function($request){
    $data=$request->data();
    return ($data['thinking']['type']??null)==='disabled'
        && ($data['response_format']['type']??null)==='json_object'
        && ($data['max_tokens']??null)===400
        && ($data['stream']??null)===false;
});
if(($provider->diagnostic()['stage']??null)!=='OUTPUT_CONTRACT_PASS')throw new RuntimeException('HRIA_FIXTURE_DIAGNOSTIC_HOLD');
foreach([
    [401, ['error'=>['message'=>'DO_NOT_PRINT_FIXTURE']], 'R89_CLOUD_PROVIDER_UNAVAILABLE', 'HTTP_RESPONSE', null],
    [402, ['error'=>['message'=>'DO_NOT_PRINT_FIXTURE']], 'R89_CLOUD_PROVIDER_UNAVAILABLE', 'HTTP_RESPONSE', null],
    [429, ['error'=>['message'=>'DO_NOT_PRINT_FIXTURE']], 'R89_CLOUD_PROVIDER_UNAVAILABLE', 'HTTP_RESPONSE', null],
    [200, ['choices'=>[['message'=>['content'=>'{}'],'finish_reason'=>'length']]], 'R89_CLOUD_PROVIDER_UNAVAILABLE', 'HTTP_RESPONSE', 'length'],
    [200, ['choices'=>[['message'=>['content'=>'   '],'finish_reason'=>'stop']]], 'R89_CLOUD_OUTPUT_INVALID', 'HTTP_RESPONSE', 'stop'],
    [200, ['choices'=>[['message'=>['content'=>'invalid-json'],'finish_reason'=>'stop']]], 'JSON_EXCEPTION', 'JSON_DECODE', 'stop'],
] as [$status,$body,$expected,$stage,$finish]){
    \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory);
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    \Illuminate\Support\Facades\Http::fake(['https://api.deepseek.com/chat/completions'=>\Illuminate\Support\Facades\Http::response($body,$status)]);
    $failed=false;
    try{$provider->propose(['scope'=>'PRIMARY_TEXT_LAYER_CANDIDATES_NOT_CERTIFIED_LEGAL_TEXT'],'deepseek',$r['model'],$r['api_key']);}
    catch(Throwable $e){$failed=($e instanceof JsonException?'JSON_EXCEPTION':$e->getMessage())===$expected;}
    $diag=$provider->diagnostic();
    if(!$failed||$diag['http_status']!==$status||$diag['stage']!==$stage||$diag['finish_reason']!==$finish||str_contains(json_encode($diag),'DO_NOT_PRINT_FIXTURE'))throw new RuntimeException('HRIA_FIXTURE_FAILURE_DIAGNOSTIC_HOLD');
    \Illuminate\Support\Facades\Http::assertSentCount(1);
}
echo "HRIA_DEEPSEEK_BOUNDED_JSON_AND_SAFE_FAILURE_DIAGNOSTICS=PASS\n";
// Gemini fixture exercises original vault, fixed endpoint and visible JSON parsing.
$vault->save('ID-DIY-YOG','gemini','synthetic-gemini-key','gemini-3.5-flash-lite');
$g=$vault->resolve('ID-DIY-YOG','gemini');
if($g['source']!=='encrypted_vault'||$g['api_key']!=='synthetic-gemini-key')throw new RuntimeException('HRIA_GEMINI_VAULT_HOLD');
\Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory);
\Illuminate\Support\Facades\Http::preventStrayRequests();
\Illuminate\Support\Facades\Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent'=>\Illuminate\Support\Facades\Http::response(['candidates'=>[['content'=>['parts'=>[['thought'=>true,'text'=>'IGNORE_THOUGHT'],['text'=>'{"semantic_relation":"RELATED","semantic_confidence":"MEDIUM","explanation":"P1 and P2; synthetic"}']]],'finishReason'=>'STOP']],'usageMetadata'=>['promptTokenCount'=>120,'candidatesTokenCount'=>35,'thoughtsTokenCount'=>8,'totalTokenCount'=>163]],200)]);
$out=$provider->propose(['scope'=>'PRIMARY_TEXT_LAYER_CANDIDATES_NOT_CERTIFIED_LEGAL_TEXT'],'gemini',$g['model'],$g['api_key']);
if($out['semantic_relation']!=='RELATED'||($provider->diagnostic()['usage']['totalTokenCount']??null)!==163)throw new RuntimeException('HRIA_GEMINI_OUTPUT_HOLD');
\Illuminate\Support\Facades\Http::assertSentCount(1);
\Illuminate\Support\Facades\Http::assertSent(static function($request){
    $data=$request->data();
    return $request->hasHeader('x-goog-api-key','synthetic-gemini-key')
        && ($data['generationConfig']['thinkingConfig']['thinkingLevel']??null)==='MINIMAL'
        && ($data['generationConfig']['responseMimeType']??null)==='application/json'
        && ($data['generationConfig']['maxOutputTokens']??null)===400;
});
echo "HRIA_GEMINI_VAULT_VISIBLE_JSON_USAGE=PASS\n";
if(is_dir($root.'/bootstrap/cache'))throw new RuntimeException('HRIA_PACKAGE_CACHE_MUTATION_HOLD');
$registry=new \App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;
$coverage=new \App\Services\Pi\Regulatory\RegulatoryDelegationCoverage($registry);
$projection=$coverage->project($qa.'/resources/pi/regulatory-delegation-coverage-r80.json',$qa.'/resources/pi/regulatory-genealogy-r74.json');
if($projection['candidate_article_count']!==7||is_dir($root.'/resources'))throw new RuntimeException('HRIA_QA_REGISTRY_ISOLATION_HOLD');
echo "HRIA_QA_REGISTRY_ISOLATION=PASS\n";
echo "HRIA_MINIMAL_CONTEXT_VAULT_FAKE_PROVIDER=PASS\n";
echo "HRIA_PACKAGE_CACHE_DISCOVERY=NOT_REQUIRED\n";
