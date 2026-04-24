<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Adapter\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Adapter\Guzzle\PzRequestGuzzle;
use Puzl\PzRequest\Adapter\PzRequestBase;
use Puzl\PzRequest\Contract\PzRequestInterface;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;
use Puzl\PzRequest\Tests\Unit\Adapter\Guzzle\Fixtures\PzRequestGuzzleTestable;
use ReflectionProperty;
use RuntimeException;

final class PzRequestGuzzleTest extends TestCase
{
    /**
     * @param  array<int, mixed>  $queue
     * @param  array<int, array<string, mixed>>|null  $history
     */
    private function makeClient(array $queue, ?array &$history = null): Client
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);

        if ($history !== null) {
            $stack->push(Middleware::history($history));
        }

        return new Client(['handler' => $stack]);
    }

    private function makePz(Client $client): PzRequestGuzzleTestable
    {
        $pz = new PzRequestGuzzleTestable();
        $pz->setClientOverride($client);

        return $pz;
    }

    private function readProtected(PzRequestGuzzle $pz, string $property): mixed
    {
        return (new ReflectionProperty(PzRequestBase::class, $property))->getValue($pz);
    }

    public function test_execute_returns_pzresponse_for_2xx(): void
    {
        $pz = $this->makePz($this->makeClient([new Response(200, [], 'ok')]));

        $response = $pz->endpoint('https://api.test/x')->execute();

        self::assertInstanceOf(PzResponse::class, $response);
        self::assertSame(200, $response->statusCode);
        self::assertSame('OK', $response->statusText);
        self::assertSame('ok', $response->content);
    }

    public function test_execute_returns_pzresponse_for_4xx_with_real_status(): void
    {
        $pz = $this->makePz($this->makeClient([new Response(404, [], '{"error":"not found"}')]));

        $response = $pz->endpoint('https://api.test/x')->execute();

        self::assertSame(404, $response->statusCode);
        self::assertSame('Not Found', $response->statusText);
        self::assertSame('{"error":"not found"}', $response->content);
    }

    public function test_execute_returns_pzresponse_for_5xx_with_real_status(): void
    {
        $pz = $this->makePz($this->makeClient([new Response(503, [], 'maintenance')]));

        $response = $pz->endpoint('https://api.test/x')->execute();

        self::assertSame(503, $response->statusCode);
        self::assertSame('Service Unavailable', $response->statusText);
        self::assertSame('maintenance', $response->content);
    }

    public function test_execute_maps_connect_exception_to_504_with_configured_seconds(): void
    {
        $request = new Request('GET', 'https://api.test/x');
        $pz = $this->makePz($this->makeClient([
            new ConnectException('cURL error 28: Connection timed out', $request),
        ]));

        $response = $pz->endpoint('https://api.test/x')->setConnectTimeout(7)->execute();

        self::assertSame(504, $response->statusCode);
        self::assertSame('Connection timed out after 7 seconds', $response->statusText);
        self::assertStringContainsString('Connection timed out', $response->content);
    }

    public function test_execute_maps_generic_throwable_to_500(): void
    {
        $pz = $this->makePz($this->makeClient([new RuntimeException('boom')]));

        $response = $pz->endpoint('https://api.test/x')->execute();

        self::assertSame(500, $response->statusCode);
        self::assertSame('Uncategorised exception occurred.', $response->statusText);
        self::assertSame('boom', $response->content);
    }

    public function test_execute_calls_prepare_on_success(): void
    {
        $pz = $this->makePz($this->makeClient([new Response(200, [], 'ok')]));
        $pz->endpoint('https://api.test/x')->addQuery('a', 1)->addBody(['k' => 'v'])->execute();

        self::assertSame('', $this->readProtected($pz, 'endpoint'));
        self::assertSame([], $this->readProtected($pz, 'queries'));
        self::assertSame([], $this->readProtected($pz, 'body'));
    }

    public function test_execute_calls_prepare_on_bad_response(): void
    {
        $pz = $this->makePz($this->makeClient([new Response(404, [], 'no')]));
        $pz->endpoint('https://api.test/x')->addQuery('a', 1)->execute();

        self::assertSame('', $this->readProtected($pz, 'endpoint'));
        self::assertSame([], $this->readProtected($pz, 'queries'));
    }

    public function test_execute_calls_prepare_on_connect_exception(): void
    {
        $pz = $this->makePz($this->makeClient([
            new ConnectException('timed out', new Request('GET', 'https://api.test/x')),
        ]));
        $pz->endpoint('https://api.test/x')->addQuery('a', 1)->execute();

        self::assertSame('', $this->readProtected($pz, 'endpoint'));
        self::assertSame([], $this->readProtected($pz, 'queries'));
    }

    public function test_execute_calls_prepare_on_generic_exception(): void
    {
        $pz = $this->makePz($this->makeClient([new RuntimeException('boom')]));
        $pz->endpoint('https://api.test/x')->addQuery('a', 1)->execute();

        self::assertSame('', $this->readProtected($pz, 'endpoint'));
        self::assertSame([], $this->readProtected($pz, 'queries'));
    }

    public function test_execute_uses_method_name_from_enum(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->execute(PzRequestMethodEnum::PUT);

        self::assertSame('PUT', $history[0]['request']->getMethod());
    }

    /**
     * @return array<string, array{0: string, 1: PzRequestMethodEnum}>
     */
    public static function shortcutMethodProvider(): array
    {
        return [
            'get' => ['get', PzRequestMethodEnum::GET],
            'post' => ['post', PzRequestMethodEnum::POST],
            'put' => ['put', PzRequestMethodEnum::PUT],
            'patch' => ['patch', PzRequestMethodEnum::PATCH],
            'delete' => ['delete', PzRequestMethodEnum::DELETE],
            'head' => ['head', PzRequestMethodEnum::HEAD],
            'options' => ['options', PzRequestMethodEnum::OPTIONS],
        ];
    }

    /**
     * @dataProvider shortcutMethodProvider
     */
    public function test_each_shortcut_uses_corresponding_http_method(string $shortcut, PzRequestMethodEnum $expected): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->{$shortcut}();

        self::assertSame($expected->name, $history[0]['request']->getMethod());
    }

    public function test_body_array_without_form_flag_is_sent_as_json(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->addBody(['a' => 1])->post();

        $request = $history[0]['request'];
        self::assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"a":1}', (string) $request->getBody());
    }

    public function test_body_array_with_form_flag_is_sent_as_form_params(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->addBody(['a' => 1, 'b' => 2])->asFormUrlEncoded()->post();

        $request = $history[0]['request'];
        self::assertStringContainsString('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('a=1&b=2', (string) $request->getBody());
    }

    public function test_body_string_is_sent_as_raw_body(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->addBody('raw payload')->post();

        self::assertSame('raw payload', (string) $history[0]['request']->getBody());
    }

    public function test_multipart_overrides_body(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')
            ->addMultipart('file', 'data', 'doc.txt')
            ->addBody(['x' => 1])
            ->post();

        self::assertStringContainsString('multipart/form-data', $history[0]['request']->getHeaderLine('Content-Type'));
    }

    public function test_basic_auth_is_set_via_guzzle_auth_option(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->withBasicAuth('user', 'secret')->get();

        $expected = 'Basic ' . base64_encode('user:secret');
        self::assertSame($expected, $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_bearer_token_is_set_via_authorization_header(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->withBearer('abc.def.ghi')->get();

        self::assertSame('Bearer abc.def.ghi', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_timeouts_are_passed_to_guzzle_options(): void
    {
        $pz = new PzRequestGuzzleTestable();
        $pz->setConnectTimeout(5)->setReadTimeout(10)->setTimeout(15);

        $options = $pz->exposeBuildOptions();

        self::assertSame(5, $options['connect_timeout']);
        self::assertSame(10, $options['read_timeout']);
        self::assertSame(15, $options['timeout']);
    }

    public function test_pz_response_sent_body_reflects_serialized_request_body(): void
    {
        $pz = $this->makePz($this->makeClient([new Response(200)]));

        $response = $pz->endpoint('https://api.test/x')->addBody(['k' => 'v'])->post();

        self::assertSame('{"k":"v"}', $response->sentBody);
    }

    public function test_pz_response_sent_body_reflects_form_encoded_request_body(): void
    {
        $pz = $this->makePz($this->makeClient([new Response(200)]));

        $response = $pz->endpoint('https://api.test/x')->addBody(['k' => 'v'])->asFormUrlEncoded()->post();

        self::assertSame('k=v', $response->sentBody);
    }

    public function test_pz_request_guzzle_is_pz_request_interface_implementation(): void
    {
        self::assertInstanceOf(PzRequestInterface::class, new PzRequestGuzzle());
    }

    public function test_pz_request_guzzle_extends_pz_request_base(): void
    {
        self::assertInstanceOf(PzRequestBase::class, new PzRequestGuzzle());
    }
}
