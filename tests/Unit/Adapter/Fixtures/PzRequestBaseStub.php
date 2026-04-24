<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Adapter\Fixtures;

use LogicException;
use Puzl\PzRequest\Adapter\PzRequestBase;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;

/**
 * Subclasse mínima para testar `PzRequestBase`.
 *
 * Os métodos HTTP abstratos lançam — eles não estão sob teste nesta classe;
 * essa responsabilidade é do adapter concreto (`PzRequestGuzzle`).
 */
final class PzRequestBaseStub extends PzRequestBase
{
    public function execute(PzRequestMethodEnum $httpMethod = PzRequestMethodEnum::GET): PzResponse
    {
        throw new LogicException('not under test');
    }

    public function get(): PzResponse
    {
        throw new LogicException('not under test');
    }

    public function post(): PzResponse
    {
        throw new LogicException('not under test');
    }

    public function put(): PzResponse
    {
        throw new LogicException('not under test');
    }

    public function patch(): PzResponse
    {
        throw new LogicException('not under test');
    }

    public function delete(): PzResponse
    {
        throw new LogicException('not under test');
    }

    public function head(): PzResponse
    {
        throw new LogicException('not under test');
    }

    public function options(): PzResponse
    {
        throw new LogicException('not under test');
    }
}
