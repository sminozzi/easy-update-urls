<?php

/*
Plugin Name: easy-update-urls
Description: Easy Search and Replace in WP database 
Version: 2.0.0
Text Domain: easy-update-urls
Domain Path: /language
Author: Bill Minozzi
Author URI: http://billminozzi.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 7.4
Requires CP: 1.0
*/

// Make sure the file is not directly accessible.
if (!defined('ABSPATH')) {
	die('We\'re sorry, but you can not directly access this file.');
}

$easy_update_urls_plugin_data    = get_file_data(__FILE__, array('Version' => 'Version'), false);
$easy_update_urls_plugin_version = $easy_update_urls_plugin_data['Version'];
define('EASY_UPDATE_URLS_VERSION', $easy_update_urls_plugin_version);
define('EASY_UPDATE_URLS_URL', plugin_dir_url(__FILE__));
define('EASY_UPDATE_URLS_PATH', plugin_dir_path(__FILE__));
define('EASY_UPDATE_URLS_IMAGES', plugin_dir_url(__FILE__) . 'assets/images');
$easy_update_urls_is_admin = easy_update_urls_check_wordpress_logged_in_cookie();

// Initialize the plugin hooks.
add_action('init', 'easy_update_urls_init', 1000);
add_action('admin_enqueue_scripts', 'easy_update_urls_enqueue', 1000);

/**
 * Original initialization function for admin pages.
 */
function easy_update_urls_init_ori()
{
	global $easy_update_urls_is_admin;
	if ($easy_update_urls_is_admin) {
		add_management_page(
			'Easy Search Replace',
			'Easy Search Replace',
			'manage_options',
			'easy_update_urls_admin_page', // Slug.
			'easy_update_urls_admin_page'
		);
	}
}

add_action('admin_menu', 'easy_update_urls_init', 20);

/**
 * Initializes the admin menu page under the Tools section.
 */
function easy_update_urls_init()
{
	global $easy_update_urls_is_admin;
	if ($easy_update_urls_is_admin) {
		add_management_page(
			'Easy Search Replace', // Page title.
			'Easy Search Replace', // Menu title.
			'manage_options',
			'easy_update_urls_admin_page', // Menu slug.
			'easy_update_urls_admin_page' // Callback function.
		);
	}
}

/**
 * Enqueues admin scripts and styles.
 */
function easy_update_urls_enqueue()
{
	// 1. Scripts nativos do Core (não precisam de versão manual).
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-core');
	wp_enqueue_script('jquery-ui-accordion');

	// 2. CSS principal do plugin usando a constante de versão.
	wp_enqueue_style(
		'easy-update-urls',
		EASY_UPDATE_URLS_URL . 'assets/css/styles.css',
		array(),
		EASY_UPDATE_URLS_VERSION
	);

	// 3. JS principal do plugin (com dependência do jQuery e carregamento no footer).
	wp_enqueue_script(
		'easy-update-urls-js',
		EASY_UPDATE_URLS_URL . 'assets/js/easy-update-urls.js',
		array('jquery'),
		EASY_UPDATE_URLS_VERSION,
		true
	);

	// 4. jQuery UI Local (mantendo a versão específica da biblioteca).
	wp_enqueue_style(
		'bill-jquery-ui',
		EASY_UPDATE_URLS_URL . 'assets/css/jquery-ui.css',
		array(),
		'1.12.1'
	);
}

/**
 * Renders the admin dashboard container.
 */
function easy_update_urls_admin_page()
{
	require_once EASY_UPDATE_URLS_PATH . '/dashboard/dashboard-container.php';
}

$easy_update_urls_base = plugin_basename(__FILE__);

/**
 * Checks if the user is logged in via the WordPress cookie.
 *
 * @return bool True if logged in cookie is found.
 */
function easy_update_urls_check_wordpress_logged_in_cookie()
{
	// Percorre todos os cookies definidos.
	foreach ($_COOKIE as $key => $value) {
		// Verifica se algum cookie começa com 'wordpress_logged_in_'.
		if (strpos($key, 'wordpress_logged_in_') === 0) {
			// Cookie encontrado.
			return true;
		}
	}
	// Cookie não encontrado.
	return false;
}

/**
 * Initializes plugin localization and fallbacks.
 */
function easy_update_urls_localization_init()
{
	// Definimos o diretório de idiomas relativo ao wp-content/plugins.
	$mofile_dir = dirname(plugin_basename(__FILE__)) . '/language/';

	// Filtro prefixado para evitar o erro de NamingConventions.
	$locale = apply_filters('easy_update_urls_plugin_locale', determine_locale(), 'easy-update-urls');

	$path                      = EASY_UPDATE_URLS_PATH . 'language/';
	$specific_translation_path = $path . "easy-update-urls-$locale.mo";
	$loaded                    = false;

	// 1. Tenta carregar o locale específico (ex: es_AR).
	if (file_exists($specific_translation_path)) {
		$loaded = load_textdomain('easy-update-urls', $specific_translation_path);
	}

	// 2. Lógica de Fallback (apenas se o específico falhar).
	if (!$loaded) {
		$language         = explode('_', $locale)[0];
		$fallback_locales = array(
			'de' => 'de_DE',
			'fr' => 'fr_FR',
			'it' => 'it_IT',
			'es' => 'es_ES',
			'pt' => 'pt_BR',
			'nl' => 'nl_NL',
		);

		if (array_key_exists($language, $fallback_locales)) {
			$fallback_path = $path . "easy-update-urls-{$fallback_locales[$language]}.mo";
			if (file_exists($fallback_path)) {
				load_textdomain('easy-update-urls', $fallback_path);
			}
		}
	}

	// 3. Por fim, carrega o textdomain padrão.
	load_plugin_textdomain('easy-update-urls', false, $mofile_dir);
}

if ($easy_update_urls_is_admin) {
	add_action('init', 'easy_update_urls_localization_init');
}

if ($easy_update_urls_is_admin) {

	/**
	 * Adds settings and more tools links to the plugin action links.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified links.
	 */
	function easy_update_urls_settings2_link($links)
	{
		// Define o nome da 'action' para o nonce.
		$action = 'easy-update-url';

		// 1. URL do Settings.
		$settings_link = '<a href="' . esc_url(admin_url('tools.php?page=easy_update_urls_admin_page')) . '">' . __('Settings', 'easy-update-urls') . '</a>';


		array_unshift($links, $settings_link);
		return $links;
	}
	$easy_update_urls_current_plugin = plugin_basename(__FILE__);
	add_filter("plugin_action_links_$easy_update_urls_current_plugin", 'easy_update_urls_settings2_link');
}
