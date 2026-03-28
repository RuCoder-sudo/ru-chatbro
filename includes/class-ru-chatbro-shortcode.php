<?php
if (!defined('ABSPATH')) exit;

class RuChatbroShortcode {

    public static function init() {
        add_shortcode('ru_chatbro', array(__CLASS__, 'render'));
        add_shortcode('ru-chatbro', array(__CLASS__, 'render'));
    }

    /**
     * Использование: [ru_chatbro id="1"] или [ru_chatbro id="1" width="400px" height="350px"]
     */
    public static function render($atts) {
        $atts = shortcode_atts(array(
            'id'     => 0,
            'width'  => '',
            'height' => '',
            'inline' => 'false',
        ), $atts, 'ru_chatbro');

        $chat_id = intval($atts['id']);
        if (!$chat_id) {
            return '<p style="color:red;">[Ru-chatbro] Укажите ID чата: [ru_chatbro id="1"]</p>';
        }

        $chat = RuChatbroDB::get_chat($chat_id);
        if (!$chat || !$chat['is_active']) {
            return '';
        }

        $settings = get_option('ru_chatbro_settings', RuChatbroDB::get_default_settings());

        $width  = $atts['width'] ?: ($settings['chat_width'] ?? '350px');
        $height = $atts['height'] ?: ($settings['chat_height'] ?? '300px');
        $inline = filter_var($atts['inline'], FILTER_VALIDATE_BOOLEAN);

        $current_user = wp_get_current_user();
        $is_logged_in = $current_user->ID > 0;
        $username = $is_logged_in ? ($current_user->display_name ?: $current_user->user_login) : '';
        $avatar   = $is_logged_in ? get_avatar_url($current_user->ID, ['size' => 40]) : '';

        // Цвета
        $color_primary     = sanitize_hex_color($settings['color_primary'] ?? '#0077ff');
        $color_bg          = sanitize_hex_color($settings['color_bg'] ?? '#ffffff');
        $color_text        = sanitize_hex_color($settings['color_text'] ?? '#222222');
        $color_header_bg   = sanitize_hex_color($settings['color_header_bg'] ?? '#0077ff');
        $color_header_text = sanitize_hex_color($settings['color_header_text'] ?? '#ffffff');

        $font_size       = intval($settings['font_size'] ?? 13);
        $show_avatars    = !empty($settings['show_avatars']);
        $allow_anonymous = !empty($chat['allow_anonymous']);
        $req_reg         = !empty($chat['require_registration']);

        $data_attrs = sprintf(
            'data-chat-id="%d" data-logged-in="%s" data-username="%s" data-avatar="%s" data-allow-anon="%s" data-req-reg="%s" data-show-avatars="%s" data-font-size="%d"',
            $chat_id,
            $is_logged_in ? 'true' : 'false',
            esc_attr($username),
            esc_attr($avatar),
            $allow_anonymous ? 'true' : 'false',
            $req_reg ? 'true' : 'false',
            $show_avatars ? 'true' : 'false',
            $font_size
        );

        $inline_style = sprintf(
            '--rcb-primary:%s;--rcb-bg:%s;--rcb-text:%s;--rcb-header-bg:%s;--rcb-header-text:%s;',
            $color_primary, $color_bg, $color_text, $color_header_bg, $color_header_text
        );

        $wrapper_class = $inline ? 'ru-chatbro-inline' : 'ru-chatbro-floating';

        ob_start();
        ?>
        <div class="ru-chatbro-wrapper <?php echo esc_attr($wrapper_class); ?>"
             id="ru-chatbro-<?php echo $chat_id; ?>"
             <?php echo $data_attrs; ?>
             style="--rcb-width:<?php echo esc_attr($width); ?>;--rcb-height:<?php echo esc_attr($height); ?>;<?php echo esc_attr($inline_style); ?>">

            <div class="ru-chatbro-container">
                <div class="ru-chatbro-header">
                    <span class="ru-chatbro-title"><?php echo esc_html($chat['name']); ?></span>
                    <div class="ru-chatbro-header-actions">
                        <span class="ru-chatbro-users-count" title="Участников">0</span>
                        <?php if (!$inline): ?>
                        <button class="ru-chatbro-toggle" title="Свернуть/развернуть">&#x2212;</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ru-chatbro-body">
                    <div class="ru-chatbro-messages" id="ru-chatbro-messages-<?php echo $chat_id; ?>">
                        <div class="ru-chatbro-loading">Загрузка...</div>
                    </div>

                    <?php if (!$is_logged_in && $allow_anonymous && !$req_reg): ?>
                    <div class="ru-chatbro-anon-name">
                        <input type="text" class="ru-chatbro-guest-name" placeholder="Ваше имя (необязательно)" maxlength="50">
                    </div>
                    <?php endif; ?>

                    <?php if ($req_reg && !$is_logged_in): ?>
                    <div class="ru-chatbro-auth-msg">
                        <a href="<?php echo wp_login_url(get_permalink()); ?>">Войдите</a>, чтобы написать в чат
                    </div>
                    <?php else: ?>
                    <div class="ru-chatbro-input-area">
                        <textarea class="ru-chatbro-input" placeholder="Напишите сообщение..." rows="1" maxlength="2000"></textarea>
                        <button class="ru-chatbro-send" title="Отправить">&#x27A4;</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$inline): ?>
            <button class="ru-chatbro-bubble" title="<?php echo esc_attr($chat['name']); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
                <span class="ru-chatbro-bubble-count"></span>
            </button>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
