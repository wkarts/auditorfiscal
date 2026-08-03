<?php

namespace App\Services;

use Illuminate\Support\Str;

class ProductReconciliation
{
    public function build(iterable $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $identity = $this->identity($item);
            if (! $identity) {
                continue;
            }
            $groups[$identity['key']][] = ['item' => $item, 'identity' => $identity];
        }

        $rows = [];
        foreach ($groups as $key => $entries) {
            $identity = $entries[0]['identity'];
            $inputs = array_values(array_filter($entries, fn (array $entry): bool => $entry['item']->document?->direction === 'entrada'));
            $outputs = array_values(array_filter($entries, fn (array $entry): bool => $entry['item']->document?->direction === 'saida'));
            $inputQuantity = $this->sumQuantity($inputs, $identity);
            $outputQuantity = $this->sumQuantity($outputs, $identity);
            $inputValue = $this->sumMoney($inputs);
            $outputValue = $this->sumMoney($outputs);
            $quantityComplete = collect([...$inputs, ...$outputs])->every(
                fn (array $entry): bool => $this->hasQuantity($entry['item'], $identity),
            );
            $unitCost = bccomp($inputQuantity, '0', 6) > 0 ? bcdiv($inputValue, $inputQuantity, 6) : null;
            $enoughStock = bccomp($outputQuantity, '0', 6) > 0 && bccomp($inputQuantity, $outputQuantity, 6) >= 0;
            $estimatedCost = $unitCost !== null && $quantityComplete && $enoughStock
                ? $this->roundMoney(bcmul($unitCost, $outputQuantity, 6))
                : null;
            $margin = $estimatedCost !== null ? bcsub($outputValue, $estimatedCost, 2) : null;

            $status = match (true) {
                $outputs === [] => 'in_stock',
                $inputs === [] => 'missing_input',
                ! $quantityComplete => 'insufficient_quantity_data',
                ! $enoughStock => 'insufficient_input_quantity',
                $identity['confidence'] === 'indicative' => 'review_identity',
                $margin !== null && bccomp($margin, '0', 2) < 0 => 'negative_margin',
                $margin !== null && bccomp($margin, '0', 2) === 0 => 'zero_margin',
                $identity['confidence'] === 'exact' => 'reconciled',
                default => 'reconciled_estimate',
            };
            $latestInput = $this->latest($inputs);
            $latestOutput = $this->latest($outputs);
            $firstItem = $entries[0]['item'];

            $rows[] = [
                'key' => $key,
                'identifier' => $identity['label'],
                'identity_type' => $identity['type'],
                'confidence' => $identity['confidence'],
                'basis' => $identity['basis'] ?? null,
                'description' => $firstItem->description ?? null,
                'ncm' => $firstItem->ncm ?? null,
                'unit' => data_get($firstItem->details, 'unit'),
                'input_quantity' => $this->decimalText($inputQuantity),
                'output_quantity' => $this->decimalText($outputQuantity),
                'input_value' => $inputValue,
                'output_value' => $outputValue,
                'estimated_unit_cost' => $unitCost,
                'estimated_cost' => $estimatedCost,
                'margin' => $margin,
                'status' => $status,
                'input_document' => $latestInput['item']->document?->number ?? null,
                'output_document' => $latestOutput['item']->document?->number ?? null,
                'chassis' => $firstItem->chassis ?? null,
            ];
        }

        $confidence = ['exact' => 0, 'high' => 1, 'indicative' => 2];
        usort($rows, fn (array $left, array $right): int =>
            [$confidence[$left['confidence']] ?? 9, $left['identifier']]
            <=> [$confidence[$right['confidence']] ?? 9, $right['identifier']]
        );

