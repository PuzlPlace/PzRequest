<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;

final class PzRequestMethodEnumTest extends TestCase
{
    public function test_has_all_seven_http_methods_with_expected_int_values(): void
    {
        $expected = [
            'GET' => 0,
            'POST' => 1,
            'PUT' => 2,
            'PATCH' => 3,
            'DELETE' => 4,
            'OPTIONS' => 5,
            'HEAD' => 6,
        ];

        $actual = [];
        foreach (PzRequestMethodEnum::cases() as $case) {
            $actual[$case->name] = $case->value;
        }

        self::assertSame($expected, $actual);
        self::assertCount(7, PzRequestMethodEnum::cases());
    }

    public function test_name_matches_http_verb_for_each_case(): void
    {
        $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        foreach (PzRequestMethodEnum::cases() as $case) {
            self::assertContains($case->name, $verbs);
        }
    }
}
