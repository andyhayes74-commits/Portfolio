<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Provider_OpenAI_Direct {
    public function generate($project, $prompt, $format) {
        $key = defined('PORTFOLIO_AI_OPENAI_API_KEY') ? PORTFOLIO_AI_OPENAI_API_KEY : get_option(PAI_Constants::OPT_OPENAI_API_KEY, '');
        $base = defined('PORTFOLIO_AI_OPENAI_BASE_URL') ? PORTFOLIO_AI_OPENAI_BASE_URL : get_option(PAI_Constants::OPT_OPENAI_BASE_URL, 'https://api.openai.com/v1');
        $model = get_option(PAI_Constants::OPT_OPENAI_MODEL, 'gpt-image-1-mini');
        $quality = get_option(PAI_Constants::OPT_OPENAI_QUALITY, 'medium');

        $key = trim((string) $key);
        $base = untrailingslashit(trim((string) $base));
        $model = trim((string) $model) ?: 'gpt-image-1-mini';
        $quality = in_array($quality, array('low', 'medium', 'high', 'auto'), true) ? $quality : 'medium';

        if (!$key) {
            return new WP_Error('pai_openai_key', 'OpenAI API key is not configured.');
        }

        if (!$base) {
            $base = 'https://api.openai.com/v1';
        }

        $url = $base . '/images/generations';
        $body = array(
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->size($format),
            'quality' => $quality,
        );

        PAI_Logger::log('info', 'Calling OpenAI image endpoint', array(
            'provider' => 'openai_direct',
            'model' => $model,
            'quality' => $quality,
            'generation_format' => $format,
            'size' => $body['size'],
            'has_reference_image' => !empty($project['reference_image_id']),
        ));

        if (!empty($project['reference_image_id'])) {
            PAI_Logger::log('info', 'OpenAI reference image note', array(
                'provider' => 'openai_direct',
                'message' => 'Reference image prompt guidance is included, but OpenAI Direct v1.4.1 uses text-to-image generation only. Gemini Direct currently sends the image bytes.',
            ));
        }

        $response = wp_remote_post($url, array(
            'timeout' => 180,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        PAI_Logger::log($code >= 200 && $code < 300 ? 'info' : 'error', 'OpenAI image endpoint response', array(
            'provider' => 'openai_direct',
            'status' => $code,
            'body_preview' => $this->safe_preview($raw),
        ));

        if ($code < 200 || $code >= 300) {
            $message = isset($json['error']['message']) ? sanitize_text_field($json['error']['message']) : 'OpenAI image request failed with HTTP ' . $code . '. Check Debug Logs.';
            return new WP_Error('pai_openai_api', $message);
        }

        return $this->extract_image_from_response($json, $raw);
    }

    private function size($format) {
        if ($format === 'landscape') {
            return '1536x1024';
        }

        if ($format === 'square') {
            return '1024x1024';
        }

        return '1024x1536';
    }

    private function extract_image_from_response($json, $raw) {
        if (is_array($json)) {
            if (!empty($json['data'][0]['url'])) {
                return array('url' => esc_url_raw($json['data'][0]['url']));
            }

            if (!empty($json['data'][0]['b64_json'])) {
                return array('b64_json' => $json['data'][0]['b64_json']);
            }

            if (!empty($json['b64_json'])) {
                return array('b64_json' => $json['b64_json']);
            }

            if (!empty($json['image'])) {
                return array('b64_json' => $json['image']);
            }
        }

        if (substr((string) $raw, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return array('binary' => $raw, 'mime' => 'image/png');
        }

        return new WP_Error('pai_openai_no_image', 'OpenAI response did not contain a recognised image URL or base64 image. Check Debug Logs.');
    }

    private function safe_preview($raw) {
        $safe = preg_replace('/"b64_json"\s*:\s*"[^"]+"/i', '"b64_json":"[redacted]"', (string) $raw);
        $safe = preg_replace('/"data"\s*:\s*"[^"]+"/i', '"data":"[redacted]"', (string) $safe);
        return substr(wp_strip_all_tags($safe), 0, 1200);
    }
}
