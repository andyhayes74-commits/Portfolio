<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Provider_Gemini_Direct {
    public function generate($project, $prompt, $ratio) {
        $api_key = trim((string) get_option(PAI_Constants::OPT_GEMINI_API_KEY, ''));
        $model = trim((string) get_option(PAI_Constants::OPT_GEMINI_MODEL, 'gemini-2.5-flash-image'));
        $limit = (int) get_option(PAI_Constants::OPT_GEMINI_PROMPT_LIMIT, 4000);
        $limit = $limit > 0 ? $limit : 4000;

        if (!$api_key) {
            return new WP_Error('pai_gemini_key', 'Gemini API key is not configured.');
        }

        if (!$model) {
            $model = 'gemini-2.5-flash-image';
        }

        $prompt_length = function_exists('mb_strlen') ? mb_strlen($prompt) : strlen($prompt);
        if ($prompt_length > $limit) {
            return new WP_Error('pai_gemini_prompt_too_long', 'Prompt is too long for Gemini. Shorten the project prompt or user request.');
        }

        $parts = array(array('text' => $prompt));
        $has_reference = false;

        if (!empty($project['reference_image_id'])) {
            $reference = PAI_Media::reference_part((int) $project['reference_image_id']);
            if (is_wp_error($reference)) {
                return $reference;
            }
            if ($reference) {
                $parts[] = $reference;
                $has_reference = true;
            }
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $body = array(
            'contents' => array(
                array('parts' => $parts),
            ),
        );

        PAI_Logger::log('info', 'Calling Gemini endpoint', array(
            'provider' => 'gemini_direct',
            'model' => $model,
            'prompt_length' => $prompt_length,
            'has_reference_image' => $has_reference,
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 180,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $api_key,
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        PAI_Logger::log($code >= 200 && $code < 300 ? 'info' : 'error', 'Gemini endpoint response', array(
            'provider' => 'gemini_direct',
            'status' => $code,
            'body_preview' => $this->safe_preview($json, $raw),
        ));

        if ($code < 200 || $code >= 300) {
            $message = isset($json['error']['message']) ? sanitize_text_field($json['error']['message']) : 'Gemini API request failed with HTTP ' . $code . '. Check Debug Logs.';
            return new WP_Error('pai_gemini_api', $message);
        }

        return $this->extract_image($json);
    }

    private function extract_image($json) {
        if (!is_array($json)) {
            return new WP_Error('pai_gemini_bad_json', 'Gemini returned an invalid response. Check Debug Logs.');
        }

        foreach (($json['candidates'] ?? array()) as $candidate) {
            $content = $candidate['content'] ?? array();
            foreach (($content['parts'] ?? array()) as $part) {
                $inline = $part['inlineData'] ?? ($part['inline_data'] ?? null);
                if (is_array($inline) && !empty($inline['data'])) {
                    return array('b64_json' => $inline['data']);
                }
            }
        }

        $text = $this->extract_text($json);
        if ($text) {
            PAI_Logger::log('error', 'Gemini returned text but no image', array('text_preview' => substr($text, 0, 500)));
        }

        return new WP_Error('pai_gemini_no_image', 'Gemini did not return an image. Check Debug Logs.');
    }

    private function extract_text($json) {
        $chunks = array();
        foreach (($json['candidates'] ?? array()) as $candidate) {
            $content = $candidate['content'] ?? array();
            foreach (($content['parts'] ?? array()) as $part) {
                if (!empty($part['text'])) {
                    $chunks[] = $part['text'];
                }
            }
        }
        return trim(implode("\n", $chunks));
    }

    private function safe_preview($json, $raw) {
        if (is_array($json)) {
            $copy = $json;
            $this->redact_images($copy);
            return substr(wp_json_encode($copy), 0, 1200);
        }
        return substr(wp_strip_all_tags($raw), 0, 1200);
    }

    private function redact_images(&$value) {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => &$child) {
            $lower = strtolower((string) $key);
            if ($lower === 'data' || strpos($lower, 'base64') !== false || strpos($lower, 'b64') !== false) {
                $child = '[redacted]';
            } elseif (is_array($child)) {
                $this->redact_images($child);
            }
        }
    }
}
