<?php
// TEMPORARY: finds where PHP hangs in production, step by step, since the server's logs can not be read.
// Open https://api.puzzles.butaca13.com/diagnostico.php?t=c7612cfe68bab96c57312142f48675ec once, then DELETE this file.
// It shows no secrets: only timings, versions and whether things work.

if (PHP_SAPI !== 'cli' && ($_GET['t'] ?? '') !== 'c7612cfe68bab96c57312142f48675ec') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
// Stop before the proxy's 60 s, so that the steps reached still arrive.
set_time_limit(40);
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$start = microtime(true);
$step = 'inicio';
function say(string $line): void
{
    global $start;
    printf("[%6.2f s] %s\n", microtime(true) - $start, $line);
    flush();
}
register_shutdown_function(static function () use (&$step): void {
    $error = error_get_last();
    $fatal = $error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    say('FIN en el paso: '.$step.($fatal ? ' — '.$error['message'] : ''));
});

$root = dirname(__DIR__);
say('PHP '.PHP_VERSION.' ('.PHP_SAPI.'), memory_limit '.ini_get('memory_limit').', max_execution_time '.ini_get('max_execution_time'));
$opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
say('OPcache: '.($opcache ? 'activo, '.($opcache['opcache_statistics']['num_cached_scripts'] ?? '?').' scripts' : 'NO activo'));
say('realpath_cache_size '.ini_get('realpath_cache_size').', open_basedir '.(ini_get('open_basedir') ?: '(ninguno)'));

foreach (['var', 'var/cache', 'var/cache/prod', 'var/log'] as $dir) {
    $path = "$root/$dir";
    say("$dir: ".(is_dir($path) ? 'existe' : 'NO existe').', '.(is_writable($path) ? 'escribible' : 'NO escribible'));
}
$container = glob("$root/var/cache/prod/Container*") ?: [];
say('Contenedor compilado en var/cache/prod: '.($container ? 'sí' : 'NO (Symfony lo compila en cada petición si no puede guardarlo)'));
say('.env.local.php: '.(is_file("$root/.env.local.php") ? 'sí' : 'no'));

$step = 'autoload';
$t = microtime(true);
require $root.'/vendor/autoload.php';
say(sprintf('autoload: %.2f s', microtime(true) - $t));

$step = 'variables de entorno';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($root.'/.env');
say('APP_ENV '.($_SERVER['APP_ENV'] ?? '?').', APP_DEBUG '.($_SERVER['APP_DEBUG'] ?? '?'));

$step = 'conexión a MySQL';
$db = parse_url($_SERVER['DATABASE_URL'] ?? '');
say('MySQL en '.($db['host'] ?? '?').':'.($db['port'] ?? 3306).' ...');
$t = microtime(true);
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s', $db['host'] ?? '', $db['port'] ?? 3306, ltrim($db['path'] ?? '', '/')),
        urldecode($db['user'] ?? ''),
        urldecode($db['pass'] ?? ''),
        [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->query('SELECT 1');
    say(sprintf('MySQL: conecta y responde en %.2f s', microtime(true) - $t));
} catch (Throwable $e) {
    say(sprintf('MySQL: FALLA tras %.2f s: %s', microtime(true) - $t, get_class($e).' '.$e->getCode()));
}

$step = 'arranque de Symfony';
$t = microtime(true);
$kernel = new App\Kernel('prod', false);
$kernel->boot();
say(sprintf('Symfony arranca en %.2f s', microtime(true) - $t));

$step = 'una petición a Symfony';
$t = microtime(true);
$response = $kernel->handle(Symfony\Component\HttpFoundation\Request::create('/no-existe'));
say(sprintf('Petición a /no-existe: %d en %.2f s', $response->getStatusCode(), microtime(true) - $t));
$step = 'terminado';
