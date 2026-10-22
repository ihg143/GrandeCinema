<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TurnstileService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $turnstileSecretKey
    ) {
    }

    public function verify(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'body' => [
                        'secret' => $this->turnstileSecretKey,
                        'response' => $token,
                    ],
                    'timeout' => 5,
                ]
            );

            $data = $response->toArray();

            return ($data['success'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
