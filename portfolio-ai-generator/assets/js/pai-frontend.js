(function ($) {
    'use strict';

    function setStatus($box, message, isError) {
        $box.text(message || '').toggleClass('pai-status--error', !!isError);
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
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
            aspect_ratio: $form.find('select[name="aspect_ratio"]').val()
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
                    html += '<button class="pai-button pai-submit-gallery" type="button" data-id="' + parseInt(response.data.id, 10) + '">Submit to Gallery</button>';
                }
                html += '</div></div>';

                $result.html(html).prop('hidden', false);
                setStatus($status, response.data.message || 'Image generated.', false);
            })
            .fail(function () {
                setStatus($status, 'Generation request failed. Please try again.', true);
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

        $button.prop('disabled', true);
        setStatus($status, 'Submitting image...', false);

        $.post(PortfolioAI.ajaxUrl, {
            action: 'pai_submit_gallery',
            nonce: $form.find('input[name="nonce"]').val(),
            project: $form.find('input[name="project"]').val(),
            id: $button.data('id')
        })
            .done(function (response) {
                if (!response || !response.success) {
                    setStatus($status, response && response.data && response.data.message ? response.data.message : 'Submission failed.', true);
                    $button.prop('disabled', false);
                    return;
                }
                setStatus($status, response.data.message || 'Submitted.', false);
                $button.remove();
            })
            .fail(function () {
                setStatus($status, 'Submission request failed. Please try again.', true);
                $button.prop('disabled', false);
            });
    });
})(jQuery);
