<?php
/**
 * Cotex LMS App Shell
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php wp_title( '|', true, 'right' ); ?> Cotex LMS</title>
	<?php wp_head(); ?>
</head>
<body class="cotex-lms-app-body">

<div class="cotex-lms-app">
	<!-- TOP NAV -->
	<header class="lms-app-header">
		<div class="lms-brand">
			<span class="dashicons dashicons-welcome-learn-more"></span>
			COTEX <span class="v-tag">LMS</span>
		</div>
		<nav class="lms-main-nav">
			<a href="<?php echo home_url('/lms/catalog/'); ?>" class="<?php echo (get_query_var('cotex_lms_page') == 'catalog') ? 'active' : ''; ?>">Catalog</a>
			<a href="<?php echo home_url('/lms/dashboard/'); ?>" class="<?php echo (get_query_var('cotex_lms_page') == 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
		</nav>
		<div class="lms-user-actions">
			<?php if ( is_user_logged_in() ) : ?>
				<span class="user-name"><?php echo wp_get_current_user()->display_name; ?></span>
				<a href="<?php echo wp_logout_url( home_url() ); ?>" class="lms-btn-logout">Logout</a>
			<?php else : ?>
				<a href="<?php echo wp_login_url( home_url('/lms/catalog/') ); ?>" class="lms-btn-login">Sign In</a>
			<?php endif; ?>
		</div>
	</header>

	<!-- MAIN VIEWPORT -->
	<main class="lms-app-viewport">
		<?php 
		// The individual template content will be included here via the interceptor.
		// However, since we are using template_include to return THIS shell (or the specific template),
		// we need a way to let the specific template render INSIDE this shell.
		// A common pattern is to have the shell provide the header/footer and include the content.
		
		// In this specific implementation, Templates::intercept_templates returns a template path.
		// If that template path is e.g. lesson-view.php, it should include app-shell-header.php and app-shell-footer.php.
		
		// OR we can make app-shell.php a "partial" system.
		// For simplicity and strictness, let's make app-shell-header.php and app-shell-footer.php.
		?>
