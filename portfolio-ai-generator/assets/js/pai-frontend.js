(function ($) {
    'use strict';

    function setStatus($box, message, isError) {
        $box.text(message || '').toggleClass('pai-status--error', !!isError);
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function errorMessage(xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            return xhr.responseJSON.data.message;
        }
        return fallback;
    }

    function refreshMatchingGalleries(project) {
        $('.pai-gallery[data-project="' + project + '"]').each(function () {
            var $gallery = $(this);

            $.post(PortfolioAI.ajaxUrl, {
                action: 'pai_load_gallery',
                project: project,
                nonce: $gallery.data('nonce'),
                limit: $gallery.data('limit'),
                shape: $gallery.data('shape'),
                size: $gallery.data('size'),
                caption: $gallery.data('caption'),
                download: $gallery.data('download')
            })
                .done(function (response) {
                    if (response && response.success && response.data && response.data.html) {
                        $gallery.replaceWith(response.data.html);
                    }
                });
        });
    }

    $(document).on('submit', '.pai-generator__form', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $wrap = $form.closest('.pai-generator');
        var $status = $wrap.find('.pai-status');
        var $result = $wrap.find('.pai-result');
        var $button = $form.find('button[type="submit"]');
        var data = {
            action: 'pai_generate',
            nonce: $form.find('input[name="nonce"]').val(),
            project: $form.find('input[name="project"]').val(),
            prompt: $form.find('textarea[name="prompt"]').val(),
            generation_format: $form.find('input[name="generation_format"]').val()
        };

        $button.prop('disabled', true);
        $result.prop('hidden', true).empty();
        setStatus($status, 'Generating image...', false);

        $.post(PortfolioAI.ajaxUrl, data)
            .done(function (response) {
                if (!response || !response.success) {
                    setStatus($status, response && response.data && response.data.message ? response.data.message : 'Generation failed.', true);
                    return;
                }

                var imageUrl = response.data.image_url;
                var html = '';
                html += '<div class="pai-result__card">';
                html += '<img src="' + escapeHtml(imageUrl) + '" alt="Generated image" />';
                html += '<div class="pai-result__actions">';
                html += '<a class="pai-button pai-button--secondary" href="' + escapeHtml(imageUrl) + '" download target="_blank" rel="noopener noreferrer">Download</a>';
                if (response.data.can_submit_gallery) {
                    html += '<button class="pai-button pai-submit-gallery" type="button" data-id="' + parseInt(response.data.id, 10) + '" data-gallery-token="' + escapeHtml(response.data.gallery_token || '') + '">Submit to Gallery</button>';
                }
                html += '</div></div>';

                $result.html(html).prop('hidden', false);
                setStatus($status, response.data.message || 'Image generated.', false);
            })
            .fail(function (xhr) {
                setStatus($status, errorMessage(xhr, 'Generation request failed. Please try again.'), true);
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.pai-submit-gallery', function () {
        var $button = $(this);
        var $wrap = $button.closest('.pai-generator');
        var $form = $wrap.find('.pai-generator__form');
        var $status = $wrap.find('.pai-status');
        var project = $form.find('input[name="project"]').val();

        $button.prop('disabled', true);
        setStatus($status, 'Submitting image...', false);

        $.post(PortfolioAI.ajaxUrl, {
            action: 'pai_submit_gallery',
            nonce: $form.find('input[name="nonce"]').val(),
            project: project,
            id: $button.data('id'),
            gallery_token: $button.data('gallery-token')
        })
            .done(function (response) {
                if (!response || !response.success) {
                    setStatus($status, response && response.data && response.data.message ? response.data.message : 'Submission failed.', true);
                    $button.prop('disabled', false);
                    return;
                }
                setStatus($status, response.data.message || 'Submitted.', false);
                $button.remove();

                if (response.data && response.data.status === 'approved' && parseInt(response.data.auto_refresh, 10) === 1) {
                    refreshMatchingGalleries(project);
                }
            })
            .fail(function (xhr) {
                setStatus($status, errorMessage(xhr, 'Submission request failed. Please try again.'), true);
                $button.prop('disabled', false);
            });
    });
})(jQuery);
