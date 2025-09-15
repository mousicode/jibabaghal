<?php
namespace JBG\Admin;


class Menu {
public static function register(): void {
add_menu_page(
__('JBG','jbg-core'),
__('JBG','jbg-core'),
'manage_options',
'jbg-core',
[self::class, 'render_health'],
'dashicons-shield',
58
);
}


public static function render_health(): void {
    if (!current_user_can('manage_options')) { wp_die(__('Unauthorized','jbg-core')); }

    // فراخوانی داخلی REST بدون نیاز به کوکی/nonce
    $req = new \WP_REST_Request('GET', '/jbg/v1/health');
    $res = rest_do_request($req);

    $ok = (!is_wp_error($res) && method_exists($res, 'get_status') && 200 === $res->get_status());
    $data = $ok && method_exists($res, 'get_data') ? $res->get_data() : null;

    echo '<div class="wrap"><h1>JBG — Health</h1>';
    if (!$ok) {
        echo '<p style="color:#b32d2e">Health endpoint failed (internal). Check permissions or REST route.</p>';
    } else {
        echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:60vh;overflow:auto">' .
             esc_html( wp_json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ) .
             '</pre>';
    }
    echo '</div>';
}
}