jQuery(document).ready(function($) {
    console.log("Markdown Master frontend loaded.");

    // Quiz submission
    $('.mm-quiz-form').on('submit', function(e) {
        e.preventDefault();
        let form = $(this);
        let quizId = form.data('quiz-id');

        $.ajax({
            url: mm_public.ajax_url,
            method: 'POST',
            data: form.serialize() + '&action=mm_submit_quiz&quiz_id=' + quizId,
            success: function(response) {
                if (response.success) {
                    form.find('.mm-quiz-result').html(response.data.message).show();
                } else {
                    form.find('.mm-quiz-result').html('<span style="color:red;">Error submitting quiz</span>').show();
                }
            }
        });
    });

    // Quiz timer
    $('.mm-quiz-timer').each(function() {
        let timerElem = $(this);
        let timeLeft = parseInt(timerElem.data('time'), 10);

        if (!isNaN(timeLeft) && timeLeft > 0) {
            let timerInterval = setInterval(function() {
                let minutes = Math.floor(timeLeft / 60);
                let seconds = timeLeft % 60;
                timerElem.text(`Time Left: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`);
                timeLeft--;

                if (timeLeft < 0) {
                    clearInterval(timerInterval);
                    timerElem.closest('form').submit();
                }
            }, 1000);
        }
    });

    // Highlight.js init
    if (typeof hljs !== 'undefined') {
        $('pre code').each(function(i, block) {
            hljs.highlightElement(block);
        });
    }
});
