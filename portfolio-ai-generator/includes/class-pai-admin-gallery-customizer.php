<?php
if (!defined('ABSPATH')) {
    exit;
}

final class PAI_Admin_Gallery_Customizer {
    public static function init() {
        add_action('admin_footer-settings_page_portfolio-ai-generator', array(__CLASS__, 'render_project_gallery_fields'));
    }

    public static function render_project_gallery_fields() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'projects';
        if ($tab !== 'projects') {
            return;
        }

        $edit = isset($_GET['edit']) ? sanitize_key(wp_unslash($_GET['edit'])) : '';
        $project = $edit ? PAI_Projects::get($edit) : PAI_Projects::defaults();
        if (!$project) {
            $project = PAI_Projects::defaults();
        }

        $project = wp_parse_args($project, PAI_Projects::defaults($edit));
        ?>
        <script>
        (function () {
            var marker = document.querySelector('h2 + table.form-table');
            var headings = Array.prototype.slice.call(document.querySelectorAll('h2'));
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

            if (document.getElementById('pai-gallery-customisation-fields')) {
                return;
            }

            function option(value, label, selected) {
                return '<option value="' + value + '"' + (String(value) === String(selected) ? ' selected' : '') + '>' + label + '</option>';
            }

            function select(name, selected, options) {
                var html = '<select name="' + name + '">';
                options.forEach(function (item) {
                    html += option(item[0], item[1], selected);
                });
                html += '</select>';
                return html;
            }

            function input(name, value, type, attrs) {
                return '<input type="' + (type || 'text') + '" name="' + name + '" value="' + String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '" ' + (attrs || '') + '>';
            }

            var values = <?php echo wp_json_encode(array(
                'desktop_columns' => (int) ($project['gallery_desktop_columns'] ?? 3),
                'tablet_columns' => (int) ($project['gallery_tablet_columns'] ?? 2),
                'mobile_columns' => (int) ($project['gallery_mobile_columns'] ?? 1),
                'gap' => (string) ($project['gallery_gap'] ?? 'medium'),
                'crop_mode' => (string) ($project['gallery_crop_mode'] ?? 'cover'),
                'max_width' => (string) ($project['gallery_max_width'] ?? 'full'),
                'alignment' => (string) ($project['gallery_alignment'] ?? 'center'),
                'background_color' => (string) ($project['gallery_background_color'] ?? 'transparent'),
                'card_background_color' => (string) ($project['gallery_card_background_color'] ?? 'rgba(255,255,255,0.06)'),
                'card_text_color' => (string) ($project['gallery_card_text_color'] ?? 'inherit'),
                'card_border_color' => (string) ($project['gallery_card_border_color'] ?? 'rgba(255,255,255,0.16)'),
                'card_border_enabled' => !empty($project['gallery_card_border_enabled']) ? 1 : 0,
                'card_radius' => (int) ($project['gallery_card_radius'] ?? 16),
                'card_padding' => (string) ($project['gallery_card_padding'] ?? 'none'),
                'card_shadow' => (string) ($project['gallery_card_shadow'] ?? 'none'),
                'caption_position' => (string) ($project['gallery_caption_position'] ?? 'below'),
                'caption_color' => (string) ($project['gallery_caption_color'] ?? 'inherit'),
                'caption_background_color' => (string) ($project['gallery_caption_background_color'] ?? 'rgba(0,0,0,0.58)'),
                'caption_text_size' => (string) ($project['gallery_caption_text_size'] ?? 'small'),
                'caption_words' => (int) ($project['gallery_caption_words'] ?? 10),
            )); ?>;

            var layoutRows = '';
            layoutRows += '<h2 id="pai-gallery-customisation-fields">Gallery Layout & Style</h2>';
            layoutRows += '<table class="form-table"><tbody>';
            layoutRows += '<tr><th>Columns</th><td>';
            layoutRows += '<label>Desktop ' + input('gallery_desktop_columns', values.desktop_columns, 'number', 'min="1" max="6" style="width:70px"') + '</label> ';
            layoutRows += '<label>Tablet ' + input('gallery_tablet_columns', values.tablet_columns, 'number', 'min="1" max="4" style="width:70px"') + '</label> ';
            layoutRows += '<label>Mobile ' + input('gallery_mobile_columns', values.mobile_columns, 'number', 'min="1" max="2" style="width:70px"') + '</label>';
            layoutRows += '<p class="description">Controls the public gallery columns per project.</p></td></tr>';
            layoutRows += '<tr><th>Spacing and width</th><td>';
            layoutRows += '<label>Gap ' + select('gallery_gap', values.gap, [['none','None'],['small','Small'],['medium','Medium'],['large','Large']]) + '</label> ';
            layoutRows += '<label>Max width ' + select('gallery_max_width', values.max_width, [['full','Full'],['wide','Wide'],['contained','Contained']]) + '</label> ';
            layoutRows += '<label>Alignment ' + select('gallery_alignment', values.alignment, [['left','Left'],['center','Centre']]) + '</label>';
            layoutRows += '</td></tr>';
            layoutRows += '<tr><th>Image fit</th><td>' + select('gallery_crop_mode', values.crop_mode, [['cover','Cover / crop'],['contain','Contain / no crop']]) + '<p class="description">Cover fills the card. Contain preserves the full image.</p></td></tr>';
            layoutRows += '<tr><th>Gallery background</th><td>' + input('gallery_background_color', values.background_color, 'text', 'class="regular-text"') + '<p class="description">Use hex, rgb, rgba, transparent, or inherit.</p></td></tr>';
            layoutRows += '<tr><th>Card colours</th><td>';
            layoutRows += '<label>Background ' + input('gallery_card_background_color', values.card_background_color, 'text', 'class="regular-text"') + '</label><br><br>';
            layoutRows += '<label>Text ' + input('gallery_card_text_color', values.card_text_color, 'text', 'class="regular-text"') + '</label><br><br>';
            layoutRows += '<label>Border ' + input('gallery_card_border_color', values.card_border_color, 'text', 'class="regular-text"') + '</label><br>';
            layoutRows += '<label><input type="checkbox" name="gallery_card_border_enabled" value="1"' + (parseInt(values.card_border_enabled, 10) === 1 ? ' checked' : '') + '> Show card border</label>';
            layoutRows += '</td></tr>';
            layoutRows += '<tr><th>Card shape</th><td>';
            layoutRows += '<label>Radius ' + input('gallery_card_radius', values.card_radius, 'number', 'min="0" max="60" style="width:90px"') + ' px</label> ';
            layoutRows += '<label>Padding ' + select('gallery_card_padding', values.card_padding, [['none','None'],['small','Small'],['medium','Medium'],['large','Large']]) + '</label> ';
            layoutRows += '<label>Shadow ' + select('gallery_card_shadow', values.card_shadow, [['none','None'],['soft','Soft'],['strong','Strong']]) + '</label>';
            layoutRows += '</td></tr>';
            layoutRows += '<tr><th>Caption styling</th><td>';
            layoutRows += '<label>Position ' + select('gallery_caption_position', values.caption_position, [['below','Below image'],['overlay','Overlay bottom']]) + '</label> ';
            layoutRows += '<label>Text size ' + select('gallery_caption_text_size', values.caption_text_size, [['small','Small'],['medium','Medium'],['large','Large']]) + '</label> ';
            layoutRows += '<label>Prompt words ' + input('gallery_caption_words', values.caption_words, 'number', 'min="3" max="40" style="width:80px"') + '</label><br><br>';
            layoutRows += '<label>Text colour ' + input('gallery_caption_color', values.caption_color, 'text', 'class="regular-text"') + '</label><br><br>';
            layoutRows += '<label>Overlay background ' + input('gallery_caption_background_color', values.caption_background_color, 'text', 'class="regular-text"') + '</label>';
            layoutRows += '</td></tr>';
            layoutRows += '</tbody></table>';

            galleryTable.insertAdjacentHTML('afterend', layoutRows);
        })();
        </script>
        <?php
    }
}

PAI_Admin_Gallery_Customizer::init();
