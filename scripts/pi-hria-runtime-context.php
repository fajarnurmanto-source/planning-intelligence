<?php
declare(strict_types=1);

/**
 * Read the original Laravel configuration and bind only the framework services
 * needed for a local credential-vault read and bounded HTTP request.
 * Do not bootstrap routes, console commands, database or application providers.
 */
function piHriaRuntimeContext(string $runtime,string $qa): \Illuminate\Foundation\Application
{
    $app=new \Illuminate\Foundation\Application($runtime);
    (new \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables)->bootstrap($app);
    (new \Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);
    (new \Illuminate\Foundation\Bootstrap\RegisterFacades)->bootstrap($app);
    $config=$app->make('config');
    if($config->get('pi.tenant_id')!=='ID-DIY-YOG')throw new RuntimeException('HRIA_TENANT_HOLD');
    if(!is_string($config->get('app.key'))||trim($config->get('app.key'))==='')throw new RuntimeException('HRIA_APP_ENCRYPTION_CONFIG_HOLD');
    $disk=$config->get('filesystems.disks.local');
    if(!is_array($disk)||($disk['driver']??null)!=='local'||!is_string($disk['root']??null))throw new RuntimeException('HRIA_VAULT_DISK_CONFIG_HOLD');
    $root=realpath($disk['root']);
    $storage=realpath($app->storagePath());
    if(!$root||!$storage||!str_starts_with(strtolower(str_replace('\\','/',$root).'/'),strtolower(rtrim(str_replace('\\','/',$storage),'/').'/')))throw new RuntimeException('HRIA_VAULT_DISK_SCOPE_HOLD');
    // No filesystem routes, service-provider boot, writes or database access.
    $app->register(\Illuminate\Encryption\EncryptionServiceProvider::class);
    $app->register(\Illuminate\Filesystem\FilesystemServiceProvider::class);
    $app->singleton(\Illuminate\Http\Client\Factory::class,static fn()=>new \Illuminate\Http\Client\Factory);
    foreach(['regulatory-genealogy-r74.json','regulatory-delegation-coverage-r80.json'] as $name){
        $a=$runtime.'/resources/pi/'.$name;$b=$qa.'/resources/pi/'.$name;
        if(!is_file($a)||!is_file($b)||hash_file('sha256',$a)!==hash_file('sha256',$b))throw new RuntimeException('HRIA_REGISTRY_PARITY_HOLD');
    }
    return $app;
}
