jQuery(function ($) {
  $(document).on('click', '.pai-generate', function (e) {
    e.preventDefault();
    var $box = $(this).closest('.pai-generator');
    var prompt = $box.find('.pai-prompt').val();
    var aspect = $box.find('.pai-aspect').val();
    var project = $box.data('project');
    $box.find('.pai-status').text('Generating...');
    $.post(paiData.ajaxUrl, {
      action: 'portfolio_ai_generate',
      nonce: paiData.nonce,
      prompt: prompt,
      aspect: aspect,
      project: project
    }).done(function (resp) {
      if (!resp.success) {
        $box.find('.pai-status').text(resp.data && resp.data.message ? resp.data.message : 'Error');
        return;
      }
      var d = resp.data;
      var html = '<img src="' + d.image_url + '" alt="Generated" />';
      html += ' <a href="' + d.image_url + '" download>Download</a>';
      html += ' <button class="pai-submit" data-id="' + d.id + '">Submit to gallery</button>';
      $box.find('.pai-result').html(html);
      $box.find('.pai-status').text('Done');
    }).fail(function () {
      $box.find('.pai-status').text('Request failed');
    });
  });

  $(document).on('click', '.pai-submit', function (e) {
    e.preventDefault();
    var id = $(this).data('id');
    var $btn = $(this);
    $.post(paiData.ajaxUrl, {
      action: 'portfolio_ai_submit_gallery',
      nonce: paiData.nonce,
      id: id
    }).done(function (resp) {
      if (resp.success) {
        $btn.replaceWith('<span>Submitted for moderation</span>');
      }
    });
  });
});
