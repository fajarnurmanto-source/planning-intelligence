<?php
/** Read-only real-engine payload export for a clearly labeled browser render replay. */
require __DIR__.'/../laravel/vendor/autoload.php';
$app = require __DIR__.'/../laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$model = $app->make(App\Services\Pi\Development\RegulatoryPlanningCrosswalk::class);
$out = getenv('PI_R88_EVIDENCE_DIR') ?: __DIR__.'/../qualification/r88';
if (!is_dir($out)) mkdir($out, 0777, true);
foreach (['2026'=>[], '2027'=>['year'=>2027], '2020'=>['as_of'=>'2020-01-01']] as $key=>$filters) {
    file_put_contents($out.'/model-'.$key.'.json', json_encode($model->read($filters), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
