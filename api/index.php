<?php

/*
 * Vercel serverless entrypoint.
 *
 * Vercel's filesystem is read-only except for /tmp, so:
 *  1. create the writable dirs Laravel needs (compiled views, cache, sessions);
 *  2. copy the bundled, pre-seeded SQLite demo database into /tmp on cold start
 *     (set DB_DATABASE=/tmp/database.sqlite in the Vercel env).
 * Then hand off to the normal Laravel front controller.
 *
 * For a persistent database, set DB_CONNECTION=pgsql (or mysql) + DB_* env vars
 * in Vercel instead — this SQLite block is skipped automatically.
 */

foreach (['/tmp/views', '/tmp/cache', '/tmp/sessions'] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

$dbTarget = getenv('DB_DATABASE') ?: '';
if ((getenv('DB_CONNECTION') ?: 'sqlite') === 'sqlite' && str_starts_with($dbTarget, '/tmp/')) {
    if (! file_exists($dbTarget)) {
        $seed = __DIR__ . '/../database/vercel-seed.sqlite';
        file_exists($seed) ? @copy($seed, $dbTarget) : @touch($dbTarget);
    }
}

require __DIR__ . '/../public/index.php';
