<?php

declare(strict_types=1);

namespace Puzl\PzRequest\Tests\Unit\Adapter;

use PHPUnit\Framework\TestCase;
use Puzl\PzRequest\Adapter\PzRequestBase;
use Puzl\PzRequest\Contract\PzRequestInterface;
use Puzl\PzRequest\Tests\Unit\Adapter\Fixtures\PzRequestBaseStub;
use ReflectionProperty;

final class PzRequestBaseTest extends TestCase
{
    private PzRequestBaseStub $stub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stub = new PzRequestBaseStub();
    }

    /**
     * Lê uma propriedade protegida do stub via reflexão.
     */
    private function readProtected(string $property): mixed
    {
        return (new ReflectionProperty(PzRequestBase::class, $property))->getValue($this->stub);
    }

    public function test_default_timeouts_are_thirty_seconds(): void
    {
        self::assertSame(30, $this->stub->getConnectTimeout());
        self::assertSame(30, $this->stub->getReadTimeout());
        self::assertSame(30, $this->stub->getTimeout());
    }

    public function test_endpoint_setter_returns_self_and_stores_value(): void
    {
        $returned = $this->stub->endpoint('https://api.test/x');

        self::assertSame($this->stub, $returned);
        self::assertSame('https://api.test/x', $this->readProtected('endpoint'));
    }

    public function test_add_header_returns_self_and_stores_pair(): void
    {
        $returned = $this->stub->addHeader('X-Trace', 'abc');

        self::assertSame($this->stub, $returned);
        self::assertSame(['X-Trace' => 'abc'], $this->readProtected('headers'));
    }

    public function test_add_headers_iterates_array(): void
    {
        $this->stub->addHeaders(['A' => '1', 'B' => '2']);

        self::assertSame(['A' => '1', 'B' => '2'], $this->readProtected('headers'));
    }

    public function test_add_header_content_type_form_urlencoded_activates_flag(): void
    {
        $this->stub->addHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

        self::assertTrue($this->stub->getAsFormUrlEncoded());
    }

    public function test_add_header_content_type_form_urlencoded_is_case_insensitive(): void
    {
        $this->stub->addHeader('content-type', 'APPLICATION/X-WWW-FORM-URLENCODED');

        self::assertTrue($this->stub->getAsFormUrlEncoded());
    }

    public function test_add_header_content_type_json_does_not_activate_form_flag(): void
    {
        $this->stub->addHeader('Content-Type', 'application/json');

        self::assertFalse($this->stub->getAsFormUrlEncoded());
    }

    public function test_add_query_accepts_mixed_value(): void
    {
        $this->stub->addQuery('ids', [1, 2, 3]);

        self::assertSame(['ids' => [1, 2, 3]], $this->readProtected('queries'));
    }

    public function test_add_queries_iterates_array(): void
    {
        $this->stub->addQueries(['a' => 1, 'b' => 'x']);

        self::assertSame(['a' => 1, 'b' => 'x'], $this->readProtected('queries'));
    }

    public function test_add_param_returns_self_and_stores_value(): void
    {
        $returned = $this->stub->addParam('id', '42');

        self::assertSame($this->stub, $returned);
        self::assertSame(['id' => '42'], $this->readProtected('params'));
    }

    public function test_add_body_accepts_array(): void
    {
        $this->stub->addBody(['k' => 'v']);

        self::assertSame(['k' => 'v'], $this->readProtected('body'));
    }

    public function test_add_body_accepts_string(): void
    {
        $this->stub->addBody('raw=payload');

        self::assertSame('raw=payload', $this->readProtected('body'));
    }

    public function test_add_multipart_stores_part_with_optional_filename(): void
    {
        $this->stub->addMultipart('file', 'binary', 'doc.pdf');

        self::assertSame(
            [['name' => 'file', 'contents' => 'binary', 'filename' => 'doc.pdf']],
            $this->readProtected('multiparts'),
        );
    }

    public function test_add_multipart_without_filename_omits_key(): void
    {
        $this->stub->addMultipart('field', 'value');

        $multiparts = $this->readProtected('multiparts');
        self::assertIsArray($multiparts);
        self::assertSame(['name' => 'field', 'contents' => 'value'], $multiparts[0]);
        self::assertArrayNotHasKey('filename', $multiparts[0]);
    }

    public function test_add_multiparts_iterates_array(): void
    {
        $this->stub->addMultiparts([
            ['name' => 'a', 'contents' => '1'],
            ['name' => 'b', 'contents' => '2', 'filename' => 'b.txt'],
        ]);

        self::assertSame(
            [
                ['name' => 'a', 'contents' => '1'],
                ['name' => 'b', 'contents' => '2', 'filename' => 'b.txt'],
            ],
            $this->readProtected('multiparts'),
        );
    }

    public function test_as_form_url_encoded_setter_with_default_true(): void
    {
        $this->stub->asFormUrlEncoded();

        self::assertTrue($this->stub->getAsFormUrlEncoded());
    }

    public function test_as_form_url_encoded_setter_with_explicit_false(): void
    {
        $this->stub->asFormUrlEncoded(true);
        $this->stub->asFormUrlEncoded(false);

        self::assertFalse($this->stub->getAsFormUrlEncoded());
    }

    public function test_with_bearer_sets_authorization_header(): void
    {
        $this->stub->withBearer('abc.def.ghi');

        self::assertSame(
            ['Authorization' => 'Bearer abc.def.ghi'],
            $this->readProtected('headers'),
        );
    }

    public function test_with_basic_auth_stores_credentials_array(): void
    {
        $this->stub->withBasicAuth('user', 'secret');

        self::assertSame(['user', 'secret'], $this->readProtected('basicAuth'));
    }

    public function test_with_basic_auth_does_not_add_credentials_to_queries(): void
    {
        $this->stub->withBasicAuth('user', 'secret');

        self::assertSame([], $this->readProtected('queries'));
    }

    public function test_set_timeouts_applies_same_value_to_all_three(): void
    {
        $this->stub->setTimeouts(45);

        self::assertSame(45, $this->stub->getConnectTimeout());
        self::assertSame(45, $this->stub->getReadTimeout());
        self::assertSame(45, $this->stub->getTimeout());
    }

    public function test_individual_timeout_setters_are_independent(): void
    {
        $this->stub
            ->setConnectTimeout(5)
            ->setReadTimeout(10)
            ->setTimeout(15);

        self::assertSame(5, $this->stub->getConnectTimeout());
        self::assertSame(10, $this->stub->getReadTimeout());
        self::assertSame(15, $this->stub->getTimeout());
    }

    public function test_get_body_as_string_returns_string_body_unchanged(): void
    {
        $this->stub->addBody('raw=payload');

        self::assertSame('raw=payload', $this->stub->getBodyAsString());
    }

    public function test_get_body_as_string_encodes_array_as_json_by_default(): void
    {
        $this->stub->addBody(['a' => 1]);

        self::assertSame('{"a":1}', $this->stub->getBodyAsString());
    }

    public function test_get_body_as_string_encodes_array_as_form_when_flag_active(): void
    {
        $this->stub->addBody(['a' => 1, 'b' => 'x'])->asFormUrlEncoded();

        self::assertSame('a=1&b=x', $this->stub->getBodyAsString());
    }

    public function test_prepare_resets_endpoint_params_queries_multiparts_body_and_form_flag(): void
    {
        $this->stub
            ->endpoint('https://x')
            ->addParam('id', '1')
            ->addQuery('q', 'v')
            ->addMultipart('f', 'x')
            ->addBody(['k' => 'v'])
            ->asFormUrlEncoded();

        $this->stub->prepare();

        self::assertSame('', $this->readProtected('endpoint'));
        self::assertSame([], $this->readProtected('params'));
        self::assertSame([], $this->readProtected('queries'));
        self::assertSame([], $this->readProtected('multiparts'));
        self::assertSame([], $this->readProtected('body'));
        self::assertFalse($this->stub->getAsFormUrlEncoded());
    }

    public function test_prepare_does_not_reset_timeouts_or_basic_auth_or_headers(): void
    {
        $this->stub
            ->setTimeouts(45)
            ->withBasicAuth('user', 'secret')
            ->addHeader('X-Trace', 'abc');

        $this->stub->prepare();

        self::assertSame(45, $this->stub->getConnectTimeout());
        self::assertSame(45, $this->stub->getReadTimeout());
        self::assertSame(45, $this->stub->getTimeout());
        self::assertSame(['user', 'secret'], $this->readProtected('basicAuth'));
        self::assertSame(['X-Trace' => 'abc'], $this->readProtected('headers'));
    }

    public function test_prepare_returns_self_for_chaining(): void
    {
        self::assertSame($this->stub, $this->stub->prepare());
    }

    public function test_all_setters_are_chainable(): void
    {
        $result = $this->stub
            ->endpoint('https://api.test/x')
            ->addHeader('X-A', '1')
            ->addHeaders(['X-B' => '2'])
            ->addQuery('q', 'v')
            ->addQueries(['p' => 1])
            ->addParam('id', '42')
            ->addBody(['k' => 'v'])
            ->addMultipart('file', 'data', 'doc.pdf')
            ->addMultiparts([['name' => 'g', 'contents' => 'x']])
            ->asFormUrlEncoded(false)
            ->withBearer('token')
            ->withBasicAuth('u', 'p')
            ->setConnectTimeout(1)
            ->setReadTimeout(2)
            ->setTimeout(3)
            ->setTimeouts(7)
            ->prepare();

        self::assertInstanceOf(PzRequestBase::class, $result);
    }

    public function test_base_implements_pz_request_interface(): void
    {
        self::assertInstanceOf(PzRequestInterface::class, $this->stub);
    }
}
