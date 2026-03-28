<?php
if (!defined('ABSPATH')) exit;

class RuChatbroPublic {

    public static function init() {
        add_action('wp_footer', array(__CLASS__, 'maybe_render_global_chat'));
    }

    /**
     * Глобальный показ чата — вставляется в wp_footer на нужных страницах
     */
    public static function maybe_render_global_chat() {
        $settings = get_option('ru_chatbro_settings', array());
        $mode = $settings['display_mode'] ?? 'shortcode';

        if ($mode === 'shortcode') return;

        $chat_id = intval($settings['global_chat_id'] ?? 0);
        if (!$chat_id) return;

        // Проверяем режим показа
        if ($mode === 'all_site') {
            // Показываем везде, кроме исключённых
            $exclude = $settings['exclude_urls'] ?? array();
            $current_url = self::get_current_url();
            if (self::url_matches_list($current_url, $exclude)) return;

            // Проверка мобильных
            if (empty($settings['show_on_mobile']) && wp_is_mobile()) return;
        }

        if ($mode === 'url_list') {
            $allowed = $settings['display_urls'] ?? array();
            $current_url = self::get_current_url();
            if (!self::url_matches_list($current_url, $allowed)) return;
        }

        // Рендеримся
        echo do_shortcode('[ru_chatbro id="' . $chat_id . '"]');
    }

    private static function get_current_url() {
        $url = home_url(add_query_arg(null, null));
        return strtolower(untrailingslashit($url));
    }

    /**
     * Проверяет, совпадает ли текущий URL с одним из шаблонов в списке.
     * Поддерживает * как wildcard: /blog/* совпадёт с /blog/post-1
     */
    private static function url_matches_list($url, $list) {
        foreach ($list as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) continue;

            // Если шаблон не содержит домен — добавляем home_url
            if (strpos($pattern, 'http') !== 0) {
                $pattern = untrailingslashit(home_url()) . '/' . ltrim($pattern, '/');
            }
            $pattern = strtolower(untrailingslashit($pattern));

            // Wildcard поддержка
            if (strpos($pattern, '*') !== false) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
                if (preg_match($regex, $url)) return true;
            } else {
                if ($url === $pattern) return true;
            }
        }
        return false;
    }
}

// Инициализация
add_action('init', array('RuChatbroPublic', 'init'));
