# php2hell — php2shell (CVE-2026-109618 / CVE-2026-109619)

Notes for understanding **php2shell**, a critical PHP vulnerability rooted in
how the engine handled OOM (memory allocation failure), using the official
source diffs and safe sample scripts.

- CVE-2026-109618 (GHSA-w8hw-w9fx-j79w)
- CVE-2026-109619 (GHSA-6wmf-4rv5-p3rp)
- Fixed in: **8.5.12 / 8.4.27 / 8.3.36 / 8.2.35** and later
- Fix commit (8.3 branch): `3c832b52` "Avoid state corruption via bailout"

> ⚠️ No working exploit lives here. The goal is to understand the mechanism.
> Every sample merely triggers an OOM and observes it; none corrupts memory.

## What was wrong

PHP's memory manager (ZMM) used to call `zend_bailout()` (a `longjmp`) on
allocation failure, **jumping out without unwinding the current execution
context**. That left half-constructed objects, hash tables, and global state
behind, which could cause type confusion and dangling pointers.

On long-lived SAPIs that reuse one process across requests (php-fpm, mod_php),
this corrupted state **leaked into the next request** (stale global state). An
attacker who exhausted `memory_limit` at a chosen moment could escalate this to
remote code execution (a shell) — hence the name "PHP → shell".

## What the patch changes

| CVE | Summary | Key files |
|---|---|---|
| 109618 | Replace bailout on OOM with UnwindExit | `Zend/zend_alloc.c`, `Zend/zend_execute_API.c` |
| 109619 | Explicitly reset globals touched before startup | `Zend/zend.c`, `Zend/zend_execute_API.c` |

- Instead of bailing out immediately, an allocation failure is queued as a
  **delayed OOM (`EG(delayed_oom)`)** and later unwound in an orderly way via
  `zend_throw_unwind_exit()` at a safe point. New API: `ZEND_HAS_OOM()`,
  `zend_handle_delayed_oom()`, and the state machine
  `ZEND_OOM_STATE_{NONE,PENDING,HANDLING,HANDLED}`.
- The memory ceiling becomes two-tier: a **soft limit (`memory_limit`)** and a
  **hard limit**, with a new `memory_hard_limit_percent` INI (default 200% /
  1 GB buffer).
- `user_error_handler` / `exception` / `objects_store` and friends are reset
  explicitly at shutdown, cutting off stale references from a prior request.

See [`../diffs/`](../diffs/) for the full diffs (start with `Zend/zend_alloc.c`,
`Zend/zend_execute_API.c`, and `Zend/zend.c`).

## Running the samples

Compare the vulnerable and fixed builds with the official Docker images
(no local PHP needed).

```console
$ ./samples/run.sh
```

Run an individual sample:

```console
$ docker run --rm -v "$PWD/samples":/app php:8.3.35-cli php /app/01-oom-trigger.php
$ docker run --rm -v "$PWD/samples":/app php:8.3.36-cli php /app/01-oom-trigger.php
```

### [01-oom-trigger.php](samples/01-oom-trigger.php)
Minimal example: cap `memory_limit` at 16M and grow a string until it hits the
limit, producing the OOM — the starting point of php2shell. Both builds end
with the same `Allowed memory size ... exhausted` fatal error.

### [02-engine-changed.php](samples/02-engine-changed.php)
The **one clean observable** that the OOM subsystem was reworked. The presence
of the new `memory_hard_limit_percent` INI distinguishes the generations.

```text
----- php:8.3.35-cli -----
memory_hard_limit_percent: bool(false)   # INI does not exist (pre-patch)
----- php:8.3.36-cli -----
memory_hard_limit_percent: string(0) ""  # INI exists (post-patch)
```

### [03-why-invisible.php](samples/03-why-invisible.php)
Demonstrates **why php2shell is invisible from userland**. Even when the OOM is
wrapped in `try/catch/finally` with a local destructor, the output is
essentially **identical** on 8.3.35 and 8.3.36.

```text
[main] before f
[f] try: force a soft OOM in a single allocation

Fatal error: Allowed memory size of 33554432 bytes exhausted ...
[shutdown] register_shutdown_function  <- always runs
```

Observation: in both builds the OOM is an **uncatchable fatal error** — neither
`catch`, nor `finally`, nor the local destructor runs. Only
`register_shutdown_function` does.

So the patch did not change user-visible behavior; it fixed the **correctness of
the engine's internal teardown** (whether the execution context is unwound
without corruption, i.e. `bailout` → `UnwindExit`). The corrupted internal state
is not observable from PHP code — **that invisibility is the whole danger**, and
the reason it could be weaponized as a cross-request corruption primitive on
long-lived processes.

## References

- php-src NEWS / commit `3c832b52`
- [watchTowr FAQ](https://watchtowr.com/intelligence/php-cve-2026-109618-faq/)
- [Imperva: PHP2Shell](https://www.imperva.com/blog/php2shell-vulnerability-updates/)
- [Help Net Security](https://www.helpnetsecurity.com/2026/10/21/php2shell-php-vulnerabilities-patched-cve-2026-109618-cve-2026-109619/)
