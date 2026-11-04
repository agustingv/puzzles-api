<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Put;
use App\Dto\ScoreResult;
use App\Dto\ScoreSubmission;
use App\Repository\ScoreRepository;
use App\State\ScoreSubmissionProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One of the ten best times on a game and level, among all players. A time that does not make the ten
 * is never stored, and one pushed out by better times is deleted: the table holds the rankings and
 * nothing else, like the app's own records.
 *
 * The app picks the id when the game is won, so sending it again, after an answer that never came, is
 * safe: a time already on the table is not added twice.
 */
#[ORM\Entity(repositoryClass: ScoreRepository::class)]
#[ORM\Index(name: 'score_ranking', columns: ['game', 'variant', 'seconds', 'sequence'])]
#[ApiResource(
    requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    operations: [
        new Put(
            input: ScoreSubmission::class,
            output: ScoreResult::class,
            read: false,
            processor: ScoreSubmissionProcessor::class,
            allowCreate: true,
            security: "is_granted('ROLE_PLAYER')",
        ),
    ],
)]
class Score
{
    /** Games are the app's: only the form of their names is checked, as with avatars. */
    public const GAME_PATTERN = '[a-z]{1,32}';

    /** A difficulty, such as EASY, or a word length, such as 5. */
    public const VARIANT_PATTERN = '[A-Za-z0-9]{1,16}';

    public const RANKING_SIZE = 10;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $player;

    #[ORM\Column(length: 32)]
    private string $game;

    #[ORM\Column(length: 16)]
    private string $variant;

    #[ORM\Column]
    private int $seconds;

    /** When it was won, as the app says; a time sent without one gets the moment it arrived. */
    #[ORM\Column]
    private \DateTimeImmutable $playedAt;

    /**
     * The order times arrived in, in microseconds. Two equal times rank by it: the first to get there
     * stays ahead, as in the app's records.
     */
    #[ORM\Column(type: 'bigint')]
    private string $sequence;

    public function __construct(Uuid $id, Player $player, string $game, string $variant, int $seconds, \DateTimeImmutable $playedAt)
    {
        $this->id = $id;
        $this->player = $player;
        $this->game = $game;
        $this->variant = $variant;
        $this->seconds = $seconds;
        $this->playedAt = $playedAt;
        $this->sequence = (string) (int) (microtime(true) * 1_000_000);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getGame(): string
    {
        return $this->game;
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getPlayedAt(): \DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function getSequence(): int
    {
        return (int) $this->sequence;
    }
}
