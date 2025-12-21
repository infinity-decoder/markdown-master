<?php
/**
 * Cotex LMS Certificate View
 */

require_once __DIR__ . '/app-shell-header.php';
?>

<div class="lms-page-container">
	<div class="certificate-container" style="max-width: 1000px; margin: 0 auto; background: #fff; color: #000; padding: 80px; text-align: center; border-radius: 4px; box-shadow: 0 40px 100px rgba(0,0,0,0.5); position: relative; overflow: hidden;">
		<!-- Certificate Ornament -->
		<div style="position: absolute; top:0; left:0; right:0; height: 10px; background: var(--lms-primary);"></div>
		
		<div class="cert-header" style="margin-bottom: 60px;">
			<h1 style="font-family: 'Orbitron', sans-serif; font-size: 3rem; margin: 0; color: #121C3D;">CERTIFICATE</h1>
			<p style="text-transform: uppercase; letter-spacing: 5px; color: var(--lms-text-secondary); margin-top: 10px;">of Completion</p>
		</div>

		<div class="cert-body" style="margin-bottom: 60px;">
			<p style="font-size: 1.2rem; color: #444;">This is to certify that</p>
			<h2 style="font-family: 'Exo 2', sans-serif; font-size: 2.5rem; margin: 20px 0; border-bottom: 2px solid #eee; display: inline-block; padding: 0 50px;">John Doe</h2>
			<p style="font-size: 1.2rem; color: #444;">has successfully completed the course</p>
			<h3 style="font-family: 'Orbitron', sans-serif; font-size: 1.8rem; color: #121C3D;">Advanced LMS Architecture Study</h3>
		</div>

		<div class="cert-footer" style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 80px; border-top: 1px solid #eee; padding-top: 40px;">
			<div class="cert-id" style="text-align: left; font-size: 0.8rem; color: #999;">
				UNIQUE CERTIFICATE ID<br>
				<strong style="color: #666;">CTX-8822-LMS-2025</strong>
			</div>
			<div class="cert-date" style="text-align: right; font-size: 0.8rem; color: #999;">
				DATE ISSUED<br>
				<strong style="color: #666;"><?php echo date('F j, Y'); ?></strong>
			</div>
		</div>
	</div>

	<div class="cert-actions" style="margin-top: 50px; text-align: center;">
		<button class="lms-btn-action" style="padding: 15px 50px;">Download PDF Portrait</button>
		<button class="lms-btn-secondary" style="margin-left: 20px; padding: 15px 50px;">Share on LinkedIn</button>
	</div>
</div>

<?php
require_once __DIR__ . '/app-shell-footer.php';
