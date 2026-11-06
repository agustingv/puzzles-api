<?php

namespace App\Tests\Api;

use ApiPlatform\Test\ApiTestCase;
use App\Entity\Player;
use App\Entity\Score;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Uid\Uuid;

final class PlayerTest extends ApiTestCase
{
    use ResetsSecretGuesses;

    protected static ?bool $alwaysBootKernel = true;

    private const SECRET = 'un-secreto-de-prueba-largo-de-al-menos-32-caracteres';

    protected function setUp(): void
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->resetSecretGuesses();
    }

    private function register(string $id, string $name = 'Zorro Astuto 42', string $secret = self::SECRET, ?string $avatar = null): array
    {
        $json = ['name' => $name, 'secret' => $secret];
        if (null !== $avatar) {
            $json['avatar'] = $avatar;
        }
        $response = static::createClient()->request('PUT', "/api/v1/players/$id", [
            'headers' => ['Accept' => 'application/json'],
            'json' => $json,
        ]);

        return [$response->getStatusCode(), $response->toArray(false)];
    }

    public function testAPlayerRegistersWithTheIdTheAppChose(): void
    {
        $id = (string) Uuid::v4();

        [$status, $body] = $this->register($id);

        $this->assertContains($status, [200, 201]);
        $this->assertSame($id, $body['id']);
        $this->assertSame('Zorro Astuto 42', $body['name']);
        $this->assertArrayHasKey('registeredAt', $body);
        $this->assertArrayNotHasKey('secret', $body);
        $this->assertArrayNotHasKey('tokenHash', $body);
    }

    public function testRegisteringAgainWithTheSameSecretIsSafeAndTakesTheName(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id, 'Zorro Astuto 42');
        $this->register($id, 'Zorro Astuto 42');

        [$status, $body] = $this->register($id, 'Otro Nombre');

        // API Platform answers 201 whether or not the player existed: the app only needs a 2xx.
        $this->assertContains($status, [200, 201]);
        $this->assertSame('Otro Nombre', $body['name']);
        $this->assertSame(1, static::getContainer()->get(EntityManagerInterface::class)->getRepository(Player::class)->count([]));
    }

    public function testAnIdTakenWithAnotherSecretIsRefused(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        [$status] = $this->register($id, 'Intruso', str_repeat('x', 40));

        $this->assertSame(409, $status);
        $body = static::createClient()->request('GET', "/api/v1/players/$id", ['headers' => ['Accept' => 'application/json']])->toArray();
        $this->assertSame('Zorro Astuto 42', $body['name']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function badRegistrations(): iterable
    {
        yield 'name too short' => ['Zo', self::SECRET];
        yield 'name too long' => [str_repeat('a', 25), self::SECRET];
        yield 'name with symbols' => ['Zorro <script>', self::SECRET];
        yield 'blank name' => ['   ', self::SECRET];
        yield 'short secret' => ['Zorro Astuto 42', 'corto'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badRegistrations')]
    public function testBadRegistrationsAreRefused(string $name, string $secret): void
    {
        [$status] = $this->register((string) Uuid::v4(), $name, $secret);

        $this->assertSame(422, $status);
    }

    public function testAccentsAndSpacesAreFineInANameButItIsTrimmed(): void
    {
        [$status, $body] = $this->register((string) Uuid::v4(), '  Ñandú Ágil 7 ');

        $this->assertContains($status, [200, 201]);
        $this->assertSame('Ñandú Ágil 7', $body['name']);
    }

    public function testAnyoneCanReadAPlayerButNotItsSecret(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id, avatar: 'zorro:menta');

        $response = static::createClient()->request('GET', "/api/v1/players/$id", ['headers' => ['Accept' => 'application/json']]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id', 'name', 'avatar', 'registeredAt'], array_keys($response->toArray()));
    }

    private function read(string $id): array
    {
        return static::createClient()->request('GET', "/api/v1/players/$id", ['headers' => ['Accept' => 'application/json']])->toArray();
    }

    public function testThePlayerSendsItsAvatarAndChangesIt(): void
    {
        $id = (string) Uuid::v4();

        [, $body] = $this->register($id, avatar: 'buho:lila');
        $this->assertSame('buho:lila', $body['avatar']);

        [, $body] = $this->register($id, avatar: 'buho:coral:corona');
        $this->assertSame('buho:coral:corona', $body['avatar']);
        $this->assertSame('buho:coral:corona', $this->read($id)['avatar']);

        // Several accessories at once, the longest the app can make and more.
        $longest = 'mariposa:melocoton:estrella:chistera:gafas:trofeo';
        [, $body] = $this->register($id, avatar: $longest);
        $this->assertSame($longest, $body['avatar']);
        $most = 'mariposa:melocoton'.str_repeat(':abcdefghijklmnop', 8);
        [, $body] = $this->register($id, avatar: $most);
        $this->assertSame($most, $this->read($id)['avatar']);
    }

    public function testAnAppWithoutAvatarsLeavesTheOneThereIs(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id, avatar: 'oso:arena');

        $this->register($id, 'Nombre Nuevo');

        $this->assertSame('Nombre Nuevo', $this->read($id)['name']);
        $this->assertSame('oso:arena', $this->read($id)['avatar']);
    }

    public function testAPlayerWithoutAvatarHasNone(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        $this->assertArrayNotHasKey('avatar', $this->read($id));
    }

    /** @return iterable<string, array{string}> */
    public static function badAvatars(): iterable
    {
        yield 'animal only' => ['zorro'];
        yield 'capitals' => ['Zorro:menta'];
        yield 'accents' => ['búho:menta'];
        yield 'too many accessories' => ['zorro:menta'.str_repeat(':corona', 9)];
        yield 'empty part' => ['zorro::corona'];
        yield 'markup' => ['<b>:menta'];
        yield 'too long' => [str_repeat('a', 17).':menta'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badAvatars')]
    public function testABadAvatarIsRefused(string $avatar): void
    {
        [$status] = $this->register((string) Uuid::v4(), avatar: $avatar);

        $this->assertSame(422, $status);
    }

    public function testThePatchChangesTheAvatarToo(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id, avatar: 'gato:cielo');

        $status = static::createClient()->request('PATCH', "/api/v1/players/$id", [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'Accept' => 'application/json', 'Authorization' => 'Bearer '.self::SECRET],
            'json' => ['avatar' => 'gato:rosa:lazo'],
        ])->getStatusCode();

        $this->assertSame(200, $status);
        $this->assertSame('gato:rosa:lazo', $this->read($id)['avatar']);
    }

    private function rename(string $id, string $name, ?string $token): int
    {
        $headers = ['Content-Type' => 'application/merge-patch+json', 'Accept' => 'application/json'];
        if (null !== $token) {
            $headers['Authorization'] = "Bearer $token";
        }

        return static::createClient()->request('PATCH', "/api/v1/players/$id", [
            'headers' => $headers,
            'json' => ['name' => $name],
        ])->getStatusCode();
    }

    public function testAPlayerRenamesItselfWithItsSecret(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        $this->assertSame(200, $this->rename($id, 'Búho Sabio 3', self::SECRET));

        $body = static::createClient()->request('GET', "/api/v1/players/$id", ['headers' => ['Accept' => 'application/json']])->toArray();
        $this->assertSame('Búho Sabio 3', $body['name']);
    }

    public function testRenamingNeedsTheSecret(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        $this->assertSame(401, $this->rename($id, 'Sin Permiso', null));
        $this->assertSame(401, $this->rename($id, 'Sin Permiso', 'un-token-que-no-existe-de-ninguna-forma'));
    }

    public function testAPlayerCanNotRenameAnother(): void
    {
        $mine = (string) Uuid::v4();
        $theirs = (string) Uuid::v4();
        $this->register($mine);
        $this->register($theirs, 'Otro Jugador', str_repeat('y', 40));

        $this->assertSame(403, $this->rename($theirs, 'Robado', self::SECRET));
    }

    public function testARenameIsValidatedToo(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        $this->assertSame(422, $this->rename($id, 'x', self::SECRET));
    }

    private function delete(string $id, ?string $token): int
    {
        $headers = ['Accept' => 'application/json'];
        if (null !== $token) {
            $headers['Authorization'] = "Bearer $token";
        }

        return static::createClient()->request('DELETE', "/api/v1/players/$id", ['headers' => $headers])->getStatusCode();
    }

    private function readStatus(string $id): int
    {
        return static::createClient()->request('GET', "/api/v1/players/$id", ['headers' => ['Accept' => 'application/json']])->getStatusCode();
    }

    public function testAPlayerDeletesItselfWithItsTimes(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);
        $other = (string) Uuid::v4();
        $this->register($other, 'Otro Jugador', str_repeat('y', 40));
        foreach ([[self::SECRET, 30], [str_repeat('y', 40), 40]] as [$secret, $seconds]) {
            static::createClient()->request('PUT', '/api/v1/scores/'.Uuid::v4(), [
                'headers' => ['Accept' => 'application/json', 'Authorization' => "Bearer $secret"],
                'json' => ['game' => 'sudoku', 'variant' => 'EASY', 'seconds' => $seconds],
            ]);
        }

        $this->assertSame(204, $this->delete($id, self::SECRET));

        $this->assertSame(404, $this->readStatus($id));
        $this->assertSame(200, $this->readStatus($other));
        $entries = static::createClient()->request('GET', '/api/v1/rankings/sudoku/EASY', ['headers' => ['Accept' => 'application/json']])->toArray()['entries'];
        $this->assertSame([$other], array_column($entries, 'playerId'));
        $this->assertSame(1, static::getContainer()->get(EntityManagerInterface::class)->getRepository(Score::class)->count([]));
    }

    public function testDeletingNeedsTheSecret(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        $this->assertSame(401, $this->delete($id, null));
        $this->assertSame(401, $this->delete($id, 'un-token-que-no-existe-de-ninguna-forma'));
        $this->assertSame(200, $this->readStatus($id));
    }

    public function testAPlayerCanNotDeleteAnother(): void
    {
        $mine = (string) Uuid::v4();
        $theirs = (string) Uuid::v4();
        $this->register($mine);
        $this->register($theirs, 'Otro Jugador', str_repeat('y', 40));

        $this->assertSame(403, $this->delete($theirs, self::SECRET));
        $this->assertSame(200, $this->readStatus($theirs));
    }

    public function testAPlayerAlreadyDeletedIsNotFound(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);
        $this->delete($id, self::SECRET);

        // Its secret is gone with it, so the token is no one's.
        $this->assertSame(401, $this->delete($id, self::SECRET));
        $this->register($id);
        $this->assertSame(404, $this->delete((string) Uuid::v4(), self::SECRET));
    }

    public function testTooManyWrongTokensBlockTheAddressForAWhile(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        for ($i = 0; $i < 20; ++$i) {
            $this->assertSame(401, $this->delete($id, "token-equivocado-numero-$i-de-al-menos-32"));
        }

        $response = static::createClient()->request('DELETE', "/api/v1/players/$id", [
            'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.self::SECRET],
        ]);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThan(0, (int) $response->getHeaders(false)['retry-after'][0]);
        // Blocked writes, even with the right secret; reading stays open.
        $this->assertSame(429, $this->register((string) Uuid::v4())[0]);
        $this->assertSame(200, $this->readStatus($id));
    }

    public function testWrongSecretsInARegistrationCountToo(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        for ($i = 0; $i < 20; ++$i) {
            $this->assertSame(409, $this->register($id, 'Intruso', "secreto-equivocado-numero-$i-de-al-menos-32")[0]);
        }

        $this->assertSame(429, $this->register($id)[0]);
    }

    public function testARightSecretDoesNotClearTheCount(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        for ($i = 0; $i < 19; ++$i) {
            $this->delete($id, "token-equivocado-numero-$i-de-al-menos-32");
        }
        $this->assertSame(200, $this->rename($id, 'Sigo Siendo Yo', self::SECRET));
        $this->delete($id, 'el-que-hace-veinte-de-al-menos-32-caracteres');

        $this->assertSame(429, $this->rename($id, 'Bloqueado', self::SECRET));
    }

    public function testAnIdThatIsNotAUuidIsNotAPlayer(): void
    {
        [$status] = $this->register('no-es-un-uuid');

        $this->assertSame(404, $status);
    }

    public function testThePlayersCanNotBeListed(): void
    {
        $response = static::createClient()->request('GET', '/api/v1/players');

        $this->assertContains($response->getStatusCode(), [404, 405]);
    }
}
