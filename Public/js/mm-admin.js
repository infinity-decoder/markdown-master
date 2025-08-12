jQuery(document).ready(function($) {
    console.log("Markdown Master admin JS loaded.");

    // Live Markdown preview
    $('.mm-markdown-input').on('input', function() {
        let content = $(this).val();
        let previewBox = $('#' + $(this).data('preview-target'));

        $.ajax({
            url: mm_admin.ajax_url,
            method: 'POST',
            data: {
                action: 'mm_render_markdown',
                content: content,
                _ajax_nonce: mm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    previewBox.html(response.data.html);
                }
            }
        });
    });

    // Delete confirmation
    $('.mm-delete-item').on('click', function(e) {
        if (!confirm("Are you sure you want to delete this item?")) {
            e.preventDefault();
        }
    });

    // Toggle quiz question form
    $('.mm-add-question').on('click', function(e) {
        e.preventDefault();
        let container = $('.mm-questions-container');
        let newQuestion = $('.mm-question-template').clone().removeClass('mm-question-template').show();
        container.append(newQuestion);
    });

    // Remove question
    $(document).on('click', '.mm-remove-question', function(e) {
        e.preventDefault();
        $(this).closest('.mm-question-item').remove();
    });

    // Syntax highlighting in admin preview
    if (typeof hljs !== 'undefined') {
        $('pre code').each(function(i, block) {
            hljs.highlightElement(block);
        });
    }
});
