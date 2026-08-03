<?php

namespace App\Services;

use App\Models\AnalysisBatch;

class AnalysisAnalytics
{
    public function __construct(private readonly ProductReconciliation $productReconciliation) {}

    public function build(AnalysisBatch $batch): array
    {
        $batch->loadMissing('documents.items', 'findings');
        $documents = $batch->documents->where('status', '!=', 'cancelled');
        $items = $documents->flatMap(function ($document) {
            return $document->items->each(fn ($item) => $item->setRelation('document', $document));
        });
        $findings = $batch->findings;
        $sum = fn ($records, string $field) => $records->reduce(
            fn (string $carry, $record) => bcadd($carry, (string) ($record->{$field} ?? '0'), 2),
            '0.00',
        );

        return [
            'overview' => [
                'documents' => $documents->count(), 'items' => $items->count(), 'findings' => $findings->count(),
                'total_value' => $sum($documents, 'total_value'),
                'input_value' => $sum($documents->where('direction', 'entrada'), 'total_value'),
                'output_value' => $sum($documents->where('direction', 'saida'), 'total_value'),
                'ibs_cbs_base' => $sum($documents, 'ibs_cbs_base'),
                'ibs' => $sum($documents, 'ibs_value'), 'cbs' => $sum($documents, 'cbs_value'),
            ],
            'severity_counts' => $findings->countBy('severity'),
            'category_counts' => $findings->countBy('category'),
            'classification_counts' => $items->countBy(fn ($item) => $item->classification_status ?: 'UNCLASSIFIED'),
            'critical_findings' => $findings->whereIn('severity', ['critical', 'high'])->sortBy(fn ($finding) => ['critical' => 0, 'high' => 1][$finding->severity] ?? 2)->take(20)->values(),
            'reconciliation' => $this->productReconciliation->build($items),
            'limitations' => [
                'O DANFE é apenas uma representação visual; todos os cálculos usam os XMLs.',
                'Achados cadastrais, econômicos e paramétricos exigem validação profissional antes de retificação ou crédito.',
                'A conciliação prioriza identificadores individuais, lotes e GTIN; NCM, descrição e unidade geram somente correspondência indicativa.',
                'Margens agregadas usam custo médio documental da amostra e não substituem estoque inicial, escrituração ou CMV contábil.',
                'Ausência de documento de entrada representa lacuna na amostra, não necessariamente irregularidade.',
            ],
        ];
    }
}
