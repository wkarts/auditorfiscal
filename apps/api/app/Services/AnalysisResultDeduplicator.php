<?php

namespace App\Services;

class AnalysisResultDeduplicator
{
    public function normalize(array $result): array
    {
        $receivedDocuments = $result['documents'] ?? [];
        $documents = [];
        $identityMap = [];
        $identityOccurrences = [];
        $referenceMap = [];
        $duplicateFindings = [];
        $duplicates = [];

        foreach ($receivedDocuments as $position => $document) {
            [$identityType, $identityValue] = $this->identity($document, $position);
            $identity = $identityType.':'.$identityValue;

            if (! isset($identityMap[$identity])) {
                $identityMap[$identity] = count($documents);
                $identityOccurrences[$identity] = 1;
                $documents[] = $document;

                continue;
            }

            $canonical = $documents[$identityMap[$identity]];
            $identityOccurrences[$identity]++;
            $classification = $this->classification($canonical, $document, $identityType);
            $canonicalReference = (string) ($canonical['document_ref'] ?? '');
            $duplicateReference = (string) ($document['document_ref'] ?? '');
            if ($canonicalReference !== '' && $duplicateReference !== '') {
                $referenceMap[$duplicateReference] = $canonicalReference;
            }

            $duplicate = [
                'classification' => $classification,
                'identity_type' => $identityType,
                'identity_value' => $identityValue,
                'canonical_document_ref' => $canonicalReference ?: null,
                'duplicate_document_ref' => $duplicateReference ?: null,
                'canonical_source_file_id' => $canonical['source_file_id'] ?? null,
                'duplicate_source_file_id' => $document['source_file_id'] ?? null,
                'canonical_xml_sha256' => $this->xmlHash($canonical),
                'duplicate_xml_sha256' => $this->xmlHash($document),
                'occurrence' => $identityOccurrences[$identity],
            ];
            $duplicates[] = $duplicate;
            $duplicateFindings[] = $this->finding($duplicate, $canonicalReference);
        }

        $findings = array_map(function (array $finding) use ($referenceMap): array {
            $reference = (string) ($finding['document_ref'] ?? '');
            if ($reference !== '' && isset($referenceMap[$reference])) {
                $finding['document_ref'] = $referenceMap[$reference];
            }

            return $finding;
        }, $result['findings'] ?? []);

        $result['documents'] = $documents;
        $result['findings'] = $this->uniqueFindings([...$findings, ...$duplicateFindings]);
        $result['summary'] = $this->summary(
            $result['summary'] ?? [],
            $documents,
            $result['findings'],
            count($receivedDocuments),
            count($duplicates),
        );

        return ['result' => $result, 'duplicates' => $duplicates];
    }

    private function identity(array $document, int $position): array
    {
        $accessKey = trim((string) ($document['access_key'] ?? ''));
        if ($accessKey !== '') {
            return ['access_key', $accessKey];
        }

        $xmlHash = $this->xmlHash($document);
        if ($xmlHash !== null) {
            return ['xml_sha256', $xmlHash];
        }

        return ['position', (string) $position];
    }

    private function classification(array $canonical, array $duplicate, string $identityType): string
    {
        $canonicalHash = $this->xmlHash($canonical);
        $duplicateHash = $this->xmlHash($duplicate);
        if ($canonicalHash !== null && hash_equals($canonicalHash, (string) $duplicateHash)) {
            return 'exact';
        }
        if ($identityType === 'access_key' && $canonicalHash !== null && $duplicateHash !== null) {
            return 'conflicting_content';
        }

        return 'unverified';
    }

    private function xmlHash(array $document): ?string
    {
        $hash = strtolower(trim((string) ($document['normalized']['xml_sha256'] ?? '')));

        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? $hash : null;
    }

