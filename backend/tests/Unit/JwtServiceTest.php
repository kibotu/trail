<?php

declare(strict_types=1);

namespace Trail\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trail\Services\JwtService;

class JwtServiceTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'jwt' => [
                'secret' => 'test_secret_key_for_testing_purposes_only',
                'expiry_hours' => 24,
            ],
        ];
    }

    public function testGenerateJwt(): void
    {
        $service = new JwtService($this->config);
        $jwt = $service->generate(1, 'test@example.com');
        
        $this->assertIsString($jwt);
        $this->assertNotEmpty($jwt);
        $this->assertGreaterThan(50, strlen($jwt));
    }

    public function testVerifyValidJwt(): void
    {
        $service = new JwtService($this->config);
        $jwt = $service->generate(1, 'test@example.com');
        
        $payload = $service->verify($jwt);
        
        $this->assertIsArray($payload);
        $this->assertEquals(1, $payload['user_id']);
        $this->assertEquals('test@example.com', $payload['email']);
        $this->assertFalse($payload['is_admin']);
    }

    public function testVerifyJwtWithAdminFlag(): void
    {
        $service = new JwtService($this->config);
        $jwt = $service->generate(1, 'admin@example.com', true);
        
        $payload = $service->verify($jwt);
        
        $this->assertTrue($payload['is_admin']);
    }

    public function testVerifyInvalidJwt(): void
    {
        $service = new JwtService($this->config);
        $payload = $service->verify('invalid.jwt.token');
        
        $this->assertNull($payload);
    }

    public function testVerifyExpiredJwt(): void
    {
        $expiredConfig = [
            'jwt' => [
                'secret' => 'test_secret_key',
                'expiry_hours' => -1, // Expired
            ],
        ];
        
        $service = new JwtService($expiredConfig);
        $jwt = $service->generate(1, 'test@example.com');
        
        // Wait a moment to ensure expiry
        sleep(1);
        
        $payload = $service->verify($jwt);
        $this->assertNull($payload);
    }

    public function testVerifyIgnoringExpiryOfExpiredJwt(): void
    {
        $expiredConfig = [
            'jwt' => [
                'secret' => 'test_secret_key',
                'expiry_hours' => -1, // Expired
            ],
        ];

        $service = new JwtService($expiredConfig);
        $jwt = $service->generate(7, 'expired@example.com', true);

        $payload = $service->verifyIgnoringExpiry($jwt);

        $this->assertIsArray($payload);
        $this->assertEquals(7, $payload['user_id']);
        $this->assertEquals('expired@example.com', $payload['email']);
        $this->assertTrue($payload['is_admin']);
    }

    public function testVerifyIgnoringExpiryOfValidJwt(): void
    {
        $service = new JwtService($this->config);
        $jwt = $service->generate(1, 'test@example.com');

        $payload = $service->verifyIgnoringExpiry($jwt);

        $this->assertIsArray($payload);
        $this->assertEquals(1, $payload['user_id']);
    }

    public function testVerifyIgnoringExpiryOfInvalidJwt(): void
    {
        $service = new JwtService($this->config);

        $this->assertNull($service->verifyIgnoringExpiry('invalid.jwt.token'));
        $this->assertNull($service->verifyIgnoringExpiry(''));
    }
}
