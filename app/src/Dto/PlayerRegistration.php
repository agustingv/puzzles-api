<?php

namespace App\Dto;

use App\Entity\Player;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the app sends to register a player, at the address of the id it made up: the name, the avatar
 * and a secret. The avatar is optional, for versions of the app that have none.
 */
final class PlayerRegistration
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(min: 3, max: 24, normalizer: 'trim')]
    #[Assert\Regex(pattern: Player::NAME_PATTERN, message: Player::NAME_MESSAGE, normalizer: 'trim')]
    public string $name = '';

    #[Assert\Regex(pattern: Player::AVATAR_PATTERN, message: Player::AVATAR_MESSAGE)]
    public ?string $avatar = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 32, max: 128)]
    public string $secret = '';
}
