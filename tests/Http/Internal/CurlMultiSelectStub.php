<?php

declare(strict_types=1);

namespace Manychois\PhpStrong\Http\Internal;

use CurlMultiHandle;

/**
 * Test seam: PHP resolves an unqualified function call first against the current
 * namespace before falling back to the global one, so defining `curl_multi_select()`
 * here shadows the built-in for every call inside `CurlMultiExecutor`. When the flag
 * below is enabled it reports -1 (no descriptors to wait on), deterministically
 * exercising the `usleep()` fallback branch of `CurlMultiExecutor::pump()` — a
 * condition the installed cURL build does not reliably produce on its own. The flag
 * defaults to disabled, in which case every call simply delegates to the real
 * `curl_multi_select()`.
 *
 * @internal
 */
$GLOBALS['__phpStrongForceCurlMultiSelectNegOne'] ??= false;

/**
 * @internal
 */
function curl_multi_select(CurlMultiHandle $multiHandle, float $timeout = 1.0): int
{
    if ($GLOBALS['__phpStrongForceCurlMultiSelectNegOne'] ?? false) {
        return -1;
    }

    return \curl_multi_select($multiHandle, $timeout);
}
