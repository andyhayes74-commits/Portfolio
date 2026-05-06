<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Admin_Help {
    public static function init() {
        add_action('admin_footer-settings_page_portfolio-ai-generator', array(__CLASS__, 'inject_help_text'));
    }

    public static function inject_help_text() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'projects';
        if ($tab !== 'projects') {
            return;
        }
        ?>
        <style>
            .pai-admin-help {
                max-width: 780px;
                margin-top: 6px;
                color: #646970;
                line-height: 1.5;
            }
        </style>
        <script>
        (function () {
            var help = {
                hidden_prompt: 'The private master prompt for this project. Visitors never see this. Use it to define the visual style, artistic direction, composition rules, colours, mood, quality targets, and anything the AI should consistently follow.',
                negative_prompt: 'Things the image generator should avoid. Useful for reducing common failures such as blurry images, distorted hands, incorrect art styles, watermarks, extra text, duplicated subjects, messy typography, photorealism, or low-quality outputs.',
                user_template: 'Controls how the visitor prompt is wrapped before generation. Keep {{user_prompt}} somewhere in this template so the visitor input is still included.',
                reference_image_id: 'Optional WordPress Media Library attachment ID. Gemini and OpenAI can use this image as a visual reference to improve style or subject consistency.',
                generation_format: 'Backend-controlled image format. Visitors do not choose this on the frontend.',
                daily_limit: 'Maximum generations allowed per visitor/IP per day for this project. Helps protect API usage and prevent abuse.',
                gallery_mode: 'Controls what happens after image generation. Pending requires admin approval, Approved publishes automatically, and Private hides images from public galleries.',
                frontend_heading: 'Public heading shown above the generator. Use this to give each project its own identity and tone.',
                frontend_description: 'Optional public description shown below the heading. Explain what visitors should create or what makes the project unique.',
                frontend_prompt_placeholder: 'Helper text shown inside the visitor prompt box.',
                frontend_generate_button: 'Custom text shown on the public generate button.',
                relevance_guard_mode: 'Optional safety layer before image generation. Smart mode uses AI to decide whether the prompt fits this project before spending image credits.',
                relevance_allowed_intent: 'Describe the type of prompts this project should allow. Write broad intent instead of listing every possible keyword.',
                relevance_rejection_message: 'Message visitors see when their prompt is rejected by the relevance guard.',
                relevance_basic_blocklist: 'Comma-separated terms blocked before generation. This is a lightweight local filter, not a full AI classifier.'
            };

            function addHelp(name, text) {
                var el = document.querySelector('[name="' + name + '"]');
                if (!el || el.dataset.paiHelpAdded === '1') {
                    return;
                }

                var helpEl = document.createElement('p');
                helpEl.className = 'description pai-admin-help';
                helpEl.textContent = text;

                el.insertAdjacentElement('afterend', helpEl);
                el.dataset.paiHelpAdded = '1';
            }

            Object.keys(help).forEach(function (key) {
                addHelp(key, help[key]);
            });
        })();
        </script>
        <?php
    }
}

PAI_Admin_Help::init();
