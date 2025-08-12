<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$note_id = isset($_GET['note_id']) ? intval($_GET['note_id']) : 0;
$note = $note_id ? MM_Note::get_note($note_id) : null;
?>
<div class="wrap">
    <h1><?php echo $note ? 'Edit Markdown Note' : 'Create Markdown Note'; ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('mm_save_note', 'mm_note_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th><label for="note_title">Note Title</label></th>
                <td><input name="note_title" type="text" id="note_title" value="<?php echo esc_attr($note->title ?? ''); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="note_content">Markdown Content</label></th>
                <td>
                    <textarea name="note_content" id="note_content" rows="12" class="large-text code"><?php echo esc_textarea($note->content ?? ''); ?></textarea>
                </td>
            </tr>
        </table>

        <p class="submit"><input type="submit" class="button-primary" value="Save Note"></p>
    </form>
</div>
