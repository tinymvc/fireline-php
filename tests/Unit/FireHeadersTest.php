<?php

namespace Tests\Unit;

use Spark\Fire\FireHeaders;
use Spark\Http\Response;
use Spark\Testing\{TestCase, TestResponse};

final class FireHeadersTest extends TestCase
{
    public function testMergesExistingVaryWithoutDuplicateHeaders(): void
    {
        $response = (new Response('ok'))->setHeader('vary', 'Accept-Encoding');
        FireHeaders::apply($response, true);
        FireHeaders::apply($response, true);

        (new TestResponse($response))
            ->assertHeader('Vary', 'Accept-Encoding, X-FireLine')
            ->assertHeader('X-FireLine', '1');
        $this->assertArrayNotHasKey('Vary', $response->getHeaders());
        $this->assertStringContainsString('no-store', $response->getHeaders()['Cache-Control']);
    }

    public function testPreservesWildcardVary(): void
    {
        $response = FireHeaders::apply((new Response())->setHeader('Vary', '*'), true);
        (new TestResponse($response))->assertHeader('Vary', '*');
    }

    public function testNativeResponsesVaryWithoutForcingNoCache(): void
    {
        $response = FireHeaders::apply(new Response('ok'), false);
        (new TestResponse($response))
            ->assertHeader('Vary', 'X-FireLine')
            ->assertHeaderMissing('X-FireLine')
            ->assertHeaderMissing('Cache-Control');
    }
}
