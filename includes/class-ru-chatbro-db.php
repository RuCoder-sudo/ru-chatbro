<?php
if (!defined('ABSPATH')) exit;

class RuChatbroDB {

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_chats = $wpdb->prefix . 'ru_chatbro_chats';
        $sql_chats = "CREATE TABLE IF NOT EXISTS $table_chats (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(255) NOT NULL,
            description TEXT DEFAULT '',
            type       ENUM('public','private','anonymous') NOT NULL DEFAULT 'public',
            integrations TEXT DEFAULT '[]',
            is_active  TINYINT(1) NOT NULL DEFAULT 1,
            allow_anonymous TINYINT(1) NOT NULL DEFAULT 1,
            require_registration TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $table_messages = $wpdb->prefix . 'ru_chatbro_messages';
        $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chat_id    BIGINT UNSIGNED NOT NULL,
            user_id    BIGINT UNSIGNED DEFAULT NULL,
            username   VARCHAR(255) NOT NULL,
            avatar     TEXT DEFAULT NULL,
            message_text TEXT NOT NULL,
            source     ENUM('website','vk','telegram','ok','max') NOT NULL DEFAULT 'website',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY chat_id (chat_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $table_users = $wpdb->prefix . 'ru_chatbro_users';
        $sql_users = "CREATE TABLE IF NOT EXISTS $table_users (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            chat_id    BIGINT UNSIGNED NOT NULL,
            wp_user_id BIGINT UNSIGNED DEFAULT NULL,
            username   VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) DEFAULT '',
            avatar     TEXT DEFAULT NULL,
            last_seen  DATETIME DEFAULT NULL,
            is_banned  TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY chat_id (chat_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_chats);
        dbDelta($sql_messages);
        dbDelta($sql_users);

        if (!get_option('ru_chatbro_settings')) {
            update_option('ru_chatbro_settings', self::get_default_settings());
        }

