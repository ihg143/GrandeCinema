<?php

namespace App\Tests\Entity;

use App\Entity\ResetPasswordRequest;
use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class ResetPasswordRequestTest extends TestCase
{
    private function creerUtilisateur(): Utilisateur
    {
        $user = new Utilisateur();
        $user->setNom('Test');
        $user->setPrenom('Utilisateur');
        $user->setEmail('test@example.com');
        $user->setMdp('password');

        return $user;
    }

    public function testConstructorAssociatesUser(): void
    {
        $user = $this->creerUtilisateur();

        $request = new ResetPasswordRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedToken123'
        );

        self::assertSame($user, $request->getUser());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $request = new ResetPasswordRequest(
            $this->creerUtilisateur(),
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedToken123'
        );

        self::assertNull($request->getId());
    }

    public function testConstructorAcceptsDateTimeInterface(): void
    {
        $user = $this->creerUtilisateur();
        $expiresAt = new \DateTime('+2 hours');

        $request = new ResetPasswordRequest(
            $user,
            $expiresAt,
            'selector123',
            'hashedToken123'
        );

        self::assertSame($user, $request->getUser());
        self::assertNull($request->getId());
    }
}
