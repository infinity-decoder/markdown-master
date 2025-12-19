jQuery(document).ready(function($) {
    console.log("Cortex frontend loaded.");

    // Quiz submission
    $('.cortex-quiz-form').on('submit', function(e) {
        e.preventDefault();
        let form = $(this);
        let quizId = form.data('quiz-id');

        $.ajax({
            url: Cortex_Public.ajax_url,
            method: 'POST',
            data: form.serialize() + '&action=cortex_submit_quiz&quiz_id=' + quizId,
            success: function(response) {
                if (response.success) {
                    form.find('.cortex-quiz-result').html(response.data.message).show();
                } else {
                    form.find('.cortex-quiz-result').html('<span style="color:red;">Error submitting quiz</span>').show();
                }
            }
        });
    });

    // Quiz timer
    $('.cortex-quiz-timer').each(function() {
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
