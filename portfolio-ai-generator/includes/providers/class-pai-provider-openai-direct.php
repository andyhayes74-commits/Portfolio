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
            return new WP_Error('pai_openai_key', 'OpenAI key is not configured.');
        }

        if (!$base) {
            $base = 'https://api.openai.com/v1';
        }

        $reference = $this->reference_image_file($project);
        $has_reference = !empty($reference['path']);

        PAI_Logger::log('info', 'Calling OpenAI image endpoint', array(
            'provider' => 'openai_direct',
            'model' => $model,
            'quality' => $quality,
            'generation_format' => $format,
            'size' => $this->size($format),
            'has_reference_image' => $has_reference,
            'request_mode' => $has_reference ? 'edit_with_reference' : 'generate',
        ));

        if ($has_reference) {
            return $this->generate_with_reference($base, $key, $model, $quality, $prompt, $format, $reference);
        }

        return $this->generate_text_only($base, $key, $model, $quality, $prompt, $format);
    }

    private function generate_text_only($base, $key, $model, $quality, $prompt, $format) {
        $url = $base . '/images/generations';
        $body = array(
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->size($format),
            'quality' => $quality,
        );

        $response = wp_remote_post($url, array(
            'timeout' => 180,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));

        return $this->handle_response($response, 'OpenAI image endpoint response');
    }

    private function generate_with_reference($base, $key, $model, $quality, $prompt, $format, $reference) {
        $url = $base . '/images/edits';

        $fields = array(
            'model' => $model,
            'prompt' => $prompt,
            'size' => $this->size($format),
            'quality' => $quality,
            'n' => '1',
        );

        $contents = file_get_contents($reference['path']);
        if ($contents === false) {
            return new WP_Error('pai_openai_reference_read', 'Could not read the OpenAI reference image from the Media Library.');
        }

        $multipart = $this->build_multipart_body($fields, array(
            'field_name' => 'image',
            'filename' => basename($reference['path']),
            'mime' => $reference['mime'],
            'contents' => $contents,
        ));

        $response = wp_remote_post($url, array(
            'timeout' => 180,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'multipart/form-data; boundary=' . $multipart['boundary'],
            ),
            'body' => $multipart['body'],
        ));

        return $this->handle_response($response, 'OpenAI image edit endpoint response');
    }

    private function handle_response($response, $log_message) {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        PAI_Logger::log($code >= 200 && $code < 300 ? 'info' : 'error', $log_message, array(
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

    private function reference_image_file($project) {
        $attachment_id = absint($project['reference_image_id'] ?? 0);
        if (!$attachment_id) {
            return array();
        }

        $path = get_attached_file($attachment_id);
        if (!$path || !file_exists($path) || !is_readable($path)) {
            PAI_Logger::log('error', 'OpenAI reference image missing', array(
                'provider' => 'openai_direct',
                'attachment_id' => $attachment_id,
            ));
            return array();
        }

        $mime = get_post_mime_type($attachment_id);
        if (!$mime || strpos($mime, 'image/') !== 0) {
            $mime = $this->detect_mime($path);
        }

        $mime = $this->normalize_mime($mime);
        if (!in_array($mime, array('image/png', 'image/jpeg', 'image/webp'), true)) {
            PAI_Logger::log('error', 'OpenAI reference image has unsupported mime type', array(
                'provider' => 'openai_direct',
                'attachment_id' => $attachment_id,
                'mime' => $mime,
            ));
            return array();
        }

        return array(
            'path' => $path,
            'mime' => $mime,
        );
    }

    private function detect_mime($path) {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if ($mime) {
                return $mime;
            }
        }

        $check = wp_check_filetype($path);
        if (!empty($check['type'])) {
            return $check['type'];
        }

        return 'application/octet-stream';
    }

    private function normalize_mime($mime) {
        $mime = strtolower((string) $mime);
        if ($mime === 'image/jpg') {
            return 'image/jpeg';
        }
        return $mime;
    }

    private function build_multipart_body($fields, $file) {
        $boundary = 'pai_' . wp_generate_password(24, false, false);
        $eol = "\r\n";
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
            $body .= (string) $value . $eol;
        }

        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . $file['field_name'] . '"; filename="' . $file['filename'] . '"' . $eol;
        $body .= 'Content-Type: ' . $file['mime'] . $eol . $eol;
        $body .= $file['contents'] . $eol;
        $body .= '--' . $boundary . '--' . $eol;

        return array(
            'boundary' => $boundary,
            'body' => $body,
        );
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
