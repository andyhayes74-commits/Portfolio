<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Media {
    public static function save_generated_image($result, $slug, $id) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $filename = 'portfolio-ai-' . sanitize_file_name($slug) . '-' . (int) $id . '-' . time() . '.png';

        if (!empty($result['url'])) {
            return self::save_from_url($result['url'], $filename);
        }

        if (!empty($result['b64_json'])) {
            $binary = base64_decode($result['b64_json'], true);
            if (!$binary) {
                return new WP_Error('pai_b64', 'Invalid base64 image.');
            }
            return self::save_binary($binary, $filename);
        }

        if (!empty($result['binary'])) {
            return self::save_binary($result['binary'], $filename);
        }

        return new WP_Error('pai_missing_image', 'No image data was available to save.');
    }

    private static function save_from_url($url, $filename) {
        $tmp = download_url($url, 120);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $file = array(
            'name' => $filename,
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => 0,
            'size' => filesize($tmp),
        );

        $attachment_id = media_handle_sideload($file, 0, null, array('post_title' => $filename));
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return $attachment_id;
        }

        return array(
            'attachment_id' => (int) $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'path' => get_attached_file($attachment_id) ?: '',
        );
    }

    private static function save_binary($binary, $filename) {
        $upload = wp_upload_bits($filename, null, $binary);
        if (!empty($upload['error'])) {
            return new WP_Error('pai_upload', $upload['error']);
        }

        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => 'image/png',
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_status' => 'inherit',
        ), $upload['file']);

        if (!is_wp_error($attachment_id)) {
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
        }

        return array(
            'attachment_id' => (int) $attachment_id,
            'url' => $upload['url'],
            'path' => $upload['file'],
        );
    }

    public static function reference_part($attachment_id) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) {
            return null;
        }

        $path = get_attached_file($attachment_id);
        if (!$path || !is_readable($path)) {
            return new WP_Error('pai_reference_missing', 'Reference image file could not be read.');
        }

        $mime = get_post_mime_type($attachment_id);
        if (!$mime || strpos($mime, 'image/') !== 0) {
            return new WP_Error('pai_reference_not_image', 'Reference attachment is not an image.');
        }

        $bytes = file_get_contents($path);
        if (!$bytes) {
            return new WP_Error('pai_reference_empty', 'Reference image file was empty.');
        }

        return array(
            'inlineData' => array(
                'mimeType' => $mime,
                'data' => base64_encode($bytes),
            ),
        );
    }
}
