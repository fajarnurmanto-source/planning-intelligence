<?php

namespace App\Services\PlanningIntelligence;

class EntitlementService
{
    /** @return array<string, mixed> */
    public function forTenant(string $tenantId): array
    {
        if ($tenantId !== config('pi.tenant_id')) {
            throw new \InvalidArgumentException('TENANT_NOT_AVAILABLE');
        }

        return [
            'schema' => 'PI_LARAVEL_ENTITLEMENTS_V1',
            'tenant_id' => $tenantId,
            'plan' => 'FREE',
            'modules' => ['regulatory'],
            'locked_modules' => ['evidence', 'performance', 'fiscal', 'evaluation'],
            'superior_basic' => true,
            'evidence_core' => true,
            'cross_app_edges' => [],
            'legal_harmonization' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'billing' => 'NOT_CONNECTED_QA',
            'production_admission' => 'DENIED',
        ];
    }
}
