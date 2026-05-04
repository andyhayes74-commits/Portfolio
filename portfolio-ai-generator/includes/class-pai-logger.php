<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Logger {
    public static function log($level, $message, $data = array()) {
        if (!get_option(PAI_Constants::OPT_DEBUG)) {
            return;
        }

        $logs = get_option(PAI_Constants::OPT_LOGS, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        $logs[] = array(
            'time' => current_time('mysql'),
            'level' => sanitize_key($level),
            'message' => sanitize_text_field($message),
            'data' => self::safe_log_data($data),
        );

        update_option(PAI_Constants::OPT_LOGS, array_slice($logs, -100), false);
    }

    public static function clear() {
        delete_option(PAI_Constants::OPT_LOGS);
    }

    public static function all() {
        $logs = get_option(PAI_Constants::OPT_LOGS, array());
        return is_array($logs) ? $logs : array();
    }

    private static function safe_log_data($data) {
        if (!is_array($data)) {
            return array();
        }

        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);

            if (strpos($lower, 'key') !== false || strpos($lower, 'authorization') !== false || strpos($lower, 'token') !== false) {
                $data[$key] = '[redacted]';
                continue;
            }

            if (strpos($lower, 'prompt') !== false && $lower !== 'prompt_length') {
                $data[$key] = '[redacted]';
                continue;
            }

            if (strpos($lower, 'base64') !== false || strpos($lower, 'b64') !== false || strpos($lower, 'image_data') !== false) {
                $data[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::safe_log_data($value);
            } elseif (is_string($value)) {
                $data[$key] = substr($value, 0, 1200);
            }
        }

        return $data;
    }
}
