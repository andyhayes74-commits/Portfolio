<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Admin_V16_UI {
    public static function init() {
        add_action('admin_footer-settings_page_portfolio-ai-generator', array(__CLASS__, 'inject_ui'));
    }

    public static function inject_ui() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'projects';
        if ($tab !== 'projects') {
            return;
        }

        $edit = isset($_GET['edit']) ? sanitize_key(wp_unslash($_GET['edit'])) : '';
        ?>
        <script>
        (function () {
            var headings = Array.prototype.slice.call(document.querySelectorAll('h2'));
            var projectHeading = headings.find(function (heading) {
                return heading.textContent.trim() === 'Add project';
            });

            if (projectHeading && '<?php echo esc_js($edit); ?>' === '') {
                var projectForm = projectHeading.nextElementSibling;
                if (projectForm && projectForm.tagName === 'FORM') {
                    projectForm.style.display = 'none';

                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'button button-primary';
                    button.textContent = 'Add New Project';
                    button.style.marginBottom = '18px';

                    button.addEventListener('click', function () {
                        projectForm.style.display = projectForm.style.display === 'none' ? 'block' : 'none';
                        button.textContent = projectForm.style.display === 'none' ? 'Add New Project' : 'Hide New Project Form';
                    });

                    projectHeading.parentNode.insertBefore(button, projectHeading);
                    projectHeading.style.display = 'none';
                }
            }

            var galleryHeading = headings.find(function (heading) {
                return heading.textContent.trim() === 'Gallery Display Settings';
            });

            if (!galleryHeading) {
                return;
            }

            var galleryTable = galleryHeading.nextElementSibling;
            if (!galleryTable || !galleryTable.classList.contains('form-table')) {
                return;
            }

            if (document.getElementById('pai-v16-extra-fields')) {
                return;
            }

            function input(name, value, attrs) {
                return '<input class="regular-text" name="' + name + '" value="' + String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '" ' + (attrs || '') + '>';
            }

            function textarea(name, value, rows) {
                return '<textarea class="large-text" rows="' + (rows || 3) + '" name="' + name + '">' + String(value).replace(/</g, '&lt;') + '</textarea>';
            }

            function select(name, selected, options) {
                var html = '<select name="' + name + '">';
                options.forEach(function (item) {
                    html += '<option value="' + item[0] + '"' + (String(item[0]) === String(selected) ? ' selected' : '') + '>' + item[1] + '</option>';
                });
                html += '</select>';
                return html;
            }

            var values = {
                frontend_heading: document.querySelector('[name="name"]') ? 'Create an image in the ' + document.querySelector('[name="name"]').value + ' style' : '',
                frontend_description: '',
                frontend_prompt_placeholder: 'Describe the image',
                frontend_generate_button: 'Generate Image',
                relevance_guard_mode: 'off',
                relevance_allowed_intent: '',
                relevance_rejection_message: 'That prompt does not fit this project.',
                relevance_basic_blocklist: 'logo, website, app ui, code, essay, cv, weapon'
            };

            var hiddenPromptField = document.querySelector('[name="hidden_prompt"]');
            if (hiddenPromptField && hiddenPromptField.dataset.v16Processed !== '1') {
                hiddenPromptField.dataset.v16Processed = '1';
            }

            var html = '';
            html += '<div id="pai-v16-extra-fields">';
            html += '<h2>Frontend Text & Branding</h2>';
            html += '<table class="form-table"><tbody>';
            html += '<tr><th>Generator heading</th><td>' + input('frontend_heading', values.frontend_heading) + '</td></tr>';
            html += '<tr><th>Generator description</th><td>' + textarea('frontend_description', values.frontend_description, 3) + '</td></tr>';
            html += '<tr><th>Prompt placeholder</th><td>' + input('frontend_prompt_placeholder', values.frontend_prompt_placeholder) + '</td></tr>';
            html += '<tr><th>Generate button text</th><td>' + input('frontend_generate_button', values.frontend_generate_button) + '</td></tr>';
            html += '</tbody></table>';

            html += '<h2>Prompt Relevance & Safety</h2>';
            html += '<table class="form-table"><tbody>';
            html += '<tr><th>Relevance guard</th><td>' + select('relevance_guard_mode', values.relevance_guard_mode, [['off','Off'],['basic','Basic local filter'],['smart','Smart AI check']]) + '<p class="description">Smart AI check uses the project provider before image generation.</p></td></tr>';
            html += '<tr><th>Allowed prompt intent</th><td>' + textarea('relevance_allowed_intent', values.relevance_allowed_intent, 5) + '<p class="description">Describe the type of prompts this project should allow.</p></td></tr>';
            html += '<tr><th>Rejection message</th><td>' + input('relevance_rejection_message', values.relevance_rejection_message) + '</td></tr>';
            html += '<tr><th>Basic blocked terms</th><td>' + textarea('relevance_basic_blocklist', values.relevance_basic_blocklist, 3) + '<p class="description">Comma-separated terms rejected before generation.</p></td></tr>';
            html += '</tbody></table>';
            html += '</div>';

            galleryTable.insertAdjacentHTML('afterend', html);
        })();
        </script>
        <?php
    }
}

PAI_Admin_V16_UI::init();
