<?php

namespace Tests\Unit;

use App\Services\AnalysisResultDeduplicator;
use PHPUnit\Framework\TestCase;

class AnalysisResultDeduplicatorTest extends TestCase
{
    public function test_it_consolidates_an_exact_duplicate_and_recalculates_the_summary(): void
    {
        $document = $this->document(str_repeat('1', 44), str_repeat('a', 64), 'source-a', '100.00');
        $duplicate = $document;
        $duplicate['source_file_id'] = 'source-b';

        $normalized = (new AnalysisResultDeduplicator)->normalize([
            'documents' => [$document, $duplicate],
            'findings' => [],
            'summary' => ['document_count' => 2, 'total_value' => '200.00'],
            'reports' => [],
        ]);

        $this->assertCount(1, $normalized['result']['documents']);
        $this->assertCount(1, $normalized['duplicates']);
        $this->assertSame('exact', $normalized['duplicates'][0]['classification']);
        $this->assertSame('DOCUMENT-DUPLICATE-EXACT-001', $normalized['result']['findings'][0]['rule_code']);
        $this->assertSame(1, $normalized['result']['summary']['document_count']);
        $this->assertSame('100.00', $normalized['result']['summary']['total_value']);
        $this->assertSame(2, $normalized['result']['summary']['received_document_count']);
        $this->assertSame(1, $normalized['result']['summary']['duplicate_occurrence_count']);

        $reprocessed = (new AnalysisResultDeduplicator)->normalize($normalized['result']);
        $this->assertCount(1, $reprocessed['result']['documents']);
        $this->assertCount(1, $reprocessed['result']['findings']);
        $this->assertSame(2, $reprocessed['result']['summary']['received_document_count']);
        $this->assertSame(1, $reprocessed['result']['summary']['duplicate_occurrence_count']);
    }

    public function test_it_marks_same_access_key_with_different_content_as_critical(): void
    {
        $canonical = $this->document(str_repeat('2', 44), str_repeat('b', 64), 'source-a', '75.00');
        $duplicate = $this->document(str_repeat('2', 44), str_repeat('c', 64), 'source-b', '80.00');

        $normalized = (new AnalysisResultDeduplicator)->normalize([
            'documents' => [$canonical, $duplicate],
            'findings' => [],
        ]);

        $finding = $normalized['result']['findings'][0];
        $this->assertCount(1, $normalized['result']['documents']);
        $this->assertSame('conflicting_content', $normalized['duplicates'][0]['classification']);
        $this->assertSame('DOCUMENT-DUPLICATE-CONFLICT-001', $finding['rule_code']);
        $this->assertSame('critical', $finding['severity']);
        $this->assertSame(str_repeat('b', 64), $finding['evidence']['canonical_xml_sha256']);
        $this->assertSame(str_repeat('c', 64), $finding['evidence']['duplicate_xml_sha256']);
    }

    public function test_it_uses_the_xml_hash_when_the_access_key_is_absent(): void
    {
        $canonical = $this->document(null, str_repeat('d', 64), 'source-a', '25.00');
        $duplicate = $this->document(null, str_repeat('d', 64), 'source-b', '25.00');

        $normalized = (new AnalysisResultDeduplicator)->normalize([
            'documents' => [$canonical, $duplicate],
            'findings' => [],
        ]);

        $this->assertCount(1, $normalized['result']['documents']);
        $this->assertSame('xml_sha256', $normalized['duplicates'][0]['identity_type']);
    }

    private function document(?string $accessKey, string $hash, string $source, string $value): array
    {
        return [
            'document_ref' => $accessKey ?: $hash,
            'source_file_id' => $source,
            'access_key' => $accessKey,
            'direction' => 'entrada',
            'status' => 'authorized',
            'total_value' => $value,
            'ibs_cbs_base' => '50.00',
            'ibs_value' => '0.50',
            'cbs_value' => '4.50',
            'normalized' => ['xml_sha256' => $hash],
            'items' => [[
                'item_number' => 1,
                'classification_status' => 'MATCH_EXACT',
            ]],
        ];
    }
}
