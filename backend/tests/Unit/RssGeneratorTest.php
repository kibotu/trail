<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Services\RssGenerator;

class RssGeneratorTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'app' => [
                'base_url' => 'https://example.com/trail',
                'rss_title' => 'Trail - Link Journal',
                'rss_description' => 'Public link journal feed',
            ],
        ];
    }

    public function testGenerateEmptyFeed(): void
    {
        $generator = new RssGenerator($this->config);
        $xml = $generator->generate([]);
        
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('<rss version="2.0">', $xml);
        $this->assertStringContainsString('<channel>', $xml);
        $this->assertStringContainsString('Trail - Link Journal', $xml);
    }

    public function testGenerateFeedWithEntries(): void
    {
        $entries = [
            [
                'id' => 1,
                'url' => 'https://example.com/article',
                'message' => 'Test article',
                'user_email' => 'test@example.com',
                'user_name' => 'Test User',
                'created_at' => '2026-01-26 10:00:00',
            ],
        ];
        
        $generator = new RssGenerator($this->config);
        $xml = $generator->generate($entries);
        
        $this->assertStringContainsString('<item>', $xml);
        $this->assertStringContainsString('Test article', $xml);
        $this->assertStringContainsString('https://example.com/article', $xml);
        $this->assertStringContainsString('test@example.com', $xml);
    }

    public function testGenerateUserFeed(): void
    {
        $entries = [
            [
                'id' => 1,
                'url' => 'https://example.com/article',
                'message' => 'Test article',
                'user_email' => 'test@example.com',
                'user_name' => 'Test User',
                'created_at' => '2026-01-26 10:00:00',
            ],
        ];
        
        $generator = new RssGenerator($this->config);
        $xml = $generator->generate($entries, 123);
        
        $this->assertStringContainsString('User 123', $xml);
    }

    public function testXmlEscaping(): void
    {
        $entries = [
            [
                'id' => 1,
                'url' => 'https://example.com/article?param=value&other=test',
                'message' => 'Test & User with special chars < > "quotes"',
                'user_email' => 'test@example.com',
                'user_name' => 'Test & User',
                'created_at' => '2026-01-26 10:00:00',
            ],
        ];
        
        $generator = new RssGenerator($this->config);
        $xml = $generator->generate($entries);
        
        // Verify special characters are properly escaped in XML
        $this->assertStringContainsString('&amp;', $xml); // & is escaped
        $this->assertStringContainsString('&lt;', $xml);  // < is escaped
        $this->assertStringContainsString('&gt;', $xml);  // > is escaped
        
        // Verify no literal dangerous characters in content
        $this->assertStringNotContainsString('Test & User with', $xml); // & should be escaped
        $this->assertStringNotContainsString('chars < >', $xml); // < and > should be escaped
    }
}
