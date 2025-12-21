jQuery(document).ready(function ($) {

    $('#cotex-complete-lesson').on('click', function (e) {
        e.preventDefault();
        var btn = $(this);
        var originalText = btn.text();
        btn.text('Processing...');

        $.ajax({
            url: cotexLmsData.ajaxurl,
            method: 'POST',
            data: {
                action: 'cortex_complete_lesson',
                nonce: cotexLmsData.nonce,
                lesson_id: typeof cotexCurrentLessonId !== 'undefined' ? cotexCurrentLessonId : cotexLmsData.post_id,
                course_id: cotexLmsData.course_id
            },
            success: function (response) {
                if (response.success) {
                    btn.text('Completed!');
                    btn.addClass('cotex-completed-btn');
                    // Optionally update progress bar or redirect
                    if (response.data.percentage) {
                        $('.cotex-progress-fill').css('width', response.data.percentage + '%');
                    }
                } else {
                    btn.text('Error');
                    setTimeout(() => btn.text(originalText), 2000);
                }
            }
        });
    });

});
