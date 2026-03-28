<?php
if (!defined('ABSPATH')) exit;

class RuChatbroAdmin {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_menu'));
    }

    public static function add_menu() {
        add_menu_page('Ru-chatbro', 'Ru-chatbro', 'manage_options', 'ru-chatbro', array(__CLASS__, 'page_dashboard'), 'dashicons-format-chat', 80);
        add_submenu_page('ru-chatbro', 'Дашборд',      'Дашборд',      'manage_options', 'ru-chatbro',               array(__CLASS__, 'page_dashboard'));
        add_submenu_page('ru-chatbro', 'Чаты',          'Чаты',          'manage_options', 'ru-chatbro-chats',         array(__CLASS__, 'page_chats'));
        add_submenu_page('ru-chatbro', 'Создать чат',   'Создать чат',   'manage_options', 'ru-chatbro-create',        array(__CLASS__, 'page_create_chat'));
        add_submenu_page('ru-chatbro', 'Настройки',     'Настройки',     'manage_options', 'ru-chatbro-settings',      array(__CLASS__, 'page_settings'));
        add_submenu_page('ru-chatbro', 'Документация',  'Документация',  'manage_options', 'ru-chatbro-docs',          array(__CLASS__, 'page_docs'));
    }

    private static function header($title, $subtitle = '') {
        ?>
        <div class="rcb-admin-header">
            <div class="rcb-admin-logo">
                <span class="dashicons dashicons-format-chat"></span>
                <strong>Ru-chatbro</strong>
            </div>
            <div>
                <h1><?php echo esc_html($title); ?></h1>
                <?php if ($subtitle): ?><p class="rcb-subtitle"><?php echo esc_html($subtitle); ?></p><?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ========================= ДАШБОРД =========================
    public static function page_dashboard() {
        $chats     = RuChatbroDB::get_chats();
        $total     = count($chats);
        $active    = count(array_filter($chats, fn($c) => $c['is_active']));
        $msg_total = array_sum(array_column($chats, 'message_count'));
        $settings  = get_option('ru_chatbro_settings', RuChatbroDB::get_default_settings());
        $mode      = $settings['display_mode'] ?? 'shortcode';
        ?>
        <div class="wrap rcb-admin-wrap">
        <?php self::header('Дашборд', 'Обзор системы Ru-chatbro'); ?>

        <?php if ($mode === 'shortcode' && $total > 0): ?>
        <div class="rcb-notice rcb-notice-info" style="margin-bottom:18px;">
            💡 <strong>Совет:</strong> Сейчас чат показывается только через шорткод. Чтобы он появлялся на всём сайте автоматически — перейдите
            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=display'); ?>">в Настройки → Отображение</a>.
        </div>
        <?php endif; ?>

        <div class="rcb-stats">
            <div class="rcb-stat-card"><span class="rcb-stat-number"><?php echo $total; ?></span><span class="rcb-stat-label">Всего чатов</span></div>
            <div class="rcb-stat-card rcb-stat-active"><span class="rcb-stat-number"><?php echo $active; ?></span><span class="rcb-stat-label">Активных</span></div>
            <div class="rcb-stat-card rcb-stat-messages"><span class="rcb-stat-number"><?php echo $msg_total; ?></span><span class="rcb-stat-label">Сообщений</span></div>
            <div class="rcb-stat-card rcb-stat-mode">
                <span class="rcb-stat-number" style="font-size:18px;">
                    <?php echo $mode === 'all_site' ? '🌐' : ($mode === 'url_list' ? '📄' : '📌'); ?>
                </span>
                <span class="rcb-stat-label"><?php echo $mode === 'all_site' ? 'Весь сайт' : ($mode === 'url_list' ? 'По URL' : 'Шорткод'); ?></span>
            </div>
        </div>

        <div class="rcb-quick-actions">
            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-create'); ?>" class="rcb-btn rcb-btn-primary">➕ Создать чат</a>
            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=display'); ?>" class="rcb-btn rcb-btn-secondary">🌐 Настроить отображение</a>
            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=appearance'); ?>" class="rcb-btn rcb-btn-secondary">🎨 Внешний вид</a>
            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-docs'); ?>" class="rcb-btn rcb-btn-secondary">📖 Документация</a>
        </div>

        <?php if (!empty($chats)): ?>
        <h2>Чаты</h2>
        <table class="wp-list-table widefat fixed striped rcb-table">
            <thead>
                <tr><th>ID</th><th>Название</th><th>Тип</th><th>Интеграции</th><th>Сообщений</th><th>Статус</th><th>Шорткод</th></tr>
            </thead>
            <tbody>
                <?php foreach ($chats as $chat): ?>
                <tr>
                    <td><?php echo $chat['id']; ?></td>
                    <td><strong><?php echo esc_html($chat['name']); ?></strong></td>
                    <td><?php echo self::type_badge($chat['type']); ?></td>
                    <td><?php echo self::integrations_badges($chat['integrations']); ?></td>
                    <td><?php echo intval($chat['message_count']); ?></td>
                    <td><?php echo $chat['is_active'] ? '<span class="rcb-badge rcb-badge-green">Активен</span>' : '<span class="rcb-badge rcb-badge-gray">Неактивен</span>'; ?></td>
                    <td><code>[ru_chatbro id="<?php echo $chat['id']; ?>"]</code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="rcb-empty-state">
            <div class="rcb-empty-icon">💬</div>
            <h3>Чатов пока нет</h3>
            <p>Создайте первый чат, чтобы начать общаться с посетителями.</p>
            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-create'); ?>" class="rcb-btn rcb-btn-primary">➕ Создать первый чат</a>
        </div>
        <?php endif; ?>
        </div>
        <?php
    }

    // ========================= ЧАТЫ =========================
    public static function page_chats() {
        $chats   = RuChatbroDB::get_chats();
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_chat = $edit_id ? RuChatbroDB::get_chat($edit_id) : null;
        ?>
        <div class="wrap rcb-admin-wrap">
        <?php self::header('Управление чатами'); ?>

        <?php if ($edit_chat): ?>
            <h2>✏️ Редактировать: <?php echo esc_html($edit_chat['name']); ?></h2>
            <?php self::render_chat_form($edit_chat); ?>
            <br><a href="<?php echo admin_url('admin.php?page=ru-chatbro-chats'); ?>" class="rcb-btn rcb-btn-secondary">← Назад</a>
        <?php else: ?>
            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-create'); ?>" class="rcb-btn rcb-btn-primary" style="margin-bottom:16px;display:inline-block;">➕ Создать чат</a>
            <?php if (empty($chats)): ?>
                <p class="rcb-empty">Чатов нет. <a href="<?php echo admin_url('admin.php?page=ru-chatbro-create'); ?>">Создать первый</a></p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped rcb-table">
                <thead>
                    <tr><th>ID</th><th>Название</th><th>Тип</th><th>Интеграции</th><th>Сообщ.</th><th>Статус</th><th>Шорткод</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($chats as $chat): ?>
                    <tr id="rcb-chat-row-<?php echo $chat['id']; ?>">
                        <td><?php echo $chat['id']; ?></td>
                        <td><strong><?php echo esc_html($chat['name']); ?></strong><?php if ($chat['description']): ?><br><small style="color:#888;"><?php echo esc_html($chat['description']); ?></small><?php endif; ?></td>
                        <td><?php echo self::type_badge($chat['type']); ?></td>
                        <td><?php echo self::integrations_badges($chat['integrations']); ?></td>
                        <td><?php echo intval($chat['message_count']); ?></td>
                        <td><?php echo $chat['is_active'] ? '<span class="rcb-badge rcb-badge-green">Активен</span>' : '<span class="rcb-badge rcb-badge-gray">Неактивен</span>'; ?></td>
                        <td><code class="rcb-shortcode" title="Нажмите чтобы скопировать" onclick="rcbCopy(this)">[ru_chatbro id="<?php echo $chat['id']; ?>"]</code></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-chats&edit=' . $chat['id']); ?>" class="rcb-btn rcb-btn-small">✏️ Изменить</a>
                            <button class="rcb-btn rcb-btn-small rcb-btn-danger rcb-delete-chat" data-id="<?php echo $chat['id']; ?>" data-name="<?php echo esc_attr($chat['name']); ?>">🗑️ Удалить</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php endif; ?>
        </div>
        <script>
        function rcbCopy(el) {
            navigator.clipboard.writeText(el.textContent).then(function() {
                var orig = el.title; el.title = '✅ Скопировано!';
                setTimeout(function(){ el.title = orig; }, 1500);
            });
        }
        </script>
        <?php
    }

    // ========================= СОЗДАТЬ ЧАТ =========================
    public static function page_create_chat() {
        ?>
        <div class="wrap rcb-admin-wrap">
        <?php self::header('Создать новый чат'); ?>
        <?php self::render_chat_form(null); ?>
        </div>
        <?php
    }

    private static function render_chat_form($chat = null) {
        $is_edit = $chat !== null;
        $integrations = $is_edit ? ($chat['integrations'] ?? []) : [];
        $available = ['vk' => '🔵 ВКонтакте', 'telegram' => '🔷 Telegram', 'ok' => '🟠 Одноклассники', 'max' => '🟣 Макс'];
        ?>
        <form class="rcb-form" id="rcb-chat-form" data-id="<?php echo $is_edit ? $chat['id'] : 0; ?>">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'ru_chatbro_admin_update_chat' : 'ru_chatbro_admin_create_chat'; ?>">
            <?php if ($is_edit): ?><input type="hidden" name="id" value="<?php echo $chat['id']; ?>"><?php endif; ?>

            <?php if ($is_edit): ?>
            <div class="rcb-shortcode-display">
                <strong>📋 Шорткод для вставки на страницу:</strong>
                <code class="rcb-shortcode-big" onclick="navigator.clipboard.writeText(this.textContent)" title="Нажмите для копирования">[ru_chatbro id="<?php echo $chat['id']; ?>"]</code>
                <span class="rcb-copy-hint">👆 Нажмите для копирования</span>
                <br><small>Или используйте встроенный режим: <code>[ru_chatbro id="<?php echo $chat['id']; ?>" inline="true"]</code></small>
            </div>
            <?php endif; ?>

            <div class="rcb-form-group">
                <label>Название чата <span class="required">*</span></label>
                <input type="text" name="name" value="<?php echo $is_edit ? esc_attr($chat['name']) : ''; ?>" required maxlength="255" placeholder="Например: Чат поддержки">
            </div>
            <div class="rcb-form-group">
                <label>Описание</label>
                <textarea name="description" rows="2" maxlength="500" placeholder="Краткое описание (видно только в админке)"><?php echo $is_edit ? esc_textarea($chat['description']) : ''; ?></textarea>
            </div>
            <div class="rcb-form-group">
                <label>Тип чата</label>
                <select name="type">
                    <option value="public"    <?php selected($is_edit ? $chat['type'] : 'public', 'public'); ?>>🌐 Публичный — виден всем</option>
                    <option value="private"   <?php selected($is_edit ? $chat['type'] : '', 'private'); ?>>🔒 Приватный — только по ссылке</option>
                    <option value="anonymous" <?php selected($is_edit ? $chat['type'] : '', 'anonymous'); ?>>👤 Анонимный — без имени</option>
                </select>
            </div>
            <div class="rcb-form-group">
                <label>Синхронизация с мессенджерами</label>
                <div class="rcb-integrations-grid">
                    <?php foreach ($available as $key => $label): ?>
                    <label class="rcb-integration-item <?php echo in_array($key, $integrations) ? 'rcb-integration-checked' : ''; ?>">
                        <input type="checkbox" name="integrations[]" value="<?php echo $key; ?>" <?php checked(in_array($key, $integrations)); ?>>
                        <?php echo $label; ?>
                    </label>
                    <?php endforeach; ?>
                    <label class="rcb-integration-item <?php echo in_array('none', $integrations) ? 'rcb-integration-checked' : ''; ?>">
                        <input type="checkbox" name="integrations[]" value="none">
                        🌐 Только сайт (без соцсетей)
                    </label>
                </div>
                <p class="rcb-hint">Для работы интеграций сначала введите токены в <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=vk'); ?>">Настройках</a>.</p>
            </div>
            <div class="rcb-form-row">
                <label class="rcb-toggle-label">
                    <input type="checkbox" name="allow_anonymous" value="1" <?php checked($is_edit ? $chat['allow_anonymous'] : true); ?>>
                    👤 Разрешить анонимные сообщения
                </label>
                <label class="rcb-toggle-label">
                    <input type="checkbox" name="require_registration" value="1" <?php checked($is_edit ? $chat['require_registration'] : false); ?>>
                    📋 Требовать регистрацию WordPress
                </label>
                <label class="rcb-toggle-label">
                    <input type="checkbox" name="is_active" value="1" <?php checked($is_edit ? $chat['is_active'] : true); ?>>
                    ✅ Чат активен
                </label>
            </div>
            <div id="rcb-form-message" class="rcb-notice" style="display:none;"></div>
            <div class="rcb-form-actions">
                <button type="submit" class="rcb-btn rcb-btn-primary rcb-btn-lg">
                    <?php echo $is_edit ? '💾 Сохранить изменения' : '✅ Создать чат'; ?>
                </button>
            </div>
        </form>
        <?php
    }

    // ========================= НАСТРОЙКИ =========================
    public static function page_settings() {
        $settings = get_option('ru_chatbro_settings', RuChatbroDB::get_default_settings());
        $tab = sanitize_text_field($_GET['tab'] ?? 'display');
        $chats = RuChatbroDB::get_chats();

        $tabs = [
            'display'    => '🌐 Отображение',
            'appearance' => '🎨 Внешний вид',
            'vk'         => '🔵 ВКонтакте',
            'telegram'   => '🔷 Telegram',
            'ok'         => '🟠 Одноклассники',
            'max'        => '🟣 Макс',
            'general'    => '⚙️ Общие',
        ];
        ?>
        <div class="wrap rcb-admin-wrap">
        <?php self::header('Настройки'); ?>
        <div class="rcb-tabs">
            <?php foreach ($tabs as $slug => $label): ?>
            <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=' . $slug); ?>"
               class="rcb-tab <?php echo $tab === $slug ? 'rcb-tab-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
            <?php endforeach; ?>
        </div>

        <form class="rcb-form" id="rcb-settings-form">
            <input type="hidden" name="action" value="ru_chatbro_admin_save_settings">

            <?php
            switch ($tab) {
                case 'display':    self::tab_display($settings, $chats);    break;
                case 'appearance': self::tab_appearance($settings);          break;
                case 'vk':         self::tab_vk($settings);                 break;
                case 'telegram':   self::tab_telegram($settings);            break;
                case 'ok':         self::tab_ok($settings);                 break;
                case 'max':        self::tab_max($settings);                 break;
                case 'general':    self::tab_general($settings);             break;
            }
            ?>

            <div id="rcb-settings-message" class="rcb-notice" style="display:none;"></div>
            <div class="rcb-form-actions">
                <button type="submit" class="rcb-btn rcb-btn-primary rcb-btn-lg">💾 Сохранить настройки</button>
            </div>
        </form>
        </div>
        <?php
    }

    // ——— Вкладка: Отображение ———
    private static function tab_display($s, $chats) {
        $mode = $s['display_mode'] ?? 'shortcode';
        ?>
        <div class="rcb-section-title">Где показывать чат</div>
        <div class="rcb-notice rcb-notice-info">
            Выберите способ отображения чата на сайте. <strong>Шорткод</strong> — вставляете вручную на нужные страницы. <strong>Весь сайт</strong> или <strong>По URL</strong> — чат появляется автоматически без шорткода.
        </div>

        <div class="rcb-display-modes">
            <label class="rcb-display-mode-card <?php echo $mode === 'shortcode' ? 'active' : ''; ?>">
                <input type="radio" name="display_mode" value="shortcode" <?php checked($mode, 'shortcode'); ?>>
                <div class="rcb-display-mode-icon">📌</div>
                <div class="rcb-display-mode-title">Только шорткод</div>
                <div class="rcb-display-mode-desc">Чат показывается только там, где вставлен шорткод <code>[ru_chatbro id="1"]</code>. Максимальный контроль.</div>
            </label>
            <label class="rcb-display-mode-card <?php echo $mode === 'all_site' ? 'active' : ''; ?>">
                <input type="radio" name="display_mode" value="all_site" <?php checked($mode, 'all_site'); ?>>
                <div class="rcb-display-mode-icon">🌐</div>
                <div class="rcb-display-mode-title">На всём сайте</div>
                <div class="rcb-display-mode-desc">Чат автоматически появляется на всех страницах сайта. Можно исключить отдельные URL.</div>
            </label>
            <label class="rcb-display-mode-card <?php echo $mode === 'url_list' ? 'active' : ''; ?>">
                <input type="radio" name="display_mode" value="url_list" <?php checked($mode, 'url_list'); ?>>
                <div class="rcb-display-mode-icon">📄</div>
                <div class="rcb-display-mode-title">По списку URL</div>
                <div class="rcb-display-mode-desc">Чат появляется только на указанных страницах. Можно использовать * как маску.</div>
            </label>
        </div>

        <div id="rcb-display-global" class="rcb-display-section" <?php echo $mode === 'shortcode' ? 'style="display:none"' : ''; ?>>
            <div class="rcb-form-group" style="margin-top:20px;">
                <label>Чат для автоматического показа <span class="required">*</span></label>
                <select name="global_chat_id">
                    <option value="">— Выберите чат —</option>
                    <?php foreach ($chats as $chat): ?>
                    <option value="<?php echo $chat['id']; ?>" <?php selected($s['global_chat_id'] ?? '', $chat['id']); ?>>
                        <?php echo esc_html($chat['name']); ?> (ID: <?php echo $chat['id']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($chats)): ?>
                <p class="rcb-hint" style="color:#c0392b;">⚠️ Сначала <a href="<?php echo admin_url('admin.php?page=ru-chatbro-create'); ?>">создайте хотя бы один чат</a>.</p>
                <?php endif; ?>
            </div>

            <div class="rcb-form-group">
                <label class="rcb-toggle-label">
                    <input type="checkbox" name="show_on_mobile" value="1" <?php checked(!empty($s['show_on_mobile'] ?? true)); ?>>
                    📱 Показывать на мобильных устройствах
                </label>
            </div>

            <div id="rcb-exclude-urls" <?php echo $mode !== 'all_site' ? 'style="display:none"' : ''; ?>>
                <div class="rcb-form-group">
                    <label>🚫 Исключённые URL (не показывать чат на этих страницах)</label>
                    <textarea name="exclude_urls" rows="4" placeholder="/wp-admin/*&#10;/cart/&#10;/checkout/&#10;https://example.com/login/"><?php echo esc_textarea(implode("\n", $s['exclude_urls'] ?? [])); ?></textarea>
                    <p class="rcb-hint">По одному на строку. Можно использовать <code>*</code> как маску. Пример: <code>/cart/*</code> исключит все страницы корзины.</p>
                </div>
            </div>

            <div id="rcb-include-urls" <?php echo $mode !== 'url_list' ? 'style="display:none"' : ''; ?>>
                <div class="rcb-form-group">
                    <label>✅ URL для показа чата</label>
                    <textarea name="display_urls" rows="6" placeholder="/blog/*&#10;/products/my-product/&#10;https://example.com/contact/"><?php echo esc_textarea(implode("\n", $s['display_urls'] ?? [])); ?></textarea>
                    <p class="rcb-hint">По одному на строку. Поддерживается <code>*</code> как маска. Примеры:<br>
                    <code>/blog/*</code> — все страницы блога<br>
                    <code>/products/</code> — только эта страница<br>
                    <code>https://example.com/contact/</code> — полный URL
                    </p>
                </div>
            </div>
        </div>

        <script>
        document.querySelectorAll('[name="display_mode"]').forEach(function(el) {
            el.addEventListener('change', function() {
                var val = this.value;
                var globalDiv = document.getElementById('rcb-display-global');
                var excludeDiv = document.getElementById('rcb-exclude-urls');
                var includeDiv = document.getElementById('rcb-include-urls');
                globalDiv.style.display = val === 'shortcode' ? 'none' : '';
                excludeDiv.style.display = val === 'all_site' ? '' : 'none';
                includeDiv.style.display = val === 'url_list' ? '' : 'none';
                document.querySelectorAll('.rcb-display-mode-card').forEach(function(card) {
                    card.classList.remove('active');
                });
                this.closest('.rcb-display-mode-card').classList.add('active');
            });
        });
        </script>
        <?php
    }

    // ——— Вкладка: Внешний вид ———
    private static function tab_appearance($s) {
        $fonts = [
            'system'     => 'Системный (по умолчанию)',
            'inter'      => 'Inter',
            'roboto'     => 'Roboto',
            'opensans'   => 'Open Sans',
            'montserrat' => 'Montserrat',
            'nunito'     => 'Nunito',
        ];
        ?>
        <!-- ПРЕДПРОСМОТР -->
        <div class="rcb-section-title">Предпросмотр</div>
        <div class="rcb-preview-wrapper">
            <div class="rcb-preview-chat" id="rcb-preview">
                <div class="rcb-preview-header">
                    <span>💬 Название чата</span>
                    <span>3 участника</span>
                </div>
                <div class="rcb-preview-messages">
                    <div class="rcb-preview-msg rcb-preview-msg-other">
                        <div class="rcb-preview-avatar">А</div>
                        <div><span class="rcb-preview-author">Алексей</span><br><span class="rcb-preview-text">Привет! Как дела?</span></div>
                    </div>
                    <div class="rcb-preview-msg rcb-preview-msg-own">
                        <div><span class="rcb-preview-author" style="text-align:right;display:block;">Вы</span><br><span class="rcb-preview-text">Всё отлично, спасибо! 👍</span></div>
                    </div>
                    <div class="rcb-preview-msg rcb-preview-msg-other">
                        <div class="rcb-preview-avatar">М</div>
                        <div><span class="rcb-preview-author">Мария</span> <span class="rcb-preview-badge">TG</span><br><span class="rcb-preview-text">Хороший чат!</span></div>
                    </div>
                </div>
                <div class="rcb-preview-input">
                    <span>Написать сообщение...</span>
                    <button>➤</button>
                </div>
            </div>
        </div>

        <!-- ЦВЕТА -->
        <div class="rcb-section-title">🎨 Цвета</div>
        <div class="rcb-color-grid">
            <?php $colors = [
                'color_primary'       => ['Основной цвет (кнопки, ссылки)', '#0077ff'],
                'color_header_bg'     => ['Фон заголовка чата', '#0077ff'],
                'color_header_text'   => ['Текст заголовка', '#ffffff'],
                'color_bubble_bg'     => ['Фон кнопки-пузыря', '#0077ff'],
                'color_bubble_text'   => ['Иконка кнопки-пузыря', '#ffffff'],
                'color_bg'            => ['Фон чата', '#ffffff'],
                'color_text'          => ['Основной текст', '#222222'],
                'color_msg_own_bg'    => ['Фон своих сообщений', '#e8f0ff'],
                'color_msg_other_bg'  => ['Фон чужих сообщений', '#f0f0f0'],
                'color_input_bg'      => ['Фон поля ввода', '#f5f7fb'],
                'color_link'          => ['Цвет ссылок', '#0077ff'],
            ];
            foreach ($colors as $key => [$label, $default]): ?>
            <div class="rcb-color-item">
                <label><?php echo $label; ?></label>
                <input type="color" name="<?php echo $key; ?>" value="<?php echo esc_attr($s[$key] ?? $default); ?>" class="rcb-color-input" data-target="<?php echo $key; ?>">
                <input type="text" class="rcb-color-hex" value="<?php echo esc_attr($s[$key] ?? $default); ?>" maxlength="7" style="width:80px;">
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ШРИФТ -->
        <div class="rcb-section-title">🔤 Шрифт</div>
        <div class="rcb-form-row">
            <div class="rcb-form-group">
                <label>Гарнитура шрифта</label>
                <select name="font_family" class="rcb-font-select">
                    <?php foreach ($fonts as $val => $label): ?>
                    <option value="<?php echo $val; ?>" <?php selected($s['font_family'] ?? 'system', $val); ?> style="font-family:<?php echo $val === 'system' ? 'inherit' : $val; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="rcb-hint">Google Fonts подключаются автоматически при выборе.</p>
            </div>
            <div class="rcb-form-group">
                <label>Размер шрифта (px)</label>
                <div class="rcb-range-row">
                    <input type="range" name="font_size" min="10" max="20" value="<?php echo intval($s['font_size'] ?? 13); ?>" class="rcb-range" oninput="document.getElementById('fs-val').textContent=this.value">
                    <span id="fs-val"><?php echo intval($s['font_size'] ?? 13); ?></span> px
                </div>
            </div>
            <div class="rcb-form-group">
                <label>Межстрочный интервал</label>
                <select name="line_height">
                    <?php foreach (['1.2' => 'Компактный (1.2)', '1.4' => 'Нормальный (1.4)', '1.5' => 'Стандартный (1.5)', '1.7' => 'Широкий (1.7)'] as $v => $l): ?>
                    <option value="<?php echo $v; ?>" <?php selected($s['line_height'] ?? '1.5', $v); ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="rcb-form-group">
            <label class="rcb-toggle-label">
                <input type="checkbox" name="font_bold" value="1" <?php checked(!empty($s['font_bold'])); ?>>
                <strong>Жирный текст сообщений</strong>
            </label>
        </div>

        <!-- РАЗМЕР И ПОЗИЦИЯ -->
        <div class="rcb-section-title">📐 Размер и позиция</div>
        <div class="rcb-form-row">
            <div class="rcb-form-group">
                <label>Ширина чата</label>
                <input type="text" name="chat_width" value="<?php echo esc_attr($s['chat_width'] ?? '350px'); ?>" placeholder="350px">
                <p class="rcb-hint">Минимум 250px. Можно: px, %, vw</p>
            </div>
            <div class="rcb-form-group">
                <label>Высота чата</label>
                <input type="text" name="chat_height" value="<?php echo esc_attr($s['chat_height'] ?? '300px'); ?>" placeholder="300px">
                <p class="rcb-hint">Минимум 200px</p>
            </div>
            <div class="rcb-form-group">
                <label>Высота заголовка</label>
                <input type="text" name="header_height" value="<?php echo esc_attr($s['header_height'] ?? '44px'); ?>" placeholder="44px">
            </div>
        </div>
        <div class="rcb-form-row">
            <div class="rcb-form-group">
                <label>Горизонтальная позиция</label>
                <select name="position_h">
                    <option value="right" <?php selected($s['position_h'] ?? 'right', 'right'); ?>>Справа</option>
                    <option value="left"  <?php selected($s['position_h'] ?? 'right', 'left'); ?>>Слева</option>
                </select>
            </div>
            <div class="rcb-form-group">
                <label>Вертикальная позиция</label>
                <select name="position_v">
                    <option value="bottom" <?php selected($s['position_v'] ?? 'bottom', 'bottom'); ?>>Снизу</option>
                    <option value="top"    <?php selected($s['position_v'] ?? 'bottom', 'top'); ?>>Сверху</option>
                </select>
            </div>
            <div class="rcb-form-group">
                <label>Отступ по горизонтали</label>
                <input type="text" name="offset_h" value="<?php echo esc_attr($s['offset_h'] ?? '20px'); ?>" placeholder="20px">
            </div>
            <div class="rcb-form-group">
                <label>Отступ по вертикали</label>
                <input type="text" name="offset_v" value="<?php echo esc_attr($s['offset_v'] ?? '20px'); ?>" placeholder="20px">
            </div>
        </div>

        <!-- СКРУГЛЕНИЯ -->
        <div class="rcb-section-title">🔵 Скругления</div>
        <div class="rcb-form-row">
            <div class="rcb-form-group">
                <label>Скругление чата</label>
                <input type="text" name="border_radius_chat" value="<?php echo esc_attr($s['border_radius_chat'] ?? '12px'); ?>" placeholder="12px">
            </div>
            <div class="rcb-form-group">
                <label>Скругление сообщений</label>
                <input type="text" name="border_radius_msg" value="<?php echo esc_attr($s['border_radius_msg'] ?? '8px'); ?>" placeholder="8px">
            </div>
            <div class="rcb-form-group">
                <label>Скругление кнопки-пузыря</label>
                <input type="text" name="border_radius_bubble" value="<?php echo esc_attr($s['border_radius_bubble'] ?? '50%'); ?>" placeholder="50%">
            </div>
            <div class="rcb-form-group">
                <label>Размер кнопки-пузыря</label>
                <input type="text" name="bubble_size" value="<?php echo esc_attr($s['bubble_size'] ?? '54px'); ?>" placeholder="54px">
            </div>
        </div>

        <!-- ЭЛЕМЕНТЫ -->
        <div class="rcb-section-title">✅ Элементы интерфейса</div>
        <div class="rcb-toggles-grid">
            <?php $toggles = [
                'show_avatars'       => 'Показывать аватары пользователей',
                'show_date'          => 'Показывать время сообщений',
                'show_source_badge'  => 'Значок источника (ВК, TG, ОК, Макс)',
                'show_user_count'    => 'Счётчик участников в заголовке',
                'chat_shadow'        => 'Тень у чата',
                'hide_copyright'     => 'Скрыть ссылку "Ru-chatbro"',
            ];
            foreach ($toggles as $key => $label): ?>
            <label class="rcb-toggle-label">
                <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php
                    $val = $s[$key] ?? ($key === 'hide_copyright' ? false : true);
                    checked(!empty($val));
                ?>>
                <?php echo $label; ?>
            </label>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($s['show_avatars'])): ?>
        <div class="rcb-form-group" style="margin-top:12px;">
            <label>Скругление аватаров</label>
            <input type="text" name="avatar_radius" value="<?php echo esc_attr($s['avatar_radius'] ?? '6px'); ?>" placeholder="6px">
            <p class="rcb-hint">50% = круглые аватары, 0 = квадратные</p>
        </div>
        <?php endif; ?>

        <!-- JS для цветовых инпутов -->
        <script>
        document.querySelectorAll('.rcb-color-input').forEach(function(input) {
            var hex = input.nextElementSibling;
            input.addEventListener('input', function() { hex.value = this.value; });
            hex.addEventListener('input', function() {
                if (/^#[0-9a-f]{6}$/i.test(this.value)) input.value = this.value;
            });
        });
        </script>
        <?php
    }

    // ——— Вкладка: ВКонтакте ———
    private static function tab_vk($s) { ?>
        <div class="rcb-section-title">🔵 ВКонтакте — настройка интеграции</div>
        <div class="rcb-doc-block">
            <h4>📋 Как получить токен группы ВКонтакте — пошагово:</h4>
            <ol>
                <li>Перейдите на страницу вашей группы/сообщества: <a href="https://vk.com" target="_blank">vk.com</a></li>
                <li>Нажмите кнопку <strong>«Управление»</strong> (под аватаркой группы)</li>
                <li>В левом меню выберите <strong>«Работа с API»</strong></li>
                <li>Нажмите вкладку <strong>«Ключи доступа»</strong></li>
                <li>Нажмите кнопку <strong>«Создать ключ»</strong></li>
                <li>Поставьте галочки на следующих разрешениях:
                    <ul>
                        <li>✅ <strong>Управление сообществом</strong></li>
                        <li>✅ <strong>Сообщения сообщества</strong></li>
                        <li>✅ <strong>Фотографии</strong></li>
                        <li>✅ <strong>Документы</strong></li>
                    </ul>
                </li>
                <li>Скопируйте полученный токен и вставьте ниже</li>
                <li><strong>ID группы</strong> — число в адресе страницы (например: <code>vk.com/club<strong>123456789</strong></code> → ID = <code>123456789</code>)</li>
            </ol>
            <p>📖 Официальная документация: <a href="https://dev.vk.com/ru/api/access-token/getting-started" target="_blank">dev.vk.com/ru/api/access-token</a></p>
            <p>⚠️ <strong>Важно:</strong> Токен выдаётся от имени группы, не пользователя. Убедитесь, что вы администратор этой группы.</p>
        </div>
        <div class="rcb-form-group">
            <label>Токен доступа группы ВКонтакте</label>
            <div class="rcb-input-row">
                <input type="password" name="vk_token" id="rcb-vk-token" value="<?php echo esc_attr($s['vk_token'] ?? ''); ?>" placeholder="vk1.a.xxxxxxxxxxxx...">
                <button type="button" class="rcb-btn rcb-btn-secondary rcb-btn-small" onclick="rcbTogglePass('rcb-vk-token')">👁️</button>
            </div>
        </div>
        <div class="rcb-form-group">
            <label>ID группы ВКонтакте <span style="color:#888;font-weight:normal;">(только цифры, без знака минус)</span></label>
            <input type="text" name="vk_group_id" value="<?php echo esc_attr($s['vk_group_id'] ?? ''); ?>" placeholder="123456789">
        </div>
        <?php
    }

    // ——— Вкладка: Telegram ———
    private static function tab_telegram($s) { ?>
        <div class="rcb-section-title">🔷 Telegram — настройка бота</div>
        <div class="rcb-doc-block">
            <h4>📋 Как создать бота в Telegram — пошагово:</h4>
            <ol>
                <li>Откройте Telegram и найдите официального бота <a href="https://t.me/BotFather" target="_blank"><strong>@BotFather</strong></a> <small>(с синей галочкой ✔️)</small></li>
                <li>Нажмите <strong>«Начать»</strong> или отправьте <code>/start</code></li>
                <li>Отправьте команду <code>/newbot</code></li>
                <li>Введите <strong>имя бота</strong> (например: <em>«Мой Чат Бот»</em>) — это отображаемое имя</li>
                <li>Введите <strong>username бота</strong> — должен быть уникальным и заканчиваться на <code>bot</code> (например: <code>moi_chat_bot</code>)</li>
                <li>BotFather пришлёт токен вида: <code>123456789:ABCdefGHIjklMNOpqrstuvwxyz</code> — <strong>сохраните его!</strong></li>
                <li>⚠️ <strong>Отключите режим конфиденциальности</strong> бота, чтобы он читал сообщения в группах:<br>
                    Отправьте <code>/mybots</code> → выберите бота → <em>Bot Settings</em> → <em>Group Privacy</em> → <strong>Turn off</strong></li>
            </ol>
            <h4>📋 Как узнать ID чата/группы:</h4>
            <ol>
                <li>Добавьте бота в нужную группу/канал</li>
                <li>Назначьте бота <strong>администратором</strong> с правами: «Отправлять сообщения», «Читать сообщения»</li>
                <li>Напишите любое сообщение в группу</li>
                <li>Откройте в браузере: <code>https://api.telegram.org/bot<strong>ВАШ_ТОКЕН</strong>/getUpdates</code></li>
                <li>В ответе найдите поле <code>"chat":{"id":</code> — это и есть ID чата</li>
                <li>Для группы ID будет отрицательным числом, например: <code>-1001234567890</code></li>
            </ol>
            <p>💡 Также можно узнать ID через бота <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a> или <a href="https://t.me/RawDataBot" target="_blank">@RawDataBot</a></p>
            <p>📖 Официальная документация: <a href="https://core.telegram.org/bots/api" target="_blank">core.telegram.org/bots/api</a></p>
        </div>
        <div class="rcb-form-group">
            <label>Токен Telegram-бота</label>
            <div class="rcb-input-row">
                <input type="password" name="telegram_bot_token" id="rcb-tg-token" value="<?php echo esc_attr($s['telegram_bot_token'] ?? ''); ?>" placeholder="123456789:ABCdefGHIjklMNOpqrstuvwxyz">
                <button type="button" class="rcb-btn rcb-btn-secondary rcb-btn-small" onclick="rcbTogglePass('rcb-tg-token')">👁️</button>
            </div>
        </div>
        <div class="rcb-form-group">
            <label>ID чата / группы / канала Telegram</label>
            <input type="text" name="telegram_chat_id" value="<?php echo esc_attr($s['telegram_chat_id'] ?? ''); ?>" placeholder="-1001234567890">
            <p class="rcb-hint">Для групп/каналов ID начинается с минуса: <code>-1001234567890</code>. Для личного чата — просто число.</p>
        </div>
        <div class="rcb-notice rcb-notice-info">
            🔄 <strong>Синхронизация:</strong> Плагин получает новые сообщения из Telegram каждые <strong>30 секунд</strong> через WP-Cron. Сообщения с сайта — сразу отправляются в Telegram при отправке.
        </div>
        <?php
    }

    // ——— Вкладка: Одноклассники ———
    private static function tab_ok($s) { ?>
        <div class="rcb-section-title">🟠 Одноклассники — настройка</div>
        <div class="rcb-doc-block">
            <h4>📋 Как получить токен Одноклассников — пошагово:</h4>
            <ol>
                <li>Зайдите в <a href="https://ok.ru" target="_blank">Одноклассники</a> под своим аккаунтом</li>
                <li>Перейдите в раздел разработчиков: <a href="https://apiok.ru/dev/app/create" target="_blank">apiok.ru/dev/app/create</a></li>
                <li>Нажмите <strong>«Создать приложение»</strong> и заполните форму:
                    <ul>
                        <li><strong>Тип:</strong> «Веб-приложение»</li>
                        <li><strong>Название:</strong> любое (например: «Мой чат»)</li>
                        <li><strong>Доменное имя:</strong> адрес вашего сайта</li>
                    </ul>
                </li>
                <li>После создания скопируйте <strong>App ID</strong> и <strong>Секретный ключ</strong></li>
                <li>Для получения <code>access_token</code> выполните OAuth авторизацию по адресу:<br>
                    <code>https://connect.ok.ru/oauth/authorize?client_id=APP_ID&response_type=token&redirect_uri=ВАШ_САЙТ&scope=GROUP_CONTENT</code></li>
                <li><strong>ID группы:</strong> откройте группу на ok.ru, в адресе после <code>/group/</code> будет число — это и есть ID</li>
            </ol>
            <p>📖 Документация API: <a href="https://apiok.ru/dev/methods/" target="_blank">apiok.ru/dev/methods</a></p>
            <p>⚠️ Одноклассники не поддерживают отправку сообщений в чаты через API. Реализовано только получение.</p>
        </div>
        <div class="rcb-form-group">
            <label>Access Token Одноклассников</label>
            <div class="rcb-input-row">
                <input type="password" name="ok_token" id="rcb-ok-token" value="<?php echo esc_attr($s['ok_token'] ?? ''); ?>" placeholder="ваш access_token...">
                <button type="button" class="rcb-btn rcb-btn-secondary rcb-btn-small" onclick="rcbTogglePass('rcb-ok-token')">👁️</button>
            </div>
        </div>
        <div class="rcb-form-group">
            <label>ID группы Одноклассников</label>
            <input type="text" name="ok_group_id" value="<?php echo esc_attr($s['ok_group_id'] ?? ''); ?>" placeholder="12345678901234">
        </div>
        <?php
    }

    // ——— Вкладка: Макс ———
    private static function tab_max($s) { ?>
        <div class="rcb-section-title">🟣 Макс (MAX) — настройка бота</div>
        <div class="rcb-doc-block">
            <h4>📋 Как создать бота в мессенджере Макс — пошагово:</h4>
            <ol>
                <li>Скачайте и установите мессенджер <a href="https://max.ru" target="_blank"><strong>Макс</strong></a> (бывший Mail.ru Агент)</li>
                <li>Найдите официального метабота <strong>@metabot</strong> в поиске</li>
                <li>Нажмите <strong>«Начать»</strong> и отправьте <code>/newbot</code></li>
                <li>Следуйте инструкциям: введите имя и username бота</li>
                <li>Получите <strong>токен бота</strong> — сохраните его!</li>
                <li>Добавьте бота в нужный чат</li>
                <li>Для получения ID чата напишите в чате команду <code>/chatInfo</code></li>
            </ol>
            <p>📖 Документация Bot API: <a href="https://botapi.max.ru/" target="_blank">botapi.max.ru</a></p>
            <p>📱 Скачать Макс: <a href="https://max.ru/download" target="_blank">max.ru/download</a></p>
        </div>
        <div class="rcb-form-group">
            <label>Токен бота Макс</label>
            <div class="rcb-input-row">
                <input type="password" name="max_bot_token" id="rcb-max-token" value="<?php echo esc_attr($s['max_bot_token'] ?? ''); ?>" placeholder="ваш токен бота...">
                <button type="button" class="rcb-btn rcb-btn-secondary rcb-btn-small" onclick="rcbTogglePass('rcb-max-token')">👁️</button>
            </div>
        </div>
        <div class="rcb-form-group">
            <label>ID чата Макс</label>
            <input type="text" name="max_chat_id" value="<?php echo esc_attr($s['max_chat_id'] ?? ''); ?>" placeholder="123456789">
        </div>
        <?php
    }

    // ——— Вкладка: Общие ———
    private static function tab_general($s) { ?>
        <div class="rcb-section-title">⚙️ Ограничения и фильтрация</div>
        <div class="rcb-form-group">
            <label>Задержка между сообщениями (slow mode), секунд</label>
            <div class="rcb-range-row">
                <input type="range" name="slow_mode_delay" min="0" max="60" value="<?php echo intval($s['slow_mode_delay'] ?? 3); ?>" oninput="document.getElementById('smd-val').textContent=this.value">
                <span id="smd-val"><?php echo intval($s['slow_mode_delay'] ?? 3); ?></span> сек
            </div>
            <p class="rcb-hint">0 = без задержки. Администраторы WordPress отправляют сообщения без ограничений.</p>
        </div>
        <div class="rcb-toggles-grid">
            <label class="rcb-toggle-label"><input type="checkbox" name="allow_file_upload"    value="1" <?php checked(!empty($s['allow_file_upload'])); ?>> 📎 Разрешить загрузку файлов</label>
            <label class="rcb-toggle-label"><input type="checkbox" name="allow_message_edit"   value="1" <?php checked(!empty($s['allow_message_edit'])); ?>> ✏️ Разрешить редактировать свои сообщения</label>
            <label class="rcb-toggle-label"><input type="checkbox" name="allow_message_delete" value="1" <?php checked(!empty($s['allow_message_delete'])); ?>> 🗑️ Разрешить удалять свои сообщения</label>
            <label class="rcb-toggle-label"><input type="checkbox" name="admin_only_send"      value="1" <?php checked(!empty($s['admin_only_send'])); ?>> 🔒 Только администратор может писать</label>
        </div>
        <div class="rcb-form-group" style="margin-top:16px;">
            <label>🚫 Список запрещённых слов</label>
            <textarea name="bad_words" rows="6" placeholder="спам&#10;реклама&#10;18+&#10;казино"><?php echo esc_textarea(implode("\n", $s['bad_words'] ?? [])); ?></textarea>
            <p class="rcb-hint">По одному слову на строку. Будут автоматически заменены символами ****. Регистр не учитывается.</p>
        </div>
        <?php
    }

    // ========================= ДОКУМЕНТАЦИЯ =========================
    public static function page_docs() { ?>
        <div class="wrap rcb-admin-wrap">
        <?php self::header('Документация', 'Подробное руководство по Ru-chatbro'); ?>
        <div class="rcb-docs">

        <div class="rcb-doc-nav">
            <a href="#quick-start">🚀 Быстрый старт</a>
            <a href="#shortcodes">📌 Шорткоды</a>
            <a href="#display-modes">🌐 Режимы отображения</a>
            <a href="#telegram">🔷 Telegram</a>
            <a href="#vk">🔵 ВКонтакте</a>
            <a href="#ok">🟠 Одноклассники</a>
            <a href="#max">🟣 Макс</a>
            <a href="#appearance">🎨 Внешний вид</a>
            <a href="#faq">❓ FAQ</a>
        </div>

        <!-- БЫСТРЫЙ СТАРТ -->
        <div class="rcb-doc-section" id="quick-start">
            <h2>🚀 Быстрый старт (5 минут)</h2>
            <div class="rcb-steps">
                <div class="rcb-step"><div class="rcb-step-num">1</div><div class="rcb-step-content"><strong>Установите плагин</strong><br>Плагины → Добавить новый → Загрузить плагин → выберите <code>ru-chatbro.zip</code> → Установить → Активировать</div></div>
                <div class="rcb-step"><div class="rcb-step-num">2</div><div class="rcb-step-content"><strong>Создайте чат</strong><br>Ru-chatbro → <a href="<?php echo admin_url('admin.php?page=ru-chatbro-create'); ?>">Создать чат</a> → введите название → нажмите «Создать чат»</div></div>
                <div class="rcb-step"><div class="rcb-step-num">3</div><div class="rcb-step-content"><strong>Выберите способ показа</strong><br>Ru-chatbro → <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=display'); ?>">Настройки → Отображение</a> → выберите «На всём сайте» или вставьте шорткод на нужные страницы</div></div>
                <div class="rcb-step"><div class="rcb-step-num">4</div><div class="rcb-step-content"><strong>(Опционально) Подключите мессенджеры</strong><br>Ru-chatbro → Настройки → вкладка нужного мессенджера → введите токены → сохраните</div></div>
                <div class="rcb-step"><div class="rcb-step-num">5</div><div class="rcb-step-content"><strong>Готово!</strong><br>Откройте сайт и проверьте появился ли чат</div></div>
            </div>
        </div>

        <!-- ШОРТКОДЫ -->
        <div class="rcb-doc-section" id="shortcodes">
            <h2>📌 Шорткоды</h2>
            <p>Шорткод вставляется в любую страницу, запись или виджет WordPress.</p>
            <table class="rcb-table widefat">
                <thead><tr><th>Шорткод</th><th>Описание</th></tr></thead>
                <tbody>
                    <tr><td><code>[ru_chatbro id="1"]</code></td><td>Плавающий чат-виджет (кнопка снизу справа, по клику раскрывается)</td></tr>
                    <tr><td><code>[ru_chatbro id="1" inline="true"]</code></td><td>Встроенный чат прямо на странице (без кнопки-пузыря)</td></tr>
                    <tr><td><code>[ru_chatbro id="1" width="400px"]</code></td><td>Задать ширину чата</td></tr>
                    <tr><td><code>[ru_chatbro id="1" height="500px"]</code></td><td>Задать высоту чата</td></tr>
                    <tr><td><code>[ru_chatbro id="1" width="100%" inline="true"]</code></td><td>Встроенный чат на всю ширину</td></tr>
                </tbody>
            </table>
            <p>Найти ID чата можно в разделе <a href="<?php echo admin_url('admin.php?page=ru-chatbro-chats'); ?>">Чаты</a>.</p>
        </div>

        <!-- РЕЖИМЫ ОТОБРАЖЕНИЯ -->
        <div class="rcb-doc-section" id="display-modes">
            <h2>🌐 Режимы отображения</h2>
            <p>Настройки: <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=display'); ?>">Настройки → Отображение</a></p>
            <table class="rcb-table widefat">
                <thead><tr><th>Режим</th><th>Как работает</th><th>Когда использовать</th></tr></thead>
                <tbody>
                    <tr>
                        <td>📌 <strong>Только шорткод</strong></td>
                        <td>Чат появляется только там, где вставлен шорткод <code>[ru_chatbro id="X"]</code></td>
                        <td>Нужен чат только на конкретных страницах (контакты, поддержка)</td>
                    </tr>
                    <tr>
                        <td>🌐 <strong>На всём сайте</strong></td>
                        <td>Чат автоматически появляется на всех страницах. Можно исключить отдельные URL.</td>
                        <td>Нужен чат везде (интернет-магазин, сообщество)</td>
                    </tr>
                    <tr>
                        <td>📄 <strong>По списку URL</strong></td>
                        <td>Чат появляется только на указанных страницах. Поддерживается <code>*</code> как маска.</td>
                        <td>Нужен чат в разделе блога или на нескольких конкретных страницах</td>
                    </tr>
                </tbody>
            </table>
            <h4>Примеры масок URL:</h4>
            <table class="rcb-table widefat">
                <thead><tr><th>Маска</th><th>Совпадёт с...</th></tr></thead>
                <tbody>
                    <tr><td><code>/blog/*</code></td><td>Все страницы раздела /blog/</td></tr>
                    <tr><td><code>/products/laptop/</code></td><td>Только эта конкретная страница</td></tr>
                    <tr><td><code>/shop/category/*</code></td><td>Все страницы категории в магазине</td></tr>
                    <tr><td><code>https://example.com/contact/</code></td><td>Точный URL страницы контактов</td></tr>
                </tbody>
            </table>
        </div>

        <!-- TELEGRAM -->
        <div class="rcb-doc-section" id="telegram">
            <h2>🔷 Telegram — подробная инструкция</h2>
            <h3>Шаг 1. Создание бота через @BotFather</h3>
            <ol>
                <li>Откройте Telegram (на телефоне или в браузере: <a href="https://web.telegram.org" target="_blank">web.telegram.org</a>)</li>
                <li>В поиске найдите <a href="https://t.me/BotFather" target="_blank"><strong>@BotFather</strong></a> — ищите синюю галочку ✔️ рядом с именем</li>
                <li>Нажмите <strong>«Начать»</strong> и отправьте команду <code>/newbot</code></li>
                <li>BotFather спросит имя бота — введите любое (например: <em>«Чат Поддержки»</em>)</li>
                <li>Затем спросит username — он должен:
                    <ul>
                        <li>Быть уникальным (проверяется автоматически)</li>
                        <li>Содержать только буквы, цифры и _</li>
                        <li>Заканчиваться на <code>bot</code> (например: <code>my_support_bot</code>)</li>
                    </ul>
                </li>
                <li>После успешного создания BotFather пришлёт <strong>токен</strong> вида:<br>
                    <code>6789012345:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw</code><br>
                    ⚠️ Никому не передавайте этот токен!</li>
            </ol>
            <h3>Шаг 2. Отключение Privacy Mode</h3>
            <div class="rcb-notice rcb-notice-warning">
                ⚠️ Это обязательный шаг! Без него бот не будет читать сообщения в группе.
            </div>
            <ol>
                <li>Отправьте BotFather команду <code>/mybots</code></li>
                <li>Выберите вашего бота из списка</li>
                <li>Нажмите <strong>«Bot Settings»</strong></li>
                <li>Нажмите <strong>«Group Privacy»</strong></li>
                <li>Нажмите <strong>«Turn off»</strong> — должно появиться «Privacy mode is disabled»</li>
            </ol>
            <h3>Шаг 3. Добавление бота в группу</h3>
            <ol>
                <li>Откройте нужную группу/канал в Telegram</li>
                <li>Нажмите на название группы → <strong>«Добавить участника»</strong></li>
                <li>Найдите вашего бота по username и добавьте его</li>
                <li>Назначьте бота <strong>администратором</strong>: удерживайте бота → «Сделать администратором»</li>
                <li>Дайте права: <strong>Отправлять сообщения</strong>, <strong>Читать сообщения</strong></li>
            </ol>
            <h3>Шаг 4. Получение ID чата</h3>
            <p><strong>Способ 1 (через API):</strong></p>
            <ol>
                <li>Напишите любое сообщение в вашу группу</li>
                <li>Откройте в браузере: <code>https://api.telegram.org/bot<strong>ВАШ_ТОКЕН</strong>/getUpdates</code></li>
                <li>В ответе найдите: <code>"chat":{"id":<strong>-1001234567890</strong></code> — это ID группы</li>
            </ol>
            <p><strong>Способ 2 (через бота):</strong></p>
            <ol>
                <li>Добавьте в группу бота <a href="https://t.me/RawDataBot" target="_blank">@RawDataBot</a></li>
                <li>Он автоматически напишет информацию о чате, включая его ID</li>
                <li>После получения ID удалите @RawDataBot из группы</li>
            </ol>
            <div class="rcb-notice rcb-notice-info">
                🔄 Синхронизация работает <strong>в обе стороны</strong>: сообщения с сайта → в Telegram, сообщения из Telegram → на сайт. Polling каждые 30 секунд.
            </div>
            <p>Настройки: <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=telegram'); ?>">Настройки → Telegram</a></p>
        </div>

        <!-- ВКОНТАКТЕ -->
        <div class="rcb-doc-section" id="vk">
            <h2>🔵 ВКонтакте — подробная инструкция</h2>
            <h3>Шаг 1. Подготовка группы</h3>
            <ol>
                <li>У вас должна быть группа/сообщество ВКонтакте</li>
                <li>Вы должны быть <strong>администратором</strong> этой группы</li>
                <li>Перейдите на страницу группы: <a href="https://vk.com" target="_blank">vk.com</a></li>
            </ol>
            <h3>Шаг 2. Создание токена</h3>
            <ol>
                <li>Под аватаркой группы нажмите <strong>«Управление»</strong></li>
                <li>В левом меню найдите и нажмите <strong>«Работа с API»</strong></li>
                <li>Перейдите на вкладку <strong>«Ключи доступа»</strong></li>
                <li>Нажмите <strong>«Создать ключ»</strong></li>
                <li>В появившемся окне отметьте <strong>все необходимые разрешения</strong>:
                    <ul>
                        <li>✅ Управление сообществом</li>
                        <li>✅ Сообщения сообщества</li>
                        <li>✅ Фотографии</li>
                        <li>✅ Документы</li>
                    </ul>
                </li>
                <li>Подтвердите действие через SMS или приложение</li>
                <li>Скопируйте полученный токен</li>
            </ol>
            <h3>Шаг 3. ID группы</h3>
            <ol>
                <li>Откройте вашу группу в браузере</li>
                <li>Посмотрите на адрес: <code>vk.com/club<strong>123456789</strong></code></li>
                <li>Число после <code>club</code> — это ID группы (вводите <strong>без минуса</strong>)</li>
                <li>Если адрес кастомный (vk.com/mygroup), зайдите в Управление и посмотрите ID в разделе информации</li>
            </ol>
            <div class="rcb-notice rcb-notice-info">
                🔄 Синхронизация через <strong>VK Long Poll API</strong>. Плагин получает обновления в реальном времени (каждые 30 сек через WP-Cron).
            </div>
            <p>📖 Официальная документация: <a href="https://dev.vk.com/ru/api/access-token/getting-started" target="_blank">dev.vk.com/ru/api/access-token</a></p>
            <p>Настройки: <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=vk'); ?>">Настройки → ВКонтакте</a></p>
        </div>

        <!-- ОДНОКЛАССНИКИ -->
        <div class="rcb-doc-section" id="ok">
            <h2>🟠 Одноклассники — подробная инструкция</h2>
            <h3>Шаг 1. Регистрация приложения</h3>
            <ol>
                <li>Зайдите на <a href="https://ok.ru" target="_blank">ok.ru</a> под своим аккаунтом</li>
                <li>Перейдите по ссылке: <a href="https://apiok.ru/dev/app/create" target="_blank">apiok.ru/dev/app/create</a></li>
                <li>Нажмите <strong>«Создать приложение»</strong></li>
                <li>Заполните форму:
                    <ul>
                        <li><strong>Тип:</strong> «Веб-приложение»</li>
                        <li><strong>Название:</strong> например «Ru-chatbro»</li>
                        <li><strong>URL сайта:</strong> адрес вашего сайта</li>
                        <li><strong>Redirect URI:</strong> <code><?php echo home_url('/'); ?></code></li>
                    </ul>
                </li>
                <li>После создания сохраните <strong>App ID</strong>, <strong>Public Key</strong>, <strong>Secret Key</strong></li>
            </ol>
            <h3>Шаг 2. Получение Access Token</h3>
            <ol>
                <li>Сформируйте URL авторизации (замените значения своими):
                    <code>https://connect.ok.ru/oauth/authorize?client_id=ВАШ_APP_ID&response_type=token&redirect_uri=ВАШ_САЙТ&scope=GROUP_CONTENT,STREAM_PUBLISH</code>
                </li>
                <li>Откройте URL в браузере и авторизуйтесь</li>
                <li>После редиректа в адресной строке будет <code>access_token=XXXXXX</code> — скопируйте его</li>
            </ol>
            <h3>Шаг 3. ID группы</h3>
            <ol>
                <li>Откройте вашу группу на ok.ru</li>
                <li>В адресе будет: <code>ok.ru/group/<strong>12345678901234</strong></code> — это ID группы</li>
            </ol>
            <p>📖 Документация: <a href="https://apiok.ru/dev/methods/" target="_blank">apiok.ru/dev/methods</a></p>
            <p>Настройки: <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=ok'); ?>">Настройки → Одноклассники</a></p>
        </div>

        <!-- МАКС -->
        <div class="rcb-doc-section" id="max">
            <h2>🟣 Макс (MAX) — подробная инструкция</h2>
            <h3>Шаг 1. Установка Макс</h3>
            <ol>
                <li>Скачайте мессенджер Макс для вашей платформы: <a href="https://max.ru/download" target="_blank">max.ru/download</a></li>
                <li>Зарегистрируйтесь или войдите</li>
            </ol>
            <h3>Шаг 2. Создание бота</h3>
            <ol>
                <li>В поиске найдите <strong>@metabot</strong> (официальный бот для создания ботов)</li>
                <li>Нажмите <strong>«Начать»</strong> и отправьте <code>/newbot</code></li>
                <li>Введите имя бота и следуйте инструкциям</li>
                <li>Получите <strong>токен бота</strong> — он выглядит как длинная строка символов</li>
            </ol>
            <h3>Шаг 3. Добавление бота в чат</h3>
            <ol>
                <li>Создайте новый чат или откройте существующий</li>
                <li>Добавьте бота в чат по username</li>
                <li>Отправьте в чат команду <code>/chatInfo</code> — бот ответит с информацией о чате, включая <strong>ID</strong></li>
            </ol>
            <p>📖 Bot API документация: <a href="https://botapi.max.ru/" target="_blank">botapi.max.ru</a></p>
            <p>Настройки: <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=max'); ?>">Настройки → Макс</a></p>
        </div>

        <!-- ВНЕШНИЙ ВИД -->
        <div class="rcb-doc-section" id="appearance">
            <h2>🎨 Внешний вид — как настроить</h2>
            <p>Все визуальные настройки находятся в <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=appearance'); ?>">Настройки → Внешний вид</a></p>
            <table class="rcb-table widefat">
                <thead><tr><th>Настройка</th><th>Описание</th></tr></thead>
                <tbody>
                    <tr><td><strong>Цвета</strong></td><td>Полная настройка цветовой схемы: заголовок, фон, кнопки, сообщения, ссылки</td></tr>
                    <tr><td><strong>Шрифт</strong></td><td>Выбор из 6 шрифтов (системный, Inter, Roboto, Open Sans, Montserrat, Nunito)</td></tr>
                    <tr><td><strong>Размер</strong></td><td>Ширина и высота чата, высота заголовка</td></tr>
                    <tr><td><strong>Позиция</strong></td><td>Расположение (право/лево, верх/низ) и отступы от края экрана</td></tr>
                    <tr><td><strong>Скругления</strong></td><td>Скругление чата, сообщений, кнопки-пузыря, аватаров</td></tr>
                    <tr><td><strong>Элементы</strong></td><td>Аватары, дата, значки источника (ВК, TG), счётчик участников, тень</td></tr>
                </tbody>
            </table>
        </div>

        <!-- FAQ -->
        <div class="rcb-doc-section" id="faq">
            <h2>❓ Часто задаваемые вопросы</h2>
            <details class="rcb-details"><summary>Чат не появляется на сайте</summary><div class="rcb-details-content"><ol><li>Проверьте, что плагин <strong>активирован</strong> (Плагины → список)</li><li>В режиме «Шорткод»: проверьте, что шорткод <code>[ru_chatbro id="X"]</code> вставлен на страницу с правильным ID</li><li>В режиме «Весь сайт»: проверьте, что выбран чат в <a href="<?php echo admin_url('admin.php?page=ru-chatbro-settings&tab=display'); ?>">Настройках → Отображение</a></li><li>Проверьте, что чат активен в <a href="<?php echo admin_url('admin.php?page=ru-chatbro-chats'); ?>">разделе Чаты</a></li><li>Очистите кэш сайта и браузера</li></ol></div></details>
            <details class="rcb-details"><summary>Сообщения из Telegram не приходят в чат</summary><div class="rcb-details-content"><ol><li>Убедитесь, что режим конфиденциальности бота <strong>отключён</strong> (см. инструкцию по Telegram)</li><li>Проверьте, что бот является <strong>администратором</strong> группы</li><li>Проверьте правильность токена и ID чата</li><li>Убедитесь, что на сайте работает <strong>WP-Cron</strong>. Если WP-Cron отключён — добавьте в cron сервера: <code>*/1 * * * * curl https://ваш-сайт.ru/wp-cron.php?doing_wp_cron</code></li></ol></div></details>
            <details class="rcb-details"><summary>Как одновременно подключить несколько мессенджеров?</summary><div class="rcb-details-content"><p>При создании чата в разделе <strong>«Интеграции»</strong> отметьте все нужные мессенджеры. Убедитесь, что в настройках введены токены для каждого из них. Один чат может быть подключён ко всем мессенджерам одновременно.</p></div></details>
            <details class="rcb-details"><summary>Можно ли показывать разные чаты на разных страницах?</summary><div class="rcb-details-content"><p>Да. Используйте шорткод <code>[ru_chatbro id="X"]</code> для каждой страницы — замените X на нужный ID. Или создайте несколько чатов и настройте режим «По списку URL» для каждого чата отдельно через их шорткоды.</p></div></details>
            <details class="rcb-details"><summary>Как разрешить анонимный чат без регистрации?</summary><div class="rcb-details-content"><p>При создании или редактировании чата включите <strong>«Разрешить анонимные сообщения»</strong> и выключите <strong>«Требовать регистрацию»</strong>. Посетитель сможет ввести любое имя и писать без аккаунта.</p></div></details>
            <details class="rcb-details"><summary>Плагин замедляет сайт?</summary><div class="rcb-details-content"><p>CSS и JS загружаются только на страницах, где присутствует чат. Синхронизация с мессенджерами происходит через WP-Cron в фоне и не влияет на скорость страниц.</p></div></details>
        </div>

        </div><!-- .rcb-docs -->
        </div>
        <script>
        function rcbTogglePass(id) {
            var input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
        </script>
        <?php
    }

    private static function type_badge($type) {
        $labels  = ['public' => 'Публичный', 'private' => 'Приватный', 'anonymous' => 'Анонимный'];
        $classes = ['public' => 'rcb-badge-blue', 'private' => 'rcb-badge-purple', 'anonymous' => 'rcb-badge-gray'];
        return '<span class="rcb-badge ' . ($classes[$type] ?? '') . '">' . ($labels[$type] ?? $type) . '</span>';
    }

    private static function integrations_badges($integrations) {
        if (empty($integrations)) return '<span class="rcb-badge rcb-badge-gray">Сайт</span>';
        $labels = ['vk' => 'ВК', 'telegram' => 'TG', 'ok' => 'ОК', 'max' => 'Макс', 'none' => 'Сайт'];
        $colors = ['vk' => 'rcb-badge-vk', 'telegram' => 'rcb-badge-tg', 'ok' => 'rcb-badge-ok', 'max' => 'rcb-badge-max', 'none' => 'rcb-badge-gray'];
        $out = '';
        foreach ($integrations as $int) {
            $out .= '<span class="rcb-badge ' . ($colors[$int] ?? '') . '">' . ($labels[$int] ?? $int) . '</span> ';
        }
        return $out;
    }
}
