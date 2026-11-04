<?php

namespace App\Tests\Api;

use ApiPlatform\Test\ApiTestCase;
use App\Entity\Score;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Uid\Uuid;

final class RankingTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const SECRET = 'un-secreto-de-prueba-largo-de-al-menos-32-caracteres';
    private const OTHER_SECRET = 'otro-secreto-de-prueba-largo-de-al-menos-32-caracteres';

    protected function setUp(): void
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    private function register(string $secret = self::SECRET, string $name = 'Zorro Astuto 42', string $avatar = 'zorro:menta'): string
    {
        $id = (string) Uuid::v4();
        static::createClient()->request('PUT', "/api/v1/players/$id", [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['name' => $name, 'secret' => $secret, 'avatar' => $avatar],
        ]);

        return $id;
    }

    /** @return array{int, array<string, mixed>} */
    private function submit(int $seconds, ?string $id = null, ?string $secret = self::SECRET, array $extra = [], string $game = 'sudoku', string $variant = 'EASY'): array
    {
        $headers = ['Accept' => 'application/json'];
        if (null !== $secret) {
            $headers['Authorization'] = "Bearer $secret";
        }
        $id ??= (string) Uuid::v4();
        $response = static::createClient()->request('PUT', "/api/v1/scores/$id", [
            'headers' => $headers,
            'json' => ['game' => $game, 'variant' => $variant, 'seconds' => $seconds] + $extra,
        ]);

        // A refusal from the firewall, such as a 401, comes without a body.
        return [$response->getStatusCode(), json_decode($response->getContent(false) ?: '[]', true)];
    }

    /** @return list<array<string, mixed>> */
    private function ranking(string $game = 'sudoku', string $variant = 'EASY'): array
    {
        $response = static::createClient()->request('GET', "/api/v1/rankings/$game/$variant", ['headers' => ['Accept' => 'application/json']]);
        $this->assertSame(200, $response->getStatusCode());

        return $response->toArray()['entries'];
    }

    private function stored(): int
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Score::class)->count([]);
    }

    public function testAWonGameGoesIntoTheRankingWithItsPlayer(): void
    {
        $player = $this->register();
        $id = (string) Uuid::v4();

        [$status, $body] = $this->submit(95, $id, extra: ['playedAt' => '2026-11-04T09:00:00+01:00']);

        $this->assertContains($status, [200, 201]);
        $this->assertSame(1, $body['rank']);
        $this->assertSame($id, $body['id']);

        $entries = $this->ranking();
        $this->assertCount(1, $entries);
        $this->assertSame(1, $entries[0]['rank']);
        $this->assertSame($id, $entries[0]['id']);
        $this->assertSame(95, $entries[0]['seconds']);
        $this->assertSame($player, $entries[0]['playerId']);
        $this->assertSame('Zorro Astuto 42', $entries[0]['playerName']);
        $this->assertSame('zorro:menta', $entries[0]['playerAvatar']);
        $this->assertEquals(new \DateTimeImmutable('2026-11-04T08:00:00+00:00'), new \DateTimeImmutable($entries[0]['playedAt']));
    }

    public function testOnlyTheTenBestAreKeptAndAWorseOneIsNotStored(): void
    {
        $this->register();
        foreach ([50, 10, 90, 30, 70, 20, 100, 60, 40, 80] as $seconds) {
            $this->submit($seconds);
        }

        [$status, $body] = $this->submit(101);

        $this->assertContains($status, [200, 201]);
        $this->assertNull($body['rank']);
        $this->assertSame(10, $this->stored());

        [, $body] = $this->submit(15);

        $this->assertSame(2, $body['rank']);
        $this->assertSame(10, $this->stored());
        $this->assertSame([10, 15, 20, 30, 40, 50, 60, 70, 80, 90], array_column($this->ranking(), 'seconds'));
    }

    public function testAnEqualTimeRanksBelowTheOneThatGotThereFirst(): void
    {
        $first = $this->register(self::SECRET, 'Primero');
        $this->register(self::OTHER_SECRET, 'Segundo');
        $this->submit(60);
        foreach (range(1, 9) as $_) {
            $this->submit(60, secret: self::OTHER_SECRET);
        }

        [, $body] = $this->submit(60);

        // A tenth 60 is already there: an eleventh does not get in.
        $this->assertNull($body['rank']);
        $this->assertSame($first, $this->ranking()[0]['playerId']);
    }

    public function testSendingTheSameTimeAgainDoesNotAddItTwice(): void
    {
        $this->register();
        $id = (string) Uuid::v4();

        $this->submit(80, $id);
        [$status, $body] = $this->submit(80, $id);

        $this->assertContains($status, [200, 201]);
        $this->assertSame(1, $body['rank']);
        $this->assertSame(1, $this->stored());
    }

    public function testATimePushedOutIsNotStoredAgainWhenSentAgain(): void
    {
        $this->register();
        $id = (string) Uuid::v4();
        $this->submit(500, $id);
        foreach (range(1, 10) as $seconds) {
            $this->submit($seconds);
        }

        [, $body] = $this->submit(500, $id);

        $this->assertNull($body['rank']);
        $this->assertSame(10, $this->stored());
    }

    public function testATimeNeedsThePlayersSecret(): void
    {
        $this->register();

        [$status] = $this->submit(30, secret: null);
        $this->assertSame(401, $status);

        [$status] = $this->submit(30, secret: str_repeat('x', 40));
        $this->assertSame(401, $status);

        $this->assertSame(0, $this->stored());
    }

    public function testAPlayerCanNotTakeAnotherPlayersTime(): void
    {
        $this->register();
        $this->register(self::OTHER_SECRET, 'Otro');
        $id = (string) Uuid::v4();
        $this->submit(30, $id);

        [$status] = $this->submit(5, $id, self::OTHER_SECRET);

        $this->assertSame(409, $status);
        $this->assertSame(30, $this->ranking()[0]['seconds']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function badTimes(): iterable
    {
        yield 'zero seconds' => [['seconds' => 0]];
        yield 'a whole day' => [['seconds' => 86400]];
        yield 'a game with capitals' => [['game' => 'Sudoku']];
        yield 'a game with a slash' => [['game' => 'su/doku']];
        yield 'an empty variant' => [['variant' => '']];
        yield 'a variant with spaces' => [['variant' => 'muy facil']];
        yield 'a date that is not one' => [['playedAt' => 'ayer']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badTimes')]
    public function testBadTimesAreRefused(array $override): void
    {
        $this->register();
        $id = (string) Uuid::v4();

        $response = static::createClient()->request('PUT', "/api/v1/scores/$id", [
            'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.self::SECRET],
            'json' => $override + ['game' => 'sudoku', 'variant' => 'EASY', 'seconds' => 30],
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
        $this->assertSame(0, $this->stored());
    }

    public function testATimeWithoutADateOrFromTheFutureGetsTheMomentItArrived(): void
    {
        $this->register();
        $before = new \DateTimeImmutable('-1 minute');

        $this->submit(30);
        $this->submit(40, extra: ['playedAt' => '2099-01-01T00:00:00+00:00']);

        foreach ($this->ranking() as $entry) {
            $playedAt = new \DateTimeImmutable($entry['playedAt']);
            $this->assertGreaterThan($before, $playedAt);
            $this->assertLessThan(new \DateTimeImmutable('+1 minute'), $playedAt);
        }
    }

    public function testEachGameAndLevelHasItsOwnRanking(): void
    {
        $this->register();
        $this->submit(30);
        $this->submit(40, variant: 'HARD');
        $this->submit(50, game: 'race', variant: '5');

        $this->assertSame([30], array_column($this->ranking(), 'seconds'));
        $this->assertSame([40], array_column($this->ranking('sudoku', 'HARD'), 'seconds'));
        $this->assertSame([50], array_column($this->ranking('race', '5'), 'seconds'));
        $this->assertSame([], $this->ranking('kakuro', 'EXPERT'));
    }

    public function testTheRankingShowsThePlayersNameAsItIsNow(): void
    {
        $this->register();
        $this->submit(30);

        static::createClient()->request('PATCH', '/api/v1/players/'.$this->ranking()[0]['playerId'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'Authorization' => 'Bearer '.self::SECRET],
            'json' => ['name' => 'Nombre Nuevo', 'avatar' => 'gato:rosa:lazo'],
        ]);

        $this->assertSame('Nombre Nuevo', $this->ranking()[0]['playerName']);
        $this->assertSame('gato:rosa:lazo', $this->ranking()[0]['playerAvatar']);
    }

    public function testTheRankingNeverShowsSecretsAndTimesCanNotBeReadOneByOne(): void
    {
        $this->register();
        $id = (string) Uuid::v4();
        $this->submit(30, $id);

        $raw = static::createClient()->request('GET', '/api/v1/rankings/sudoku/EASY', ['headers' => ['Accept' => 'application/json']])->getContent();
        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertStringNotContainsString('tokenHash', $raw);
        $this->assertStringNotContainsString(hash('sha256', self::SECRET), $raw);

        $this->assertSame(404, static::createClient()->request('GET', "/api/v1/scores/$id", ['headers' => ['Accept' => 'application/json']])->getStatusCode());
        $this->assertSame(404, static::createClient()->request('GET', '/api/v1/rankings/sudoku/no-valida', ['headers' => ['Accept' => 'application/json']])->getStatusCode());
    }
}
