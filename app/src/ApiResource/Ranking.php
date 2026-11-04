<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Entity\Score;
use App\State\RankingProvider;

/** The ten best times of all players on one game and level, fastest first. Anyone can read it. */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/rankings/{game}/{variant}',
            uriVariables: [
                'game' => new Link(fromClass: self::class, identifiers: ['game']),
                'variant' => new Link(fromClass: self::class, identifiers: ['variant']),
            ],
            requirements: ['game' => Score::GAME_PATTERN, 'variant' => Score::VARIANT_PATTERN],
            provider: RankingProvider::class,
        ),
    ],
)]
final class Ranking
{
    /** @param list<RankingEntry> $entries */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public readonly string $game,
        #[ApiProperty(identifier: true)]
        public readonly string $variant,
        public readonly array $entries,
    ) {
    }
}
