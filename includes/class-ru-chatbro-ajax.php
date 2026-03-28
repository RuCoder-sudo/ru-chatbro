<?php
if (!defined('ABSPATH')) exit;

class RuChatbroAjax {

    public static function init() {
        // Публичные AJAX (для посетителей и авторизованных)
        add_action('wp_ajax_ru_chatbro_get_messages',    array(__CLASS__, 'get_messages'));
        add_action('wp_ajax_nopriv_ru_chatbro_get_messages', array(__CLASS__, 'get_messages'));

        add_action('wp_ajax_ru_chatbro_send_message',    array(__CLASS__, 'send_message'));
        add_action('wp_ajax_nopriv_ru_chatbro_send_message', array(__CLASS__, 'send_message'));

        add_action('wp_ajax_ru_chatbro_get_chat_info',   array(__CLASS__, 'get_chat_info'));
        add_action('wp_ajax_nopriv_ru_chatbro_get_chat_info', array(__CLASS__, 'get_chat_info'));

        // Только для авторизованных
        add_action('wp_ajax_ru_chatbro_delete_message',  array(__CLASS__, 'delete_message'));

        // Административные AJAX
        add_action('wp_ajax_ru_chatbro_admin_save_settings', array(__CLASS__, 'admin_save_settings'));
        add_action('wp_ajax_ru_chatbro_admin_create_chat',   array(__CLASS__, 'admin_create_chat'));
        add_action('wp_ajax_ru_chatbro_admin_update_chat',   array(__CLASS__, 'admin_update_chat'));
        add_action('wp_ajax_ru_chatbro_admin_delete_chat',   array(__CLASS__, 'admin_delete_chat'));
    }

