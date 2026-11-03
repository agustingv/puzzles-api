<?php

namespace App\Security;

use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/** "Authorization: Bearer <secret>" identifies the player whose secret it is. */
final class PlayerTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $player = $this->entityManager->getRepository(Player::class)
            ->findOneBy(['tokenHash' => Player::hashSecret($accessToken)]);

        if (null === $player) {
            throw new BadCredentialsException('Token no válido.');
        }

        return new UserBadge($player->getUserIdentifier(), fn () => $player);
    }
}
