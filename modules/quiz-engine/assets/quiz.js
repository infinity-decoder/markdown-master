jQuery(document).ready(function ($) {
    $('.cotex-submit-quiz').on('click', function () {
        var container = $(this).closest('.cotex-quiz-container');
        var quizId = container.data('id');
        var answers = {};

        container.find('input:checked').each(function () {
            var name = $(this).attr('name');
            answers[name] = $(this).val();
        });

        $.ajax({
            url: cotexQuizVars.ajaxurl,
            method: 'POST',
            data: {
                action: 'cortex_submit_quiz',
                quiz_id: quizId,
                answers: answers
            },
            success: function (response) {
                if (response.success) {
                    container.find('.cotex-quiz-result').text('Score: ' + response.data.score + '. ' + response.data.message);
                }
            }
        });
    });
});
