<?php
// The one clean observable that the OOM subsystem was reworked.
//
// The patch (8.3.36) makes the memory ceiling two-tier (soft / hard) and adds
// a new INI setting, memory_hard_limit_percent (main/main.c).
//   - Exceeding the soft limit (= memory_limit) -> queued as a delayed OOM and
//     unwound in an orderly way.
//   - Exceeding the hard limit                  -> still an immediate bailout.
//
// The presence of this INI reveals the engine generation.
//   8.3.35: ini_get("memory_hard_limit_percent") === false  (setting absent)
//   8.3.36: ini_get("memory_hard_limit_percent") === ""      (setting present)
//
// Run:
//   docker run --rm -v "$PWD":/app php:8.3.35-cli php /app/02-engine-changed.php
//   docker run --rm -v "$PWD":/app php:8.3.36-cli php /app/02-engine-changed.php

$v = ini_get('memory_hard_limit_percent');

echo 'PHP ' . PHP_VERSION . "\n";
echo 'memory_hard_limit_percent: ';
var_dump($v);

if ($v === false) {
    echo "=> INI absent. OOM uses the old bailout path (pre-patch).\n";
} else {
    echo "=> INI present. Two-tier soft/hard limit (post-patch).\n";
}
