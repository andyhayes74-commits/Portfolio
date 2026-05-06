<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Relevance_Guard {
    public static function check($project, $user_prompt) {
        $mode = sanitize_key($project['relevance_guard_mode'] ?? 'off');

        if ($mode === 'off') {
            return array(
                'decision' => 'allow',
                'reason' => 'Relevance guard disabled.',
            );
        }

        $basic = self::basic_check($project, $user_prompt);

        if ($basic['decision'] === 'reject') {
            PAI_Logger::log('info', 'Prompt rejected by basic relevance guard', array(
                'project' => $project['slug'] ?? '',
                'decision' => 'reject',
                'reason' => $basic['reason'],
            ));

            return $basic;
        }

        if ($mode === 'basic') {
            return array(
                'decision' => 'allow',
                'reason' => 'Passed basic relevance guard.',
            );
        }

        return self::smart_check($project, $user_prompt);
    }

    private static function basic_check($project, $user_prompt) {
        $blocked = strtolower((string) ($project['relevance_basic_blocklist'] ?? ''));
        $prompt = strtolower($user_prompt);

        if (!$blocked) {
            return array(
                'decision' => 'allow',
                'reason' => 'No blocked terms configured.',
            );
        }

        $terms = array_filter(array_map('trim', explode(',', $blocked)));

        foreach ($terms as $term) {
            if ($term !== '' && strpos($prompt, strtolower($term)) !== false) {
                return array(
                    'decision' => 'reject',
                    'reason' => 'Blocked term detected: ' . $term,
                );
            }
        }

        return array(
            'decision' => 'allow',
            'reason' => 'Passed blocked-term scan.',
        );
    }

    private static function smart_check($project, $user_prompt) {
        $intent = trim((string) ($project['relevance_allowed_intent'] ?? ''));
        if ($intent === '') {
            return array(
                'decision' => 'allow',
                'reason' => 'No allowed intent configured.',
            );
        }

        $provider = PAI_Projects::resolve_provider($project);

        $prompt = "You are a prompt relevance classifier for a WordPress image-generation plugin.\n\n"
            . "Project intent:\n" . $intent . "\n\n"
            . "User prompt:\n" . $user_prompt . "\n\n"
            . "Decide whether the user prompt fits the project intent.\n"
            . "Be permissive when the prompt could reasonably fit the project.\n"
            . "Return ONLY valid JSON using this exact format:\n"
            . "{\"decision\":\"allow\",\"reason\":\"short reason\"}\n"
            . "or\n"
            . "{\"decision\":\"reject\",\"reason\":\"short reason\"}";

        if ($provider === 'gemini_direct') {
            $result = self::gemini_text_check($prompt);
        } elseif ($provider === 'openai_direct') {
            $result = self::openai_text_check($prompt);
        } else {
            return array(
                'decision' => 'allow',
                'reason' => 'Smart relevance check unavailable for provider.',
            );
        }

        if (is_wp_error($result)) {
            PAI_Logger::log('error', 'Smart relevance check failed', array(
                'project' => $project['slug'] ?? '',
                'provider' => $provider,
                'error' => $result->get_error_message(),
            ));

            return array(
                'decision' => 'allow',
                'reason' => 'Classifier failed open.',
            );
        }

        PAI_Logger::log('info', 'Smart relevance decision', array(
            'project' => $project['slug'] ?? '',
            'provider' => $provider,
            'decision' => $result['decision'] ?? 'allow',
            'reason' => $result['reason'] ?? '',
        ));

        return $result;
    }

    private static function gemini_text_check($prompt) {
        $api_key = trim((string) get_option(PAI_Constants::OPT_GEMINI_API_KEY, ''));
        if ($api_key === '') {
            return new WP_Error('missing_gemini_key', 'Gemini API key missing.');
        }

        $response = wp_remote_post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
            array(
                'timeout' => 25,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $api_key,
                ),
                'body' => wp_json_encode(array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array('text' => $prompt),
                            ),
                        ),
                    ),
                )),
            )
        );

        return self::parse_text_response($response, 'gemini');
    }

    private static function openai_text_check($prompt) {
        $api_key = trim((string) get_option(PAI_Constants::OPT_OPENAI_API_KEY, ''));
        if ($api_key === '') {
            return new WP_Error('missing_openai_key', 'OpenAI API key missing.');
        }

        $base = untrailingslashit((string) get_option(PAI_Constants::OPT_OPENAI_BASE_URL, 'https://api.openai.com/v1'));

        $response = wp_remote_post(
            $base . '/chat/completions',
            array(
                'timeout' => 25,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'body' => wp_json_encode(array(
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => $prompt,
                        ),
                    ),
                )),
            )
        );

        return self::parse_text_response($response, 'openai');
    }

    private static function parse_text_response($response, $provider) {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('provider_error', 'Classifier provider returned HTTP ' . $code);
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return new WP_Error('invalid_json', 'Invalid classifier JSON response.');
        }

        $content = '';

        if ($provider === 'gemini') {
            $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            $content = $json['choices'][0]['message']['content'] ?? '';
        }

        $decoded = json_decode(trim($content), true);

        if (!is_array($decoded)) {
            return new WP_Error('invalid_classifier_output', 'Classifier did not return valid JSON.');
        }

        $decision = sanitize_key($decoded['decision'] ?? 'allow');

        return array(
            'decision' => $decision === 'reject' ? 'reject' : 'allow',
            'reason' => sanitize_text_field($decoded['reason'] ?? ''),
        );
    }
}
