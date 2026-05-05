<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Provider_Custom_Route {
    public function generate($project, $prompt, $format) {
        $base = defined('PORTFOLIO_AI_LITELLM_BASE_URL') ? PORTFOLIO_AI_LITELLM_BASE_URL : get_option(PAI_Constants::OPT_BASE_URL, '');
        $key = defined('PORTFOLIO_AI_LITELLM_API_KEY') ? PORTFOLIO_AI_LITELLM_API_KEY : get_option(PAI_Constants::OPT_API_KEY, '');
        $endpoint = trim((string) get_option(PAI_Constants::OPT_ENDPOINT_PATH, '/v1/images/generations'));
        $url = preg_match('#^https?://#i', $endpoint) ? $endpoint : untrailingslashit(trim($base)) . '/' . ltrim($endpoint ?: '/v1/images/generations', '/');

        if (!$url) {
            return new WP_Error('pai_config', 'Image generation endpoint is not configured.');
        }

        $mode = $this->endpoint_mode($endpoint);
        $body = $mode === 'nvidia_flux'
            ? $this->custom_image_body($prompt, $format)
            : $this->openai_image_body($project, $prompt, $format);

        $headers = array('Content-Type' => 'application/json');
        $auth = $this->auth_header($key, $endpoint);
        if ($auth) {
            $headers['Authorization'] = $auth;
        }

        PAI_Logger::log('info', 'Calling image endpoint', array(
            'provider' => 'custom_route',
            'url' => $url,
            'endpoint_mode' => $mode,
            'auth_mode' => $this->auth_mode($endpoint),
            'generation_format' => $format,
            'body_keys' => array_keys($body),
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 180,
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        PAI_Logger::log($code >= 200 && $code < 300 ? 'info' : 'error', 'Image endpoint response', array(
            'provider' => 'custom_route',
            'status' => $code,
            'body_preview' => substr(wp_strip_all_tags($raw), 0, 1200),
        ));

        if ($code < 200 || $code >= 300) {
            $message = isset($json['error']['message']) ? sanitize_text_field($json['error']['message']) : 'Image API request failed with HTTP ' . $code . '. Check Debug Logs.';
            return new WP_Error('pai_api', $message);
        }

        return $this->extract_image_from_response($json, $raw);
    }

    private function openai_image_body($project, $prompt, $format) {
        $body = array(
            'model' => $project['model_name'],
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->openai_size($format),
            'response_format' => 'url',
        );

        if (!empty($project['negative_prompt'])) {
            $body['negative_prompt'] = $project['negative_prompt'];
        }

        if (!empty($project['reference_image_id'])) {
            $ref = wp_get_attachment_url((int) $project['reference_image_id']);
            if ($ref) {
                $body['reference_image_url'] = esc_url_raw($ref);
            }
        }

        return $body;
    }

    private function custom_image_body($prompt, $format) {
        $dims = PAI_Projects::format_size($format);
        return array(
            'prompt' => $prompt,
            'width' => $dims[0],
            'height' => $dims[1],
            'samples' => 1,
            'steps' => 4,
            'seed' => 0,
        );
    }

    private function extract_image_from_response($json, $raw) {
        if (is_array($json)) {
            if (!empty($json['data'][0]['url'])) return array('url' => esc_url_raw($json['data'][0]['url']));
            if (!empty($json['data'][0]['b64_json'])) return array('b64_json' => $json['data'][0]['b64_json']);
            if (!empty($json['url'])) return array('url' => esc_url_raw($json['url']));
            if (!empty($json['image_url'])) return array('url' => esc_url_raw($json['image_url']));
            if (!empty($json['b64_json'])) return array('b64_json' => $json['b64_json']);
            if (!empty($json['image'])) return array('b64_json' => $json['image']);
            if (!empty($json['images'][0]['url'])) return array('url' => esc_url_raw($json['images'][0]['url']));
            if (!empty($json['images'][0]['b64_json'])) return array('b64_json' => $json['images'][0]['b64_json']);
            if (!empty($json['images'][0]['base64'])) return array('b64_json' => $json['images'][0]['base64']);
            if (!empty($json['artifacts'][0]['base64'])) return array('b64_json' => $json['artifacts'][0]['base64']);
            if (!empty($json['output'][0]) && is_string($json['output'][0])) {
                return preg_match('#^https?://#', $json['output'][0])
                    ? array('url' => esc_url_raw($json['output'][0]))
                    : array('b64_json' => $json['output'][0]);
            }
        }

        if (substr((string) $raw, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return array('binary' => $raw, 'mime' => 'image/png');
        }

        return new WP_Error('pai_no_image', 'Image API response did not contain a recognised image URL or base64 image. Check Debug Logs.');
    }

    private function openai_size($format) {
        if ($format === 'landscape') {
            return '1024x768';
        }

        if ($format === 'portrait') {
            return '768x1024';
        }

        return '1024x1024';
    }

    private function endpoint_mode($endpoint) {
        $mode = get_option(PAI_Constants::OPT_ENDPOINT_MODE, 'auto');
        if ($mode === 'auto') {
            return stripos((string) $endpoint, 'nvidia-flux') !== false ? 'nvidia_flux' : 'openai';
        }
        return in_array($mode, array('openai', 'nvidia_flux'), true) ? $mode : 'openai';
    }

    private function auth_mode($endpoint) {
        $mode = get_option(PAI_Constants::OPT_AUTH_MODE, 'auto');
        if ($mode === 'auto') {
            return stripos((string) $endpoint, 'nvidia-flux') !== false ? 'raw' : 'bearer';
        }
        return in_array($mode, array('bearer', 'raw', 'none'), true) ? $mode : 'bearer';
    }

    private function auth_header($key, $endpoint) {
        $key = trim((string) $key);
        $mode = $this->auth_mode($endpoint);
        if ($mode === 'none' || !$key) {
            return '';
        }
        return $mode === 'raw' ? $key : 'Bearer ' . $key;
    }
}
