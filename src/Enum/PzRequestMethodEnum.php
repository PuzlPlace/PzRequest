<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Enum;

/**
 * Métodos HTTP suportados pelo PzRequest.
 *
 * Os nomes (`->name`) são exatamente os verbos HTTP em uppercase, o que
 * permite passá-los direto ao Guzzle via `Client::request($enum->name, ...)`.
 * Os valores inteiros existem apenas para estabilidade serial em logs/storage
 * — a API pública usa o caso enumerado, nunca o inteiro literal.
 */
enum PzRequestMethodEnum: int
{
    case GET = 0;
    case POST = 1;
    case PUT = 2;
    case PATCH = 3;
    case DELETE = 4;
    case OPTIONS = 5;
    case HEAD = 6;
}
