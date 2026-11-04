<?php

namespace App\ApiResource;

/**
 * One place in a ranking: the time, when it was won, and who won it, with the name and avatar the
 * player has now. [$id] is the time's own, the one the app made up, so the app can find its own times.
 */
final class RankingEntry
{
    public function __construct(
        public readonly int $rank,
        public readonly string $id,
        public readonly int $seconds,
        public readonly \DateTimeImmutable $playedAt,
        public readonly string $playerId,
        public readonly string $playerName,
        public readonly ?string $playerAvatar,
    ) {
    }
}
