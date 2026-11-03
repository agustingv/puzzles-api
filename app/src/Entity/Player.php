<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use App\Dto\PlayerRegistration;
use App\State\PlayerRegistrationProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A player of the app. There is no sign-up: the app makes one up on its first launch, offline if it
 * has to, and registers it here when it can. The app chooses the id and a secret, so a registration
 * can be retried safely; the secret then proves who may change the player.
 */
#[ORM\Entity]
#[ApiResource(
    requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    operations: [
        new Get(),
        // The app picks the id, so it registers and renames with a PUT to the player's own address,
        // proving with its secret that the player is its own. Repeating the request is safe.
        new Put(
            input: PlayerRegistration::class,
            read: false,
            processor: PlayerRegistrationProcessor::class,
            allowCreate: true,
        ),
        new Patch(
            denormalizationContext: ['groups' => ['player:update']],
            security: "is_granted('ROLE_PLAYER') and object == user",
        ),
    ],
    normalizationContext: ['groups' => ['player:read']],
)]
class Player implements UserInterface
{
    public const NAME_PATTERN = '/^[\p{L}\p{N}][\p{L}\p{N} _-]*$/u';
    public const NAME_MESSAGE = 'Solo letras, números, espacios, guiones y guiones bajos.';

    /**
     * The avatar the player put together in the app, as `animal:color` or `animal:color:accessory`.
     * Only its form is checked: the pieces are the app's, which can add new ones without this API.
     */
    public const AVATAR_PATTERN = '/^[a-z]{1,16}:[a-z]{1,16}(:[a-z]{1,16})?$/';
    public const AVATAR_MESSAGE = 'Un avatar es animal:color o animal:color:accesorio, en minúsculas y sin acentos.';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['player:read'])]
    private Uuid $id;

    #[ORM\Column(length: 24)]
    #[Groups(['player:read', 'player:update'])]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(min: 3, max: 24, normalizer: 'trim')]
    #[Assert\Regex(pattern: self::NAME_PATTERN, message: self::NAME_MESSAGE, normalizer: 'trim')]
    private string $name = '';

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['player:read', 'player:update'])]
    #[Assert\Regex(pattern: self::AVATAR_PATTERN, message: self::AVATAR_MESSAGE)]
    private ?string $avatar = null;

    /** Only a hash is kept: the secret itself never leaves the player's phone again. */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash = '';

    #[ORM\Column]
    #[Groups(['player:read'])]
    private \DateTimeImmutable $registeredAt;

    public function __construct(Uuid $id, string $name, #[\SensitiveParameter] string $secret)
    {
        $this->id = $id;
        $this->setName($name);
        $this->tokenHash = self::hashSecret($secret);
        $this->registeredAt = new \DateTimeImmutable();
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = $avatar;
    }

    public function hasSecret(#[\SensitiveParameter] string $secret): bool
    {
        return hash_equals($this->tokenHash, self::hashSecret($secret));
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function getRoles(): array
    {
        return ['ROLE_PLAYER'];
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->id;
    }
}
