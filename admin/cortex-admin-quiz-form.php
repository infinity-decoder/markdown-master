<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$quiz = $quiz_id ? Cortex_Quiz::get_quiz($quiz_id) : null;
// Prepare data for JS
$quiz_data_json = $quiz ? json_encode([ 'lead_fields' => $quiz['lead_fields'] ?? [] ]) : '{}';
?>
<script>
    var cortex_quiz_data = <?php echo $quiz_data_json; ?>;
</script>
<div class="cortex-admin-wrap">
    <header class="cortex-header">
        <h1><?php echo $quiz_id ? esc_html__( 'Edit Quiz', 'cortex' ) : esc_html__( 'Create New Quiz', 'cortex' ); ?></h1>
        <div class="cortex-header-actions">
            <a href="<?php echo admin_url('admin.php?page=cortex_quizzes'); ?>" class="cortex-btn cortex-btn-light">Back to Quizzes</a>
        </div>
    </header>

    <form method="post" action="" class="cortex-modern-form" id="cortex-quiz-editor-form">
        <?php wp_nonce_field('cortex_save_quiz', 'cortex_quiz_nonce'); ?>
        
        <div class="cortex-tabs">
            <div class="cortex-tabs-nav">
                <a href="#cortex-tab-basic" class="active">Basic Info</a>
                <a href="#cortex-tab-questions">Questions</a>
                <a href="#cortex-tab-leads">Lead Capture</a>
                <a href="#cortex-tab-settings">Settings</a>
            </div>
        </div>

        <div class="cortex-tabs-container">
            <!-- Basic Info Tab -->
            <div id="cortex-tab-basic" class="cortex-tab-content active">
                <div class="cortex-form-card">
                    <div class="cortex-card-body">
                        <div class="cortex-form-group">
                            <label for="quiz_title">Quiz Title</label>
                            <input name="quiz_title" type="text" id="quiz_title" value="<?php echo esc_attr($quiz['title'] ?? ''); ?>" class="cortex-input-large" placeholder="Enter quiz title..." required>
                        </div>
                        <div class="cortex-form-group">
                            <label for="quiz_description">Description</label>
                            <?php 
                            wp_editor($quiz['description'] ?? '', 'quiz_description', array(
                                'textarea_name' => 'quiz_description',
                                'media_buttons' => true,
                                'textarea_rows' => 8,
                            )); 
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions Tab -->
            <div id="cortex-tab-questions" class="cortex-tab-content">
                <div class="cortex-questions-manager">
                    <div id="cortex-questions-list" class="cortex-sortable-list">
                        <!-- Questions will be loaded here via JS -->
                    </div>
                    
                    <div class="cortex-question-actions">
                        <select id="cortex-add-question-type" class="cortex-select">
                            <option value="radio">Single Choice (Radio)</option>
                            <option value="checkbox">Multiple Choice (Checkbox)</option>
                            <option value="dropdown">Dropdown Selection</option>
                            <option value="short_text">Short Text</option>
                            <option value="text">Long Text/Essay</option>
                            <option value="number">Numeric Answer</option>
                            <option value="date">Date Picker</option>
                            <option value="fill_blank">Fill in the Blanks</option>
                            <option value="matching">Matching Pairs</option>
                            <option value="sequence">Sequence Order</option>
                            <option value="banner">Content Banner</option>
                        </select>
                        <button type="button" class="cortex-btn cortex-btn-primary" id="cortex-btn-add-question">
                            <span class="dashicons dashicons-plus"></span> Add Question
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lead Capture Tab -->
            <div id="cortex-tab-leads" class="cortex-tab-content">
                <div class="cortex-form-card">
                    <div class="cortex-card-header">
                        <h3>Dynamic Field Builder</h3>
                        <div class="cortex-toggle-switch">
                            <input type="checkbox" name="enable_lead_capture" id="enable_lead_capture" value="1" <?php checked($quiz['enable_lead_capture'] ?? 0, 1); ?>>
                            <label for="enable_lead_capture"></label>
                            <span>Enable Lead Capture</span>
                        </div>
                    </div>
                    <div class="cortex-card-body">
                        <div id="cortex-lead-fields-builder">
                            <!-- Lead fields will be built here -->
                        </div>
                        <button type="button" class="cortex-btn cortex-btn-secondary" id="cortex-btn-add-lead-field">Add Lead Field</button>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="cortex-tab-settings" class="cortex-tab-content">
                <div class="cortex-form-grid">
                    <div class="cortex-form-card">
                        <div class="cortex-card-body">
                            <div class="cortex-form-group">
                                <label for="quiz_timer">Timer (seconds)</label>
                                <input type="number" name="quiz_timer" id="quiz_timer" value="<?php echo esc_attr($quiz['time_limit'] ?? 0); ?>" class="cortex-input-full">
                                <p class="description">Set to 0 for no time limit.</p>
                            </div>
                            <div class="cortex-form-group">
                                <label for="quiz_attempt_limit">Attempt Limit</label>
                                <input type="number" name="quiz_attempt_limit" id="quiz_attempt_limit" value="<?php echo esc_attr($quiz['attempts_allowed'] ?? 0); ?>" class="cortex-input-full">
                                <p class="description">Set to 0 for unlimited attempts.</p>
                            </div>
                        </div>
                    </div>
                    <div class="cortex-form-card">
                        <div class="cortex-card-body">
                            <div class="cortex-form-group">
                                <label class="cortex-checkbox-label">
                                    <input type="checkbox" name="randomize_questions" value="1" <?php checked($quiz['randomize_questions'] ?? 0, 1); ?>>
                                    <span>Randomize Questions</span>
                                </label>
                            </div>
                            <div class="cortex-form-group">
                                <label class="cortex-checkbox-label">
                                    <input type="checkbox" name="require_login" value="1" <?php checked($quiz['require_login'] ?? 0, 1); ?>>
                                    <span>Require Login to Take Quiz</span>
                                </label>
                            </div>
                            <div class="cortex-form-group">
                                <label class="cortex-checkbox-label">
                                    <input type="checkbox" name="show_answers" value="1" <?php checked($quiz['show_answers'] ?? 0, 1); ?>>
                                    <span>Show Correct Answers After Attempt</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cortex-form-actions">
            <button type="submit" class="cortex-btn cortex-btn-primary cortex-btn-large">Save Quiz</button>
            <span class="cortex-save-status"></span>
        </div>
    </form>
