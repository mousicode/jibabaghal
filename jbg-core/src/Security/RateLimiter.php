<?php
namespace JBG\Security;


class RateLimiter {
/**
* Simple transient-based rate limiter per key.
*/
public static function check(string $key, int $max, int $seconds): bool {
$key = 'jbg_rl_' . md5($key);
$data = get_transient($key);
if (!$data) {
$data = ['count' => 1, 'reset' => time()+$seconds];
set_transient($key, $data, $seconds);
return true;
}
if ($data['reset'] < time()) {
$data = ['count' => 1, 'reset' => time()+$seconds];
set_transient($key, $data, $seconds);
return true;
}
if ($data['count'] >= $max) {
return false;
}
$data['count']++;
set_transient($key, $data, $data['reset'] - time());
return true;
}
}