        return $rows;
    }

    private function identity(object $item): ?array
    {
        $details = is_array($item->details) ? $item->details : [];
        $configured = $details['reconciliation_identity'] ?? null;
        if (is_array($configured) && ! empty($configured['key']) && ! empty($configured['label'])) {
            return $configured;
        }
        if ($item->chassis ?? null) {
            return ['key' => 'unique:chassis:'.$this->compact($item->chassis), 'label' => 'Chassi '.$item->chassis, 'type' => 'chassis', 'confidence' => 'exact', 'basis' => 'det/infAdProd'];
        }

        $priority = ['chassis' => 0, 'imei' => 1, 'serial' => 2, 'aggregation_code' => 3];
        $identifiers = array_values(array_filter($details['identifiers'] ?? [], fn ($identifier): bool =>
            is_array($identifier) && isset($priority[$identifier['type'] ?? '']) && ! empty($identifier['value'])
        ));
        usort($identifiers, fn (array $left, array $right): int => $priority[$left['type']] <=> $priority[$right['type']]);
        if ($identifiers !== []) {
            $identifier = $identifiers[0];
            $labels = ['chassis' => 'Chassi', 'imei' => 'IMEI', 'serial' => 'Série', 'aggregation_code' => 'Agregação'];
            return ['key' => "unique:{$identifier['type']}:{$this->compact($identifier['value'])}", 'label' => "{$labels[$identifier['type']]} {$identifier['value']}", 'type' => $identifier['type'], 'confidence' => 'exact', 'basis' => $identifier['source'] ?? null];
        }

        $lot = collect($details['traceability'] ?? [])->first(fn ($entry): bool => is_array($entry) && ! empty($entry['lot']));
        if ($lot) {
            $qualifier = $this->validGtin($details['ean_taxable'] ?? $details['ean'] ?? null) ?: (($item->ncm ?? null) ?: 'SEM-CODIGO');
            return ['key' => "lot:{$qualifier}:{$this->compact($lot['lot'])}", 'label' => "Lote {$lot['lot']} · ".(($item->description ?? null) ?: $qualifier), 'type' => 'lot', 'confidence' => 'high', 'basis' => 'prod/rastro/nLote'];
        }

        $gtin = $this->validGtin($details['ean_taxable'] ?? $details['ean'] ?? null);
        if ($gtin) {
            return ['key' => "gtin:{$gtin}", 'label' => 'GTIN '.$gtin.(($item->description ?? null) ? " · {$item->description}" : ''), 'type' => 'gtin', 'confidence' => 'high', 'basis' => 'prod/cEAN|cEANTrib'];
        }

        $description = $this->normalized($item->description ?? null);
        $ncm = preg_replace('/\D/', '', (string) ($item->ncm ?? ''));
        $unit = $this->normalized($details['unit'] ?? $details['taxable_unit'] ?? null);
        if ($ncm !== '' && $description !== '') {
            return ['key' => "indicative:{$ncm}:{$description}:{$unit}", 'label' => ($item->description ?? null) ?: $ncm, 'type' => 'ncm_description', 'confidence' => 'indicative', 'basis' => 'prod/NCM+xProd+uCom'];
        }

        return null;
    }

    private function sumQuantity(array $entries, array $identity): string
    {
        return array_reduce($entries, fn (string $carry, array $entry): string =>
            bcadd($carry, $this->quantity($entry['item'], $identity), 6), '0.000000');
    }

    private function sumMoney(array $entries): string
    {
        return array_reduce($entries, fn (string $carry, array $entry): string =>
            bcadd($carry, (string) ($entry['item']->product_value ?? '0'), 2), '0.00');
    }

    private function quantity(object $item, array $identity): string
    {
        return $identity['confidence'] === 'exact' ? '1.000000' : (string) (data_get($item->details, 'quantity') ?: '0');
    }

    private function hasQuantity(object $item, array $identity): bool
    {
        return $identity['confidence'] === 'exact' || bccomp($this->quantity($item, $identity), '0', 6) > 0;
    }

    private function latest(array $entries): ?array
    {
        if ($entries === []) {
            return null;
        }
        usort($entries, fn (array $left, array $right): int =>
            ($left['item']->document?->issued_at?->getTimestamp() ?? 0) <=> ($right['item']->document?->issued_at?->getTimestamp() ?? 0)
        );
        return end($entries) ?: null;
    }

    private function roundMoney(string $value): string
    {
        return bcadd($value, str_starts_with($value, '-') ? '-0.005' : '0.005', 2);
    }

    private function decimalText(string $value): string
    {
        $text = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
        return $text === '' || $text === '-0' ? '0' : $text;
    }

    private function validGtin(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        return in_array(strlen($digits), [8, 12, 13, 14], true) ? $digits : null;
    }

    private function compact(string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', Str::upper(Str::ascii($value)));
    }

    private function normalized(?string $value): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', Str::upper(Str::ascii((string) $value))));
    }
}
