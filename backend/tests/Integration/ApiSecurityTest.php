<?php

declare(strict_types=1);

namespace Trail\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for API security
 * Tests XSS prevention, input validation, and sanitization
 */
class ApiSecurityTest extends TestCase
{
    private string $baseUrl = 'http://localhost:18000';
    private ?string $jwtToken = null;

    protected function setUp(): void
    {
        // Check if server is running
        $ch = curl_init($this->baseUrl . '/api/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->markTestSkipped('API server not running. Start with: ./run.sh');
        }
    }

    public function testHealthEndpoint(): void
    {
        $response = $this->makeRequest('GET', '/api/health');
        
        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('status', $response['data']);
        $this->assertEquals('ok', $response['data']['status']);
    }

    public function testCreateEntryBlocksScriptTags(): void
    {
        $this->skipIfNoAuth();
        
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => 'Hello <script>alert("XSS")</script> World'
        ]);
        
        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
        $this->assertStringContainsString('dangerous', strtolower($response['data']['error']));
    }

    public function testCreateEntryBlocksJavaScriptProtocol(): void
    {
        $this->skipIfNoAuth();
        
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => 'Click here: javascript:alert("XSS")'
        ]);
        
        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
    }

    public function testCreateEntryBlocksEventHandlers(): void
    {
        $this->skipIfNoAuth();
        
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => '<img src=x onerror=alert(1)>'
        ]);
        
        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
    }

    public function testCreateEntryBlocksIframeTags(): void
    {
        $this->skipIfNoAuth();
        
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => 'Test <iframe src="evil.com"></iframe> content'
        ]);
        
        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
    }

    public function testCreateEntryBlocksDataProtocol(): void
    {
        $this->skipIfNoAuth();
        
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => 'Click <a href="data:text/html,<script>alert(1)</script>">here</a>'
        ]);
        
        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
    }

    public function testCreateEntryAcceptsValidText(): void
    {
        $this->skipIfNoAuth();
        
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => 'Valid text with https://example.com and emojis 🎉'
        ]);
        
        // Should succeed or fail with auth error (not validation error)
        if ($response['status'] === 401) {
            $this->markTestSkipped('Authentication required');
        }
        
        $this->assertContains($response['status'], [201, 401]);
    }

    public function testCreateEntryRejectsEmptyText(): void
    {
        $this->skipIfNoAuth();
        
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => ''
        ]);
        
        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
        $this->assertStringContainsString('required', strtolower($response['data']['error']));
    }

    public function testCreateEntryRejectsTooLongText(): void
    {
        $this->skipIfNoAuth();
        
        $longText = str_repeat('a', 281);
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => $longText
        ]);
        
        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response['data']);
        $this->assertStringContainsString('280', $response['data']['error']);
    }

    public function testCreateEntryRequiresAuthentication(): void
    {
        $response = $this->makeRequest('POST', '/api/entries', [
            'text' => 'Test text'
        ], false);
        
        $this->assertEquals(401, $response['status']);
    }

    public function testListEntriesRequiresAuthentication(): void
    {
        $response = $this->makeRequest('GET', '/api/entries', null, false);
        
        $this->assertEquals(401, $response['status']);
    }

    public function testRateLimitingWorks(): void
    {
        // Make many requests quickly
        $responses = [];
        for ($i = 0; $i < 65; $i++) {
            $response = $this->makeRequest('GET', '/api/health');
            $responses[] = $response['status'];
        }
        
        // Should eventually get rate limited (429)
        $this->assertContains(429, $responses, 'Rate limiting should kick in after 60 requests');
    }

    public function testCorsHeadersPresent(): void
    {
        $ch = curl_init($this->baseUrl . '/api/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $this->assertStringContainsString('Access-Control-Allow-Origin', $response);
    }

    public function testSecurityHeadersPresent(): void
    {
        $ch = curl_init($this->baseUrl . '/api/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        // Check for security headers
        $this->assertStringContainsString('X-Content-Type-Options', $response);
    }

    public function testRssEndpointPubliclyAccessible(): void
    {
        $response = $this->makeRequest('GET', '/api/rss');
        
        $this->assertEquals(200, $response['status']);
        $this->assertStringContainsString('<?xml', $response['raw']);
        $this->assertStringContainsString('<rss', $response['raw']);
    }

    public function testApiDocsAccessible(): void
    {
        $response = $this->makeRequest('GET', '/api');
        
        $this->assertEquals(200, $response['status']);
        $this->assertStringContainsString('Trail API', $response['raw']);
    }

    private function makeRequest(string $method, string $path, ?array $data = null, bool $useAuth = true): array
    {
        $ch = curl_init($this->baseUrl . $path);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $headers = ['Content-Type: application/json'];
        
        if ($useAuth && $this->jwtToken) {
            $headers[] = 'Authorization: Bearer ' . $this->jwtToken;
        }
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        $body = substr($response, $headerSize);
        $decodedBody = json_decode($body, true);
        
        return [
            'status' => $httpCode,
            'data' => $decodedBody ?? [],
            'raw' => $body
        ];
    }

    private function skipIfNoAuth(): void
    {
        if (!$this->jwtToken) {
            $this->markTestSkipped('JWT token not available. Run with valid token for full tests.');
        }
    }
}
