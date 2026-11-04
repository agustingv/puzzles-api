<?php

namespace App\Dto;

use App\Entity\Score;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A won game, as the app sends it to the address of the id it made up for it. The player is the one
 * whose secret comes in the Authorization header.
 */
final class ScoreSubmission
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^'.Score::GAME_PATTERN.'$/', message: 'Un juego es un nombre en minúsculas, sin acentos.')]
    public string $game = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^'.Score::VARIANT_PATTERN.'$/', message: 'Una variante son letras o números.')]
    public string $variant = '';

    /** Under a day: the app counts only the time spent playing, and nobody plays one game that long. */
    #[Assert\Range(min: 1, max: 86399)]
    public int $seconds = 0;

    /** Optional: times kept from before the app sent them have no date. */
    public ?\DateTimeImmutable $playedAt = null;
}
