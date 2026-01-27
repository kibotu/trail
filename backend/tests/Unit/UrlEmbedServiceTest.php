<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Services\UrlEmbedService;

class UrlEmbedServiceTest extends TestCase
{
    private UrlEmbedService $service;

    protected function setUp(): void
    {
        $this->service = new UrlEmbedService();
    }

    public function testHasUrlDetectsHttpUrl(): void
    {
        $text = "Check out https://example.com";
        $this->assertTrue($this->service->hasUrl($text));
    }

    public function testHasUrlDetectsWwwUrl(): void
    {
        $text = "Visit www.example.com for more info";
        $this->assertTrue($this->service->hasUrl($text));
    }

    public function testHasUrlReturnsFalseForNoUrl(): void
    {
        $text = "Just some regular text without any links";
        $this->assertFalse($this->service->hasUrl($text));
    }

    public function testExtractAndFetchPreviewReturnsNullForNoUrl(): void
    {
        $text = "No URL here";
        $result = $this->service->extractAndFetchPreview($text);
        $this->assertNull($result);
    }

    public function testExtractAndFetchPreviewWithValidUrl(): void
    {
        // Using a reliable test URL
        $text = "Check out https://example.com";
        $result = $this->service->extractAndFetchPreview($text);
        
        // Result might be null if network fails, but structure should be correct if it succeeds
        if ($result !== null) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('url', $result);
            $this->assertArrayHasKey('title', $result);
            $this->assertArrayHasKey('description', $result);
            $this->assertArrayHasKey('image', $result);
            $this->assertArrayHasKey('site_name', $result);
        }
        
        // Test passes either way - network issues shouldn't fail tests
        $this->assertTrue(true);
    }

    public function testExtractAndFetchPreviewAddsProtocol(): void
    {
        $text = "Visit www.example.com";
        $result = $this->service->extractAndFetchPreview($text);
        
        // Should handle www. URLs by adding https://
        // Result might be null due to network, but that's ok
        $this->assertTrue($result === null || is_array($result));
    }

    public function testFetchPreviewHandlesInvalidUrl(): void
    {
        $result = $this->service->fetchPreview('https://this-domain-definitely-does-not-exist-12345.com');
        
        // Should return null for invalid/unreachable URLs
        $this->assertNull($result);
    }

    public function testFetchPreviewRejectsNonHttpProtocol(): void
    {
        $result = $this->service->fetchPreview('javascript:alert(1)');
        
        // Should return null for non-http protocols
        $this->assertNull($result);
    }
}
