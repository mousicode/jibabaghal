<?php
namespace JBG\Logging;


class Logger {
public static function log(string $channel, string $message, array $context = []): void {
$uploads = wp_get_upload_dir();
$dir = trailingslashit($uploads['basedir']) . 'jbg-logs';
if (!file_exists($dir)) wp_mkdir_p($dir);
$file = $dir . '/'. sanitize_key($channel) . '-' . gmdate('Y-m-d') . '.log';
$line = '['. gmdate('c') . "] $message";
if ($context) $line .= ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$line .= "\n";
// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fopen
@file_put_contents($file, $line, FILE_APPEND);
}
}