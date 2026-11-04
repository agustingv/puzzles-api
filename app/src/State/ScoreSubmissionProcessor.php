<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\ScoreResult;
use App\Dto\ScoreSubmission;
use App\Entity\Player;
use App\Entity\Score;
use App\Repository\ScoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Files a won game in the rankings, like the app's own records: a time that makes the ten best of its
 * game and level is stored, and the one it pushes out is deleted; any other is answered and forgotten.
 * A time sent again keeps the place it has, or, if it was pushed out meanwhile, is not stored again.
 *
 * @implements ProcessorInterface<ScoreSubmission, ScoreResult>
 */
final class ScoreSubmissionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ScoreRepository $scores,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ScoreResult
    {
        /** @var Player $player */
        $player = $this->security->getUser();
        $id = Uuid::fromString($uriVariables['id']);

        $existing = $this->entityManager->find(Score::class, $id);
        if (null !== $existing) {
            if (!$existing->getPlayer()->getId()->equals($player->getId())) {
                throw new ConflictHttpException('Ese identificador ya pertenece a otro resultado.');
            }

            return $this->result($existing, $this->scores->rankOf($existing));
        }

        $result = new ScoreResult((string) $id, $data->game, $data->variant, $data->seconds, null);
        if ($this->scores->countAtOrBelow($data->game, $data->variant, $data->seconds) >= Score::RANKING_SIZE) {
            return $result;
        }

        // A date from the future is the phone's clock being wrong: the moment it arrived is closer.
        $now = new \DateTimeImmutable();
        $playedAt = null === $data->playedAt || $data->playedAt > $now ? $now : $data->playedAt;
        // The column keeps no time zone: in the server's, the instant reads back the same.
        $playedAt = $playedAt->setTimezone($now->getTimezone());

        $score = new Score($id, $player, $data->game, $data->variant, $data->seconds, $playedAt);
        $this->entityManager->persist($score);
        $this->entityManager->flush();
        $this->scores->trim($data->game, $data->variant);

        return $this->result($score, $this->scores->rankOf($score));
    }

    private function result(Score $score, ?int $rank): ScoreResult
    {
        return new ScoreResult((string) $score->getId(), $score->getGame(), $score->getVariant(), $score->getSeconds(), $rank);
    }
}
