<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PlayerRegistration;
use App\Entity\Player;
use App\Security\SecretGuessLimiter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Registers a player, or renames it: the app sends its whole player whenever it changed, and may
 * repeat a request whose answer it never got. With the right secret an existing player takes the
 * name sent, and the avatar if one was sent; with another secret, the id belongs to someone else and nothing changes.
 *
 * @implements ProcessorInterface<PlayerRegistration, Player>
 */
final class PlayerRegistrationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretGuessLimiter $secretGuesses,
        private readonly RequestStack $requests,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Player
    {
        $id = Uuid::fromString($uriVariables['id']);
        $existing = $this->entityManager->find(Player::class, $id);

        if (null !== $existing) {
            if (!$existing->hasSecret($data->secret)) {
                // A wrong secret here is a guess as much as a wrong token is.
                $this->secretGuesses->countWrongSecret($this->requests->getMainRequest());
                throw new ConflictHttpException('Ese identificador ya pertenece a otro jugador.');
            }

            $existing->setName($data->name);
            // An app without avatars sends none, and leaves the one there is.
            if (null !== $data->avatar) {
                $existing->setAvatar($data->avatar);
            }
            $this->entityManager->flush();

            return $existing;
        }

        $player = new Player($id, $data->name, $data->secret);
        $player->setAvatar($data->avatar);
        $this->entityManager->persist($player);
        $this->entityManager->flush();

        return $player;
    }
}
