<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Adapter\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use Puzl\PzRequest\Adapter\PzRequestBase;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;
use Throwable;

/**
 * Adapter concreto que executa requisições HTTP via `GuzzleHttp\Client`.
 *
 * Mapeamento de resposta:
 *
 * | Cenário                          | Resultado                                                        |
 * |----------------------------------|------------------------------------------------------------------|
 * | 2xx                              | `PzResponse` com status real e corpo da resposta                 |
 * | 4xx/5xx (`BadResponseException`) | `PzResponse` com status/body reais — **não** lança exceção       |
 * | `ConnectException`               | `PzResponse(504, "Connection timed out after {N} seconds", ...)` |
 * | Qualquer `\Throwable`            | `PzResponse(500, "Uncategorised exception occurred.", ...)`      |
 * | **Sempre**                       | `prepare()` é chamado em `finally` — estado nunca vaza           |
 */
class PzRequestGuzzle extends PzRequestBase
{
    /**
     * Cliente Guzzle injetado para fins de teste (`MockHandler`). Quando
     * `null`, um novo `Client` é instanciado por chamada — sem pool, custo
     * desprezível (< 1 ms) frente ao tempo de rede.
     */
    protected ?ClientInterface $clientOverride = null;

    public function execute(PzRequestMethodEnum $httpMethod = PzRequestMethodEnum::GET): PzResponse
    {
        $sentBody = $this->getBodyAsString();

        try {
            $response = $this->resolveClient()->request(
                $httpMethod->name,
                $this->endpoint,
                $this->buildOptions(),
            );

            return new PzResponse(
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                (string) $response->getBody(),
                $sentBody,
            );
        } catch (BadResponseException $e) {
            $resp = $e->getResponse();

            return new PzResponse(
                $resp->getStatusCode(),
                $resp->getReasonPhrase(),
                (string) $resp->getBody(),
                $sentBody,
            );
        } catch (ConnectException $e) {
            return new PzResponse(
                504,
                sprintf('Connection timed out after %d seconds', $this->connectTimeout),
                $e->getMessage(),
                $sentBody,
            );
        } catch (Throwable $e) {
            return new PzResponse(
                500,
                'Uncategorised exception occurred.',
                $e->getMessage(),
                $sentBody,
            );
        } finally {
            $this->prepare();
        }
    }

    public function get(): PzResponse
    {
        return $this->execute(PzRequestMethodEnum::GET);
    }

    public function post(): PzResponse
    {
        return $this->execute(PzRequestMethodEnum::POST);
    }

    public function put(): PzResponse
    {
        return $this->execute(PzRequestMethodEnum::PUT);
    }

    public function patch(): PzResponse
    {
        return $this->execute(PzRequestMethodEnum::PATCH);
    }

    public function delete(): PzResponse
    {
        return $this->execute(PzRequestMethodEnum::DELETE);
    }

    public function head(): PzResponse
    {
        return $this->execute(PzRequestMethodEnum::HEAD);
    }

    public function options(): PzResponse
    {
        return $this->execute(PzRequestMethodEnum::OPTIONS);
    }

    protected function resolveClient(): ClientInterface
    {
        return $this->clientOverride ?? new Client([
            'headers' => $this->headers,
            'query' => $this->queries,
        ]);
    }

    /**
     * Constrói o array `$options` do Guzzle a partir do estado da Base.
     *
     * Precedência do body: `multipart` > `body` (string) > `form_params`/`json`
     * (array). Multipart sempre vence, alinhado com APIs típicas de upload.
     * Quando o cliente é injetado via `clientOverride` (testes), `headers` e
     * `query` viajam aqui também — caso contrário, ficam no construtor do
     * Client criado por `resolveClient()`.
     *
     * @return array<string, mixed>
     */
    protected function buildOptions(): array
    {
        $options = [
            'connect_timeout' => $this->connectTimeout,
            'read_timeout' => $this->readTimeout,
            'timeout' => $this->timeout,
            'http_errors' => true,
        ];

        if ($this->clientOverride !== null) {
            $options['headers'] = $this->headers;
            $options['query'] = $this->queries;
        }

        if ($this->basicAuth !== null) {
            $options['auth'] = $this->basicAuth;
        }

        if (! empty($this->multiparts)) {
            $options['multipart'] = $this->multiparts;
        } elseif (is_string($this->body) && $this->body !== '') {
            $options['body'] = $this->body;
        } elseif (is_array($this->body) && $this->body !== []) {
            $options[$this->asFormUrlEncoded ? 'form_params' : 'json'] = $this->body;
        }

        return $options;
    }
}
