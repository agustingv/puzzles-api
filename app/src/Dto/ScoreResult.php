<?php

namespace App\Dto;

/** What became of a time sent: its place among the ten best, or null if it did not make them. */
final class ScoreResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $game,
        public readonly string $variant,
        public readonly int $seconds,
        public readonly ?int $rank,
    ) {
    }
}
