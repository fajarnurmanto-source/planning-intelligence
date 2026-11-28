<?php

namespace App\Services\Pi\Regulatory;

use JsonException;
use RuntimeException;

/**
 * R74 source-only taxonomy and evidence-seed registry.
 * Taxonomy encodes Article 7 ranks; Article 8 has no invented universal rank.
 * No legal validity or current-effect assessment is performed.
 */
final class RegulatoryTaxonomyRegistry
{
    private const ARTICLE_7_RANKS = [
        'UUD' => 1, 'TAP_MPR' => 2, 'UU' => 3, 'PERPU' => 3,
        'PP' => 4, 'PERPRES' => 5, 'PERDA_PROV' => 6, 'PERDA_KABKOTA' => 7,
    ];

    private const GROUPS = ['ARTICLE_7', 'ARTICLE_8', 'SPECIAL_REGIONAL_SUBTYPE', 'OTHER_LEGAL_INSTRUMENT'];

    private const RELATIONS = [
        'CITES_AS_BASIS', 'ISSUED_UNDER_AUTHORITY', 'IMPLEMENTS_PROVISION_OF',
        'DELEGATED_BY', 'AMENDS', 'AMENDS_PROVISION_OF', 'PARTIALLY_REPEALS',
        'REPEALS', 'REPLACES',
    ];

    public function load(?string $path = null): array
    {
        $path ??= resource_path('pi/regulatory-genealogy-r74.json');
        $body = @file_get_contents($path);
        if (!is_string($body) || strlen($body) > 1024 * 1024) {
            throw new RuntimeException('R74 evidence seed unavailable or oversized.');
        }
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('R74 evidence seed JSON invalid.', 0, $error);
        }
        if (!is_array($data) || ($data['schema'] ?? null) !== 'PI_R74_REGULATORY_GENEALOGY_SEED_V1'
            || !is_array($data['types'] ?? null) || !is_array($data['topics'] ?? null)
            || !is_array($data['instruments'] ?? null) || !is_array($data['provisions'] ?? null)
            || !is_array($data['relations'] ?? null)) {
            throw new RuntimeException('R74 evidence seed schema mismatch.');
        }
        foreach (['types' => 100, 'topics' => 100, 'instruments' => 500, 'provisions' => 1000, 'relations' => 2000] as $key => $limit) {
            if (!array_is_list($data[$key]) || count($data[$key]) > $limit) {
                throw new RuntimeException('R74 registry collection is invalid: '.$key);
            }
        }

        $types = [];
        foreach ($data['types'] as $entry) {
            $code = $entry['code'] ?? null;
            $group = $entry['group'] ?? null;
            $rank = $entry['rank'] ?? null;
            if (!is_string($code) || !preg_match('/^[A-Z_]{2,50}$/D', $code)
                || isset($types[$code]) || !is_string($entry['label'] ?? null)
                || !in_array($group, self::GROUPS, true)) {
                throw new RuntimeException('R74 taxonomy kind invalid or duplicated.');
            }
            if ($group === 'ARTICLE_7' && (!array_key_exists($code, self::ARTICLE_7_RANKS)
                || $rank !== self::ARTICLE_7_RANKS[$code])) {
                throw new RuntimeException('R74 formal hierarchy rank mismatch.');
            }
            if ($group !== 'ARTICLE_7' && $rank !== null) {
                throw new RuntimeException('R74 unsupported Article 8 or special-regional rank.');
            }
            $types[$code] = $entry;
        }
        if (array_keys(array_intersect_key($types, self::ARTICLE_7_RANKS)) !== array_keys(self::ARTICLE_7_RANKS)) {
            throw new RuntimeException('R74 seven-tier statutory hierarchy incomplete.');
        }

        $topics = [];
        foreach ($data['topics'] as $entry) {
            $id = $entry['id'] ?? null;
            if (!is_string($id) || !preg_match('/^TOPIC:[A-Z_]{2,60}$/D', $id)
                || isset($topics[$id]) || !is_string($entry['label'] ?? null)
                || !is_array($entry['broader'] ?? null) || !array_is_list($entry['broader'])) {
                throw new RuntimeException('R74 topic definition invalid or duplicated.');
            }
            $topics[$id] = $entry;
        }
        foreach ($topics as $id => $entry) {
            foreach ($entry['broader'] as $parent) {
                if (!is_string($parent) || !isset($topics[$parent]) || $parent === $id) {
                    throw new RuntimeException('R74 topic parent unresolved.');
                }
            }
        }
        $this->assertAcyclicTopics($topics);

        $instruments = [];
        foreach ($data['instruments'] as $entry) {
            $id = $entry['id'] ?? null;
            if (!is_string($id) || !preg_match('/^[A-Z_]+-[1-9][0-9]*-[0-9]{4}$/D', $id)
                || isset($instruments[$id]) || !isset($types[$entry['kind'] ?? ''])
                || !is_int($entry['number'] ?? null) || !is_int($entry['year'] ?? null)
                || $id !== $entry['kind'].'-'.$entry['number'].'-'.$entry['year']
                || !is_string($entry['title'] ?? null) || !is_string($entry['jurisdiction'] ?? null)
                || !preg_match('/^ID(?:-[A-Z0-9]+)*$/D', $entry['jurisdiction'])
                || !$this->officialUrl($entry['source_url'] ?? null)
                || !is_array($entry['topics'] ?? null)) {
                throw new RuntimeException('R74 instrument identity or official source invalid.');
            }
            foreach ($entry['topics'] as $topic) {
                if (!isset($topics[$topic])) {
                    throw new RuntimeException('R74 instrument topic unresolved.');
                }
            }
            $instruments[$id] = $entry;
        }

