<?php

namespace App\Tests\Api;

use ApiPlatform\Test\ApiTestCase;
use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Uid\Uuid;

final class PlayerTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const SECRET = 'un-secreto-de-prueba-largo-de-al-menos-32-caracteres';

    protected function setUp(): void
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    private function register(string $id, string $name = 'Zorro Astuto 42', string $secret = self::SECRET): array
    {
        $response = static::createClient()->request('PUT', "/api/players/$id", [
            'headers' => ['Accept' => 'application/json'],
            'json' => ['name' => $name, 'secret' => $secret],
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
        $body = static::createClient()->request('GET', "/api/players/$id", ['headers' => ['Accept' => 'application/json']])->toArray();
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
        $this->register($id);

        $response = static::createClient()->request('GET', "/api/players/$id", ['headers' => ['Accept' => 'application/json']]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id', 'name', 'registeredAt'], array_keys($response->toArray()));
    }

    private function rename(string $id, string $name, ?string $token): int
    {
        $headers = ['Content-Type' => 'application/merge-patch+json', 'Accept' => 'application/json'];
        if (null !== $token) {
            $headers['Authorization'] = "Bearer $token";
        }

        return static::createClient()->request('PATCH', "/api/players/$id", [
            'headers' => $headers,
            'json' => ['name' => $name],
        ])->getStatusCode();
    }

    public function testAPlayerRenamesItselfWithItsSecret(): void
    {
        $id = (string) Uuid::v4();
        $this->register($id);

        $this->assertSame(200, $this->rename($id, 'Búho Sabio 3', self::SECRET));

        $body = static::createClient()->request('GET', "/api/players/$id", ['headers' => ['Accept' => 'application/json']])->toArray();
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

    public function testAnIdThatIsNotAUuidIsNotAPlayer(): void
    {
        [$status] = $this->register('no-es-un-uuid');

        $this->assertSame(404, $status);
    }

    public function testThePlayersCanNotBeListed(): void
    {
        $response = static::createClient()->request('GET', '/api/players');

        $this->assertContains($response->getStatusCode(), [404, 405]);
    }
}
