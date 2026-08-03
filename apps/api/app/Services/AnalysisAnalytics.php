<?php

namespace App\Services;

use App\Models\AnalysisBatch;

class AnalysisAnalytics
{
    public function build(AnalysisBatch $batch): array
    {
        $batch->loadMissing('documents.items', 'findings');
        $documents = $batch->documents->where('status', '!=', 'cancelled');
        $items = $documents->flatMap(function ($document) {
            return $document->items->each(fn ($item) => $item->setRelation('document', $document));
        });
        $findings = $batch->findings;
        $money = fn ($value) => bcadd((string) ($value ?? '0'), '0', 2);
        $sum = fn ($records, string $field) => $records->reduce(
            fn (string $carry, $record) => bcadd($carry, (string) ($record->{$field} ?? '0'), 2),
            '0.00',
        );

        $byChassis = $items->filter->chassis->groupBy('chassis')->map(function ($rows) use ($money): array {
            $inputs = $rows->filter(fn ($item) => $item->document?->direction === 'entrada')->sortBy(fn ($item) => $item->document?->issued_at);
            $outputs = $rows->filter(fn ($item) => $item->document?->direction === 'saida')->sortBy(fn ($item) => $item->document?->issued_at);
            $input = $inputs->last();
            $output = $outputs->last();
            $cost = $money($input?->product_value);
            $sale = $money($output?->product_value);
            $margin = bcsub($sale, $cost, 2);
            return [
                'chassis' => $rows->first()->chassis,
                'input_document' => $input?->document?->number,
                'output_document' => $output?->document?->number,
                'cost' => $cost,
                'sale' => $sale,
                'margin' => $margin,
                'status' => ! $input ? 'missing_input' : (! $output ? 'in_stock' : (bccomp($margin, '0', 2) < 0 ? 'negative_margin' : 'reconciled')),
            ];
        })->values();

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
            'reconciliation' => $byChassis,
            'limitations' => [
                'O DANFE é apenas uma representação visual; todos os cálculos usam os XMLs.',
                'Achados cadastrais, econômicos e paramétricos exigem validação profissional antes de retificação ou crédito.',
                'Ausência de documento de entrada pode representar lacuna na amostra, não necessariamente irregularidade.',
            ],
        ];
    }
}
