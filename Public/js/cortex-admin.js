jQuery(document).ready(function($) {
    console.log("Cortex admin JS loaded.");

    // Live Markdown preview
    $('.cortex-markdown-input').on('input', function() {
        let content = $(this).val();
        let previewBox = $('#' + $(this).data('preview-target'));

        $.ajax({
            url: cortex_admin.ajax_url,
            method: 'POST',
            data: {
                action: 'cortex_render_markdown',
                content: content,
                _ajax_nonce: cortex_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    previewBox.html(response.data.html);
                }
            }
        });
    });

    // Delete confirmation
    $('.cortex-delete-item').on('click', function(e) {
        if (!confirm("Are you sure you want to delete this item?")) {
            e.preventDefault();
        }
    });

    // Toggle quiz question form
    $('.cortex-add-question').on('click', function(e) {
        e.preventDefault();
        let container = $('.cortex-questions-container');
        let newQuestion = $('.cortex-question-template').clone().removeClass('cortex-question-template').show();
        container.append(newQuestion);
    });

    // Remove question
    $(document).on('click', '.cortex-remove-question', function(e) {
        e.preventDefault();
        $(this).closest('.cortex-question-item').remove();
    });

    // Syntax highlighting in admin preview
    if (typeof hljs !== 'undefined') {
        $('pre code').each(function(i, block) {
            hljs.highlightElement(block);
        });
    }
});
