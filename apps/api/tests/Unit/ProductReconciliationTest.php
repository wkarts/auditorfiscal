<?php

namespace Tests\Unit;

use App\Services\ProductReconciliation;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ProductReconciliationTest extends TestCase
{
    public function test_it_reconciles_generic_products_by_gtin_and_quantity(): void
    {
        $service = new ProductReconciliation;
        $items = [
            $this->item('entrada', '100.00', '10', '7891234567895', '2026-01-01'),
            $this->item('saida', '60.00', '4', '7891234567895', '2026-01-02'),
        ];

        $rows = $service->build($items);

        $this->assertCount(1, $rows);
        $this->assertSame('gtin', $rows[0]['identity_type']);
        $this->assertSame('10', $rows[0]['input_quantity']);
        $this->assertSame('4', $rows[0]['output_quantity']);
        $this->assertSame('40.00', $rows[0]['estimated_cost']);
        $this->assertSame('20.00', $rows[0]['margin']);
        $this->assertSame('reconciled_estimate', $rows[0]['status']);
    }

    public function test_it_does_not_treat_ncm_and_description_as_exact_identity(): void
    {
        $service = new ProductReconciliation;
        $items = [
            $this->item('entrada', '100.00', '1', null, '2026-01-01'),
            $this->item('saida', '90.00', '1', null, '2026-01-02'),
        ];

        $rows = $service->build($items);

        $this->assertSame('indicative', $rows[0]['confidence']);
        $this->assertSame('review_identity', $rows[0]['status']);
    }

    private function item(string $direction, string $value, string $quantity, ?string $gtin, string $date): object
    {
        return (object) [
            'description' => 'CAMISETA',
            'ncm' => '61091000',
            'chassis' => null,
            'product_value' => $value,
            'details' => ['quantity' => $quantity, 'unit' => 'UN', 'ean' => $gtin],
            'document' => (object) [
                'direction' => $direction,
                'number' => $direction === 'entrada' ? '10' : '11',
                'issued_at' => CarbonImmutable::parse($date),
            ],
        ];
    }
}
