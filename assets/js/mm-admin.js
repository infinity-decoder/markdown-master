/**
 * Markdown Master - Admin JavaScript
 * 
 * Interactive features for modern admin interface.
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Tab System
     */
    function initTabs() {
        $('.mm-tabs-nav a').on('click', function (e) {
            e.preventDefault();

            const $tab = $(this);
            const target = $tab.attr('href');

            // Update active states
            $tab.closest('.mm-tabs-nav').find('a').removeClass('active');
            $tab.addClass('active');

            // Show/hide content
            $tab.closest('.mm-tabs').next('.mm-tabs-container').find('.mm-tab-content').removeClass('active');
            $(target).addClass('active');
        });
    }

    /**
     * Card actions with confirmation
     */
    function initCardActions() {
        $('.mm-card-action.danger').on('click', function (e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Toggle switches
     */
    function initToggles() {
        $('.mm-toggle-switch input').on('change', function () {
            const $toggle = $(this);
            const isChecked = $toggle.is(':checked');
            const fieldName = $toggle.attr('name');

            // Visual feedback
            $toggle.closest('.mm-form-group').toggleClass('enabled', isChecked);
        });
    }

    /**
     * Search and filter
     */
    function initFilters() {
        const $searchInput = $('.mm-filter-bar input[type="search"]');
        const $filterSelect = $('.mm-filter-bar select');

        function filterCards() {
            const searchTerm = $searchInput.val().toLowerCase();
            const filterValue = $filterSelect.val();

            $('.mm-card').each(function () {
                const $card = $(this);
                const title = $card.find('.mm-card-title').text().toLowerCase();
                const status = $card.data('status');

                const matchesSearch = !searchTerm || title.includes(searchTerm);
                const matchesFilter = !filterValue || filterValue === 'all' || status === filterValue;

                $card.toggle(matchesSearch && matchesFilter);
            });
        }

        $searchInput.on('input', filterCards);
        $filterSelect.on('change', filterCards);
    }

    /**
     * Auto-save draft
     */
    function initAutoSave() {
        let saveTimeout;

        $('.mm-form-container input, .mm-form-container textarea, .mm-form-container select').on('change input', function () {
            clearTimeout(saveTimeout);

            // Show saving indicator
            $('.mm-save-status').text('Saving...').css('color', '#999');

            saveTimeout = setTimeout(function () {
                // Simulate save (you can add AJAX here)
                $('.mm-save-status').text('Draft saved').css('color', '#46b450');

                setTimeout(function () {
                    $('.mm-save-status').text('');
                }, 2000);
            }, 1000);
        });
    }

    /**
     * Question type selector
     */
    function initQuestionTypeSelector() {
        $('.mm-question-type-select').on('change', function () {
            const type = $(this).val();
            const $container = $(this).closest('.mm-question-item');

            // Hide all type-specific fields
            $container.find('.mm-type-specific').hide();

            // Show relevant fields
            $container.find('.mm-type-' + type).show();
        });
    }

    /**
     * Add question to quiz
     */
    function initAddQuestion() {
        let questionCount = $('.mm-question-item').length;

        $('#mm-add-question').on('click', function (e) {
            e.preventDefault();

            questionCount++;
            const template = $('#mm-question-template').html();
            const $newQuestion = $(template.replace(/{{index}}/g, questionCount));

            $('.mm-questions-list').append($newQuestion);

            // Reinitialize type selector for new question
            $newQuestion.find('.mm-question-type-select').trigger('change');

            // Scroll to new question
            $('html, body').animate({
                scrollTop: $newQuestion.offset().top - 100
            }, 500);
        });
    }

    /**
     * Remove question
     */
    function initRemoveQuestion() {
        $(document).on('click', '.mm-remove-question', function (e) {
            e.preventDefault();

            if (confirm('Remove this question?')) {
                $(this).closest('.mm-question-item').fadeOut(300, function () {
                    $(this).remove();
                });
            }
        });
    }

    /**
     * Copy UUID to clipboard
     */
    function initCopyUUID() {
        $('.mm-copy-uuid').on('click', function (e) {
            e.preventDefault();

            const $button = $(this);
            const uuid = $button.data('uuid');

            // Create temporary input
            const $temp = $('<input>');
            $('body').append($temp);
            $temp.val(uuid).select();

            try {
                document.execCommand('copy');
                $button.text('Copied!').css('color', '#46b450');

                setTimeout(function () {
                    $button.text('Copy UUID').css('color', '');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy UUID', err);
            }

            $temp.remove();
        });
    }

    /**
     * Sortable questions
     */
    function initSortable() {
        if (typeof $.fn.sortable !== 'undefined') {
            $('.mm-questions-list').sortable({
                handle: '.mm-question-handle',
                placeholder: 'mm-question-placeholder',
                update: function () {
                    // Update order numbers
                    $(this).find('.mm-question-item').each(function (index) {
                        $(this).find('.mm-question-number').text(index + 1);
                        $(this).find('input[name$="[order]"]').val(index);
                    });
                }
            });
        }
    }

    /**
     * Character counter for textareas
     */
    function initCharCounter() {
        $('.mm-char-limit').each(function () {
            const $textarea = $(this);
            const maxLength = $textarea.attr('maxlength');

            if (maxLength) {
                const $counter = $('<div class="mm-char-count"></div>');
                $textarea.after($counter);

                function updateCount() {
                    const remaining = maxLength - $textarea.val().length;
                    $counter.text(remaining + ' characters remaining');
                    $counter.toggleClass('warning', remaining < 50);
                }

                $textarea.on('input', updateCount);
                updateCount();
            }
        });
    }

    /**
     * Conditional fields
     */
    function initConditionalFields() {
        $('[data-show-if]').each(function () {
            const $field = $(this);
            const condition = $field.data('show-if');
            const [targetField, targetValue] = condition.split('=');

            const $trigger = $('[name="' + targetField + '"]');

            function checkCondition() {
                let value;
                if ($trigger.is(':checkbox')) {
                    value = $trigger.is(':checked') ? '1' : '0';
                } else {
                    value = $trigger.val();
                }

                $field.toggle(value === targetValue);
            }

            $trigger.on('change', checkCondition);
            checkCondition();
        });
    }

    /**
     * Initialize all components
     */
    $(document).ready(function () {
        initTabs();
        initCardActions();
        initToggles();
        initFilters();
        initAutoSave();
        initQuestionTypeSelector();
        initAddQuestion();
        initRemoveQuestion();
        initCopyUUID();
        initSortable();
        initCharCounter();
        initConditionalFields();
    });

})(jQuery);
