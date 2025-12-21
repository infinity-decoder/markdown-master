jQuery(document).ready(function ($) {
    if (!$('#cotex-studio-root').length) return;

    // --- State ---
    let curriculum = cotexStudio.data || []; // [ { title, lessons: [ { id, title } ] } ]
    let activeLessonId = null;
    let isSaving = false;

    // --- Core Logic ---
    function renderCurriculum() {
        const $tree = $('#cotex-curriculum-tree');
        $tree.empty();

        curriculum.forEach((section, sIdx) => {
            const sectionHtml = `
                <div class="studio-section" data-idx="${sIdx}" data-id="${section.id}">
                    <div class="studio-section-header">
                        <span class="sec-title">${section.title || 'Untitled Section'}</span>
                        <div class="sec-actions">
                            <button class="add-lesson-btn" data-s="${sIdx}">+</button>
                        </div>
                    </div>
                    <div class="section-lessons">
                        ${(section.lessons || []).map(l => `
                            <div class="studio-lesson ${activeLessonId == l.id ? 'active' : ''}" data-id="${l.id}">
                                ${l.title || 'Untitled Lesson'}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            $tree.append(sectionHtml);
        });

        // Initialize D&D
        $('.section-lessons').sortable({
            connectWith: '.section-lessons',
            stop: syncCurriculum
        });
    }

    function syncCurriculum() {
        // Rebuild curriculum array from DOM
        const newCurriculum = [];
        $('.studio-section').each(function () {
            const section = {
                id: $(this).data('id'),
                title: $(this).find('.sec-title').text(),
                lessons: []
            };
            $(this).find('.studio-lesson').each(function () {
                section.lessons.push({
                    id: $(this).data('id'),
                    title: $(this).text().trim()
                });
            });
            newCurriculum.push(section);
        });
        curriculum = newCurriculum;
    }

    // --- Lesson Editor Logic ---
    function openLesson(id) {
        if (isSaving) return;
        activeLessonId = id;

        $('.canvas-state').hide();
        $('#cotex-lesson-editor').show();
        $('.active-lesson-settings').show();

        renderCurriculum(); // Highlight active

        // Fetch lesson data via AJAX if needed, or if we have tiny bootstrap
        // For Cotex Studio, we use an AJAX-based "Inline Load"
        const $title = $('#lesson-title-field');
        const $lessonEl = $(`.studio-lesson[data-id="${id}"]`);
        $title.val($lessonEl.text().trim());

        // Reset Editor content - in real world we'd fetch via AJAX
        // For V1, we simulate fetching content from a local cache or placeholder
        // IMPORTANT: We need the actual post content.
        fetchLessonData(id);
    }

    function fetchLessonData(id) {
        // In this implementation, we fetch the actual post object via REST or AJAX
        wp.ajax.post('get-post', { post_ID: id }).done(function (post) {
            if (window.tinymce && tinymce.get('cotex_studio_canvas_editor')) {
                tinymce.get('cotex_studio_canvas_editor').setContent(post.post_content);
            }
            $('#lesson-video-url').val(post.meta._cortex_video_url || '');
            $('#lesson-rule').val(post.meta._cortex_completion_rule || 'view');
        });
    }

    // --- Actions ---
    $('#add-section-btn').on('click', function () {
        const title = prompt("Section Title:");
        if (title) {
            wp.ajax.post('cotex_studio_create_section', {
                nonce: cotexStudio.nonce,
                course_id: cotexStudio.post_id,
                title: title
            }).done(function (res) {
                curriculum.push({ id: res.id, title: res.title, lessons: [] });
                renderCurriculum();
            });
        }
    });

    $(document).on('click', '.add-lesson-btn', function () {
        const sIdx = $(this).data('s');
        wp.ajax.post('cotex_studio_create_lesson', { nonce: cotexStudio.nonce }).done(function (res) {
            curriculum[sIdx].lessons.push({ id: res.id, title: res.title });
            renderCurriculum();
            openLesson(res.id);
        });
    });

    $(document).on('click', '.studio-lesson', function () {
        openLesson($(this).data('id'));
    });

    // Save Logic
    $('#cotex-studio-save').on('click', function () {
        const $btn = $(this);
        $btn.text('Saving...').prop('disabled', true);

        // 1. Save Active Lesson via AJAX
        if (activeLessonId) {
            const lessonData = {
                action: 'cotex_studio_save_lesson',
                nonce: cotexStudio.nonce,
                lesson_id: activeLessonId,
                title: $('#lesson-title-field').val(),
                content: (window.tinymce ? tinymce.get('cotex_studio_canvas_editor').getContent() : ''),
                video_url: $('#lesson-video-url').val(),
                rule: $('#lesson-rule').val()
            };
            $.post(ajaxurl, lessonData);
        }

        // 2. Save Course Data (Curriculum structure)
        syncCurriculum();
        $('#cortex_course_data_json').val(JSON.stringify(curriculum));
        $('#studio-course-title').each(function () {
            // Update the hidden real WP title field
            $('#title').val($(this).val());
        });

        // Trigger real WP Save
        $('#publish').click();
    });

    $('#cotex-studio-exit').on('click', function () {
        if (confirm('Exit Studio? Unsaved changes may be lost.')) {
            window.location.href = 'edit.php?post_type=cortex_course';
        }
    });

    // Init
    renderCurriculum();
});