    private function finding(array $duplicate, string $canonicalReference): array
    {
        [$rule, $severity, $title, $description, $impact, $action] = match ($duplicate['classification']) {
            'exact' => [
                'DOCUMENT-DUPLICATE-EXACT-001',
                'medium',
                'Documento fiscal repetido no lote',
                'Uma ocorrência com a mesma identidade e o mesmo conteúdo XML foi consolidada no documento canônico.',
                'Sem a consolidação, valores, itens e tributos seriam contabilizados mais de uma vez.',
                'Confirmar a origem da repetição e manter apenas uma cópia do XML no próximo envio.',
            ],
            'conflicting_content' => [
                'DOCUMENT-DUPLICATE-CONFLICT-001',
                'critical',
                'Chave de acesso repetida com conteúdo divergente',
                'Foram recebidos XMLs com a mesma chave de acesso, mas hashes de conteúdo diferentes. A primeira ocorrência foi preservada como canônica.',
                'Pode indicar alteração indevida, arquivo corrompido ou versões incompatíveis do mesmo documento fiscal.',
                'Comparar os XMLs com a autorização oficial da SEFAZ antes de usar o documento na apuração.',
            ],
            default => [
                'DOCUMENT-DUPLICATE-UNVERIFIED-001',
                'high',
                'Identidade fiscal repetida sem hash verificável',
                'Mais de uma ocorrência compartilha a mesma identidade fiscal, mas não há hashes válidos suficientes para provar igualdade de conteúdo.',
                'Os totalizadores poderiam ser duplicados e o conteúdo divergente não pôde ser descartado.',
                'Validar os arquivos de origem e confrontar o documento com a autorização oficial da SEFAZ.',
            ],
        };

        return [
            'document_ref' => $canonicalReference ?: null,
            'item_number' => null,
            'rule_code' => $rule,
            'rule_version' => '1.0.0',
            'severity' => $severity,
            'category' => 'duplicate',
            'title' => $title,
            'description' => $description,
            'impact' => $impact,
            'recommended_action' => $action,
            'status' => 'open',
            'confidence' => $duplicate['classification'] === 'exact' ? '1.0000' : '0.9500',
            'evidence' => ['detection_layer' => 'document'] + $duplicate,
        ];
    }

    private function uniqueFindings(array $findings): array
    {
        $unique = [];
        foreach ($findings as $finding) {
            $fingerprint = hash('sha256', json_encode($finding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $unique[$fingerprint] ??= $finding;
        }

        return array_values($unique);
    }

    private function summary(
        array $summary,
        array $documents,
        array $findings,
        int $receivedDocumentCount,
        int $duplicateCount,
    ): array {
        $receivedDocumentCount = max($receivedDocumentCount, (int) ($summary['received_document_count'] ?? 0));
        $duplicateCount = max($duplicateCount, (int) ($summary['duplicate_occurrence_count'] ?? 0));
        $active = array_values(array_filter($documents, fn (array $document): bool => ($document['status'] ?? null) !== 'cancelled'));
        $inputs = array_values(array_filter($active, fn (array $document): bool => ($document['direction'] ?? null) === 'entrada'));
        $outputs = array_values(array_filter($active, fn (array $document): bool => ($document['direction'] ?? null) === 'saida'));
        $items = array_merge(...array_map(fn (array $document): array => $document['items'] ?? [], $active));
        $sum = fn (array $rows, string $field): string => array_reduce(
            $rows,
            fn (string $total, array $row): string => bcadd($total, (string) ($row[$field] ?? '0'), 2),
            '0.00',
        );

        return array_replace($summary, [
            'document_count' => count($active),
            'item_count' => count($items),
            'input_count' => count($inputs),
            'output_count' => count($outputs),
            'total_value' => $sum($active, 'total_value'),
            'input_value' => $sum($inputs, 'total_value'),
            'output_value' => $sum($outputs, 'total_value'),
            'ibs_cbs_base' => $sum($active, 'ibs_cbs_base'),
            'ibs_value' => $sum($active, 'ibs_value'),
            'cbs_value' => $sum($active, 'cbs_value'),
            'classification_ok' => count(array_filter($items, fn (array $item): bool => ($item['classification_status'] ?? null) === 'MATCH_EXACT')),
            'classification_divergent' => count(array_filter($items, fn (array $item): bool => ($item['classification_status'] ?? null) !== 'MATCH_EXACT')),
            'finding_count' => count($findings),
            'severity_counts' => array_combine(
                ['critical', 'high', 'medium', 'low'],
                array_map(fn (string $severity): int => count(array_filter($findings, fn (array $finding): bool => ($finding['severity'] ?? null) === $severity)), ['critical', 'high', 'medium', 'low']),
            ),
            'received_document_count' => $receivedDocumentCount,
            'duplicate_occurrence_count' => $duplicateCount,
        ]);
    }
}
