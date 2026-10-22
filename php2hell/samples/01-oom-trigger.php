<?php
// Minimal OOM demo.
// Cap memory_limit low, then keep concatenating a string to deliberately push
// usage to the limit. PHP's internal allocation (emalloc) fails here, producing
// the "Allowed memory size exhausted" fatal error.
//
// This allocation-failure moment is the starting point of php2shell
// (CVE-2026-109618 / 109619). Before the patch, PHP escaped this moment via
// zend_bailout() (a longjmp), skipping orderly teardown.
//
// Run:
//   docker run --rm -v "$PWD":/app php:8.3-cli php /app/01-oom-trigger.php

ini_set('memory_limit', '16M');

echo "memory_limit = " . ini_get('memory_limit') . "\n";
echo "start: " . memory_get_usage(true) . " bytes\n";

$chunks = [];
while (true) {
    // Allocate 1MB at a time until it hits the 16MB ceiling.
    $chunks[] = str_repeat('A', 1024 * 1024);
    echo "allocated: " . memory_get_usage(true) . " bytes\n";
}

// Unreachable.
echo "done\n";
