<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Adapter\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Tests\Unit\Adapter\Guzzle\Fixtures\PzRequestGuzzleTestable;

/**
 * Testes de regressão de segurança: garantem que credenciais
 * (Bearer / Basic Auth) nunca aparecem em query string nem no
 * snapshot de body (`PzResponse::$sentBody`) usado para auditoria.
 */
final class PzRequestGuzzleSecurityTest extends TestCase
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

    public function test_with_bearer_does_not_add_token_to_query_string(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->withBearer('secret-token')->get();

        $uri = (string) $history[0]['request']->getUri();
        self::assertStringNotContainsString('secret-token', $uri);
        self::assertSame('Bearer secret-token', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_with_basic_auth_does_not_add_credentials_to_query_string(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->withBasicAuth('user', 'secret')->get();

        $uri = (string) $history[0]['request']->getUri();
        self::assertStringNotContainsString('user', parse_url($uri, PHP_URL_QUERY) ?? '');
        self::assertStringNotContainsString('secret', parse_url($uri, PHP_URL_QUERY) ?? '');
    }

    public function test_credentials_are_not_logged_in_pz_response_sent_body(): void
    {
        $pz = $this->makePz($this->makeClient([new Response(200)]));

        $response = $pz->endpoint('https://api.test/x')
            ->withBearer('top-secret')
            ->addBody(['k' => 'v'])
            ->post();

        self::assertStringNotContainsString('top-secret', $response->sentBody);
    }

    public function test_query_string_only_contains_user_supplied_keys(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient([new Response(200)], $history));

        $pz->endpoint('https://api.test/x')->addQuery('page', 1)->withBearer('x')->get();

        $query = parse_url((string) $history[0]['request']->getUri(), PHP_URL_QUERY) ?? '';
        parse_str($query, $parsed);

        self::assertSame(['page' => '1'], $parsed);
    }

    public function test_executing_after_basic_auth_does_not_pollute_subsequent_calls_with_query_credentials(): void
    {
        $history = [];
        $pz = $this->makePz($this->makeClient(
            [new Response(200), new Response(200)],
            $history,
        ));

        $pz->endpoint('https://api.test/x')->withBasicAuth('user', 'secret')->get();
        $pz->endpoint('https://api.test/x')->get();

        foreach ($history as $entry) {
            $query = parse_url((string) $entry['request']->getUri(), PHP_URL_QUERY) ?? '';
            self::assertStringNotContainsString('user', $query);
            self::assertStringNotContainsString('secret', $query);
        }
    }
}
