<?php
namespace JBG\Security;


class Auth {
/**
* Basic REST permission checker. Use WordPress nonce or logged-in status.
* If $cap is provided, require that capability.
*/
public static function rest_permission(?string $cap = null): callable {
return function() use ($cap) {
// Allow application passwords or cookie auth via WP core
if (!is_user_logged_in()) return false;
if ($cap === null) return true;
return current_user_can($cap);
};
}


/**
* Public REST permission (no auth) — use carefully.
*/
public static function public_permission(): callable {
return '__return_true';
}
}