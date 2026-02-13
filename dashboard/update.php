<?php
/**
 * Plugin Name: Easy Update URLs
 * Description: A professional tool to search and replace URLs in your database with Cloudflare integration.
 * Version: 1.0.0
 * Author: Sminozzi
 * Text Domain: easy-update-urls
 * Domain Path: /language/
 *
 * @package easy-update-urls
 */

namespace Sminozzi\EasyUpdateUrls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants.
 */
if ( ! defined( 'EASY_UPDATE_URLS_VERSION' ) ) {
	define( 'EASY_UPDATE_URLS_VERSION', '1.0.0' );
}

/**
 * 1. LOCAL.
 */
add_action( 'init', __NAMESPACE__ . '\\localization_init' );

/**
 * Initializes the plugin localization.
 *
 * @return void
 */
function localization_init() {
	$domain        = 'easy-update-urls';
	$mofile_dir    = dirname( plugin_basename( __FILE__ ) ) . '/language/';
	$locale        = apply_filters( 'easy_update_urls_plugin_locale', determine_locale(), $domain );
	$path          = EASY_UPDATE_URLS_PATH . 'language/';
	$specific_path = $path . "{$domain}-{$locale}.mo";
	$loaded        = false;
	if ( file_exists( $specific_path ) ) {
		$loaded = load_textdomain( $domain, $specific_path );
	}
	if ( ! $loaded ) {
		$language  = explode( '_', $locale )[0];
		$fallbacks = array(
			'de' => 'de_DE',
			'fr' => 'fr_FR',
			'it' => 'it_IT',
			'es' => 'es_ES',
			'pt' => 'pt_BR',
			'nl' => 'nl_NL',
		);
		if ( array_key_exists( $language, $fallbacks ) ) {
			$fallback_path = $path . "{$domain}-{$fallbacks[ $language ]}.mo";
			if ( file_exists( $fallback_path ) ) {
				load_textdomain( $domain, $fallback_path );
			}
		}
	}
	load_plugin_textdomain( $domain, false, $mofile_dir );
}

/**
 * 2. ASSETS (Cache Busting).
 */
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );

/**
 * Enqueues admin assets.
 *
 * @return void
 */
function enqueue_assets() {
	wp_enqueue_script( 'jquery' );
	wp_enqueue_style( 'easy-update-urls', EASY_UPDATE_URLS_URL . 'assets/css/styles.css', array(), EASY_UPDATE_URLS_VERSION );
	wp_enqueue_script( 'easy-update-urls-js', EASY_UPDATE_URLS_URL . 'assets/js/easy-update-urls.js', array( 'jquery' ), EASY_UPDATE_URLS_VERSION, true );
	wp_enqueue_style( 'bill-jquery-ui', EASY_UPDATE_URLS_URL . 'assets/css/jquery-ui.css', array(), '1.12.1' );
}

/**
 * 3. DATABASE ENGINE (Bulk Search & Replace).
 *
 * @param array  $options Array of table options to update.
 * @param string $oldurl  The old URL to search for.
 * @param string $newurl  The new URL to replace with.
 * @return array Results summary.
 */
