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
$out=(new \App\Services\Pi\Development\CloudSemanticCandidateProvider)->propose(['scope'=>'PRIMARY_TEXT_LAYER_CANDIDATES_NOT_CERTIFIED_LEGAL_TEXT'],'deepseek',$r['model'],$r['api_key']);
if($out['semantic_relation']!=='RELATED')throw new RuntimeException('HRIA_FIXTURE_PROVIDER_HOLD');
\Illuminate\Support\Facades\Http::assertSentCount(1);
if(is_dir($root.'/bootstrap/cache'))throw new RuntimeException('HRIA_PACKAGE_CACHE_MUTATION_HOLD');
$registry=new \App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;
$coverage=new \App\Services\Pi\Regulatory\RegulatoryDelegationCoverage($registry);
$projection=$coverage->project($qa.'/resources/pi/regulatory-delegation-coverage-r80.json',$qa.'/resources/pi/regulatory-genealogy-r74.json');
if($projection['candidate_article_count']!==7||is_dir($root.'/resources'))throw new RuntimeException('HRIA_QA_REGISTRY_ISOLATION_HOLD');
echo "HRIA_QA_REGISTRY_ISOLATION=PASS\n";
echo "HRIA_MINIMAL_CONTEXT_VAULT_FAKE_PROVIDER=PASS\n";
echo "HRIA_PACKAGE_CACHE_DISCOVERY=NOT_REQUIRED\n";
