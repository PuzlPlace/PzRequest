<?php

declare(strict_types=1);

namespace Puzl\PzRequest;

use SimpleXMLElement;

/**
 * DTO imutável que representa a resposta HTTP devolvida pelo adapter.
 *
 * Propositalmente `final`: a interface pública é congelada. Qualquer mudança
 * em propriedades ou métodos implica bump de versão MAJOR.
 */
final class PzResponse
{
    public function __construct(
        public int $statusCode,
        public string $statusText,
        public string $content,
        public string $sentBody,
    ) {
    }

    /**
     * Decodifica o `content` como JSON em array associativo.
     *
     * Quando o conteúdo não é JSON válido **ou** é JSON válido mas escalar
     * (string/number/boolean), devolve um fallback `['content' => $content]`
     * para que o consumidor sempre lide com array, sem `null` nem exceção.
     *
     * @return array<int|string, mixed>
     */
    public function getArrayContent(): array
    {
        $decoded = json_decode($this->content, true);

        return is_array($decoded) ? $decoded : ['content' => $this->content];
    }

    /**
     * Decodifica o `content` como `SimpleXMLElement`.
     *
     * Detecta o encoding original e converte para UTF-8 quando necessário,
     * cobrindo APIs legadas que servem ISO-8859-1. Para conteúdo inválido,
     * devolve `false` — cabe ao consumidor checar antes de usar.
     */
    public function getSimpleXmlElement(): SimpleXMLElement|false
    {
        $encoding = mb_detect_encoding($this->content, 'UTF-8, ISO-8859-1', true);
        $xml = ($encoding && $encoding !== 'UTF-8')
            ? mb_convert_encoding($this->content, 'UTF-8', $encoding)
            : $this->content;

        return simplexml_load_string($xml);
    }
}
