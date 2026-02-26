<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Services\TwitterDateParser;

class TwitterDateParserTest extends TestCase
{
    public function testParseValidTwitterDate(): void
    {
        $twitterDate = 'Fri Nov 28 10:54:34 +0000 2025';
        $result = TwitterDateParser::parse($twitterDate);
        
        $this->assertNotNull($result);
        $this->assertEquals('2025-11-28 10:54:34', $result);
    }
    
    public function testParseWithDifferentTimezone(): void
    {
        // PST timezone (-0800)
        $twitterDate = 'Mon Jan 15 14:30:00 -0800 2024';
        $result = TwitterDateParser::parse($twitterDate);
        
        $this->assertNotNull($result);
        // Should convert to UTC (14:30 PST = 22:30 UTC)
        $this->assertEquals('2024-01-15 22:30:00', $result);
    }
    
    public function testParseWithPositiveTimezone(): void
    {
        // Tokyo timezone (+0900) — Dec 25, 2024 is a Wednesday
        $twitterDate = 'Wed Dec 25 15:00:00 +0900 2024';
        $result = TwitterDateParser::parse($twitterDate);
        
        $this->assertNotNull($result);
        // Should convert to UTC (15:00 JST = 06:00 UTC)
        $this->assertEquals('2024-12-25 06:00:00', $result);
    }
    
    public function testParseInvalidFormat(): void
    {
        $invalidDate = 'Invalid date format';
        $result = TwitterDateParser::parse($invalidDate);
        
        $this->assertNull($result);
    }
    
    public function testParseEmptyString(): void
    {
        $result = TwitterDateParser::parse('');
        
        $this->assertNull($result);
    }
    
    public function testParseMySQLFormat(): void
    {
        // MySQL format should fail (not Twitter format)
        $mysqlDate = '2025-11-28 10:54:34';
        $result = TwitterDateParser::parse($mysqlDate);
        
        $this->assertNull($result);
    }
    
    public function testIsValidWithValidDate(): void
    {
        $twitterDate = 'Fri Nov 28 10:54:34 +0000 2025';
        $result = TwitterDateParser::isValid($twitterDate);
        
        $this->assertTrue($result);
    }
    
    public function testIsValidWithInvalidDate(): void
    {
        $invalidDate = 'Not a valid date';
        $result = TwitterDateParser::isValid($invalidDate);
        
        $this->assertFalse($result);
    }
    
    public function testParsePastDate(): void
    {
        // Jan 1, 2020 is a Wednesday
        $twitterDate = 'Wed Jan 01 00:00:00 +0000 2020';
        $result = TwitterDateParser::parse($twitterDate);
        
        $this->assertNotNull($result);
        $this->assertEquals('2020-01-01 00:00:00', $result);
    }
    
    public function testParseFutureDate(): void
    {
        // Dec 31, 2030 is a Tuesday
        $twitterDate = 'Tue Dec 31 23:59:59 +0000 2030';
        $result = TwitterDateParser::parse($twitterDate);
        
        $this->assertNotNull($result);
        $this->assertEquals('2030-12-31 23:59:59', $result);
    }
    
    public function testParseLeapYearDate(): void
    {
        $twitterDate = 'Thu Feb 29 12:00:00 +0000 2024';
        $result = TwitterDateParser::parse($twitterDate);
        
        $this->assertNotNull($result);
        $this->assertEquals('2024-02-29 12:00:00', $result);
    }
    
    public function testParseWithVariousMonths(): void
    {
        // Use the correct day-of-week for the 15th of each month in 2025
        $months = [
            'Jan' => ['01', 'Wed'],
            'Feb' => ['02', 'Sat'],
            'Mar' => ['03', 'Sat'],
            'Apr' => ['04', 'Tue'],
            'May' => ['05', 'Thu'],
            'Jun' => ['06', 'Sun'],
            'Jul' => ['07', 'Tue'],
            'Aug' => ['08', 'Fri'],
            'Sep' => ['09', 'Mon'],
            'Oct' => ['10', 'Wed'],
            'Nov' => ['11', 'Sat'],
            'Dec' => ['12', 'Mon']
        ];
        
        foreach ($months as $month => [$expectedMonth, $dayOfWeek]) {
            $twitterDate = "{$dayOfWeek} {$month} 15 12:00:00 +0000 2025";
            $result = TwitterDateParser::parse($twitterDate);
            
            $this->assertNotNull($result, "Failed to parse month: {$month}");
            $this->assertStringContainsString("2025-{$expectedMonth}-15", $result);
        }
    }
}
