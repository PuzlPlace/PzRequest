<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Contract;

use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Contract\PzRequestInterface;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class PzRequestInterfaceTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function expectedMethodNames(): array
    {
        return [
            'endpoint',
            'addHeader',
            'addHeaders',
            'addQuery',
            'addQueries',
            'addParam',
            'addBody',
            'addMultipart',
            'addMultiparts',
            'asFormUrlEncoded',
            'withBearer',
            'withBasicAuth',
            'setConnectTimeout',
            'setReadTimeout',
            'setTimeout',
            'setTimeouts',
            'getConnectTimeout',
            'getReadTimeout',
            'getTimeout',
            'getAsFormUrlEncoded',
            'getBodyAsString',
            'execute',
            'get',
            'post',
            'put',
            'patch',
            'delete',
            'head',
            'options',
            'prepare',
        ];
    }

    public function test_interface_declares_all_expected_fluent_methods(): void
    {
        $reflection = new ReflectionClass(PzRequestInterface::class);
        $actual = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $reflection->getMethods());

        sort($actual);
        $expected = $this->expectedMethodNames();
        sort($expected);

        self::assertSame($expected, $actual);
        self::assertCount(30, $actual);
    }

    public function test_setter_methods_return_self(): void
    {
        $setters = [
            'endpoint',
            'addHeader',
            'addHeaders',
            'addQuery',
            'addQueries',
            'addParam',
            'addBody',
            'addMultipart',
            'addMultiparts',
            'asFormUrlEncoded',
            'withBearer',
            'withBasicAuth',
            'setConnectTimeout',
            'setReadTimeout',
            'setTimeout',
            'setTimeouts',
            'prepare',
        ];

        $reflection = new ReflectionClass(PzRequestInterface::class);
        foreach ($setters as $name) {
            $method = $reflection->getMethod($name);
            $type = $method->getReturnType();
            self::assertInstanceOf(ReflectionNamedType::class, $type, "Method {$name} must declare a return type.");
            self::assertSame('self', $type->getName(), "Method {$name} must return self.");
        }
    }

    public function test_http_action_methods_return_pzresponse(): void
    {
        $actions = ['execute', 'get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

        $reflection = new ReflectionClass(PzRequestInterface::class);
        foreach ($actions as $name) {
            $method = $reflection->getMethod($name);
            $type = $method->getReturnType();
            self::assertInstanceOf(ReflectionNamedType::class, $type, "Method {$name} must declare a return type.");
            self::assertSame(PzResponse::class, $type->getName(), "Method {$name} must return PzResponse.");
        }
    }

    public function test_execute_default_method_is_get(): void
    {
        $method = (new ReflectionClass(PzRequestInterface::class))->getMethod('execute');
        $params = $method->getParameters();

        self::assertCount(1, $params);
        self::assertTrue($params[0]->isDefaultValueAvailable());
        self::assertSame(PzRequestMethodEnum::GET, $params[0]->getDefaultValue());
    }
}
