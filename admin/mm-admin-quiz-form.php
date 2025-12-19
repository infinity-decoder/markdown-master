<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$quiz = $quiz_id ? MM_Quiz::get_quiz($quiz_id) : null;
?>
<div class="mm-admin-wrap">
    <header class="mm-header">
        <h1><?php echo $quiz_id ? esc_html__( 'Edit Quiz', 'markdown-master' ) : esc_html__( 'Create New Quiz', 'markdown-master' ); ?></h1>
        <div class="mm-header-actions">
            <a href="<?php echo admin_url('admin.php?page=mm_quizzes'); ?>" class="mm-btn mm-btn-light">Back to Quizzes</a>
        </div>
    </header>

    <form method="post" action="" class="mm-modern-form" id="mm-quiz-editor-form">
        <?php wp_nonce_field('mm_save_quiz', 'mm_quiz_nonce'); ?>
        
        <div class="mm-tabs">
            <div class="mm-tabs-nav">
                <a href="#mm-tab-basic" class="active">Basic Info</a>
                <a href="#mm-tab-questions">Questions</a>
                <a href="#mm-tab-leads">Lead Capture</a>
                <a href="#mm-tab-settings">Settings</a>
            </div>
        </div>

        <div class="mm-tabs-container">
            <!-- Basic Info Tab -->
            <div id="mm-tab-basic" class="mm-tab-content active">
                <div class="mm-form-card">
                    <div class="mm-card-body">
                        <div class="mm-form-group">
                            <label for="quiz_title">Quiz Title</label>
                            <input name="quiz_title" type="text" id="quiz_title" value="<?php echo esc_attr($quiz['title'] ?? ''); ?>" class="mm-input-large" placeholder="Enter quiz title..." required>
                        </div>
                        <div class="mm-form-group">
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
            <div id="mm-tab-questions" class="mm-tab-content">
                <div class="mm-questions-manager">
                    <div id="mm-questions-list" class="mm-sortable-list">
                        <!-- Questions will be loaded here via JS -->
                    </div>
                    
                    <div class="mm-question-actions">
                        <select id="mm-add-question-type" class="mm-select">
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
                        <button type="button" class="mm-btn mm-btn-primary" id="mm-btn-add-question">
                            <span class="dashicons dashicons-plus"></span> Add Question
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lead Capture Tab -->
            <div id="mm-tab-leads" class="mm-tab-content">
                <div class="mm-form-card">
                    <div class="mm-card-header">
                        <h3>Dynamic Field Builder</h3>
                        <div class="mm-toggle-switch">
                            <input type="checkbox" name="enable_lead_capture" id="enable_lead_capture" value="1" <?php checked($quiz['enable_lead_capture'] ?? 0, 1); ?>>
                            <label for="enable_lead_capture"></label>
                            <span>Enable Lead Capture</span>
                        </div>
                    </div>
                    <div class="mm-card-body">
                        <div id="mm-lead-fields-builder">
                            <!-- Lead fields will be built here -->
                        </div>
                        <button type="button" class="mm-btn mm-btn-secondary" id="mm-btn-add-lead-field">Add Lead Field</button>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="mm-tab-settings" class="mm-tab-content">
                <div class="mm-form-grid">
                    <div class="mm-form-card">
                        <div class="mm-card-body">
                            <div class="mm-form-group">
                                <label for="quiz_timer">Timer (seconds)</label>
                                <input type="number" name="quiz_timer" id="quiz_timer" value="<?php echo esc_attr($quiz['time_limit'] ?? 0); ?>" class="mm-input-full">
                                <p class="description">Set to 0 for no time limit.</p>
                            </div>
                            <div class="mm-form-group">
                                <label for="quiz_attempt_limit">Attempt Limit</label>
                                <input type="number" name="quiz_attempt_limit" id="quiz_attempt_limit" value="<?php echo esc_attr($quiz['attempts_allowed'] ?? 0); ?>" class="mm-input-full">
                                <p class="description">Set to 0 for unlimited attempts.</p>
                            </div>
                        </div>
                    </div>
                    <div class="mm-form-card">
                        <div class="mm-card-body">
                            <div class="mm-form-group">
                                <label class="mm-checkbox-label">
                                    <input type="checkbox" name="randomize_questions" value="1" <?php checked($quiz['randomize_questions'] ?? 0, 1); ?>>
                                    <span>Randomize Questions</span>
                                </label>
                            </div>
                            <div class="mm-form-group">
                                <label class="mm-checkbox-label">
                                    <input type="checkbox" name="require_login" value="1" <?php checked($quiz['require_login'] ?? 0, 1); ?>>
                                    <span>Require Login to Take Quiz</span>
                                </label>
                            </div>
                            <div class="mm-form-group">
                                <label class="mm-checkbox-label">
                                    <input type="checkbox" name="show_answers" value="1" <?php checked($quiz['show_answers'] ?? 0, 1); ?>>
                                    <span>Show Correct Answers After Attempt</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mm-form-actions">
            <button type="submit" class="mm-btn mm-btn-primary mm-btn-large">Save Quiz</button>
            <span class="mm-save-status"></span>
        </div>
    </form>
