<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap">
    <h1>Markdown & Quiz Manager</h1>

    <h2>Create New Markdown</h2>
    <form method="post">
        <input type="hidden" name="markdown_action" value="save_markdown">
        <textarea name="markdown_content" rows="5" cols="50"></textarea><br>
        <input type="submit" class="button button-primary" value="Save Markdown">
    </form>

    <h2>Create New Quiz</h2>
    <form method="post">
        <input type="hidden" name="quiz_action" value="save_quiz">
        <textarea name="quiz_content" rows="5" cols="50"></textarea><br>
        <input type="submit" class="button button-primary" value="Save Quiz">
    </form>
</div>
