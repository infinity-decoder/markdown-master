<?php
/**
 * Cotex LMS Assignment Submission
 */

require_once __DIR__ . '/app-shell-header.php';
?>

<div class="lms-page-container">
	<div class="lms-content-split" style="grid-template-columns: 1fr;">
		<div class="lms-section-box">
			<h1 class="lms-page-title" style="font-size: 1.8rem;">Submit Assignment</h1>
			<p class="instructor-note" style="color: var(--lms-text-secondary); margin-bottom: 40px;">Please upload your project files and provide a brief summary of your implementation.</p>

			<form class="assignment-form">
				<div class="form-group" style="margin-bottom: 30px;">
					<label style="display:block; margin-bottom: 15px; font-weight: 700;">Submission Text</label>
					<textarea style="width:100%; height: 200px; background: rgba(255,255,255,0.03); border: 1px solid var(--lms-border); border-radius: 8px; color: #fff; padding: 20px;" placeholder="Write your submission details here..."></textarea>
				</div>

				<div class="form-group" style="margin-bottom: 40px;">
					<label style="display:block; margin-bottom: 15px; font-weight: 700;">File Upload (ZIP, PDF)</label>
					<div class="upload-zone" style="border: 2px dashed var(--lms-border); padding: 50px; text-align: center; border-radius: 12px; transition: border-color 0.2s; cursor: pointer;">
						<span class="dashicons dashicons-upload" style="font-size: 40px; width: 40px; height: 40px; color: var(--lms-primary); margin-bottom: 15px;"></span>
						<p style="margin:0; color: var(--lms-text-secondary);">Drag and drop files here or <span style="color: var(--lms-primary);">browse</span></p>
					</div>
				</div>

				<div class="form-actions">
					<button type="submit" class="lms-btn-action" style="padding: 15px 50px; font-size: 1rem;">Submit Assignment</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php
require_once __DIR__ . '/app-shell-footer.php';
