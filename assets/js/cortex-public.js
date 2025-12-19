/**
 * Cortex 2.0 - Frontend JavaScript
 * 
 * Handles quiz interactions, timer, form submission, and progressive enhancement.
 * 
 * @package MarkdownMaster
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Quiz Handler Class
     */
    class MMQuizHandler {
        constructor(container) {
            this.container = $(container);
            this.quizId = this.container.data('quiz-id');
            this.timer = null;
            this.startTime = null;
            this.timerInterval = null;

            this.init();
        }

        init() {
            // Handle welcome screen
            this.container.find('.cortex-start-quiz').on('click', (e) => {
                e.preventDefault();
                this.startQuiz();
            });

            // Handle hint toggles
            this.container.find('.cortex-hint-toggle').on('click', (e) => {
                e.preventDefault();
                this.toggleHint($(e.currentTarget));
            });

            // Handle form submission
            this.container.find('.cortex-quiz-form-element').on('submit', (e) => {
                e.preventDefault();
                this.submitQuiz();
            });

            // Initialize timer if present
            const timerEl = this.container.find('.cortex-quiz-timer');
            if (timerEl.length) {
                this.initTimer(timerEl.data('seconds'));
            }

            // Initialize Sortable for Sequence questions
            this.initSortable();

            // Prevent duplicate submissions
            let isSubmitting = false;
            this.container.find('.cortex-submit-quiz').on('click', function () {
                if (isSubmitting) {
                    return false;
                }
                isSubmitting = true;
                $(this).prop('disabled', true).text('Submitting...');
            });

            // Copy code button
            this.container.find('.cortex-code-copy').on('click', (e) => {
                this.copyCode($(e.currentTarget));
            });
        }

        startQuiz() {
            this.container.find('.cortex-quiz-welcome').fadeOut(300, () => {
                this.container.find('.cortex-quiz-form').fadeIn(300);

                // Start timer if exists
                if (this.timerInterval) {
                    this.startTimer();
                }

                // Record start time
                this.startTime = Math.floor(Date.now() / 1000);
                this.container.find('input[name="start_time"]').val(this.startTime);
            });
        }

        toggleHint(button) {
            const content = button.next('.cortex-hint-content');
            const isExpanded = button.attr('aria-expanded') === 'true';

            content.slideToggle(300);
            button.attr('aria-expanded', !isExpanded);
            button.text(isExpanded ? 'Show Hint' : 'Hide Hint');
        }

        initTimer(seconds) {
            this.timer = seconds;
            const timerDisplay = this.container.find('.cortex-timer-value');

            this.timerInterval = setInterval(() => {
                if (this.timer <= 0) {
                    clearInterval(this.timerInterval);
                    this.timeUp();
                    return;
                }

                this.timer--;
                const minutes = Math.floor(this.timer / 60);
                const secs = this.timer % 60;
                timerDisplay.text(
                    String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0')
                );

                // Warning at 60 seconds
                if (this.timer === 60) {
                    timerDisplay.parent().addClass('cortex-timer-warning');
                }

                // Urgent at 10 seconds
                if (this.timer === 10) {
                    timerDisplay.parent().addClass('cortex-timer-urgent');
                }
            }, 1000);
        }

        startTimer() {
            // Timer starts when welcome screen is dismissed
            // Timer interval already set in initTimer
        }

        initSortable() {
            const $sortables = this.container.find('.cortex-sortable');
            if ($sortables.length && $.fn.sortable) {
                $sortables.sortable({
                    placeholder: 'cortex-sortable-placeholder',
                    forcePlaceholderSize: true,
                    update: (event, ui) => {
                        // Nothing to do here for now as hidden inputs are inside and move with items
                    }
                });
            }
        }

        timeUp() {
            alert('Time is up! The quiz will be submitted automatically.');
            this.container.find('.cortex-quiz-form-element').submit();
        }

        submitQuiz() {
            // Form will submit via POST for now
            // In future versions, this can be AJAX
            return true;
        }

        copyCode(button) {
            const code = button.data('clipboard-text');

            // Create temporary textarea
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(code).select();

            try {
                document.execCommand('copy');
                button.find('.cortex-copy-text').text('Copied!');

                setTimeout(() => {
                    button.find('.cortex-copy-text').text('Copy');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy code:', err);
            }

            $temp.remove();
        }
    }

    /**
     * Markdown Renderer Enhancement
     */
    class MMMarkdownEnhancer {
        constructor() {
            this.init();
        }

        init() {
            // Enhance code blocks with syntax highlighting
            this.highlightCode();

            // Make tables responsive
            this.responsiveTables();
        }

        highlightCode() {
            // Check if Prism is loaded
            if (typeof Prism !== 'undefined') {
                Prism.highlightAll();
            }
        }

        responsiveTables() {
            $('.cortex-markdown-content table').each(function () {
                if (!$(this).parent().hasClass('cortex-table-wrapper')) {
                    $(this).wrap('<div class="cortex-table-wrapper" style="overflow-x:auto;"></div>');
                }
            });
        }
    }

    /**
     * Form Validation
     */
    class MMFormValidator {
        constructor(form) {
            this.form = $(form);
            this.init();
        }

        init() {
            this.form.on('submit', (e) => {
                if (!this.validate()) {
                    e.preventDefault();
                    return false;
                }
            });
        }

        validate() {
            let isValid = true;
            const errors = [];

            // Check required fields
            this.form.find('[required]').each(function () {
                const $field = $(this);
                const value = $field.val();

                // Reset previous error state
                $field.removeClass('cortex-field-error');

                // Check if empty
                if (!value || (Array.isArray(value) && value.length === 0)) {
                    $field.addClass('cortex-field-error');
                    isValid = false;
                    errors.push('Please answer all required questions.');
                }

                // Email validation
                if ($field.attr('type') === 'email' && value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        $field.addClass('cortex-field-error');
                        isValid = false;
                        errors.push('Please enter a valid email address.');
                    }
                }
            });

            // Checkbox questions - ensure at least one checked
            this.form.find('.cortex-checkbox-options').each(function () {
                const $group = $(this);
                const checked = $group.find('input[type="checkbox"]:checked').length;

                if (checked === 0 && $group.closest('.cortex-question').find('[required]').length) {
                    $group.addClass('cortex-field-error');
                    isValid = false;
                }
            });

            // Display errors
            if (!isValid && errors.length > 0) {
                // Remove duplicate errors
                const uniqueErrors = [...new Set(errors)];
                alert(uniqueErrors.join('\n'));
            }

            return isValid;
        }
    }

    /**
     * Accessibility Enhancements
     */
    class MMA11y {
        constructor() {
            this.init();
        }

        init() {
            // Keyboard navigation for custom radio/checkbox
            this.keyboardSupport();

            // ARIA live regions for dynamic content
            this.ariaLiveRegions();

            // Focus management
            this.focusManagement();
        }

        keyboardSupport() {
            $('.cortex-radio-label, .cortex-checkbox-label').on('keypress', function (e) {
                if (e.which === 13 || e.which === 32) { // Enter or Space
                    e.preventDefault();
                    $(this).find('input').click();
                }
            });
        }

        ariaLiveRegions() {
            // Add aria-live to result containers
            $('.cortex-quiz-result').attr('aria-live', 'polite');
        }

        focusManagement() {
            // Focus first field when quiz starts
            $('.cortex-start-quiz').on('click', function () {
                setTimeout(() => {
                    $('.cortex-quiz-form-element').find('input, select, textarea').first().focus();
                }, 350);
            });
        }
    }

    /**
     * Progressive Enhancement for Advanced Features
     */
    class MMProgressiveEnhancement {
        constructor() {
            this.init();
        }

        init() {
            // Check for localStorage support
            if (this.hasLocalStorage()) {
                this.enableAutoSave();
            }

            // Check for Intersection Observer (lazy loading)
            if ('IntersectionObserver' in window) {
                this.lazyLoadImages();
            }
        }

        hasLocalStorage() {
            try {
                const test = '__cortex_storage_test__';
                localStorage.setItem(test, test);
                localStorage.removeItem(test);
                return true;
            } catch (e) {
                return false;
            }
        }

        enableAutoSave() {
            // Save quiz progress to localStorage
            $('.cortex-quiz-form-element').on('change', 'input, select, textarea', function () {
                const quizId = $(this).closest('.cortex-quiz-container').data('quiz-id');
                const formData = $(this).closest('form').serialize();

                try {
                    localStorage.setItem('cortex_quiz_draft_' + quizId, formData);
                } catch (e) {
                    console.warn('Failed to save quiz progress:', e);
                }
            });

            // Restore on page load
            $('.cortex-quiz-container').each(function () {
                const quizId = $(this).data('quiz-id');
                const saved = localStorage.getItem('cortex_quiz_draft_' + quizId);

                if (saved) {
                    // Show restore prompt
                    if (confirm('Restore your previous answers?')) {
                        // Parse and restore values here
                        // (Implementation depends on serialization format)
                    } else {
                        localStorage.removeItem('cortex_quiz_draft_' + quizId);
                    }
                }
            });
        }

        lazyLoadImages() {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('cortex-lazy');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img.cortex-lazy').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    /**
     * Initialize on Document Ready
     */
    $(document).ready(function () {
        // Initialize quiz handlers
        $('.cortex-quiz-container').each(function () {
            new MMQuizHandler(this);
            new MMFormValidator($(this).find('.cortex-quiz-form-element'));
        });

        // Initialize markdown enhancements
        if ($('.cortex-markdown-content').length) {
            new MMMarkdownEnhancer();
        }

        // Initialize accessibility features
        new MMA11y();

        // Initialize progressive enhancements
        new MMProgressiveEnhancement();

        // Smooth scroll to results
        $('.cortex-quiz-results').each(function () {
            $('html, body').animate({
                scrollTop: $(this).offset().top - 100
            }, 500);
        });
    });

    /**
     * Utility Functions
     */
    window.MMUtils = {
        /**
         * Format time in MM:SS
         */
        formatTime: function (seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        },

        /**
         * Debounce function
         */
        debounce: function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Sanitize HTML
         */
        escapeHtml: function (text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };

})(jQuery);
