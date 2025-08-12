<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$snippet_id = isset($_GET['snippet_id']) ? intval($_GET['snippet_id']) : 0;
$snippet = $snippet_id ? MM_Snippet::get_snippet($snippet_id) : null;
?>
<div class="wrap">
    <h1><?php echo $snippet ? 'Edit Code Snippet' : 'Create Code Snippet'; ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('mm_save_snippet', 'mm_snippet_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th><label for="snippet_title">Snippet Title</label></th>
                <td><input name="snippet_title" type="text" id="snippet_title" value="<?php echo esc_attr($snippet->title ?? ''); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="snippet_language">Language</label></th>
                <td>
                    <input name="snippet_language" type="text" id="snippet_language" value="<?php echo esc_attr($snippet->language ?? ''); ?>" placeholder="e.g. php, javascript, python" required>
                </td>
            </tr>
            <tr>
                <th><label for="snippet_code">Code</label></th>
                <td>
                    <textarea name="snippet_code" id="snippet_code" rows="12" class="large-text code"><?php echo esc_textarea($snippet->code ?? ''); ?></textarea>
                </td>
            </tr>
        </table>

        <p class="submit"><input type="submit" class="button-primary" value="Save Snippet"></p>
    </form>
</div>
