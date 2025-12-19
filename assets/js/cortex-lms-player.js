jQuery(document).ready(function ($) {

    // Mark Complete Form
    $('.cortex-mark-complete-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button');
        var lesson_id = $form.data('lesson-id');
        var course_id = $form.data('course-id');

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Saving...');

        $.ajax({
            url: Cortex_Player.ajax_url,
            type: 'POST',
            data: {
                action: 'cortex_mark_complete',
                nonce: Cortex_Player.nonce,
                lesson_id: lesson_id,
                course_id: course_id
            },
            success: function (res) {
                if (res.success) {
                    $btn.html('<span class="dashicons dashicons-yes"></span> Completed').addClass('cortex-btn-success');
                    // Optional: Auto redirect to next lesson
                } else {
                    alert(res.data.message);
                    $btn.prop('disabled', false).text('Try Again');
                }
            },
            error: function () {
                alert('Connection error');
                $btn.prop('disabled', false).text('Try Again');
            }
        });
    });

});
