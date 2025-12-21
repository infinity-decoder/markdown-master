jQuery(document).ready(function ($) {
    if (!$('#cotex-builder-app').length) return;

    // Sortables
    function updateSortables() {
        $('#cotex-sections-container').sortable({
            handle: '.cotex-section-header .handle',
            placeholder: 'cotex-sortable-placeholder',
            forcePlaceholderSize: true
        });

        $('.cotex-lessons-list').sortable({
            connectWith: '.cotex-lessons-list',
            handle: '.handle',
            placeholder: 'cotex-sortable-placeholder',
            forcePlaceholderSize: true
        });
    }

    updateSortables();

    // Add Section
    $('#cotex-add-section').on('click', function () {
        var count = $('.cotex-section').length;
        var html = `
            <div class="cotex-section" data-index="${count}">
                <div class="cotex-section-header">
                    <span class="dashicons dashicons-move handle"></span>
                    <input type="text" name="cotex_sections[${count}][title]" value="" placeholder="New Section" class="cotex-input-clean">
                    <button type="button" class="cotex-icon-btn remove-section">&times;</button>
                </div>
                <div class="cotex-lessons-list"></div>
                <div class="cotex-section-footer">
                    <button type="button" class="button button-secondary add-lesson-btn" data-section="${count}">+ Add Lesson</button>
                </div>
            </div>
        `;

        if ($('.cotex-empty-state').length) {
            $('.cotex-empty-state').remove();
        }

        $('#cotex-sections-container').append(html);
        updateSortables();
    });

    // Remove Section/Lesson (Delegated)
    $(document).on('click', '.remove-section', function () {
        if (confirm('Delete this section?')) {
            $(this).closest('.cotex-section').remove();
        }
    });

    $(document).on('click', '.remove-lesson', function () {
        $(this).closest('.cotex-builder-lesson').remove();
    });

    // Mock "Add Lesson" flow - In reality would open a modal with AJAX search
    $(document).on('click', '.add-lesson-btn', function () {
        var sectionIndex = $(this).data('section');
        var lessonId = prompt("Enter Lesson ID to add (Mock):"); // Replace with Modal later
        if (lessonId) {
            var lessonTitle = "Lesson " + lessonId; // Fetch via AJAX in real impl

            var html = `
                <div class="cotex-builder-lesson">
                    <span class="dashicons dashicons-menu handle"></span>
                    <span class="lesson-title">${lessonTitle}</span>
                    <input type="hidden" name="cotex_sections[${sectionIndex}][lessons][]" value="${lessonId}">
                    <a href="#" class="edit-link">Edit</a>
                    <button type="button" class="remove-lesson">&times;</button>
                </div>
            `;

            $(this).closest('.cotex-section').find('.cotex-lessons-list').append(html);
        }
    });
});
