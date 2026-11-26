<?php
// The messages of ExmentPMBacklog as they are kept, logged and shown (Secrets::scrub): no secret.
//
//   php project-management/tools/test-pmbl-secrets.php
//
// Plain PHP.

require __DIR__ . '/../ExmentPMBacklog/Secrets.php';

use App\Plugins\ExmentPMBacklog\Secrets;

$failed = 0;
function check(string $name, bool $ok): void
{
    global $failed;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . "\n";
    $failed += $ok ? 0 : 1;
}

$key = 'AbCdEf0123456789ZyXwVuTsRqPoNmLkJiHgFeDcBa';
$curl = 'cURL error 6: Could not resolve host: example.invalid (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://example.invalid/api/v2/issues?projectId%5B%5D=1&count=100&apiKey=' . $key;
$out = Secrets::scrub($curl, [$key]);
check('a connection error: no key', !str_contains($out, $key) && str_contains($out, 'apiKey=***'));
check('a connection error: the rest kept', str_contains($out, 'Could not resolve host: example.invalid') && str_contains($out, 'count=100'));

// the key known or not: the parameter is taken out
check('apiKey without the key given', Secrets::scrub('GET /x?apiKey=' . $key) === 'GET /x?apiKey=***');
check('other names', Secrets::scrub('a?key=s1&token=s2&access_token=s3&b=1') === 'a?key=***&token=***&access_token=***&b=1');
check('a key in a body', Secrets::scrub('{"error":"bad key ' . $key . '"}', [$key]) === '{"error":"bad key ***"}');
check('the key url-encoded', Secrets::scrub('x=' . rawurlencode('k+y/z=abc'), ['k+y/z=abc']) === 'x=***');
check('an Authorization header', Secrets::scrub('Authorization: Bearer eyJabc.def') === 'Authorization: ***');
check('a token header', Secrets::scrub('X-Cybozu-API-Token: abc123') === 'X-Cybozu-API-Token: ***');
check('nothing to take out', Secrets::scrub('Backlog 500 issues: fake failure') === 'Backlog 500 issues: fake failure');
check('a short value is not taken out of every word', Secrets::scrub('the issue', ['is']) === 'the issue');

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
