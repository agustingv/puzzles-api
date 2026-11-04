# Puzzles API

The server for the [Puzzles](https://github.com/agustingv/puzzle) Android app: Symfony 8.1, API Platform 5
and MySQL 8.4, in Docker.

It keeps players and the rankings of their best times. There is no sign-up: the app makes up a player on its first launch, with a
random id, a random name and a secret, and saves it here when it has a connection. The secret proves that a
player belongs to that phone; the server keeps only its SHA-256 hash.

## Running it

```sh
docker compose up -d
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

The API is then at http://localhost:8080/api/v1 (documentation at http://localhost:8080/api/v1/docs).
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

Debug builds of the app call the production API unless built with another one. To try this local API, build
the app with `-PpuzzlesApiUrl=http://localhost:8080/api/v1/` and, on an emulator or a phone over USB, forward
the port first:

```sh
adb reverse tcp:8080 tcp:8080
```

Android 17 asks apps for a special permission to reach the local network (such as `10.0.2.2` or the
computer's LAN address), but not `localhost`, which is why the app goes through `adb reverse`.

Release builds have no server until one is given: `./gradlew assembleRelease -PpuzzlesApiUrl=https://…/api/v1/`.
They only allow HTTPS.

## Endpoints

| Method and path | What it does | Auth |
|---|---|---|
| `PUT /api/v1/players/{id}` with `{"name", "avatar", "secret"}` | Registers the player, or updates it if it exists with that secret. Safe to repeat. `409` if the id is someone else's. `avatar` is optional: without it, the player keeps the one it has. | The secret in the body |
| `GET /api/v1/players/{id}` | The player's id, name, avatar (when it has one) and registration date. | None |
| `PATCH /api/v1/players/{id}` with `{"name"}` and/or `{"avatar"}` | Changes the player. Not used by the app yet. | `Authorization: Bearer <secret>` |
| `PUT /api/v1/scores/{id}` with `{"game", "variant", "seconds", "playedAt"}` | Files a won game in its ranking. Answers `{"id", "game", "variant", "seconds", "rank"}`: `rank` is its place (1 to 10), or `null` if it did not make the ten best, in which case nothing is stored. Safe to repeat: the app makes up the id. `409` if the id is another player's. `playedAt` is optional. | `Authorization: Bearer <secret>` |
| `GET /api/v1/rankings/{game}/{variant}` | The ten best times on that game and level, of all players: `{"game", "variant", "entries": [{"rank", "id", "seconds", "playedAt", "playerId", "playerName", "playerAvatar"}]}`. Empty if nobody has won it yet. | None |

Names: 3 to 24 letters, digits, spaces, hyphens or underscores, starting with a letter or digit. The app
applies the same rule. Players can not be listed.

Rankings work like the app's own records: one per game and level, fastest first, and between equal times
the one that arrived first. Only the ten best are kept; a time pushed out by a better one is deleted. A player
can hold several places. `game` is 1 to 32 lowercase letters and `variant` 1 to 16 letters or digits (`EASY`,
`5`): like avatars, only their form is checked. `seconds` goes from 1 to 86399. The names and avatars in a
ranking are the ones the players have now.

Avatars: `animal:color` or `animal:color:accessory`, each part 1 to 16 lowercase letters without accents
(`buho:lila`, `gato:rosa:lazo`). Only the form is checked: the pieces are defined by the app, which can add
new ones, such as prizes, without a change here.

## Versions

The version is in the path: `/api/v1`. Installed copies of the app keep calling the version they were built
with, so a change that would break them (a renamed or removed field or path, a new required field, a
stricter rule) goes to a new version, `/api/v2`, while `v1` keeps working for as long as those copies are in
use. Adding an optional field or a new endpoint is not breaking and stays in `v1`.

## Before production

- HTTPS in front of nginx, and the app built with that URL.
- On Apache instead of nginx: `app/public` as the document root, with `mod_rewrite` and `AllowOverride All`
  so that `app/public/.htaccess` applies. It does what `docker/nginx/default.conf` does, root 404 included.
- `APP_ENV=prod`, a real `APP_SECRET`, and database credentials through the environment, not
  `docker-compose.yml`.
- Rate limiting on `PUT /api/v1/players/{id}`, which anyone can call to create players, and on
  `PUT /api/v1/scores/{id}`.
- The times are the app's word: a player who forges requests can put any time in the rankings. Checking
  them would need the server to see the games themselves.
- Deleting players on request, as the app's privacy policy promises: for now, by hand in the database.
