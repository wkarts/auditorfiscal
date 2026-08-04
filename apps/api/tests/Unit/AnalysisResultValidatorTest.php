<?php

namespace Tests\Unit;

use App\Services\AnalysisResultValidationException;
use App\Services\AnalysisResultValidator;
use PHPUnit\Framework\TestCase;

class AnalysisResultValidatorTest extends TestCase
{
    public function test_it_accepts_input_and_output_documents_for_the_selected_company(): void
    {
        $validator = new AnalysisResultValidator;
        $validator->validate(['documents' => [
            $this->document('output', '11111111111111', '22222222222222'),
            $this->document('input', '33333333333333', '11111111111111'),
        ]], '11.111.111/1111-11');

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_document_from_another_company(): void
    {
        $this->expectException(AnalysisResultValidationException::class);
        $this->expectExceptionMessage('não consta como emitente nem destinatário');

        (new AnalysisResultValidator)->validate([
            'documents' => [$this->document('foreign', '22222222222222', '33333333333333')],
        ], '11111111111111');
    }

    public function test_it_rejects_duplicate_item_number_before_database_persistence(): void
    {
        $document = $this->document('duplicate-item', '11111111111111', '22222222222222');
        $document['items'][] = ['item_number' => 1];

        try {
            (new AnalysisResultValidator)->validate(['documents' => [$document]], '11111111111111');
            $this->fail('A numeração duplicada deveria ser recusada.');
        } catch (AnalysisResultValidationException $exception) {
            $this->assertSame('XML_DUPLICATE_ITEM_NUMBER', $exception->failureCode);
        }
    }

    private function document(string $reference, string $issuer, string $recipient): array
    {
        return [
            'document_ref' => $reference,
            'access_key' => str_pad($reference, 44, '1'),
            'issuer_tax_id' => $issuer,
            'recipient_tax_id' => $recipient,
            'items' => [['item_number' => 1]],
        ];
    }
}
