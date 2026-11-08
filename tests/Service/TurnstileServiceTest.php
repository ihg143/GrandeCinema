<?php

namespace App\Tests\Service;

use App\Service\TurnstileService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TurnstileServiceTest extends TestCase
{
    public function testVerifyReturnsTrueWhenCloudflareAcceptsToken(): void
    {
        // Arrange
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')
            ->willReturn(['success' => true]);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($response);

        $service = new TurnstileService($httpClient, 'secret');

        // Act
        $result = $service->verify('token-valide');

        // Assert
        $this->assertTrue($result);
    }

    public function testVerifyReturnsFalseWhenCloudflareRejectsToken(): void
    {
        // Arrange
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')
            ->willReturn(['success' => false]);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturn($response);

        $service = new TurnstileService($httpClient, 'secret');

        // Act
        $result = $service->verify('token-invalide');

        // Assert
        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseWhenTokenIsEmpty(): void
    {
        // Arrange
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())
            ->method('request');

        $service = new TurnstileService($httpClient, 'secret');

        // Act
        $result = $service->verify('');

        // Assert
        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseWhenRequestThrowsException(): void
    {
        // Arrange
        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willThrowException(new \RuntimeException('Erreur réseau'));

        $service = new TurnstileService($httpClient, 'secret');

        // Act
        $result = $service->verify('token-test');

        // Assert
        $this->assertFalse($result);
    }
}
