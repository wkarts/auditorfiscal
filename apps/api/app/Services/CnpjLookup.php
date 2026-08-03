<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CnpjLookup
{
    public function find(string $value): array
    {
        $cnpj = preg_replace('/\D/', '', $value);
        if (! self::isValid($cnpj)) {
            throw ValidationException::withMessages(['cnpj' => 'CNPJ inválido.']);
        }

        return Cache::remember("cnpj-lookup:$cnpj", now()->addDay(), function () use ($cnpj): array {
            $baseUrl = rtrim((string) config('services.cnpj_lookup.url'), '/');
            $response = Http::acceptJson()
                ->timeout((int) config('services.cnpj_lookup.timeout', 8))
                ->retry(2, 250, throw: false)
                ->get("$baseUrl/$cnpj");

            if (! $response->successful()) {
                abort(503, 'A consulta pública de CNPJ está indisponível. Cadastre os dados manualmente e tente novamente mais tarde.');
            }

            $payload = $response->json();
            if (! is_array($payload) || empty($payload['razao_social'])) {
                abort(503, 'A consulta pública de CNPJ retornou uma resposta inválida. Cadastre os dados manualmente.');
            }
            return [
                'tax_id' => $cnpj,
                'legal_name' => $payload['razao_social'] ?? '',
                'trade_name' => $payload['nome_fantasia'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['ddd_telefone_1'] ?? null,
                'state_registration' => $payload['inscricoes_estaduais'][0]['inscricao_estadual'] ?? null,
                'active' => mb_strtoupper((string) ($payload['descricao_situacao_cadastral'] ?? '')) === 'ATIVA',
                'address' => array_filter([
                    'street' => $payload['logradouro'] ?? null,
                    'number' => $payload['numero'] ?? null,
                    'complement' => $payload['complemento'] ?? null,
                    'district' => $payload['bairro'] ?? null,
                    'city' => $payload['municipio'] ?? null,
                    'state' => $payload['uf'] ?? null,
                    'postal_code' => $payload['cep'] ?? null,
                ]),
                'source' => 'BrasilAPI',
                'consulted_at' => now()->toIso8601String(),
            ];
        });
    }

    public static function isValid(?string $cnpj): bool
    {
        if (! is_string($cnpj) || ! preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        for ($size = 12; $size <= 13; $size++) {
            $sum = 0;
            $weight = $size - 7;
            for ($index = 0; $index < $size; $index++) {
                $sum += (int) $cnpj[$index] * $weight--;
                if ($weight < 2) $weight = 9;
            }
            $digit = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
            if ((int) $cnpj[$size] !== $digit) return false;
        }
        return true;
    }
}
