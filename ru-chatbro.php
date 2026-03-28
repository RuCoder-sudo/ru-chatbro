<?php
/**
 * Plugin Name: Ru-chatbro
 * Plugin URI: https://github.com/RuCoder-sudo/
 * Description: Русский чат-плагин с поддержкой ВКонтакте, Telegram, Одноклассников и Макс.
 * Version: 3.2
 * Author: Сергей Солошенко (RuCoder)
 * Author URI: https://рукодер.рф/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ru-chatbro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Network: false
 *
 * Разработчик: Сергей Солошенко | РуКодер
 * Специализация: Веб-разработка с 2018 года | WordPress / Full Stack
 * Принцип работы: "Сайт как для себя"
 * Контакты:
 * - Телефон/WhatsApp: +7 (985) 985-53-97
 * - Email: support@рукодер.рф
 * - Telegram: @RussCoder
 * - Портфолио: https://рукодер.рф
 * - GitHub: https://github.com/RuCoder-sudo
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RU_CHATBRO_VERSION', '1.0.0');
define('RU_CHATBRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RU_CHATBRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RU_CHATBRO_DB_VERSION', '1.0');

// Подключение основных файлов
require_once RU_CHATBRO_PLUGIN_DIR . 'includes/class-ru-chatbro-db.php';
require_once RU_CHATBRO_PLUGIN_DIR . 'includes/class-ru-chatbro-ajax.php';
require_once RU_CHATBRO_PLUGIN_DIR . 'includes/class-ru-chatbro-shortcode.php';
require_once RU_CHATBRO_PLUGIN_DIR . 'includes/class-ru-chatbro-integrations.php';
require_once RU_CHATBRO_PLUGIN_DIR . 'admin/class-ru-chatbro-admin.php';
require_once RU_CHATBRO_PLUGIN_DIR . 'public/class-ru-chatbro-public.php';

class RuChatbro {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('ru-chatbro', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function init() {
        RuChatbroShortcode::init();
        RuChatbroAjax::init();
        if (is_admin()) {
            RuChatbroAdmin::init();
        }
    }

    public function enqueue_public_assets() {
        wp_enqueue_style('ru-chatbro-public', RU_CHATBRO_PLUGIN_URL . 'public/css/ru-chatbro-public.css', array(), RU_CHATBRO_VERSION);
        wp_enqueue_script('ru-chatbro-public', RU_CHATBRO_PLUGIN_URL . 'public/js/ru-chatbro-public.js', array('jquery'), RU_CHATBRO_VERSION, true);
        wp_localize_script('ru-chatbro-public', 'ruChatbroConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ru_chatbro_nonce'),
            'siteUrl' => get_site_url(),
        ));
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ru-chatbro') === false) return;
        wp_enqueue_style('ru-chatbro-admin', RU_CHATBRO_PLUGIN_URL . 'admin/css/ru-chatbro-admin.css', array(), RU_CHATBRO_VERSION);
        wp_enqueue_script('ru-chatbro-admin', RU_CHATBRO_PLUGIN_URL . 'admin/js/ru-chatbro-admin.js', array('jquery'), RU_CHATBRO_VERSION, true);
        wp_localize_script('ru-chatbro-admin', 'ruChatbroAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ru_chatbro_admin_nonce'),
        ));
    }
}

// Активация плагина
register_activation_hook(__FILE__, array('RuChatbroDB', 'create_tables'));

// Деактивация плагина
register_deactivation_hook(__FILE__, function() {
    // Не удаляем данные при деактивации
});

// Удаление плагина
register_uninstall_hook(__FILE__, 'ru_chatbro_uninstall');
function ru_chatbro_uninstall() {
    if (!current_user_can('activate_plugins')) return;
    RuChatbroDB::drop_tables();
    delete_option('ru_chatbro_settings');
    delete_option('ru_chatbro_db_version');
}

// Запуск плагина
RuChatbro::get_instance();