        update_option('ru_chatbro_db_version', RU_CHATBRO_DB_VERSION);
    }

    public static function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ru_chatbro_messages");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ru_chatbro_users");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ru_chatbro_chats");
    }

    public static function get_default_settings() {
        return array(
            // === ИНТЕГРАЦИИ ===
            'vk_token'              => '',
            'vk_group_id'           => '',
            'telegram_bot_token'    => '',
            'telegram_chat_id'      => '',
            'ok_token'              => '',
            'ok_group_id'           => '',
            'max_bot_token'         => '',
            'max_chat_id'           => '',

            // === ОТОБРАЖЕНИЕ ===
            // off = только шорткод, all_site = на всём сайте, url_list = по URL
            'display_mode'          => 'shortcode',
            'global_chat_id'        => '',       // ID чата для глобального показа
            'display_urls'          => array(),  // Список URL для показа
            'exclude_urls'          => array(),  // Список URL для исключения
            'show_on_mobile'        => true,
            'hide_on_pages'         => array(),  // ID страниц где скрыть

            // === ОГРАНИЧЕНИЯ ===
            'slow_mode_delay'       => 3,
            'allow_file_upload'     => true,
            'allow_message_edit'    => true,
            'allow_message_delete'  => true,
            'bad_words'             => array(),
            'admin_only_send'       => false,

            // === ВНЕШНИЙ ВИД — РАЗМЕР И ПОЗИЦИЯ ===
            'chat_width'            => '350px',
            'chat_height'           => '300px',
            'position_h'            => 'right',  // left / right
            'position_v'            => 'bottom', // top / bottom
            'offset_h'              => '20px',
            'offset_v'              => '20px',

            // === ВНЕШНИЙ ВИД — ЦВЕТА ===
            'color_primary'         => '#0077ff',
            'color_bg'              => '#ffffff',
            'color_text'            => '#222222',
            'color_header_bg'       => '#0077ff',
            'color_header_text'     => '#ffffff',
            'color_bubble_bg'       => '#0077ff',
            'color_bubble_text'     => '#ffffff',
            'color_input_bg'        => '#f5f7fb',
            'color_msg_own_bg'      => '#e8f0ff',
            'color_msg_other_bg'    => '#f0f0f0',
            'color_link'            => '#0077ff',

            // === ВНЕШНИЙ ВИД — ШРИФТ ===
            'font_family'           => 'system',  // system / inter / roboto / opensans / montserrat
            'font_size'             => 13,
            'font_bold'             => false,
            'line_height'           => '1.5',

            // === ВНЕШНИЙ ВИД — СКРУГЛЕНИЯ И СТИЛЬ ===
            'border_radius_chat'    => '12px',
            'border_radius_msg'     => '8px',
            'border_radius_bubble'  => '50%',
            'bubble_size'           => '54px',
            'bubble_icon'           => 'chat', // chat / comment / message / custom
            'show_avatars'          => true,
            'avatar_radius'         => '6px',
            'show_date'             => true,
            'show_source_badge'     => true,
            'show_user_count'       => true,
            'chat_shadow'           => true,
            'header_height'         => '44px',

            // === АВТОРСКИЕ ПРАВА ===
            'hide_copyright'        => false,
        );
    }

    // CRUD для чатов
    public static function get_chats() {
        global $wpdb;
        $table = $wpdb->prefix . 'ru_chatbro_chats';
        $chats = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);
        foreach ($chats as &$chat) {
            $chat['integrations'] = json_decode($chat['integrations'] ?? '[]', true);
            $chat['message_count'] = self::get_message_count($chat['id']);
            $chat['user_count'] = self::get_user_count($chat['id']);
        }
        return $chats;
    }

    public static function get_chat($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ru_chatbro_chats';
        $chat = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if ($chat) {
            $chat['integrations'] = json_decode($chat['integrations'] ?? '[]', true);
            $chat['message_count'] = self::get_message_count($id);
            $chat['user_count'] = self::get_user_count($id);
        }
        return $chat;
    }

    public static function create_chat($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ru_chatbro_chats';
        $wpdb->insert($table, array(
            'name'                => sanitize_text_field($data['name']),
            'description'         => sanitize_textarea_field($data['description'] ?? ''),
            'type'                => in_array($data['type'], ['public','private','anonymous']) ? $data['type'] : 'public',
            'integrations'        => json_encode($data['integrations'] ?? []),
            'is_active'           => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'allow_anonymous'     => isset($data['allow_anonymous']) ? (int)$data['allow_anonymous'] : 1,
            'require_registration'=> isset($data['require_registration']) ? (int)$data['require_registration'] : 0,
        ));
        return $wpdb->insert_id;
    }

    public static function update_chat($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ru_chatbro_chats';
        $update = array('updated_at' => current_time('mysql'));
        if (isset($data['name']))                  $update['name'] = sanitize_text_field($data['name']);
        if (isset($data['description']))           $update['description'] = sanitize_textarea_field($data['description']);
        if (isset($data['type']))                  $update['type'] = in_array($data['type'], ['public','private','anonymous']) ? $data['type'] : 'public';
        if (isset($data['integrations']))          $update['integrations'] = json_encode($data['integrations']);
        if (isset($data['is_active']))             $update['is_active'] = (int)$data['is_active'];
        if (isset($data['allow_anonymous']))       $update['allow_anonymous'] = (int)$data['allow_anonymous'];
        if (isset($data['require_registration']))  $update['require_registration'] = (int)$data['require_registration'];
        $wpdb->update($table, $update, array('id' => $id));
    }

    public static function delete_chat($id) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'ru_chatbro_messages', array('chat_id' => $id));
        $wpdb->delete($wpdb->prefix . 'ru_chatbro_chats', array('id' => $id));
    }

    public static function get_messages($chat_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'ru_chatbro_messages';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE chat_id = %d AND is_deleted = 0 ORDER BY created_at ASC LIMIT %d OFFSET %d",
            $chat_id, $limit, $offset
        ), ARRAY_A);
    }

    public static function send_message($data) {
        global $wpdb;
        $settings = get_option('ru_chatbro_settings', array());
        $bad_words = $settings['bad_words'] ?? array();
        $text = sanitize_textarea_field($data['text']);
        foreach ($bad_words as $word) {
            if (!empty($word)) {
                $text = str_ireplace(trim($word), str_repeat('*', mb_strlen(trim($word))), $text);
            }
        }
        $wpdb->insert($wpdb->prefix . 'ru_chatbro_messages', array(
            'chat_id'      => intval($data['chat_id']),
            'user_id'      => isset($data['user_id']) ? intval($data['user_id']) : null,
            'username'     => sanitize_text_field($data['username']),
            'avatar'       => isset($data['avatar']) ? esc_url_raw($data['avatar']) : null,
            'message_text' => $text,
            'source'       => isset($data['source']) && in_array($data['source'], ['website','vk','telegram','ok','max']) ? $data['source'] : 'website',
        ));
        return $wpdb->insert_id;
    }

    public static function delete_message($id) {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'ru_chatbro_messages', array('is_deleted' => 1), array('id' => $id));
    }

    public static function get_message_count($chat_id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ru_chatbro_messages WHERE chat_id = %d AND is_deleted = 0", $chat_id
        ));
    }

    public static function get_user_count($chat_id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT username) FROM {$wpdb->prefix}ru_chatbro_messages WHERE chat_id = %d", $chat_id
        ));
    }
}
