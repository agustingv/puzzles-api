# Puzzles API

The server for the [Puzzles](https://github.com/agustingv/puzzle) Android app: Symfony 8.1, API Platform 5
and MySQL 8.4, in Docker.

For now it keeps only players. There is no sign-up: the app makes up a player on its first launch, with a
random id, a random name and a secret, and saves it here when it has a connection. The secret proves that a
player belongs to that phone; the server keeps only its SHA-256 hash.

## Running it

```sh
docker compose up -d
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

The API is then at http://localhost:8080/api (documentation at http://localhost:8080/api/docs).
`app/.env.local` holds `APP_SECRET`; it is not in git, so create it on a new machine:

```sh
printf 'APP_SECRET=%s\n' "$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')" > app/.env.local
```

## Tests

```sh
docker compose exec php bin/phpunit
```

They run against the `puzzles_test` database, which `docker/mysql/init.sql` creates on the first start.

## With the app

Debug builds of the app call `http://localhost:8080/api/`. On an emulator or a phone over USB, forward the
port first:

```sh
adb reverse tcp:8080 tcp:8080
```

Android 17 asks apps for a special permission to reach the local network (such as `10.0.2.2` or the
computer's LAN address), but not `localhost`, which is why the app goes through `adb reverse`.

Release builds have no server until one is given: `./gradlew assembleRelease -PpuzzlesApiUrl=https://…/api/`.
They only allow HTTPS.

## Endpoints

| Method and path | What it does | Auth |
|---|---|---|
| `PUT /api/players/{id}` with `{"name", "secret"}` | Registers the player, or renames it if it exists with that secret. Safe to repeat. `409` if the id is someone else's. | The secret in the body |
| `GET /api/players/{id}` | The player's id, name and registration date. | None |
| `PATCH /api/players/{id}` with `{"name"}` | Renames the player. Not used by the app yet. | `Authorization: Bearer <secret>` |

Names: 3 to 24 letters, digits, spaces, hyphens or underscores, starting with a letter or digit. The app
applies the same rule. Players can not be listed.

## Before production

- HTTPS in front of nginx, and the app built with that URL.
- `APP_ENV=prod`, a real `APP_SECRET`, and database credentials through the environment, not
  `docker-compose.yml`.
- Rate limiting on `PUT /api/players/{id}`, which anyone can call to create players.
- Deleting players on request, as the app's privacy policy promises: for now, by hand in the database.
