// Place at: markdown-master/assets/admin/js/mm-admin.js
(function($){
    'use strict';

    $(document).ready(function(){

        // Only run on pages where our table exists
        var $qTable = $('#mm-questions-table');
        if ( $qTable.length ) {
            var quizId = $qTable.data('quiz-id');

            // Load up-to-date questions via AJAX (refresh)
            function loadQuestions() {
                $.post(MM_Admin.ajax_url, {
                    action: 'mm_get_questions',
                    quiz_id: quizId,
                    nonce: MM_Admin.nonce
                }, function(resp){
                    if ( resp.success ) {
                        renderQuestions(resp.data);
                    } else {
                        console.error('Failed to load questions', resp);
                    }
                });
            }

            function renderQuestions(list) {
                var $tbody = $qTable.find('tbody').empty();
                if ( ! list || list.length === 0 ) {
                    $tbody.append('<tr><td colspan="6">' + 'No questions yet. Click \"Add Question\" to create one.' + '</td></tr>');
                    return;
                }
                list.forEach(function(q){
                    var opts = '';
                    if ( Array.isArray(q.options) ) {
                        opts = q.options.join(' | ');
                    } else if ( q.options ) {
                        opts = q.options;
                    }
                    var $row = $('<tr data-qid="'+ q.id +'"></tr>');
                    $row.append('<td>'+ q.id +'</td>');
                    $row.append('<td>'+ escapeHtml(q.question_text).substr(0,200) +'</td>');
                    $row.append('<td>'+ escapeHtml(q.type) +'</td>');
                    $row.append('<td>'+ escapeHtml(opts) +'</td>');
                    $row.append('<td>'+ (q.points||1) +'</td>');
                    $row.append('<td><a href="#" class="mm-edit-question" data-qid="'+ q.id +'">Edit</a> | <a href="#" class="mm-delete-question" data-qid="'+ q.id +'">Delete</a></td>');
                    $tbody.append( $row );
                });
            }

            // Basic escaping helper
            function escapeHtml(s) {
                if ( s === null || s === undefined ) return '';
                return String(s).replace(/[&<>"'`=\/]/g, function (ch) {
                    return '&#' + ch.charCodeAt(0) + ';';
                });
            }

            // Show modal
            var $modal = $('#mm-question-modal');
            var $form = $('#mm-question-form');

            $('.mm-add-question-btn').on('click', function(e){
                e.preventDefault();
                openModal('add');
            });

            // Delegate edit
            $qTable.on('click', '.mm-edit-question', function(e){
                e.preventDefault();
                var qid = $(this).data('qid');
                openModal('edit', qid);
            });

            // Delegate delete
            $qTable.on('click', '.mm-delete-question', function(e){
                e.preventDefault();
                var qid = $(this).data('qid');
                if ( ! confirm(MM_Admin.strings.confirm_delete_question) ) return;
                $.post(MM_Admin.ajax_url, {
                    action: 'mm_delete_question',
                    question_id: qid,
                    nonce: MM_Admin.nonce
                }, function(resp){
                    if ( resp.success ) {
                        loadQuestions();
                    } else {
                        alert('Delete failed');
                    }
                });
            });

            // Open modal in add or edit mode. If edit, fetch data from server by loading questions list and finding it.
            function openModal(mode, qid) {
                $('#mm-modal-title').text( mode === 'edit' ? MM_Admin.strings.update_question : MM_Admin.strings.add_question );
                $form[0].reset();
                $form.find('input[name="question_id"]').val('');
                // Reset UI rows
                $form.find('.mm-options-row').show();
                $form.find('.mm-correct-row').show();

                if ( mode === 'edit' && qid ) {
                    // load existing question
                    $.post(MM_Admin.ajax_url, {
                        action: 'mm_get_questions',
                        quiz_id: quizId,
                        nonce: MM_Admin.nonce
                    }, function(resp){
                        if ( resp.success ) {
                            var q = null;
                            resp.data.forEach(function(item){
                                if ( parseInt(item.id,10) === parseInt(qid,10) ) q = item;
                            });
                            if ( q ) {
                                populateForm(q);
                                $form.find('input[name="action"]').val('mm_update_question');
                                $form.find('input[name="question_id"]').val(q.id);
                                showModal();
                            } else {
                                alert('Question not found');
                            }
                        } else {
                            alert('Unable to load question for editing');
                        }
                    });
                } else {
                    $form.find('input[name="action"]').val('mm_add_question');
                    showModal();
                }
            }

            function populateForm(q) {
                $form.find('textarea[name="question_text"]').val(q.question_text||'');
                $form.find('select[name="type"]').val(q.type||'single');
                $form.find('input[name="points"]').val(q.points||1);
                $form.find('input[name="image"]').val(q.image||'');
                if ( Array.isArray(q.options) ) {
                    $form.find('textarea[name="options_text"]').val(q.options.join("\n"));
                } else {
                    $form.find('textarea[name="options_text"]').val('');
                }
                // set correct answer text: if array, join by comma
                var ca = q.correct_answer;
                if ( Array.isArray(ca) ) {
                    $form.find('input[name="correct_answer_text"]').val( ca.join(',') );
                } else if ( ca !== null && typeof ca !== 'undefined' ) {
                    $form.find('input[name="correct_answer_text"]').val( ca );
                } else {
                    $form.find('input[name="correct_answer_text"]').val('');
                }
            }

            function showModal() {
                $modal.show();
                $modal.attr('aria-hidden','false');
            }
            function hideModal() {
                $modal.hide();
                $modal.attr('aria-hidden','true');
            }

            $('#mm-modal-cancel').on('click', function(e){
                e.preventDefault();
                hideModal();
            });

            // Submit question form via AJAX
            $form.on('submit', function(e){
                e.preventDefault();
                var action = $form.find('input[name="action"]').val();
                var data = {
                    action: action,
                    nonce: MM_Admin.nonce
                };
                // add all fields
                data.quiz_id = quizId;
                data.question_text = $form.find('textarea[name="question_text"]').val();
                data.type = $form.find('select[name="type"]').val();
                data.points = $form.find('input[name="points"]').val();
                data.image = $form.find('input[name="image"]').val();
                data.options_text = $form.find('textarea[name="options_text"]').val();
                data.correct_answer_text = $form.find('input[name="correct_answer_text"]').val();
                var qid = $form.find('input[name="question_id"]').val();
                if ( qid ) data.question_id = qid;

                $.post(MM_Admin.ajax_url, data, function(resp){
                    if ( resp.success ) {
                        hideModal();
                        loadQuestions();
                    } else {
                        alert('Save failed: ' + (resp.data || 'unknown'));
                    }
                });
            });

            // initial load
            loadQuestions();
        }

    }); // ready

})(jQuery);
