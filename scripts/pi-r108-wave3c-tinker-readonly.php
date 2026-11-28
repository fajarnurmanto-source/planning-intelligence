<?php
// R108 W3C: called inside Laravel Tinker; reads only the existing registry service.
$graph = app(\App\Services\Pi\Development\DevelopmentCaseGraph::class);
$data = $graph->read(['jurisdiction'=>'ID-DIY-YOG','year'=>2026,'topic'=>'PERSAMPAHAN','case'=>'YOG-2026-PERSAMPAHAN']);
$need = static function (bool $condition, string $label): void {
    if (!$condition) { throw new \RuntimeException('PI_R108_W3C_TINKER_HOLD_'.$label); }
};
$need($data['result_count'] === 1 && $data['tenant_id'] === 'ID-DIY-YOG', 'CASE');
$edges = array_column($data['case']['edges'], null, 'id');
$nodes = array_column($data['case']['nodes'], null, 'id');
foreach (['E06','E08','E09','E10','E11','E12'] as $id) {
    $e = $edges[$id] ?? null;
    $need(is_array($e) && isset($nodes[$e['from']], $nodes[$e['to']]) && ($e['status'] ?? null) === 'CANDIDATE' && !empty($e['provenance']['locator']), 'EDGE_'.$id);
}
$need($nodes[$edges['E12']['to']]['type'] === 'Indicator', 'INDICATOR');
$need($graph->read(['year'=>2027])['result_count'] === 0, 'OUT_OF_SCOPE');
foreach (['legal_harmonization'=>'NOT_ASSESSED','numeric_comparison'=>'BLOCKED','review_decision'=>'NO_ACTIVE_DECISION','production_admission'=>'DENIED'] as $key=>$value) {
    $need(($data['interpretation'][$key] ?? null) === $value, 'GATE_'.$key);
}
echo "PI_R108_W3C_TINKER_CASE_SIX_RELATIONS_GATES=PASS\n";
