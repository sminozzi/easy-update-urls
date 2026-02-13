<?php
/**
 * Main dashboard container for Easy Update URLs.
 *
 * @package easy-update-urls
 * @author  sminozzi
 * @license GPL-2.0-or later
 * @link    https://billminozzi.com
 */

namespace Sminozzi\EasyUpdateUrls;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'easy-update-urls' ) );
}

$easy_update_urls_active_tab = 'dashboard';
if ( isset( $_GET['tab'] ) ) {
	// Verificação de segurança para troca de abas.
	check_admin_referer( 'easy-update-url' );
	$easy_update_urls_active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
}

/**
 * Nonce.
 */
$easy_update_urls_nonce = wp_create_nonce( 'easy-update-url' );
?>
<div id="easy-update-urls-logo">
	<img src="<?php echo esc_url( EASY_UPDATE_URLS_IMAGES ); ?>/logo.png" width="250" alt="Logo">
</div>
<h2 class="nav-tab-wrapper">
	<a href="tools.php?page=easy_update_urls_admin_page&tab=dashboard&_wpnonce=<?php echo esc_attr( $easy_update_urls_nonce ); ?>" class="nav-tab <?php echo 'dashboard' === $easy_update_urls_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Dashboard', 'easy-update-urls' ); ?>
	</a>
	<a href="tools.php?page=easy_update_urls_admin_page&tab=update&_wpnonce=<?php echo esc_attr( $easy_update_urls_nonce ); ?>" class="nav-tab <?php echo 'update' === $easy_update_urls_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Search/Replace', 'easy-update-urls' ); ?>
	</a>
</h2>
<div id="easy-update-urls-dashboard-wrap">
	<div id="easy-update-urls-dashboard-left">
		<?php
		if ( 'update' === $easy_update_urls_active_tab ) {
			require_once EASY_UPDATE_URLS_PATH . 'dashboard/update.php';
		} else {
			require_once EASY_UPDATE_URLS_PATH . 'dashboard/dashboard.php';
		}
		?>
	</div>
	<div id="easy-update-urls-dashboard-right">
	</div>
</div>