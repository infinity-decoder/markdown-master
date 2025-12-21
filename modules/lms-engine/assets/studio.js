```javascript
jQuery(document).ready(function ($) {
    if (!$('#cotex-studio-root').length) return;

    // Utils
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // --- State Management ---
    let courseData = cotexStudio.data || []; // Array of Sections
    // Ensure structure
    if (!Array.isArray(courseData)) courseData = [];

    let activeLesson = null; // { sIndex, lIndex }

    // --- Renders ---
    function renderTree() {
        const tree = $('#cotex-curriculum-tree');
        tree.empty();

        courseData.forEach((section, sIndex) => {
            let lessonsHtml = '';
            if (section.lessons && section.lessons.length) {
                section.lessons.forEach((lesson, lIndex) => {
                    const isActive = activeLesson && activeLesson.sIndex === sIndex && activeLesson.lIndex === lIndex;
                    lessonsHtml += `
    < div class="studio-lesson ${isActive ? 'active' : ''}" data - s="${sIndex}" data - l="${lIndex}" >
        ${ lesson.title || 'Untitled Lesson' }
                        </div >
    `;
                });
            }

            const sectionHtml = `
    < div class="studio-section" >
        <div class="studio-section-header">
            <span>${section.title || 'Untitled Section'}</span>
            <button class="cotex-btn-secondary" style="padding:2px 6px; font-size:10px;" onclick="addLesson(${sIndex})">+</button>
        </div>
                    ${ lessonsHtml }
                </div >
    `;
            tree.append(sectionHtml);
        });
    }

    function renderEditor() {
        if (!activeLesson) {
            $('#cotex-canvas-placeholder').show();
            $('#cotex-lesson-editor').hide();
            return;
        }

        const lesson = courseData[activeLesson.sIndex].lessons[activeLesson.lIndex];

        $('#cotex-canvas-placeholder').hide();
        $('#cotex-lesson-editor').show();

        $('#lesson-title-input').val(lesson.title || '');
        $('#lesson-content-area').html(lesson.content || '');
    }

    // --- Actions ---
    window.addLesson = function (sIndex) {
        if (!courseData[sIndex].lessons) courseData[sIndex].lessons = [];
        courseData[sIndex].lessons.push({
            id: generateUUID(),
            title: 'New Lesson',
            content: ''
        });
        renderTree();
        // Auto select
        activeLesson = { sIndex: sIndex, lIndex: courseData[sIndex].lessons.length - 1 };
        renderTree();
        renderEditor();
    };

    $('#add-section-btn').on('click', function () {
        const title = prompt("Section Title:");
        if (title) {
            courseData.push({
                title: title,
                lessons: []
            });
            renderTree();
        }
    });

    $(document).on('click', '.studio-lesson', function () {
        const s = $(this).data('s');
        const l = $(this).data('l');
        activeLesson = { sIndex: s, lIndex: l };
        renderTree();
        renderEditor();
    });

    // Content Updates
    $('#lesson-title-input').on('input', function () {
        if (activeLesson) {
            courseData[activeLesson.sIndex].lessons[activeLesson.lIndex].title = $(this).val();
            // Debounce re-render tree to avoid flicker? 
            // For now simple:
            $(`.studio - lesson[data - s="${activeLesson.sIndex}"][data - l="${activeLesson.lIndex}"]`).text($(this).val());
        }
    });

    $('#lesson-content-area').on('input', function () {
        if (activeLesson) {
            courseData[activeLesson.sIndex].lessons[activeLesson.lIndex].content = $(this).html();
        }
    });

    // Save
    $('#cotex-studio-save').on('click', function () {
        const json = JSON.stringify(courseData);
        $('#cortex_course_data_json').val(json);

        // Trigger WP Save
        $('#publish').click();

        // Show saving state
        const btn = $(this);
        const originalText = btn.text();
        btn.text('Saved!');
        setTimeout(() => btn.text(originalText), 2000);
    });

    $('#cotex-studio-exit').on('click', function () {
        window.history.back();
    });

    // Init
    renderTree();
});
