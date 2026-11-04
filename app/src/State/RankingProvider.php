<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Ranking;
use App\ApiResource\RankingEntry;
use App\Repository\ScoreRepository;

/**
 * A ranking, read from the stored times. A game or level nobody has won yet has an empty one, not a
 * 404: the app can not tell an unknown game from one without times, and does not need to.
 *
 * @implements ProviderInterface<Ranking>
 */
final class RankingProvider implements ProviderInterface
{
    public function __construct(private readonly ScoreRepository $scores)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Ranking
    {
        $game = $uriVariables['game'];
        $variant = $uriVariables['variant'];

        $entries = [];
        foreach ($this->scores->ranking($game, $variant) as $index => $score) {
            $player = $score->getPlayer();
            $entries[] = new RankingEntry(
                rank: $index + 1,
                id: (string) $score->getId(),
                seconds: $score->getSeconds(),
                playedAt: $score->getPlayedAt(),
                playerId: (string) $player->getId(),
                playerName: $player->getName(),
                playerAvatar: $player->getAvatar(),
            );
        }

        return new Ranking($game, $variant, $entries);
    }
}
