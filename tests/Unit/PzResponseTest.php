<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\PzResponse;
use SimpleXMLElement;

final class PzResponseTest extends TestCase
{
    public function test_constructor_assigns_all_four_properties(): void
    {
        $response = new PzResponse(200, 'OK', 'body-content', 'sent-payload');

        self::assertSame(200, $response->statusCode);
        self::assertSame('OK', $response->statusText);
        self::assertSame('body-content', $response->content);
        self::assertSame('sent-payload', $response->sentBody);
    }

    public function test_class_is_final_to_preserve_immutable_public_signature(): void
    {
        self::assertTrue((new \ReflectionClass(PzResponse::class))->isFinal());
    }

    public function test_get_array_content_decodes_valid_json_object(): void
    {
        $response = new PzResponse(200, 'OK', '{"a":1,"b":[2,3]}', '');

        self::assertSame(['a' => 1, 'b' => [2, 3]], $response->getArrayContent());
    }

    public function test_get_array_content_returns_fallback_for_invalid_json(): void
    {
        $response = new PzResponse(500, 'Internal Server Error', '<html>error</html>', '');

        self::assertSame(['content' => '<html>error</html>'], $response->getArrayContent());
    }

    public function test_get_array_content_returns_fallback_for_json_scalar(): void
    {
        $response = new PzResponse(200, 'OK', '"only-string"', '');

        self::assertSame(['content' => '"only-string"'], $response->getArrayContent());
    }

    public function test_get_simple_xml_element_parses_utf8_content(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><root><a>ok</a></root>';
        $response = new PzResponse(200, 'OK', $xml, '');

        $parsed = $response->getSimpleXmlElement();

        self::assertInstanceOf(SimpleXMLElement::class, $parsed);
        self::assertSame('ok', (string) $parsed->a);
    }

    public function test_get_simple_xml_element_normalizes_iso_8859_1_to_utf8(): void
    {
        // Construímos bytes ISO-8859-1 puros: byte 0xE7 ("ç") seguido de uma letra ASCII.
        // Em UTF-8 isolado, 0xE7 inicia uma sequência multi-byte de 3 bytes — sem
        // continuações válidas, mb_detect_encoding o classifica como ISO-8859-1.
        $latin1Body = "<root><a>a\xE7b</a></root>";

        $response = new PzResponse(200, 'OK', $latin1Body, '');

        $parsed = $response->getSimpleXmlElement();

        self::assertInstanceOf(SimpleXMLElement::class, $parsed);
        self::assertSame('açb', (string) $parsed->a);
    }

    public function test_get_simple_xml_element_returns_false_for_invalid_xml(): void
    {
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $response = new PzResponse(200, 'OK', 'not xml', '');
            $parsed = $response->getSimpleXmlElement();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        self::assertFalse($parsed);
    }
}
