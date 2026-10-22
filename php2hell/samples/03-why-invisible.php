<?php
// A demo of why php2shell is invisible from userland.
//
// Intuitively you would expect "pre-patch skips teardown (finally/destructors
// do not run), post-patch runs it". But running this on 8.3.35 and 8.3.36
// yields essentially the same output:
//   - The OOM is an uncatchable fatal error in both (try/catch does not catch).
//   - Neither finally nor the local destructor runs in either.
//   - Only register_shutdown_function runs.
//
// That is the crux:
//   What this patch fixed is not user-visible behavior but the correctness of
//   the engine's internal teardown (whether the current execution context is
//   unwound without corruption). The corrupted internal state is not observable
//   from PHP code -- which is exactly why it could leak into the next request
//   on long-lived processes (php-fpm/mod_php) as a corruption primitive.
//   The invisibility is the whole danger.
//
// Run both and confirm the output is the SAME:
//   docker run --rm -v "$PWD":/app php:8.3.35-cli php /app/03-why-invisible.php
//   docker run --rm -v "$PWD":/app php:8.3.36-cli php /app/03-why-invisible.php

ini_set('memory_limit', '32M');

final class Marker
{
    public function __construct(private string $name) {}
    public function __destruct()
    {
        fwrite(STDERR, "  [__destruct] {$this->name}  <- did it run?\n");
    }
}

register_shutdown_function(static function (): void {
    fwrite(STDERR, "[shutdown] register_shutdown_function  <- always runs\n");
});

function f(): void
{
    $local = new Marker('local-in-f');
    try {
        fwrite(STDERR, "[f] try: force a soft OOM in a single allocation\n");
        $x = str_repeat('A', 200 * 1024 * 1024); // exceeds memory_limit
        fwrite(STDERR, "[f] unreachable\n");
    } catch (\Throwable $e) {
        fwrite(STDERR, "  [catch] " . get_class($e) . "  <- is the OOM caught?\n");
    } finally {
        fwrite(STDERR, "  [finally] f finally  <- did it run?\n");
    }
}

fwrite(STDERR, "[main] before f\n");
f();
fwrite(STDERR, "[main] after f  <- reached?\n");
