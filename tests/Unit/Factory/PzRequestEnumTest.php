<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Factory\PzRequestEnum;

final class PzRequestEnumTest extends TestCase
{
    public function test_has_three_adapter_cases_with_expected_int_values(): void
    {
        $expected = [
            'GUZZLE' => 0,
            'SYMFONY_HTTP_CLIENT' => 1,
            'OTHERS' => 2,
        ];

        $actual = [];
        foreach (PzRequestEnum::cases() as $case) {
            $actual[$case->name] = $case->value;
        }

        self::assertSame($expected, $actual);
        self::assertCount(3, PzRequestEnum::cases());
    }

    public function test_try_from_name_returns_matching_case(): void
    {
        self::assertSame(PzRequestEnum::GUZZLE, PzRequestEnum::tryFromName('GUZZLE'));
        self::assertSame(PzRequestEnum::SYMFONY_HTTP_CLIENT, PzRequestEnum::tryFromName('SYMFONY_HTTP_CLIENT'));
        self::assertSame(PzRequestEnum::OTHERS, PzRequestEnum::tryFromName('OTHERS'));
    }

    public function test_try_from_name_returns_null_for_unknown_name(): void
    {
        self::assertNull(PzRequestEnum::tryFromName('NONEXISTENT'));
    }

    public function test_try_from_name_is_case_sensitive(): void
    {
        self::assertNull(PzRequestEnum::tryFromName('guzzle'));
        self::assertNull(PzRequestEnum::tryFromName('Guzzle'));
    }
}
