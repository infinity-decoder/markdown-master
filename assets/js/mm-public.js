(function($){
    $(document).ready(function(){

        function collectStudent($form){
            var student = {};
            $form.find('[name^="student"]').each(function(){
                var nameAttr = $(this).attr('name'); // e.g. student[name]
                var m = nameAttr.match(/^student\[(.+)\]$/);
                if (m && m[1]) {
                    student[m[1]] = $(this).val();
                }
            });
            return student;
        }

        function collectAnswers($form){
            var answers = {};
            $form.find('.mm-question').each(function(){
                var $q = $(this);
                var qid = $q.data('qid');
                var qtype = $q.data('qtype');

                if ( qtype === 'mcq' ) {
                    var v = $q.find('input[type="radio"]:checked').val();
                    answers[qid] = typeof v === 'undefined' ? '' : v;
                } else if ( qtype === 'checkbox' ) {
                    var arr = [];
                    $q.find('input[type="checkbox"]:checked').each(function(){
                        arr.push( $(this).val() );
                    });
                    answers[qid] = arr;
                } else {
                    // text / textarea or unknown
                    var v = $q.find('input[type="text"], textarea').first().val();
                    answers[qid] = typeof v === 'undefined' ? '' : v;
                }
            });
            return answers;
        }

        // Submit quiz
        $(document).on('click', '.mm-submit-quiz', function(e){
            e.preventDefault();
            var $btn = $(this);
            var $form = $btn.closest('.mm-quiz-form');
            var quizId = $form.data('quiz-id');

            // Basic UI block
            $btn.prop('disabled', true).text( mm_public.i18n.submitting || 'Submitting...' );

            var student = collectStudent($form);
            var answers = collectAnswers($form);

            // Basic validation
            if ( ! student.name || student.name.trim() === '' ) {
                alert('Please enter your name.');
                $btn.prop('disabled', false).text('Submit Quiz');
                return;
            }

            var data = {
                action: 'mm_submit_attempt',
                nonce: mm_public.nonce,
                quiz_id: quizId,
                student: student,
                answers: answers
            };

            $.post( mm_public.ajax_url, data, function(resp){
                if ( resp && resp.success ) {
                    var d = resp.data;
                    var resultHtml = '<div class="mm-result-success">';
                    resultHtml += '<p><strong>Score: ' + d.score + ' / ' + d.total + '</strong></p>';
                    if ( d.download_pdf ) {
                        resultHtml += '<p><a href="' + d.download_pdf + '" target="_blank" class="button">Download PDF</a></p>';
                        $form.find('.mm-download-pdf').show().off('click').on('click', function(){
                            window.open(d.download_pdf, '_blank');
                        });
                    }
                    resultHtml += '</div>';
                    $form.find('.mm-quiz-result').html(resultHtml).show();
                    // Optionally hide the form to prevent re-submits
                    $form.find('.mm-quiz-actions').hide();
                } else {
                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : (mm_public.i18n.submit_error || 'Submission error');
                    alert( msg );
                }
            }).fail(function(xhr){
                var err = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Request failed';
                alert( err );
            }).always(function(){
                $btn.prop('disabled', false).text('Submit Quiz');
            });
        });

    });
})(jQuery);
