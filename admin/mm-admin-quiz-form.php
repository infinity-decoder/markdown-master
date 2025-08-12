<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$quiz = $quiz_id ? MM_Quiz::get_quiz($quiz_id) : null;
?>
<div class="wrap">
    <h1><?php echo $quiz ? 'Edit Quiz' : 'Create New Quiz'; ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('mm_save_quiz', 'mm_quiz_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th><label for="quiz_title">Quiz Title</label></th>
                <td><input name="quiz_title" type="text" id="quiz_title" value="<?php echo esc_attr($quiz->title ?? ''); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="quiz_type">Quiz Type</label></th>
                <td>
                    <select name="quiz_type" id="quiz_type">
                        <option value="mcq" <?php selected($quiz->type ?? '', 'mcq'); ?>>Multiple Choice</option>
                        <option value="short" <?php selected($quiz->type ?? '', 'short'); ?>>Short Answer</option>
                        <option value="survey" <?php selected($quiz->type ?? '', 'survey'); ?>>Survey</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="quiz_timer">Timer (seconds)</label></th>
                <td><input type="number" name="quiz_timer" id="quiz_timer" value="<?php echo esc_attr($quiz->timer ?? 0); ?>"></td>
            </tr>
            <tr>
                <th><label for="quiz_attempt_limit">Attempt Limit</label></th>
                <td><input type="number" name="quiz_attempt_limit" id="quiz_attempt_limit" value="<?php echo esc_attr($quiz->attempt_limit ?? 0); ?>"></td>
            </tr>
        </table>

        <h2>Questions</h2>
        <div id="mm-quiz-questions">
            <?php if (!empty($quiz->questions)): foreach ($quiz->questions as $i => $q): ?>
                <div class="mm-question">
                    <input type="text" name="questions[<?php echo $i; ?>][text]" value="<?php echo esc_attr($q['text']); ?>" placeholder="Question text" required>
                    <input type="file" name="questions[<?php echo $i; ?>][image]">
                    <select name="questions[<?php echo $i; ?>][correct]">
                        <option value="">No correct answer</option>
                        <?php foreach ($q['options'] as $key => $opt): ?>
                            <option value="<?php echo $key; ?>" <?php selected($q['correct'], $key); ?>>Option <?php echo $key+1; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mm-options">
                        <?php foreach ($q['options'] as $key => $opt): ?>
                            <input type="text" name="questions[<?php echo $i; ?>][options][]" value="<?php echo esc_attr($opt); ?>" placeholder="Option <?php echo $key+1; ?>">
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <button type="button" id="mm-add-question" class="button">Add Question</button>

        <p class="submit"><input type="submit" class="button-primary" value="Save Quiz"></p>
    </form>
</div>