</div>

<!-- Underscore Templates for UI -->
<script type="text/html" id="tmpl-mm-question-item">
    <div class="mm-question-item card" data-id="<#= data.id #>" data-type="<#= data.type #>">
        <div class="mm-question-header">
            <span class="mm-drag-handle dashicons dashicons-menu"></span>
            <span class="mm-question-badge"><#= data.type.toUpperCase() #></span>
            <div class="mm-question-title-preview">
                <#= data.question_text || '(New Question)' #>
            </div>
            <div class="mm-question-tools">
                <button type="button" class="mm-btn-icon mm-toggle-edit" title="Toggle Edit"><span class="dashicons dashicons-edit"></span></button>
                <button type="button" class="mm-btn-icon mm-delete-question danger" title="Delete"><span class="dashicons dashicons-trash"></span></button>
            </div>
        </div>
        <div class="mm-question-edit-body" style="display:none;">
            <div class="mm-form-group">
                <label>Question Content (Markdown/LaTeX supported)</label>
                <textarea name="questions[<#= data.id #>][text]" class="mm-textarea-small mm-editor-trigger"><#= data.question_text #></textarea>
            </div>
            
            <div class="mm-type-fields">
                <# if ( ['radio', 'checkbox', 'dropdown', 'sequence'].indexOf(data.type) !== -1 ) { #>
                    <div class="mm-options-builder">
                        <label>Options</label>
                        <div class="mm-options-list">
                            <# _.each(data.options, function(opt, idx) { #>
                                <div class="mm-option-row">
                                    <input type="text" name="questions[<#= data.id #>][options][]" value="<#= opt #>" class="mm-input-small">
                                    <input type="<#= data.type === 'checkbox' ? 'checkbox' : 'radio' #>" name="questions[<#= data.id #>][correct][]" value="<#= idx #>" <#= (data.correct_answer == idx || (_.isArray(data.correct_answer) && _.contains(data.correct_answer, idx.toString()))) ? 'checked' : '' #>>
                                    <button type="button" class="mm-remove-option dashicons dashicons-no-alt"></button>
                                </div>
                            <# }); #>
                        </div>
                        <button type="button" class="mm-btn-link mm-add-option">+ Add Option</button>
                    </div>
                <# } else if (data.type === 'matching') { #>
                    <div class="mm-pairs-builder">
                        <label>Pairs (Left match Right)</label>
                        <div class="mm-pairs-list">
                             <# if (data.metadata && data.metadata.pairs) { #>
                                 <# _.each(data.metadata.pairs, function(right, left) { #>
                                    <div class="mm-pair-row">
                                        <input type="text" name="questions[<#= data.id #>][pairs][left][]" value="<#= left #>" placeholder="Left">
                                        <span class="dashicons dashicons-arrow-right-alt"></span>
                                        <input type="text" name="questions[<#= data.id #>][pairs][right][]" value="<#= right #>" placeholder="Right">
                                        <button type="button" class="mm-remove-pair dashicons dashicons-no-alt"></button>
                                    </div>
                                 <# }); #>
                             <# } #>
                        </div>
                        <button type="button" class="mm-btn-link mm-add-pair">+ Add Pair</button>
                    </div>
                <# } else if (data.type === 'fill_blank') { #>
                    <div class="mm-blanks-builder">
                        <p class="description">Use <code>[blank]</code> in the question text above. Each <code>[blank]</code> will be replaced by an input field.</p>
                        <div class="mm-form-group">
                            <label>Correct Answers (one per blank, in order)</label>
                            <textarea name="questions[<#= data.id #>][correct]" class="mm-textarea-tiny" placeholder="Answer 1&#10;Answer 2"><?php 
                                // This won't work inside underscore template easily for complex data
                                // but we can handle it in JS rendering
                            ?><#= _.isArray(data.correct_answer) ? data.correct_answer.join('\n') : data.correct_answer #></textarea>
                        </div>
                    </div>
                <# } else if (data.type === 'number') { #>
                    <div class="mm-number-config">
                        <div class="mm-form-group">
                            <label>Correct Number</label>
                            <input type="number" step="any" name="questions[<#= data.id #>][correct]" value="<#= data.correct_answer #>" class="mm-input-small">
                        </div>
                    </div>
                <# } else if (data.type === 'date') { #>
                    <div class="mm-date-config">
                        <div class="mm-form-group">
                            <label>Correct Date</label>
                            <input type="date" name="questions[<#= data.id #>][correct]" value="<#= data.correct_answer #>" class="mm-input-medium">
                        </div>
                    </div>
                <# } #>
            </div>

            <div class="mm-question-footer">
                <div class="mm-form-group-inline">
                    <label>Points</label>
                    <input type="number" name="questions[<#= data.id #>][points]" value="<#= data.points || 1 #>" class="mm-input-tiny" step="0.5">
                </div>
                <div class="mm-form-group-inline">
                    <label>Hint (optional)</label>
                    <input type="text" name="questions[<#= data.id #>][hint]" value="<#= data.hint #>" class="mm-input-medium">
                </div>
            </div>
        </div>
    </div>
</script>
