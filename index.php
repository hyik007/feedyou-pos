<?php

/*
 * Vercel serverless entrypoint (project root, NOT /api — the vercel-php runtime
 * strips the /api prefix from the request path, which would break Laravel's
 * own /api/v1/* routes). Mounted at "/", so the full path reaches Laravel.
 *
 * Vercel's filesystem is read-only except /tmp:
 *  1. create the writable dirs Laravel needs;
 *  2. copy the bundled pre-seeded SQLite demo DB into /tmp on cold start.
 * For a persistent DB, set DB_CONNECTION=pgsql + DB_* env in Vercel instead.
 */

foreach (['/tmp/views', '/tmp/cache', '/tmp/sessions'] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

$dbTarget = getenv('DB_DATABASE') ?: '';
if ((getenv('DB_CONNECTION') ?: 'sqlite') === 'sqlite' && str_starts_with($dbTarget, '/tmp/')) {
    if (! file_exists($dbTarget)) {
        $seed = __DIR__ . '/database/vercel-seed.sqlite';
        file_exists($seed) ? @copy($seed, $dbTarget) : @touch($dbTarget);
    }
}

require __DIR__ . '/public/index.php';
