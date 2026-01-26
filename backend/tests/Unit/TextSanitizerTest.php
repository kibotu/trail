<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Services\TextSanitizer;

class TextSanitizerTest extends TestCase
{
    public function testSanitizeRemovesScriptTags(): void
    {
        $input = 'Hello <script>alert("xss")</script> World';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
    }

    public function testSanitizeRemovesJavaScriptProtocol(): void
    {
        $input = 'Click here: javascript:alert("xss")';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringContainsString('Click here', $result);
    }

    public function testSanitizePreservesUrls(): void
    {
        $input = 'Check out https://example.com and http://test.org';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertStringContainsString('https://example.com', $result);
        $this->assertStringContainsString('http://test.org', $result);
    }

    public function testSanitizePreservesEmojis(): void
    {
        $input = 'Hello 👋 World 🌍 with emojis 😊🎉';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertStringContainsString('👋', $result);
        $this->assertStringContainsString('🌍', $result);
        $this->assertStringContainsString('😊', $result);
        $this->assertStringContainsString('🎉', $result);
    }

    public function testSanitizeRemovesEventHandlers(): void
    {
        $input = 'Click <img src=x onerror=alert(1)>';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testSanitizeRemovesIframeTags(): void
    {
        $input = 'Test <iframe src="evil.com"></iframe> content';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('<iframe>', $result);
        $this->assertStringNotContainsString('evil.com', $result);
    }

    public function testSanitizeNormalizesWhitespace(): void
    {
        $input = 'Hello    World   with   spaces';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertEquals('Hello World with spaces', $result);
    }

    public function testSanitizeTrimsWhitespace(): void
    {
        $input = '   Hello World   ';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertEquals('Hello World', $result);
    }

    public function testIsSafeDetectsScriptTags(): void
    {
        $input = 'Hello <script>alert("xss")</script> World';
        
        $this->assertFalse(TextSanitizer::isSafe($input));
    }

    public function testIsSafeDetectsJavaScriptProtocol(): void
    {
        $input = 'javascript:alert("xss")';
        
        $this->assertFalse(TextSanitizer::isSafe($input));
    }

    public function testIsSafeDetectsEventHandlers(): void
    {
        $input = '<img src=x onerror=alert(1)>';
        
        $this->assertFalse(TextSanitizer::isSafe($input));
    }

    public function testIsSafeAllowsSafeContent(): void
    {
        $input = 'Hello World with https://example.com and emojis 👋';
        
        $this->assertTrue(TextSanitizer::isSafe($input));
    }

    public function testExtractUrlsFindsHttpsUrls(): void
    {
        $input = 'Check out https://example.com and https://test.org';
        $urls = TextSanitizer::extractUrls($input);
        
        $this->assertCount(2, $urls);
        $this->assertContains('https://example.com', $urls);
        $this->assertContains('https://test.org', $urls);
    }

    public function testExtractUrlsFindsHttpUrls(): void
    {
        $input = 'Visit http://example.com';
        $urls = TextSanitizer::extractUrls($input);
        
        $this->assertCount(1, $urls);
        $this->assertContains('http://example.com', $urls);
    }

    public function testExtractUrlsFindsWwwUrls(): void
    {
        $input = 'Go to www.example.com';
        $urls = TextSanitizer::extractUrls($input);
        
        $this->assertCount(1, $urls);
        $this->assertContains('www.example.com', $urls);
    }

    public function testExtractUrlsReturnsEmptyArrayWhenNoUrls(): void
    {
        $input = 'Just some text without URLs';
        $urls = TextSanitizer::extractUrls($input);
        
        $this->assertEmpty($urls);
    }

    public function testIsValidUtf8AcceptsValidUtf8(): void
    {
        $input = 'Hello World 👋 with emojis';
        
        $this->assertTrue(TextSanitizer::isValidUtf8($input));
    }

    public function testIsValidUtf8RejectsInvalidUtf8(): void
    {
        $input = "Invalid UTF-8: \x80\x81";
        
        $this->assertFalse(TextSanitizer::isValidUtf8($input));
    }

    public function testSanitizeComplexExample(): void
    {
        $input = 'Check out https://example.com 🎉 <script>alert("xss")</script> and enjoy!';
        $result = TextSanitizer::sanitize($input);
        
        // Should preserve URL and emoji
        $this->assertStringContainsString('https://example.com', $result);
        $this->assertStringContainsString('🎉', $result);
        
        // Should remove script
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
        
        // Should preserve safe text
        $this->assertStringContainsString('Check out', $result);
        $this->assertStringContainsString('enjoy', $result);
    }

    public function testSanitizeDataProtocol(): void
    {
        $input = 'Click data:text/html,<script>alert(1)</script>';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('data:', $result);
    }

    public function testSanitizeVbscriptProtocol(): void
    {
        $input = 'Click vbscript:msgbox(1)';
        $result = TextSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('vbscript:', $result);
    }

    public function testSanitizeHtmlEntities(): void
    {
        $input = '&lt;script&gt;alert("xss")&lt;/script&gt;';
        $result = TextSanitizer::sanitize($input);
        
        // Should decode and then remove the script
        $this->assertStringNotContainsString('script', $result);
        $this->assertStringNotContainsString('alert', $result);
    }
}
