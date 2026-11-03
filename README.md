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

Debug builds of the app call `http://localhost:8080/api/v1/`. On an emulator or a phone over USB, forward the
port first:

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

Names: 3 to 24 letters, digits, spaces, hyphens or underscores, starting with a letter or digit. The app
applies the same rule. Players can not be listed.

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
- Rate limiting on `PUT /api/v1/players/{id}`, which anyone can call to create players.
- Deleting players on request, as the app's privacy policy promises: for now, by hand in the database.
