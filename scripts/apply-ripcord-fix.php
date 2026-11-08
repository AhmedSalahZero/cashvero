<?php

/**
 * Fixes public/apis/ripcord_client.php (the Odoo connection library). Run from the project folder:
 *
 *     php scripts/apply-ripcord-fix.php
 *
 * Two small changes, each applied only if it is not there yet (safe to run any number of times):
 *
 *  1. "$http_response_header ?? []"  - PHP only creates that variable when the request got an answer.
 *     When Odoo cannot be reached the old line crashed with "Undefined variable" instead of the clean
 *     "Could not access ..." error.
 *
 *  2. An explicit 10-minute timeout, and the caller's own http options (timeout, proxy...) are kept.
 *     Without it PHP gives up after 60 seconds, which is too short for a big Odoo import, and the
 *     library used to throw away any timeout the caller asked for.
 *
 * A backup copy is saved next to the file (ripcord_client.php.bak) the first time.
 */

$file = __DIR__.'/../public/apis/ripcord_client.php';

if (! is_file($file)) {
    exit("Cannot find {$file}\nRun this from the project folder (the one that contains 'artisan').\n");
}

$original = file_get_contents($file);
$code = $original;
$done = [];

// 1. headers
$old = '$this->responseHeaders = $http_response_header;';
if (str_contains($code, $old)) {
    $code = str_replace($old, '$this->responseHeaders = $http_response_header ?? [];', $code);
    $done[] = 'headers';
}

// 2. timeout + keep the caller's http options
$marker = '/* cashvero: explicit timeout */';
if (! str_contains($code, $marker)) {
    $pattern = '/^([ \t]*)\$context\s*=\s*stream_context_create\(\s*\$options\s*\)\s*;/m';

    if (substr_count($code, '$this->options') < 1 || preg_match_all($pattern, $code) !== 1) {
        exit("The Ripcord file does not look the way I expected, so the timeout fix was NOT applied.\nPlease send public/apis/ripcord_client.php to Claude.\n");
    }

    $code = preg_replace_callback($pattern, function ($m) use ($marker) {
        $i = $m[1];

        return $i.$marker."\n"
            .$i."\$forcedHttp = isset( \$options['http'] ) ? \$options['http'] : array();\n"
            .$i."\$callerOptions = is_array( \$this->options ) ? \$this->options : array();\n"
            .$i."\$callerHttp = isset( \$callerOptions['http'] ) ? \$callerOptions['http'] : array();\n"
            .$i."\$options = array_merge( \$callerOptions, \$options );\n"
            .$i."\$options['http'] = array_merge( array( 'timeout' => 600 ), \$callerHttp, \$forcedHttp );\n"
            .$i.'$context = stream_context_create( $options );';
    }, $code, 1);
    $done[] = 'timeout';
}

if ($done === []) {
    exit("Already fixed - nothing to do.\n");
}

if (! is_file($file.'.bak')) {
    copy($file, $file.'.bak');
}

file_put_contents($file, $code);

// Make sure the result is valid PHP; if not, put the original back.
exec('"'.PHP_BINARY.'" -l '.escapeshellarg($file).' 2>&1', $out, $status);

if ($status !== 0) {
    file_put_contents($file, $original);
    exit("The edited file was not valid PHP, so I put the original back. Please send public/apis/ripcord_client.php to Claude.\n");
}

echo 'Fixed ('.implode(' + ', $done)."). Backup saved as ripcord_client.php.bak\n";
