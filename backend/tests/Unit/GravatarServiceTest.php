<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Services\GravatarService;

class GravatarServiceTest extends TestCase
{
    public function testGenerateHash(): void
    {
        $email = 'test@example.com';
        $hash = GravatarService::generateHash($email);
        
        $this->assertIsString($hash);
        $this->assertEquals(32, strlen($hash));
        $this->assertEquals(md5(strtolower(trim($email))), $hash);
    }

    public function testGenerateHashWithWhitespace(): void
    {
        $email = '  test@example.com  ';
        $hash = GravatarService::generateHash($email);
        
        $this->assertEquals(md5('test@example.com'), $hash);
    }

    public function testGenerateHashWithMixedCase(): void
    {
        $email = 'Test@Example.COM';
        $hash = GravatarService::generateHash($email);
        
        $this->assertEquals(md5('test@example.com'), $hash);
    }

    public function testGenerateUrl(): void
    {
        $hash = 'abc123';
        $url = GravatarService::generateUrl($hash);
        
        $this->assertStringContainsString('gravatar.com', $url);
        $this->assertStringContainsString($hash, $url);
        $this->assertStringContainsString('s=80', $url);
        $this->assertStringContainsString('d=identicon', $url);
    }

    public function testGenerateUrlWithCustomSize(): void
    {
        $hash = 'abc123';
        $url = GravatarService::generateUrl($hash, 200);
        
        $this->assertStringContainsString('s=200', $url);
    }

    public function testGenerateUrlFromEmail(): void
    {
        $email = 'test@example.com';
        $url = GravatarService::generateUrlFromEmail($email);
        
        $expectedHash = md5(strtolower(trim($email)));
        $this->assertStringContainsString($expectedHash, $url);
    }
}