        $provisions = [];
        foreach ($data['provisions'] as $entry) {
            $id = $entry['id'] ?? null;
            if (!is_string($id) || !preg_match('/^[A-Z0-9_-]+:PASAL-[0-9]+$/D', $id)
                || isset($provisions[$id]) || !isset($instruments[$entry['instrument_id'] ?? ''])
                || !str_starts_with($id, $entry['instrument_id'].':')
                || !is_string($entry['locator'] ?? null) || !is_array($entry['topics'] ?? null)) {
                throw new RuntimeException('R74 provision identity invalid.');
            }
            foreach ($entry['topics'] as $topic) {
                if (!isset($topics[$topic])) {
                    throw new RuntimeException('R74 provision topic unresolved.');
                }
            }
            $provisions[$id] = $entry;
        }

        foreach ($data['relations'] as $entry) {
            if (!in_array($entry['type'] ?? null, self::RELATIONS, true)
                || !isset($instruments[$entry['from'] ?? ''])
                || (!isset($instruments[$entry['to'] ?? '']) && !isset($provisions[$entry['to'] ?? '']))
                || ($entry['from'] ?? '') === ($entry['to'] ?? '')
                || !$this->officialUrl($entry['source_url'] ?? null)
                || !is_string($entry['locator'] ?? null) || trim($entry['locator']) === ''
                || !in_array($entry['status'] ?? null, [
                    'PRIMARY_TEXT_LOCATOR_NOT_HASH_PINNED',
                    'OFFICIAL_METADATA_NO_PROVISION_RECONCILIATION',
                ], true)) {
                throw new RuntimeException('R74 evidence relation invalid.');
            }
        }

        return $data;
    }

    /**
     * Issuer-qualified identity for the successor overlay. Legacy seed keys remain
     * read-only aliases; never infer an issuer from a generic instrument kind.
     */
    public function canonicalIdentity(array $instrument): string
    {
        foreach (['jurisdiction','issuer','kind','number','year'] as $field) {
            if (!array_key_exists($field,$instrument)) {
                throw new RuntimeException('PI_REG_IDENTITY_INCOMPLETE:'.$field);
            }
        }
        if (!is_string($instrument['jurisdiction']) || !preg_match('/^ID(?:-[A-Z0-9]+)*$/D',$instrument['jurisdiction'])
            || !is_string($instrument['issuer']) || !preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/D',$instrument['issuer'])
            || !is_string($instrument['kind']) || !preg_match('/^[A-Z_]{2,50}$/D',$instrument['kind'])
            || !is_string($instrument['number']) || !preg_match('/^[A-Z0-9]+(?:[.\/-][A-Z0-9]+)*$/D',$instrument['number'])
            || !is_int($instrument['year']) || $instrument['year'] < 1900 || $instrument['year'] > 2200) {
            throw new RuntimeException('PI_REG_IDENTITY_INVALID');
        }
        return implode(':',[$instrument['jurisdiction'],$instrument['issuer'],$instrument['kind'],$instrument['number'],(string)$instrument['year']]);
    }

    /** Functional classification is independent of formal legal rank. */
    public function functionalRoles(array $roles): array
    {
        $allowed=['FOUNDATIONAL','FRAMEWORK','IMPLEMENTING','TECHNICAL','PERIODIC','TRANSITIONAL','AMENDING','REPEALING','OPERATIONAL'];
        if (!array_is_list($roles) || !$roles || count($roles)>count($allowed)) throw new RuntimeException('PI_REG_ROLE_HOLD');
        foreach ($roles as $role) if (!in_array($role,$allowed,true)) throw new RuntimeException('PI_REG_ROLE_HOLD');
        return array_values(array_unique($roles));
    }

    public function officialUrl(mixed $url): bool
    {
        if (!is_string($url) || strlen($url) > 600) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && in_array(strtolower((string) ($parts['host'] ?? '')), [
                'peraturan.go.id', 'www.peraturan.go.id', 'peraturan.bpk.go.id',
            ], true)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
            && !isset($parts['fragment']);
    }

    private function assertAcyclicTopics(array $topics): void
    {
        $seen = [];
        $walk = function (string $id) use (&$walk, &$seen, $topics): void {
            if (($seen[$id] ?? 0) === 1) {
                throw new RuntimeException('R74 topic genealogy cycle detected.');
            }
            if (($seen[$id] ?? 0) === 2) {
                return;
            }
            $seen[$id] = 1;
            foreach ($topics[$id]['broader'] as $parent) {
                $walk($parent);
            }
            $seen[$id] = 2;
        };
        foreach (array_keys($topics) as $id) {
            $walk($id);
        }
    }
}
