<?php

namespace App\Services;

class AnalysisResultValidator
{
    public function validate(array $result, string $companyTaxId): void
    {
        $companyTaxId = $this->digits($companyTaxId);
        $documentReferences = [];
        $accessKeys = [];

        foreach ($result['documents'] ?? [] as $document) {
            $reference = trim((string) ($document['document_ref'] ?? ''));
            if ($reference === '' || isset($documentReferences[$reference])) {
                throw new AnalysisResultValidationException(
                    'AUDIT_RESULT_DUPLICATE_DOCUMENT',
                    'O motor fiscal retornou documentos com identidade ausente ou repetida. A persistência foi bloqueada.',
                );
            }
            $documentReferences[$reference] = true;

            $accessKey = trim((string) ($document['access_key'] ?? ''));
            if ($accessKey !== '' && isset($accessKeys[$accessKey])) {
                throw new AnalysisResultValidationException(
                    'AUDIT_RESULT_DUPLICATE_DOCUMENT',
                    'O motor fiscal retornou chaves de acesso repetidas. A persistência foi bloqueada.',
                );
            }
            if ($accessKey !== '') {
                $accessKeys[$accessKey] = true;
            }

            $issuerTaxId = $this->digits((string) ($document['issuer_tax_id'] ?? ''));
            $recipientTaxId = $this->digits((string) ($document['recipient_tax_id'] ?? ''));
            if ($companyTaxId !== '' && ! in_array($companyTaxId, [$issuerTaxId, $recipientTaxId], true)) {
                throw new AnalysisResultValidationException(
                    'XML_COMPANY_MISMATCH',
                    'O CNPJ do cliente selecionado não consta como emitente nem destinatário de uma NF-e. Selecione o cliente correto ou remova o XML do lote.',
                );
            }

            $itemNumbers = [];
            foreach ($document['items'] ?? [] as $item) {
                $itemNumber = filter_var($item['item_number'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($itemNumber === false || isset($itemNumbers[$itemNumber])) {
                    throw new AnalysisResultValidationException(
                        'XML_DUPLICATE_ITEM_NUMBER',
                        'Uma NF-e contém atributo nItem inválido ou repetido. A persistência foi bloqueada para evitar colisão e totalização incorreta.',
                    );
                }
                $itemNumbers[$itemNumber] = true;
            }
        }
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
