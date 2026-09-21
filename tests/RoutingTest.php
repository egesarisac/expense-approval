<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiTestCase.php';

final class RoutingTest extends ApiTestCase
{
    public function testRootReturnsSuccess(): void
    {
        self::assertSame(200, $this->request('/', [], null, 'GET')['status']);
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        self::assertSame(404, $this->request('/missing')['status']);
    }

    public function testWrongMethodReturnsAllowedMethods(): void
    {
        $response = $this->request('/login', [], null, 'GET');
        self::assertSame(405, $response['status']);
        self::assertSame('POST', $response['headers']['allow']);
    }
}