    private static function verify_nonce($action = 'ru_chatbro_nonce') {
        if (!check_ajax_referer($action, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Ошибка безопасности'), 403);
        }
    }

    private static function verify_admin_nonce() {
        self::verify_nonce('ru_chatbro_admin_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Доступ запрещён'), 403);
        }
    }

    public static function get_messages() {
        self::verify_nonce();
        $chat_id = intval($_POST['chat_id'] ?? 0);
        $limit   = min(intval($_POST['limit'] ?? 50), 200);
        $offset  = intval($_POST['offset'] ?? 0);

        if (!$chat_id) wp_send_json_error(array('message' => 'Неверный ID чата'));

        $messages = RuChatbroDB::get_messages($chat_id, $limit, $offset);
        wp_send_json_success($messages);
    }

    public static function send_message() {
        self::verify_nonce();
        $chat_id = intval($_POST['chat_id'] ?? 0);
        $text    = sanitize_textarea_field($_POST['text'] ?? '');

        if (!$chat_id || empty($text)) {
            wp_send_json_error(array('message' => 'Заполните все поля'));
        }

        $chat = RuChatbroDB::get_chat($chat_id);
        if (!$chat) wp_send_json_error(array('message' => 'Чат не найден'));
        if (!$chat['is_active']) wp_send_json_error(array('message' => 'Чат неактивен'));

        $settings = get_option('ru_chatbro_settings', array());

        // Проверка режима медленной отправки через сессию
        if (!session_id()) session_start();
        $last_send = $_SESSION['ru_chatbro_last_send_' . $chat_id] ?? 0;
        $delay = intval($settings['slow_mode_delay'] ?? 3);
        if ($delay > 0 && (time() - $last_send) < $delay) {
            wp_send_json_error(array('message' => "Подождите {$delay} секунды перед отправкой"));
        }
        $_SESSION['ru_chatbro_last_send_' . $chat_id] = time();

        // Определяем пользователя
        $current_user = wp_get_current_user();
        $username = '';
        $user_id  = null;
        $avatar   = null;

        if ($current_user->ID) {
            $username = $current_user->display_name ?: $current_user->user_login;
            $user_id  = $current_user->ID;
            $avatar   = get_avatar_url($current_user->ID, array('size' => 40));
        } elseif ($chat['allow_anonymous']) {
            $username = sanitize_text_field($_POST['username'] ?? 'Гость');
            if (empty($username)) $username = 'Гость';
        } else {
            wp_send_json_error(array('message' => 'Необходима авторизация'));
        }

        $id = RuChatbroDB::send_message(array(
            'chat_id'  => $chat_id,
            'user_id'  => $user_id,
            'username' => $username,
            'avatar'   => $avatar,
            'text'     => $text,
            'source'   => 'website',
        ));

        $message = array(
            'id'           => $id,
            'chat_id'      => $chat_id,
            'user_id'      => $user_id,
            'username'     => $username,
            'avatar'       => $avatar,
            'message_text' => $text,
            'source'       => 'website',
            'created_at'   => current_time('mysql'),
            'is_deleted'   => 0,
        );

        // Синхронизация с мессенджерами
        RuChatbroIntegrations::sync_outgoing($chat, $message, $settings);

        wp_send_json_success($message);
    }

    public static function get_chat_info() {
        self::verify_nonce();
        $chat_id = intval($_POST['chat_id'] ?? 0);
        if (!$chat_id) wp_send_json_error(array('message' => 'Неверный ID чата'));
        $chat = RuChatbroDB::get_chat($chat_id);
        if (!$chat) wp_send_json_error(array('message' => 'Чат не найден'));
        wp_send_json_success($chat);
    }

    public static function delete_message() {
        self::verify_nonce();
        if (!current_user_can('manage_options') && !current_user_can('moderate_comments')) {
            wp_send_json_error(array('message' => 'Доступ запрещён'), 403);
        }
        $message_id = intval($_POST['message_id'] ?? 0);
        RuChatbroDB::delete_message($message_id);
        wp_send_json_success();
    }

    // === АДМИНИСТРАТИВНЫЕ ===

    public static function admin_save_settings() {
        self::verify_admin_nonce();
        $settings = get_option('ru_chatbro_settings', RuChatbroDB::get_default_settings());

        // Обновляем только переданные поля
        $allowed_text_fields = ['vk_token','vk_group_id','telegram_bot_token','telegram_chat_id',
            'ok_token','ok_group_id','max_bot_token','max_chat_id',
            'chat_width','chat_height','color_primary','color_bg','color_text',
            'color_header_bg','color_header_text','avatar_radius','collapsed_radius',
            'border_radius_tl','border_radius_tr','border_radius_bl','border_radius_br',
            'position_side','offset_right','offset_bottom','position_type'];

        foreach ($allowed_text_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        $allowed_int_fields = ['slow_mode_delay','font_size'];
        foreach ($allowed_int_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = intval($_POST[$field]);
            }
        }

        $allowed_bool_fields = ['allow_file_upload','allow_message_edit','allow_message_delete',
            'show_avatars','font_bold','show_date','show_border','hide_copyright','admin_only_send'];
        foreach ($allowed_bool_fields as $field) {
            $settings[$field] = !empty($_POST[$field]);
        }

        if (isset($_POST['bad_words'])) {
            $bad_words = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['bad_words']))));
            $settings['bad_words'] = array_values($bad_words);
        }

        update_option('ru_chatbro_settings', $settings);
        wp_send_json_success(array('message' => 'Настройки сохранены'));
    }

    public static function admin_create_chat() {
        self::verify_admin_nonce();
        $data = array(
            'name'                 => sanitize_text_field($_POST['name'] ?? ''),
            'description'          => sanitize_textarea_field($_POST['description'] ?? ''),
            'type'                 => sanitize_text_field($_POST['type'] ?? 'public'),
            'integrations'         => array_map('sanitize_text_field', (array)($_POST['integrations'] ?? [])),
            'is_active'            => !empty($_POST['is_active']) ? 1 : 0,
            'allow_anonymous'      => !empty($_POST['allow_anonymous']) ? 1 : 0,
            'require_registration' => !empty($_POST['require_registration']) ? 1 : 0,
        );
        if (empty($data['name'])) wp_send_json_error(array('message' => 'Укажите название чата'));
        $id = RuChatbroDB::create_chat($data);
        wp_send_json_success(array('id' => $id, 'message' => 'Чат создан'));
    }

    public static function admin_update_chat() {
        self::verify_admin_nonce();
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(array('message' => 'Неверный ID'));
        $data = array(
            'name'                 => sanitize_text_field($_POST['name'] ?? ''),
            'description'          => sanitize_textarea_field($_POST['description'] ?? ''),
            'type'                 => sanitize_text_field($_POST['type'] ?? 'public'),
            'integrations'         => array_map('sanitize_text_field', (array)($_POST['integrations'] ?? [])),
            'is_active'            => !empty($_POST['is_active']) ? 1 : 0,
            'allow_anonymous'      => !empty($_POST['allow_anonymous']) ? 1 : 0,
            'require_registration' => !empty($_POST['require_registration']) ? 1 : 0,
        );
        RuChatbroDB::update_chat($id, $data);
        wp_send_json_success(array('message' => 'Чат обновлён'));
    }

    public static function admin_delete_chat() {
        self::verify_admin_nonce();
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(array('message' => 'Неверный ID'));
        RuChatbroDB::delete_chat($id);
        wp_send_json_success(array('message' => 'Чат удалён'));
    }
}
