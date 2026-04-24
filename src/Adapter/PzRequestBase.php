<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Adapter;

use Puzl\PzRequest\Contract\PzRequestInterface;
use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;

/**
 * Estado fluente reutilizável por todos os adapters HTTP do PzRequest.
 *
 * `PzRequestBase` é abstrata: ela conhece apenas headers, queries, params,
 * body, multiparts, autenticação, timeouts e a flag form-urlencoded — nunca
 * Guzzle ou qualquer cliente HTTP. Os métodos HTTP (`execute`, `get`, ...)
 * são `abstract` e devem ser implementados pelo adapter concreto.
 */
abstract class PzRequestBase implements PzRequestInterface
{
    protected const DEFAULT_TIMEOUT_SECONDS = 30;

    protected string $endpoint = '';

    /** @var array<string, string> */
    protected array $params = [];

    /** @var array<string, mixed> */
    protected array $queries = [];

    /** @var array<string, string> */
    protected array $headers = [];

    /** @var array<int|string, mixed>|string */
    protected array|string $body = [];

    /** @var array<int, array{name: string, contents: string, filename?: string}> */
    protected array $multiparts = [];

    /** @var array{0: string, 1: string}|null */
    protected ?array $basicAuth = null;

    protected bool $asFormUrlEncoded = false;

    protected int $connectTimeout = self::DEFAULT_TIMEOUT_SECONDS;

    protected int $readTimeout = self::DEFAULT_TIMEOUT_SECONDS;

    protected int $timeout = self::DEFAULT_TIMEOUT_SECONDS;

    public function endpoint(string $value): self
    {
        $this->endpoint = $value;

        return $this;
    }

    /**
     * Adiciona um header. Quando o nome (case-insensitive) é `Content-Type`
     * e o valor contém `application/x-www-form-urlencoded`, ativa o flag
     * automaticamente — declarar o header equivale a chamar
     * `asFormUrlEncoded(true)`.
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        if (
            strcasecmp($name, 'Content-Type') === 0
            && stripos($value, 'application/x-www-form-urlencoded') !== false
        ) {
            $this->asFormUrlEncoded = true;
        }

        return $this;
    }

    /**
     * @param array<string, string|int|float|bool> $keyValue
     */
    public function addHeaders(array $keyValue): self
    {
        foreach ($keyValue as $name => $value) {
            $this->addHeader((string) $name, (string) $value);
        }

        return $this;
    }

    public function addQuery(string $name, mixed $value): self
    {
        $this->queries[$name] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $keyValue
     */
    public function addQueries(array $keyValue): self
    {
        foreach ($keyValue as $name => $value) {
            $this->addQuery((string) $name, $value);
        }

        return $this;
    }

    public function addParam(string $name, string $value): self
    {
        $this->params[$name] = $value;

        return $this;
    }

    public function addBody(array|string $value): self
    {
        $this->body = $value;

        return $this;
    }

    public function addMultipart(string $name, string $contents, ?string $filename = null): self
    {
        $part = ['name' => $name, 'contents' => $contents];
        if ($filename !== null) {
            $part['filename'] = $filename;
        }
        $this->multiparts[] = $part;

        return $this;
    }

    /**
     * @param array<int, array{name: string, contents: string, filename?: string}> $multiparts
     */
    public function addMultiparts(array $multiparts): self
    {
        foreach ($multiparts as $m) {
            $this->addMultipart(
                (string) $m['name'],
                (string) $m['contents'],
                isset($m['filename']) ? (string) $m['filename'] : null,
            );
        }

        return $this;
    }

    public function asFormUrlEncoded(bool $value = true): self
    {
        $this->asFormUrlEncoded = $value;

        return $this;
    }

    public function withBearer(string $token): self
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;

        return $this;
    }

    public function withBasicAuth(string $username, string $password): self
    {
        $this->basicAuth = [$username, $password];

        return $this;
    }

    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    public function setReadTimeout(int $seconds): self
    {
        $this->readTimeout = $seconds;

        return $this;
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function setTimeouts(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        $this->readTimeout = $seconds;
        $this->timeout = $seconds;

        return $this;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function getReadTimeout(): int
    {
        return $this->readTimeout;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getAsFormUrlEncoded(): bool
    {
        return $this->asFormUrlEncoded;
    }

    public function getBodyAsString(): string
    {
        if (is_string($this->body)) {
            return $this->body;
        }

        if ($this->asFormUrlEncoded) {
            return http_build_query($this->body);
        }

        $encoded = json_encode($this->body);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Reseta o estado "por chamada": endpoint, params, queries, multiparts,
     * body e flag form-urlencoded. **Não** reseta headers, basicAuth nem
     * timeouts — assim, `withBearer`/`setTimeout` configurados uma vez
     * podem ser reaproveitados em N chamadas (útil para instâncias
     * compartilhadas via DI singleton).
     */
    public function prepare(): self
    {
        $this->endpoint = '';
        $this->params = [];
        $this->queries = [];
        $this->multiparts = [];
        $this->body = [];
        $this->asFormUrlEncoded = false;

        return $this;
    }

    abstract public function execute(PzRequestMethodEnum $httpMethod = PzRequestMethodEnum::GET): PzResponse;

    abstract public function get(): PzResponse;

    abstract public function post(): PzResponse;

    abstract public function put(): PzResponse;

    abstract public function patch(): PzResponse;

    abstract public function delete(): PzResponse;

    abstract public function head(): PzResponse;

    abstract public function options(): PzResponse;
}
