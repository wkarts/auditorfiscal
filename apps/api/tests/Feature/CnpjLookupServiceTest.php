<?php

namespace Tests\Feature;

use App\Services\CnpjLookup;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CnpjLookupServiceTest extends TestCase
{
    public function test_normalizes_cnpj_and_maps_public_registry_response(): void
    {
        Http::fake(['*' => Http::response([
            'razao_social' => 'Empresa Sintética de Demonstração',
            'nome_fantasia' => 'Demonstração',
            'descricao_situacao_cadastral' => 'ATIVA',
            'uf' => 'BA',
            'municipio' => 'Salvador',
        ])]);

        $result = app(CnpjLookup::class)->find('99.999.999/0001-91');

        $this->assertSame('99999999000191', $result['tax_id']);
        $this->assertSame('Empresa Sintética de Demonstração', $result['legal_name']);
        $this->assertTrue($result['active']);
        $this->assertSame('BA', $result['address']['state']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/99999999000191'));
    }
}
