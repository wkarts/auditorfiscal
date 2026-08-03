<?php

namespace Tests\Unit;

use App\Services\CnpjLookup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CnpjLookupTest extends TestCase
{
    public static function values(): array
    {
        return [['99999999000191', true], ['99.999.999/0001-91', false], ['00000000000000', false], ['123', false], ['', false]];
    }

    #[DataProvider('values')]
    public function test_validates_normalized_cnpj(string $value, bool $expected): void
    {
        $this->assertSame($expected, CnpjLookup::isValid($value));
    }
}
