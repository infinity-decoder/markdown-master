/**
 * Cortex - Admin JavaScript
 * 
 * Interactive features for modern admin interface.
 */

(function ($) {
    'use strict';

    const MM_QuestionManager = {
        init() {
            this.cacheElements();
            this.bindEvents();
            this.initSortable();
            this.loadQuestions();
            this.initLeadBuilder();
        },

        cacheElements() {
            this.$list = $('#cortex-questions-list');
            this.$addBtn = $('#cortex-btn-add-question');
            this.$typeSelect = $('#cortex-add-question-type');
            this.$form = $('#cortex-quiz-editor-form');
            this.$leadBuilder = $('#cortex-lead-fields-builder');
        },

        bindEvents() {
            this.$addBtn.on('click', (e) => this.addQuestion(e));
            $(document).on('click', '.cortex-toggle-edit', (e) => this.toggleEdit(e));
            $(document).on('click', '.cortex-delete-question', (e) => this.deleteQuestion(e));
            $(document).on('click', '.cortex-add-option', (e) => this.addOption(e));
            $(document).on('click', '.cortex-remove-option', (e) => this.removeOption(e));
            $(document).on('click', '.cortex-add-pair', (e) => this.addPair(e));
            $(document).on('click', '.cortex-remove-pair', (e) => this.removePair(e));

            $('#cortex-btn-add-lead-field').on('click', (e) => this.addLeadField(e));
            $(document).on('click', '.cortex-remove-lead-field', (e) => this.removeLeadField(e));

            // Tab system
            $('.cortex-tabs-nav a').on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');
                $('.cortex-tabs-nav a').removeClass('active');
                $(this).addClass('active');
                $('.cortex-tab-content').removeClass('active');
                $(target).addClass('active');
            });

            // Prevent form submission from enter key in inputs
            this.$form.on('keydown', 'input', function (e) {
                if (e.keyCode == 13) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        initLeadBuilder() {
            // Fetch existing lead fields from the hidden data or already rendered HTML
            if (this.$leadBuilder.length) {
                // Initial logic if needed
            }
        },

        addLeadField(e) {
            e.preventDefault();
            const index = this.$leadBuilder.find('.cortex-lead-field-row').length;
            const html = `
                <div class="cortex-lead-field-row card">
                    <div class="cortex-form-group-inline">
                        <label>Label</label>
                        <input type="text" name="lead_fields[${index}][label]" placeholder="e.g. Class" class="cortex-input-medium">
                    </div>
                    <div class="cortex-form-group-inline">
                        <label>Type</label>
                        <select name="lead_fields[${index}][type]" class="cortex-select-small">
                            <option value="text">Text</option>
                            <option value="email">Email</option>
                            <option value="number">Number</option>
                            <option value="dropdown">Dropdown</option>
                            <option value="checkbox">Checkbox</option>
                        </select>
                    </div>
                    <div class="cortex-form-group-inline">
                        <label>Required</label>
                        <input type="checkbox" name="lead_fields[${index}][required]" value="1">
                    </div>
                    <button type="button" class="cortex-remove-lead-field dashicons dashicons-trash danger"></button>
                </div>
            `;
            this.$leadBuilder.append(html);
        },

        removeLeadField(e) {
            $(e.currentTarget).closest('.cortex-lead-field-row').remove();
        },

        initSortable() {
            if (this.$list.length && $.fn.sortable) {
                this.$list.sortable({
                    handle: '.cortex-drag-handle',
                    placeholder: 'cortex-sortable-placeholder',
                    update: () => this.saveOrder()
                });
            }
        },

        loadQuestions() {
            if (!Cortex_Admin.quiz_id) return;

            wp.ajax.send('cortex_get_questions', {
                data: {
                    quiz_id: Cortex_Admin.quiz_id,
                    nonce: Cortex_Admin.nonce
                },
                success: (questions) => {
                    this.$list.empty();
                    if (questions && questions.length) {
                        questions.forEach(q => this.renderQuestion(q));
                    }
                }
            });
        },

        renderQuestion(data) {
            const template = wp.template('cortex-question-item');

            // Ensure options and metadata are objects/arrays
            if (typeof data.options === 'string') {
                try { data.options = JSON.parse(data.options); } catch (e) { data.options = []; }
            }
            if (typeof data.correct_answer === 'string') {
                try { data.correct_answer = JSON.parse(data.correct_answer); } catch (e) { /* keep as string if not JSON */ }
            }
            if (typeof data.metadata === 'string') {
                try { data.metadata = JSON.parse(data.metadata); } catch (e) { data.metadata = {}; }
            }

            if (!data.options) data.options = [];
            if (!data.metadata) data.metadata = {};

            const $item = $(template(data));
            this.$list.append($item);
            return $item;
        },

        addQuestion(e) {
            e.preventDefault();
            const type = this.$typeSelect.val();

            this.setLoading(true);

            wp.ajax.send('cortex_add_question', {
                data: {
                    quiz_id: Cortex_Admin.quiz_id,
                    type: type,
                    question_text: '',
                    points: 1,
                    nonce: Cortex_Admin.nonce
                },
                success: (data) => {
                    const $item = this.renderQuestion(data);
                    $item.find('.cortex-toggle-edit').trigger('click');
                    this.setLoading(false);
                },
                error: () => this.setLoading(false)
            });
        },

        toggleEdit(e) {
            const $item = $(e.currentTarget).closest('.cortex-question-item');
            const $body = $item.find('.cortex-question-edit-body');
            const isVisible = $body.is(':visible');

            if (!isVisible) {
                // Close others? Optional.
                $body.slideDown(200);
            } else {
                this.saveQuestion($item);
                $body.slideUp(200);
            }
        },

        saveQuestion($item) {
            const id = $item.data('id');
            const data = {
                question_id: id,
                quiz_id: Cortex_Admin.quiz_id,
                nonce: Cortex_Admin.nonce,
                question_text: $item.find('textarea[name$="[text]"]').val(),
                points: $item.find('input[name$="[points]"]').val(),
                hint: $item.find('input[name$="[hint]"]').val(),
                type: $item.data('type')
            };

            // Options
            const options = [];
            $item.find('.cortex-option-row input[type="text"]').each(function () {
                options.push($(this).val());
            });
            data.options = options;

            // Correct answer
            const correct = [];
            if (data.type === 'fill_blank') {
                const val = $item.find('textarea[name$="[correct]"]').val();
                data.correct_answer = val ? val.split('\n').filter(v => v.trim() !== '') : [];
            } else if (['radio', 'checkbox', 'dropdown'].indexOf(data.type) !== -1) {
                $item.find('.cortex-option-row input[name$="[correct][]"]:checked').each(function () {
                    correct.push($(this).val());
                });
                data.correct_answer = (data.type === 'checkbox') ? correct : (correct.length ? correct[0] : '');
            } else {
                data.correct_answer = $item.find('input[name$="[correct]"]').val();
            }

            // Metadata for specific types
            if (data.type === 'matching') {
                const pairs = {};
                $item.find('.cortex-pair-row').each(function () {
                    const left = $(this).find('input[placeholder="Left"]').val();
                    const right = $(this).find('input[placeholder="Right"]').val();
                    if (left) pairs[left] = right;
                });
                data.metadata = { pairs: pairs };
            } else if (data.type === 'sequence') {
                data.metadata = { order: options };
            } else if (data.type === 'fill_blank') {
                const text = data.question_text || '';
                const matches = text.match(/\[blank\]/g);
                data.metadata = { blank_count: matches ? matches.length : 0 };
            }

            $('.cortex-save-status').text(Cortex_Admin.strings.saving);

            wp.ajax.send('cortex_update_question', {
                data: data,
                success: (q) => {
                    $item.find('.cortex-question-title-preview').text(q.question_text || '(Empty Question)');
                    $('.cortex-save-status').text(Cortex_Admin.strings.saved);
                    setTimeout(() => $('.cortex-save-status').text(''), 2000);
                },
                error: () => {
                    $('.cortex-save-status').text(Cortex_Admin.strings.error);
                }
            });
        },

        deleteQuestion(e) {
            if (!confirm(Cortex_Admin.strings.confirm_delete_question)) return;

            const $item = $(e.currentTarget).closest('.cortex-question-item');
            const id = $item.data('id');

            wp.ajax.send('cortex_delete_question', {
                data: {
                    id: id,
                    nonce: Cortex_Admin.nonce
                },
                success: () => {
                    $item.fadeOut(300, () => $item.remove());
                }
            });
        },

        addOption(e) {
            const $list = $(e.currentTarget).prev('.cortex-options-list');
            const type = $(e.currentTarget).closest('.cortex-question-item').data('type');
            const inputType = (type === 'checkbox') ? 'checkbox' : 'radio';
            const index = $list.find('.cortex-option-row').length;

            const html = `
                <div class="cortex-option-row">
                    <input type="text" name="options[]" value="" class="cortex-input-small" placeholder="Option text...">
                    <input type="${inputType}" name="correct[]" value="${index}">
                    <button type="button" class="cortex-remove-option dashicons dashicons-no-alt"></button>
                </div>
            `;
            $list.append(html);
        },

        removeOption(e) {
            $(e.currentTarget).closest('.cortex-option-row').remove();
        },

        addPair(e) {
            const $list = $(e.currentTarget).prev('.cortex-pairs-list');
            const html = `
                <div class="cortex-pair-row">
                    <input type="text" name="pairs_left[]" value="" placeholder="Left">
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                    <input type="text" name="pairs_right[]" value="" placeholder="Right">
                    <button type="button" class="cortex-remove-pair dashicons dashicons-no-alt"></button>
                </div>
            `;
            $list.append(html);
        },

        removePair(e) {
            $(e.currentTarget).closest('.cortex-pair-row').remove();
        },

        saveOrder() {
            // Implement global reorder if needed, or just let users save
        },

        setLoading(loading) {
            this.$addBtn.prop('disabled', loading);
            if (loading) {
                this.$addBtn.addClass('loading');
            } else {
                this.$addBtn.removeClass('loading');
            }
        }
    };

    $(document).ready(() => {
        MM_QuestionManager.init();
    });

})(jQuery);
