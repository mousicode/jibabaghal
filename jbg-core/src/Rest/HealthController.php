<?php
namespace JBG\Rest;


use JBG\Security\Auth;


class HealthController {
public static function register_routes(): void {
register_rest_route('jbg/v1', '/health', [
'methods' => 'GET',
'callback' => [self::class, 'get_health'],
'permission_callback' => Auth::rest_permission('manage_options'),
]);
}


public static function get_health(\WP_REST_Request $req) {
global $wpdb;
$uploads = wp_get_upload_dir();
$ok_logs = is_dir(trailingslashit($uploads['basedir']).'jbg-logs');
$resp = [
'plugin' => 'jbg-core',
'version' => JBG_CORE_VERSION,
'wp' => get_bloginfo('version'),
'php' => PHP_VERSION,
'db_version' => (int) get_option('jbg_core_db_version', 0),
'time_utc' => gmdate('c'),
'uploads' => $uploads,
'logs_dir_ok' => $ok_logs,
'db' => [
'prefix' => $wpdb->prefix,
'charset' => $wpdb->charset,
'collate' => $wpdb->collate,
],
];
return new \WP_REST_Response($resp, 200);
}
}