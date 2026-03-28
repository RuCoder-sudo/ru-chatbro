<?php
if (!defined('ABSPATH')) exit;

/**
 * Класс для синхронизации с мессенджерами:
 * ВКонтакте, Telegram, Одноклассники, Макс
 */
class RuChatbroIntegrations {

    /**
     * Отправляем сообщение из сайта в подключённые мессенджеры
     */
    public static function sync_outgoing($chat, $message, $settings) {
        $integrations = $chat['integrations'] ?? [];

        foreach ($integrations as $integration) {
            switch ($integration) {
                case 'telegram':
                    if (!empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id'])) {
                        self::send_to_telegram(
                            $settings['telegram_bot_token'],
                            $settings['telegram_chat_id'],
                            $message['username'],
                            $message['message_text']
                        );
                    }
                    break;

                case 'vk':
                    if (!empty($settings['vk_token']) && !empty($settings['vk_group_id'])) {
                        self::send_to_vk(
                            $settings['vk_token'],
                            $settings['vk_group_id'],
                            $message['username'],
                            $message['message_text']
                        );
                    }
                    break;

                case 'max':
                    if (!empty($settings['max_bot_token']) && !empty($settings['max_chat_id'])) {
                        self::send_to_max(
                            $settings['max_bot_token'],
                            $settings['max_chat_id'],
                            $message['username'],
                            $message['message_text']
                        );
                    }
                    break;

                case 'ok':
                    // Одноклассники не поддерживает исходящие сообщения через API в чаты
                    break;
            }
        }
    }

    // ==================== TELEGRAM ====================

    public static function send_to_telegram($bot_token, $chat_id, $username, $text) {
        $formatted = "*{$username}:* " . self::escape_markdown($text);
        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        $response = wp_remote_post($url, [
            'body' => [
                'chat_id'    => $chat_id,
                'text'       => $formatted,
                'parse_mode' => 'Markdown',
            ],
            'timeout' => 10,
        ]);
        if (is_wp_error($response)) {
            error_log('[Ru-chatbro Telegram] Ошибка: ' . $response->get_error_message());
        }
    }