function execute_search_replace( $options, $oldurl, $newurl ) {
	global $wpdb;
	$results_summary = array();
	foreach ( $options as $option ) {
		$affected_rows = 0;
		$label         = '';
		switch ( $option ) {
			case 'content':
				$label = __( 'Content Items (Posts, Pages, Custom Post Types, Revisions)', 'easy-update-urls' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$affected_rows = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_content = replace(post_content, %s, %s)", $oldurl, $newurl ) );
				break;
			case 'excerpts':
				$label = __( 'Excerpts', 'easy-update-urls' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$affected_rows = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_excerpt = replace(post_excerpt, %s, %s)", $oldurl, $newurl ) );
				break;
			case 'custom':
				$label = __( 'Custom Fields', 'easy-update-urls' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$items = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM $wpdb->postmeta WHERE meta_value LIKE %s", '%' . $wpdb->esc_like( $oldurl ) . '%' ) );
				if ( $items ) {
					foreach ( $items as $item ) {
						$edited = unserialize_replace_logic( $oldurl, $newurl, $item->meta_value );
						if ( $edited !== $item->meta_value ) {
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery
							$updated = $wpdb->query( $wpdb->prepare( "UPDATE `$wpdb->postmeta` SET meta_value = %s WHERE meta_id = %d", $edited, $item->meta_id ) );
							if ( $updated ) {
								++$affected_rows;
							}
						}
					}
				}
				break;
			case 'links':
				$label = __( 'Links', 'easy-update-urls' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$affected_rows = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->links SET link_url = replace(link_url, %s, %s)", $oldurl, $newurl ) );
				break;
			case 'attachments':
				$label = __( 'Attachments', 'easy-update-urls' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$affected_rows = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET guid = replace(guid, %s, %s) WHERE post_type = 'attachment'", $oldurl, $newurl ) );
				break;
			case 'guids':
				$label = __( 'GUIDs', 'easy-update-urls' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$affected_rows = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET guid = replace(guid, %s, %s)", $oldurl, $newurl ) );
				break;
		}
		if ( $label ) {
			$results_summary[ $option ] = array( $affected_rows, $label );
		}
	}
	wp_cache_flush();
	return $results_summary;
}

/**
 * 4. SERIALIZED DATA HANDLER.
 *
 * @param string $from       Search string.
 * @param string $to         Replace string.
 * @param mixed  $data       Data to process.
 * @param bool   $serialized Whether the data is serialized.
 * @return mixed Processed data.
 */
function unserialize_replace_logic( $from, $to, $data, $serialized = false ) {
	if ( is_serialized( $data ) ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		$unserialized = unserialize( $data );
		$data         = unserialize_replace_logic( $from, $to, $unserialized, true );
	} elseif ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			$data[ $key ] = unserialize_replace_logic( $from, $to, $value, false );
		}
	} elseif ( is_string( $data ) ) {
		$data = str_replace( $from, $to, $data );
	}
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	return $serialized ? serialize( $data ) : $data;
}

/**
 * 5. UI & (NONCE & UNSLASH).
 *
 * @return void
 */
function render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<h2 class="title">' . esc_html__( 'Update URLs', 'easy-update-urls' ) . '</h2>' . "\n";
	$easy_results_html = '';
	$easy_is_empty     = true;
	$easy_empty_msg    = '<strong>' . esc_html__( '0 URLs updated. This happens if a URL is incorrect OR if it is not found in the content. Check your URLs and try again.', 'easy-update-urls' ) . '</strong><br/>';

	if ( isset( $_POST['process'] ) && 'run_update_url' === $_POST['process'] ) {
		if ( ! check_admin_referer( 'easy_update_urls_submit', 'easy_update_urls_nonce' ) ) {
			echo '<div class="error"><p>' . esc_html__( 'Security check failed.', 'easy-update-urls' ) . '</p></div>';
			return;
		}
		$old  = isset( $_POST['easy_update_urls_oldurl'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['easy_update_urls_oldurl'] ) ) ) : '';
		$new  = isset( $_POST['easy_update_urls_newurl'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['easy_update_urls_newurl'] ) ) ) : '';
		$opts = ( isset( $_POST['easy_update_urls_update_links'] ) && is_array( $_POST['easy_update_urls_update_links'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['easy_update_urls_update_links'] ) ) : array();

		if ( $old && $new && 'https://www.oldurl.com' !== $old ) {
			$results = execute_search_replace( $opts, $old, $new );
			foreach ( $results as $res ) {
				if ( $res[0] > 0 ) {
					$easy_is_empty = false;
				}
				$easy_results_html .= '<br/><strong>' . esc_html( $res[0] ) . '</strong> ' . esc_html( $res[1] );
			}
			if ( $easy_is_empty ) {
				echo '<div id="message" class="updated fade"><p>' . wp_kses(
					$easy_empty_msg,
					array(
						'strong' => array(),
						'br'     => array(),
					)
				) . '</p></div>';
			} else {
				echo '<div id="message" class="updated fade"><table><tr><td>';
				echo '<p><strong>' . esc_html__( 'Success! Your URLs have been updated.', 'easy-update-urls' ) . '</strong></p>';
				echo '<p><u>' . esc_html__( 'Results', 'easy-update-urls' ) . '</u>' . wp_kses(
					$easy_results_html,
					array(
						'strong' => array(),
						'br'     => array(),
					)
				) . '</p>';
				echo '</td></tr></table></div>';
			}
		} else {
			echo '<div id="message" class="error fade"><p><strong>' . esc_html__( 'ERROR', 'easy-update-urls' ) . ' - ' . esc_html__( 'Your URLs have not been updated.', 'easy-update-urls' ) . '</strong></p><p>' . esc_html__( 'Please enter values for both the old url and the new url.', 'easy-update-urls' ) . '</p></div>';
		}
	}
	?>
	<form id="easy-update-urls-form-run" method="post" action="">
		<input type="hidden" name="process" value="run_update_url" />
		<big>
			<div id="easy-update-urls-help-run">
				<br><?php esc_html_e( 'Enter your URLs in the fields below', 'easy-update-urls' ); ?>
			</div>
		</big>
		<table class="form-table">
			<tr valign="middle">
				<th scope="row" width="140" style="width:140px">
					<strong><?php esc_html_e( 'Old URL', 'easy-update-urls' ); ?></strong><br />
					<span class="description"><?php esc_html_e( 'Old Site Address', 'easy-update-urls' ); ?></span>
				</th>
				<td><input name="easy_update_urls_oldurl" type="text" id="easy_update_urls_oldurl" value="https://www.oldurl.com" style="width:300px;font-size:20px;" onfocus="if(this.value=='https://www.oldurl.com') this.value='';" onblur="if(this.value=='') this.value='https://www.oldurl.com';" /></td>
			</tr>
			<tr valign="middle">
				<th scope="row" width="140" style="width:140px">
					<strong><?php esc_html_e( 'New URL', 'easy-update-urls' ); ?></strong><br />
					<span class="description"><?php esc_html_e( 'New Site Address', 'easy-update-urls' ); ?></span>
				</th>
				<td><input name="easy_update_urls_newurl" type="text" id="easy_update_urls_newurl" value="https://www.newurl.com" style="width:300px;font-size:20px;" onfocus="if(this.value=='https://www.newurl.com') this.value='';" onblur="if(this.value=='') this.value='https://www.newurl.com';" /></td>
			</tr>
		</table>
		<big>
			<div id="easy-update-urls-help-run"><br><?php esc_html_e( 'Choose which URLs should be updated', 'easy-update-urls' ); ?></div>
		</big>
		<table class="form-table">
			<tr>
				<td>
					<p style="line-height:20px;">
						<input name="easy_update_urls_update_links[]" type="checkbox" id="easy_update_urls_update_true" value="content" checked="checked" />
						<label for="easy_update_urls_update_true"><strong><?php esc_html_e( 'URLs in page content', 'easy-update-urls' ); ?></strong> (<?php esc_html_e( 'posts, pages, custom post types, revisions', 'easy-update-urls' ); ?>)</label><br />
						<input name="easy_update_urls_update_links[]" type="checkbox" id="easy_update_urls_update_true1" value="excerpts" />
						<label for="easy_update_urls_update_true1"><strong><?php esc_html_e( 'URLs in excerpts', 'easy-update-urls' ); ?></strong></label><br />
						<input name="easy_update_urls_update_links[]" type="checkbox" id="easy_update_urls_update_true2" value="links" />
						<label for="easy_update_urls_update_true2"><strong><?php esc_html_e( 'URLs in links', 'easy-update-urls' ); ?></strong></label><br />
						<input name="easy_update_urls_update_links[]" type="checkbox" id="easy_update_urls_update_true3" value="attachments" />
						<label for="easy_update_urls_update_true3"><strong><?php esc_html_e( 'URLs for attachments', 'easy-update-urls' ); ?></strong></label><br />
						<input name="easy_update_urls_update_links[]" type="checkbox" id="easy_update_urls_update_true4" value="custom" />
						<label for="easy_update_urls_update_true4"><strong><?php esc_html_e( 'URLs in custom fields and meta boxes', 'easy-update-urls' ); ?></strong></label><br />
						<input name="easy_update_urls_update_links[]" type="checkbox" id="easy_update_urls_update_true5" value="guids" />
						<label for="easy_update_urls_update_true5"><strong><?php esc_html_e( 'Update ALL GUIDs', 'easy-update-urls' ); ?></strong></label>
					</p>
				</td>
			</tr>
		</table>
		<div id="easy-update-urls-spinner">
			<img id="easy-update_urls_snake" src="<?php echo esc_url( EASY_UPDATE_URLS_IMAGES ); ?>/snake.gif" width="32">
		</div>
		<br><big><strong><span style="color:red;"><?php esc_html_e( 'Run a Backup of your database before begin.', 'easy-update-urls' ); ?></span></strong><br>
			<?php esc_html_e( "After click, please, wait a few seconds... and don't reload page neither click back or stop in your browser.", 'easy-update-urls' ); ?></big><br>
		<?php wp_nonce_field( 'easy_update_urls_submit', 'easy_update_urls_nonce' ); ?>
		<input id="easy-update-urls-run-update" class="button-primary" type="submit" value="<?php esc_attr_e( 'Update URLs Now', 'easy-update-urls' ); ?>">
	</form>
	<?php
}
render_admin_page();