</div>

<!-- Underscore Templates for UI -->
<script type="text/html" id="tmpl-cortex-question-item">
    <div class="cortex-question-item card" data-id="<#= data.id #>" data-type="<#= data.type #>">
        <div class="cortex-question-header">
            <span class="cortex-drag-handle dashicons dashicons-menu"></span>
            <span class="cortex-question-badge"><#= data.type.toUpperCase() #></span>
            <div class="cortex-question-title-preview">
                <#= data.question_text || '(New Question)' #>
            </div>
            <div class="cortex-question-tools">
                <button type="button" class="cortex-btn-icon cortex-toggle-edit" title="Toggle Edit"><span class="dashicons dashicons-edit"></span></button>
                <button type="button" class="cortex-btn-icon cortex-delete-question danger" title="Delete"><span class="dashicons dashicons-trash"></span></button>
            </div>
        </div>
        <div class="cortex-question-edit-body" style="display:none;">
            <div class="cortex-form-group">
                <label>Question Content (Markdown/LaTeX supported)</label>
                <textarea name="questions[<#= data.id #>][text]" class="cortex-textarea-small cortex-editor-trigger"><#= data.question_text #></textarea>
            </div>
            
            <div class="cortex-type-fields">
                <# if ( ['radio', 'checkbox', 'dropdown', 'sequence'].indexOf(data.type) !== -1 ) { #>
                    <div class="cortex-options-builder">
                        <label>Options</label>
                        <div class="cortex-options-list">
                            <# _.each(data.options, function(opt, idx) { #>
                                <div class="cortex-option-row">
                                    <input type="text" name="questions[<#= data.id #>][options][]" value="<#= opt #>" class="cortex-input-small">
                                    <input type="<#= data.type === 'checkbox' ? 'checkbox' : 'radio' #>" name="questions[<#= data.id #>][correct][]" value="<#= idx #>" <#= (data.correct_answer == idx || (_.isArray(data.correct_answer) && _.contains(data.correct_answer, idx.toString()))) ? 'checked' : '' #>>
                                    <button type="button" class="cortex-remove-option dashicons dashicons-no-alt"></button>
                                </div>
                            <# }); #>
                        </div>
                        <button type="button" class="cortex-btn-link cortex-add-option">+ Add Option</button>
                    </div>
                <# } else if (data.type === 'matching') { #>
                    <div class="cortex-pairs-builder">
                        <label>Pairs (Left match Right)</label>
                        <div class="cortex-pairs-list">
                             <# if (data.metadata && data.metadata.pairs) { #>
                                 <# _.each(data.metadata.pairs, function(right, left) { #>
                                    <div class="cortex-pair-row">
                                        <input type="text" name="questions[<#= data.id #>][pairs][left][]" value="<#= left #>" placeholder="Left">
                                        <span class="dashicons dashicons-arrow-right-alt"></span>
                                        <input type="text" name="questions[<#= data.id #>][pairs][right][]" value="<#= right #>" placeholder="Right">
                                        <button type="button" class="cortex-remove-pair dashicons dashicons-no-alt"></button>
                                    </div>
                                 <# }); #>
                             <# } #>
                        </div>
                        <button type="button" class="cortex-btn-link cortex-add-pair">+ Add Pair</button>
                    </div>
                <# } else if (data.type === 'fill_blank') { #>
                    <div class="cortex-blanks-builder">
                        <p class="description">Use <code>[blank]</code> in the question text above. Each <code>[blank]</code> will be replaced by an input field.</p>
                        <div class="cortex-form-group">
                            <label>Correct Answers (one per blank, in order)</label>
                            <textarea name="questions[<#= data.id #>][correct]" class="cortex-textarea-tiny" placeholder="Answer 1&#10;Answer 2"><?php 
                                // This won't work inside underscore template easily for complex data
                                // but we can handle it in JS rendering
                            ?><#= _.isArray(data.correct_answer) ? data.correct_answer.join('\n') : data.correct_answer #></textarea>
                        </div>
                    </div>
                <# } else if (data.type === 'number') { #>
                    <div class="cortex-number-config">
                        <div class="cortex-form-group">
                            <label>Correct Number</label>
                            <input type="number" step="any" name="questions[<#= data.id #>][correct]" value="<#= data.correct_answer #>" class="cortex-input-small">
                        </div>
                    </div>
                <# } else if (data.type === 'date') { #>
                    <div class="cortex-date-config">
                        <div class="cortex-form-group">
                            <label>Correct Date</label>
                            <input type="date" name="questions[<#= data.id #>][correct]" value="<#= data.correct_answer #>" class="cortex-input-medium">
                        </div>
                    </div>
                <# } #>
            </div>

            <div class="cortex-question-footer">
                <div class="cortex-form-group-inline">
                    <label>Points</label>
                    <input type="number" name="questions[<#= data.id #>][points]" value="<#= data.points || 1 #>" class="cortex-input-tiny" step="0.5">
                </div>
                <div class="cortex-form-group-inline">
                    <label>Hint (optional)</label>
                    <input type="text" name="questions[<#= data.id #>][hint]" value="<#= data.hint #>" class="cortex-input-medium">
                </div>
            </div>
        </div>
    </div>
