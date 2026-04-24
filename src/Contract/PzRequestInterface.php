<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Contract;

use Puzl\PzRequest\Enum\PzRequestMethodEnum;
use Puzl\PzRequest\PzResponse;

/**
 * Contrato fluente do PzRequest.
 *
 * Todos os setters retornam `self` para encadeamento; os métodos HTTP
 * (`execute`, `get`, ...) retornam um `PzResponse`. Uma única assinatura
 * usa `mixed` (`addQuery`) — decisão deliberada para alinhamento com os
 * adapters concretos, que precisam aceitar escalares e arrays.
 *
 * Esta interface é a "linguagem" pública do pacote. Mudanças aqui implicam
 * bump de versão MAJOR.
 */
interface PzRequestInterface
{
    /** Define a URL completa (com schema) do recurso alvo. */
    public function endpoint(string $value): self;

    /** Adiciona um header. Disparar `Content-Type: application/x-www-form-urlencoded` ativa o modo form. */
    public function addHeader(string $name, string $value): self;

    /**
     * Adiciona múltiplos headers de uma vez.
     *
     * @param array<string, string> $keyValue
     */
    public function addHeaders(array $keyValue): self;

    /** Adiciona um parâmetro de query string. Aceita escalares e arrays (Guzzle serializa). */
    public function addQuery(string $name, mixed $value): self;

    /**
     * Adiciona múltiplos parâmetros de query string de uma vez.
     *
     * @param array<string, mixed> $keyValue
     */
    public function addQueries(array $keyValue): self;

    /** Adiciona um parâmetro de path/route. Reservado para uso pelo consumidor antes de `endpoint()`. */
    public function addParam(string $name, string $value): self;

    /** Define o body. Array é serializado como JSON ou form-urlencoded; string é enviada crua. */
    public function addBody(array|string $value): self;

    /** Adiciona um item multipart (upload). `filename` é opcional. */
    public function addMultipart(string $name, string $contents, ?string $filename = null): self;

    /**
     * Adiciona vários itens multipart de uma vez.
     *
     * @param array<int, array{name: string, contents: string, filename?: string}> $multiparts
     */
    public function addMultiparts(array $multiparts): self;

    /** Liga/desliga o modo `form_params`. Default `true` quando chamado sem argumento. */
    public function asFormUrlEncoded(bool $value = true): self;

    /** Adiciona header `Authorization: Bearer <token>`. */
    public function withBearer(string $token): self;

    /** Configura Basic Auth (vai como header pelo Guzzle, **nunca** em query string). */
    public function withBasicAuth(string $username, string $password): self;

    /** Timeout para estabelecer conexão TCP (segundos). Default 30. */
    public function setConnectTimeout(int $seconds): self;

    /** Timeout para leitura entre bytes (segundos). Default 30. */
    public function setReadTimeout(int $seconds): self;

    /** Timeout total da requisição (segundos). Default 30. */
    public function setTimeout(int $seconds): self;

    /** Atalho que aplica o mesmo valor aos três timeouts. */
    public function setTimeouts(int $seconds): self;

    public function getConnectTimeout(): int;

    public function getReadTimeout(): int;

    public function getTimeout(): int;

    public function getAsFormUrlEncoded(): bool;

    /** Serializa o body atual para string (JSON ou query-string), respeitando o flag form. */
    public function getBodyAsString(): string;

    /** Executa a requisição com o método HTTP indicado. Default GET. */
    public function execute(PzRequestMethodEnum $httpMethod = PzRequestMethodEnum::GET): PzResponse;

    public function get(): PzResponse;

    public function post(): PzResponse;

    public function put(): PzResponse;

    public function patch(): PzResponse;

    public function delete(): PzResponse;

    public function head(): PzResponse;

    public function options(): PzResponse;

    /** Reseta endpoint, params, queries, multiparts, body e flag form. **Não** reseta headers, auth nem timeouts. */
    public function prepare(): self;
}