    /**
     * Получить обновления от Telegram (webhook или polling)
     * Вызывается через WP-Cron
     */
    public static function receive_from_telegram($bot_token, $chat_id_filter, $db_chat_id) {
        $offset_key = 'ru_chatbro_tg_offset_' . $db_chat_id;
        $offset = get_option($offset_key, 0);

        $url = "https://api.telegram.org/bot{$bot_token}/getUpdates?offset={$offset}&limit=20&timeout=0";
        $response = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($response)) return;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['result']) || empty($body['result'])) return;

        foreach ($body['result'] as $update) {
            $update_id = $update['update_id'];
            $msg = $update['message'] ?? $update['channel_post'] ?? null;
            if (!$msg) continue;

            $tg_chat_id = (string)($msg['chat']['id'] ?? '');
            if ($tg_chat_id !== (string)$chat_id_filter) continue;

            $text = $msg['text'] ?? '';
            if (empty($text) || strpos($text, '/') === 0) continue;

            $from = $msg['from'] ?? $msg['chat'] ?? [];
            $username = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
            if (empty($username)) $username = $from['username'] ?? 'Telegram User';

            RuChatbroDB::send_message([
                'chat_id'  => $db_chat_id,
                'username' => $username . ' (Telegram)',
                'text'     => $text,
                'source'   => 'telegram',
            ]);

            update_option($offset_key, $update_id + 1);
        }
    }

    // ==================== ВКОНТАКТЕ ====================

    public static function send_to_vk($token, $group_id, $username, $text) {
        $message = "{$username}: {$text}";
        $url = 'https://api.vk.com/method/messages.send';
        $response = wp_remote_post($url, [
            'body' => [
                'access_token' => $token,
                'peer_id'      => '-' . ltrim($group_id, '-'),
                'message'      => $message,
                'random_id'    => rand(1, 9999999),
                'v'            => '5.131',
            ],
            'timeout' => 10,
        ]);
        if (is_wp_error($response)) {
            error_log('[Ru-chatbro VK] Ошибка: ' . $response->get_error_message());
        }
    }

    /**
     * Получить сообщения из беседы ВКонтакте (через Callback API или polling)
     */
    public static function receive_from_vk($token, $group_id, $db_chat_id) {
        $offset_key = 'ru_chatbro_vk_ts_' . $db_chat_id;
        $server_key = 'ru_chatbro_vk_server_' . $db_chat_id;
        $key_key    = 'ru_chatbro_vk_key_' . $db_chat_id;

        $server = get_option($server_key, '');
        $key    = get_option($key_key, '');
        $ts     = get_option($offset_key, '');

        // Получаем Long Poll сервер если нужно
        if (empty($server) || empty($key) || empty($ts)) {
            $url = 'https://api.vk.com/method/groups.getLongPollServer';
            $resp = wp_remote_post($url, [
                'body' => ['access_token' => $token, 'group_id' => $group_id, 'v' => '5.131'],
                'timeout' => 10,
            ]);
            if (is_wp_error($resp)) return;
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            $lp = $data['response'] ?? null;
            if (!$lp) return;
            $server = $lp['server'];
            $key    = $lp['key'];
            $ts     = $lp['ts'];
            update_option($server_key, $server);
            update_option($key_key, $key);
            update_option($offset_key, $ts);
        }

        $url = "{$server}?act=a_check&key={$key}&ts={$ts}&wait=1";
        $resp = wp_remote_get($url, ['timeout' => 5]);
        if (is_wp_error($resp)) return;
        $data = json_decode(wp_remote_retrieve_body($resp), true);

        if (isset($data['ts'])) update_option($offset_key, $data['ts']);

        foreach ($data['updates'] ?? [] as $event) {
            if (($event['type'] ?? '') !== 'message_new') continue;
            $obj = $event['object']['message'] ?? [];
            $text = $obj['text'] ?? '';
            if (empty($text)) continue;

            $from_id = $obj['from_id'] ?? 0;
            $user_info = self::get_vk_user_name($token, $from_id);

            RuChatbroDB::send_message([
                'chat_id'  => $db_chat_id,
                'username' => $user_info . ' (ВКонтакте)',
                'text'     => $text,
                'source'   => 'vk',
            ]);
        }
    }

    private static function get_vk_user_name($token, $user_id) {
        if ($user_id <= 0) return 'VK User';
        $resp = wp_remote_get("https://api.vk.com/method/users.get?user_ids={$user_id}&access_token={$token}&v=5.131", ['timeout' => 5]);
        if (is_wp_error($resp)) return 'VK User';
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $user = $data['response'][0] ?? [];
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'VK User';
    }

    // ==================== ОДНОКЛАССНИКИ ====================
    // OK.ru поддерживает боты в группах, но API ограничен. Реализуем приём через Webhook.

    public static function receive_from_ok($token, $group_id, $db_chat_id) {
        // OK.ru не предоставляет polling API для чатов групп.
        // Получение происходит через Callback/Webhook в OK.ru API.
        // Здесь — заглушка, реализация через webhook endpoint плагина.
    }

    // ==================== МАКС (MAX) ====================

    public static function send_to_max($bot_token, $chat_id, $username, $text) {
        // Max (бывший ICQ/Mail.ru Агент) — Bot API совместим с Bot API Telegram-like
        $formatted = "{$username}: {$text}";
        $url = "https://botapi.max.ru/messages/sendText";
        $response = wp_remote_post($url, [
            'headers' => ['Authorization' => "Bearer {$bot_token}", 'Content-Type' => 'application/json'],
            'body'    => json_encode(['chat_id' => $chat_id, 'text' => $formatted]),
            'timeout' => 10,
        ]);
        if (is_wp_error($response)) {
            error_log('[Ru-chatbro Max] Ошибка: ' . $response->get_error_message());
        }
    }

    public static function receive_from_max($bot_token, $chat_id_filter, $db_chat_id) {
        $offset_key = 'ru_chatbro_max_offset_' . $db_chat_id;
        $offset = get_option($offset_key, 0);

        $url = "https://botapi.max.ru/updates?limit=20&timeout=0&marker={$offset}";
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => "Bearer {$bot_token}"],
            'timeout' => 10,
        ]);
        if (is_wp_error($response)) return;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $updates = $body['updates'] ?? [];

        foreach ($updates as $update) {
            $marker = $update['update_id'] ?? 0;
            $type   = $update['update_type'] ?? '';
            if ($type !== 'message_created') continue;

            $msg  = $update['message'] ?? [];
            $chat = $msg['recipient'] ?? [];
            if ((string)($chat['chat_id'] ?? '') !== (string)$chat_id_filter) continue;

            $text = $msg['body']['text'] ?? '';
            if (empty($text)) continue;

            $sender   = $msg['sender'] ?? [];
            $username = $sender['name'] ?? 'Max User';

            RuChatbroDB::send_message([
                'chat_id'  => $db_chat_id,
                'username' => $username . ' (Макс)',
                'text'     => $text,
                'source'   => 'max',
            ]);

            update_option($offset_key, $marker + 1);
        }
    }

    // ==================== CRON ====================

    public static function register_cron() {
        if (!wp_next_scheduled('ru_chatbro_sync_cron')) {
            wp_schedule_event(time(), 'ru_chatbro_30s', 'ru_chatbro_sync_cron');
        }
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_interval'));
        add_action('ru_chatbro_sync_cron', array(__CLASS__, 'run_sync'));
    }

    public static function add_cron_interval($schedules) {
        $schedules['ru_chatbro_30s'] = [
            'interval' => 30,
            'display'  => 'Каждые 30 секунд (Ru-chatbro)',
        ];
        return $schedules;
    }

    public static function run_sync() {
        $settings = get_option('ru_chatbro_settings', []);
        $chats = RuChatbroDB::get_chats();

        foreach ($chats as $chat) {
            if (!$chat['is_active']) continue;
            $integrations = $chat['integrations'] ?? [];

            foreach ($integrations as $integration) {
                switch ($integration) {
                    case 'telegram':
                        if (!empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id'])) {
                            self::receive_from_telegram($settings['telegram_bot_token'], $settings['telegram_chat_id'], $chat['id']);
                        }
                        break;
                    case 'vk':
                        if (!empty($settings['vk_token']) && !empty($settings['vk_group_id'])) {
                            self::receive_from_vk($settings['vk_token'], $settings['vk_group_id'], $chat['id']);
                        }
                        break;
                    case 'max':
                        if (!empty($settings['max_bot_token']) && !empty($settings['max_chat_id'])) {
                            self::receive_from_max($settings['max_bot_token'], $settings['max_chat_id'], $chat['id']);
                        }
                        break;
                }
            }
        }
    }

    private static function escape_markdown($text) {
        return str_replace(['_', '*', '[', ']', '`'], ['\\_', '\\*', '\\[', '\\]', '\\`'], $text);
    }
}

// Регистрируем cron при загрузке плагина
add_action('init', array('RuChatbroIntegrations', 'register_cron'));
