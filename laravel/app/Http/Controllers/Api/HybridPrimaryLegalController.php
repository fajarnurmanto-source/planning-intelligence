<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\HybridPrimaryLegalAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class HybridPrimaryLegalController extends Controller
{
    private function rules(): array
    {
        return ['article'=>['required','integer','between:1,999'],
            'case_date'=>['required','date_format:Y-m-d'],
            'knowledge_date'=>['required','date_format:Y-m-d']];
    }

    private function response(array $body,int $code=200): JsonResponse
    {
        return response()->json($body,$code)->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
    }

    private function hold(): JsonResponse
    {
        return $this->response(['state'=>'ABSTAIN','reason'=>'PRIMARY_EVIDENCE_UNAVAILABLE','production_admission'=>'DENIED'],503);
    }

    public function preview(Request $request,HybridPrimaryLegalAnalysis $analysis): JsonResponse
    {
        abort_unless($request->user()->tenant_id==='ID-DIY-YOG',403);
        $v=$request->validate($this->rules());
        try{
            return $this->response($analysis->preview((int)$v['article'],$v['case_date'],$v['knowledge_date']));
        }catch(\Throwable){return $this->hold();}
    }

    public function analyze(Request $request,HybridPrimaryLegalAnalysis $analysis): JsonResponse
    {
        abort_unless($request->user()->tenant_id==='ID-DIY-YOG',403);
        $v=$request->validate($this->rules()+[
            'provider'=>['required','in:deepseek,openai,gemini,groq,openrouter'],
            'approved_context_sha256'=>['required','string','regex:/\A[A-Fa-f0-9]{64}\z/D'],
            'approved_cloud_evidence'=>['required','accepted'],
            'approved_provider_charges'=>['required','accepted']]);
        try{
            $r=$analysis->analyze((int)$v['article'],$v['case_date'],$v['knowledge_date'],(string)$request->user()->tenant_id,$v['provider'],$v['approved_context_sha256'],true,true);
            return $this->response($r,($r['reason']??null)==='PREVIEW_DRIFT'?409:200);
        }catch(\Throwable){return $this->hold();}
    }
}