</script>

<!-- Template: Lead Field Item -->
<script type="text/html" id="tmpl-cortex-lead-field-item">
    <div class="cortex-lead-field-item cortex-card" data-id="<#= data.id #>">
        <div class="cortex-card-header cortex-sortable-handle">
            <strong><span class="dashicons dashicons-menu"></span> <#= data.label || 'New Field' #></strong>
            <button type="button" class="cortex-btn-icon cortex-remove-lead-field danger"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="cortex-card-body">
            <div class="cortex-form-row">
                <div class="cortex-field-col">
                    <label>Field Label</label>
                    <input type="text" name="lead_fields[<#= data.id #>][label]" value="<#= data.label #>" class="cortex-input-full cortex-update-preview" placeholder="e.g. Full Name">
                </div>
                <div class="cortex-field-col">
                    <label>Type</label>
                    <select name="lead_fields[<#= data.id #>][type]" class="cortex-select-full">
                        <option value="text" <#= data.type === 'text' ? 'selected' : '' #>>Text</option>
                        <option value="email" <#= data.type === 'email' ? 'selected' : '' #>>Email</option>
                        <option value="number" <#= data.type === 'number' ? 'selected' : '' #>>Number</option>
                        <option value="select" <#= data.type === 'select' ? 'selected' : '' #>>Dropdown</option>
                    </select>
                </div>
            </div>
            <div class="cortex-form-row">
                <div class="cortex-field-col">
                    <label>Field Name (Slug)</label>
                    <input type="text" name="lead_fields[<#= data.id #>][name]" value="<#= data.name #>" class="cortex-input-full" placeholder="e.g. full_name">
                </div>
                <div class="cortex-field-col" style="padding-top: 24px;">
                    <label class="cortex-checkbox-inline">
                        <input type="checkbox" name="lead_fields[<#= data.id #>][required]" value="1" <#= data.required ? 'checked' : '' #>> Required
                    </label>
                </div>
            </div>
            <# if (data.type === 'select') { #>
                <div class="cortex-form-group" style="margin-top: 10px;">
                     <label>Options (comma separated)</label>
                     <input type="text" name="lead_fields[<#= data.id #>][options]" value="<#= data.options #>" class="cortex-input-full" placeholder="Option 1, Option 2">
                </div>
            <# } #>
        </div>
    </div>
</script>
