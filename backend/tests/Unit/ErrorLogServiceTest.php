<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Trail\Services\ErrorLogService;

/**
 * Test ErrorLogService security and functionality
 */
class ErrorLogServiceTest extends TestCase
{
    private PDO $db;
    private ErrorLogService $service;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create test table
        $this->db->exec("
            CREATE TABLE trail_error_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                status_code INTEGER NOT NULL,
                url TEXT NOT NULL,
                referer TEXT,
                user_agent TEXT,
                user_id INTEGER,
                ip_address TEXT,
                occurrence_count INTEGER DEFAULT 1,
                first_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->service = new ErrorLogService($this->db);
    }

    public function testLogBasicError(): void
    {
        $result = $this->service->logError(
            404,
            '/test-page',
            null,
            'Mozilla/5.0',
            null,
            '192.168.1.1'
        );
        
        $this->assertTrue($result);
        
        // Verify it was logged
        $stmt = $this->db->query("SELECT * FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals(404, $log['status_code']);
        $this->assertEquals('/test-page', $log['url']);
        $this->assertEquals(1, $log['occurrence_count']);
    }

    public function testDeduplication(): void
    {
        // Log same error twice
        $this->service->logError(404, '/test-page', null, null, null, null);
        $this->service->logError(404, '/test-page', null, null, null, null);
        
        // Should only have one record with count = 2
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM trail_error_logs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, $result['count']);
        
        $stmt = $this->db->query("SELECT occurrence_count FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(2, $log['occurrence_count']);
    }

    public function testXSSPrevention(): void
    {
        // Try to inject XSS via URL
        $maliciousUrl = '/page?q=<script>alert("XSS")</script>';
        $this->service->logError(404, $maliciousUrl, null, null, null, null);
        
        $stmt = $this->db->query("SELECT url FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Script tags should be stripped
        $this->assertStringNotContainsString('<script>', $log['url']);
        $this->assertStringNotContainsString('</script>', $log['url']);
    }

    public function testJavaScriptProtocolStripped(): void
    {
        $maliciousUrl = 'javascript:alert("XSS")';
        $this->service->logError(404, $maliciousUrl, null, null, null, null);
        
        $stmt = $this->db->query("SELECT url FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // JavaScript protocol should be stripped
        $this->assertStringNotContainsString('javascript:', strtolower($log['url']));
    }

    public function testDataProtocolStripped(): void
    {
        $maliciousUrl = 'data:text/html,<script>alert("XSS")</script>';
        $this->service->logError(404, $maliciousUrl, null, null, null, null);
        
        $stmt = $this->db->query("SELECT url FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Data protocol should be stripped
        $this->assertStringNotContainsString('data:', strtolower($log['url']));
    }

    public function testUserAgentSanitization(): void
    {
        $maliciousUA = 'Mozilla/5.0 <script>alert("XSS")</script>';
        $this->service->logError(404, '/page', null, $maliciousUA, null, null);
        
        $stmt = $this->db->query("SELECT user_agent FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Script tags should be stripped
        $this->assertStringNotContainsString('<script>', $log['user_agent']);
    }

    public function testRefererSanitization(): void
    {
        $maliciousReferer = 'http://example.com/<script>alert("XSS")</script>';
        $this->service->logError(404, '/page', $maliciousReferer, null, null, null);
        
        $stmt = $this->db->query("SELECT referer FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Script tags should be stripped
        $this->assertStringNotContainsString('<script>', $log['referer']);
    }

    public function testInvalidStatusCodeNormalization(): void
    {
        // Test invalid status codes
        $this->service->logError(999, '/page', null, null, null, null);
        
        $stmt = $this->db->query("SELECT status_code FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Should be normalized to 500
        $this->assertEquals(500, $log['status_code']);
    }

    public function testInvalidIPAddress(): void
    {
        $this->service->logError(404, '/page', null, null, null, 'not-an-ip');
        
        $stmt = $this->db->query("SELECT ip_address FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Invalid IP should be NULL
        $this->assertNull($log['ip_address']);
    }

    public function testValidIPv4(): void
    {
        $this->service->logError(404, '/page', null, null, null, '192.168.1.1');
        
        $stmt = $this->db->query("SELECT ip_address FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('192.168.1.1', $log['ip_address']);
    }

    public function testValidIPv6(): void
    {
        $this->service->logError(404, '/page', null, null, null, '2001:0db8:85a3::8a2e:0370:7334');
        
        $stmt = $this->db->query("SELECT ip_address FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('2001:0db8:85a3::8a2e:0370:7334', $log['ip_address']);
    }

    public function testURLTruncation(): void
    {
        // Create a very long URL
        $longUrl = '/' . str_repeat('a', 3000);
        $this->service->logError(404, $longUrl, null, null, null, null);
        
        $stmt = $this->db->query("SELECT url FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Should be truncated to 2048 characters
        $this->assertLessThanOrEqual(2048, strlen($log['url']));
    }

    public function testUserAgentTruncation(): void
    {
        // Create a very long user agent
        $longUA = str_repeat('Mozilla/5.0 ', 100);
        $this->service->logError(404, '/page', null, $longUA, null, null);
        
        $stmt = $this->db->query("SELECT user_agent FROM trail_error_logs");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Should be truncated to 512 characters
        $this->assertLessThanOrEqual(512, strlen($log['user_agent']));
    }

    public function testGetErrorLogs(): void
    {
        // Insert test data
        $this->service->logError(404, '/page1', null, null, null, null);
        $this->service->logError(500, '/page2', null, null, null, null);
        $this->service->logError(404, '/page3', null, null, null, null);
        
        // Get all logs
        $logs = $this->service->getErrorLogs(10, 0);
        $this->assertCount(3, $logs);
        
        // Filter by status code
        $logs404 = $this->service->getErrorLogs(10, 0, 404);
        $this->assertCount(2, $logs404);
    }

    public function testGetErrorStatistics(): void
    {
        // Insert test data
        $this->service->logError(404, '/page1', null, null, null, null);
        $this->service->logError(404, '/page1', null, null, null, null); // Duplicate
        $this->service->logError(500, '/page2', null, null, null, null);
        
        $stats = $this->service->getErrorStatistics();
        
        $this->assertCount(2, $stats); // Two different status codes
        
        // Find 404 stats
        $stats404 = array_filter($stats, fn($s) => $s['status_code'] == 404);
        $stats404 = array_values($stats404)[0];
        
        $this->assertEquals(1, $stats404['unique_errors']); // One unique URL
        $this->assertEquals(2, $stats404['total_occurrences']); // Two total occurrences
    }
}
