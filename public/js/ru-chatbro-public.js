/* Ru-chatbro Public Chat Widget */
(function($) {
    'use strict';

    var ajaxUrl = ruChatbroConfig.ajaxUrl;
    var nonce   = ruChatbroConfig.nonce;
    var pollIntervals = {};

    // === Инициализация всех чатов на странице ===
    function initChats() {
        $('.ru-chatbro-wrapper').each(function() {
            initChat($(this));
        });
    }

    function initChat($wrapper) {
        var chatId   = $wrapper.data('chat-id');
        var isInline = $wrapper.hasClass('ru-chatbro-inline');

        if (!chatId) return;

        // Загрузить сообщения
        loadMessages($wrapper, chatId);

        // Для плавающего — открыть при клике на bubble
        if (!isInline) {
            $wrapper.find('.ru-chatbro-bubble').on('click', function() {
                $wrapper.toggleClass('rcb-open');
                if ($wrapper.hasClass('rcb-open')) {
                    scrollToBottom($wrapper);
                    $wrapper.find('.ru-chatbro-bubble-count').hide().text('0');
                }
            });

            $wrapper.find('.ru-chatbro-toggle').on('click', function() {
                $wrapper.removeClass('rcb-open');
            });
        }

        // Отправка сообщения
        $wrapper.find('.ru-chatbro-send').on('click', function() {
            sendMessage($wrapper, chatId);
        });
        $wrapper.find('.ru-chatbro-input').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage($wrapper, chatId);
            }
            // Авто-размер textarea
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Polling для новых сообщений каждые 5 секунд
        pollIntervals[chatId] = setInterval(function() {
            loadMessages($wrapper, chatId, true);
        }, 5000);
    }

    function loadMessages($wrapper, chatId, silent) {
        var $messagesEl = $wrapper.find('.ru-chatbro-messages');
        if (!silent) {
            $messagesEl.html('<div class="ru-chatbro-loading">Загрузка...</div>');
        }

        $.post(ajaxUrl, {
            action:  'ru_chatbro_get_messages',
            nonce:   nonce,
            chat_id: chatId,
            limit:   50,
            offset:  0
        }, function(res) {
            if (!res.success) return;

            var messages = res.data;
            var $existing = $messagesEl.find('.rcb-message');
            var existingIds = {};
            $existing.each(function() { existingIds[$(this).data('id')] = true; });

            var showAvatars = $wrapper.data('show-avatars') !== false;
            var newCount = 0;

            $.each(messages, function(i, msg) {
                if (existingIds[msg.id]) return;
                var $msg = renderMessage(msg, showAvatars);
                $messagesEl.find('.ru-chatbro-loading').remove();
                $messagesEl.append($msg);
                newCount++;
            });

            if (messages.length === 0 && !silent) {
                $messagesEl.html('<div class="ru-chatbro-loading">Сообщений пока нет. Напишите первым!</div>');
            }

            // Показать счётчик для плавающего чата
            if (silent && newCount > 0 && !$wrapper.hasClass('rcb-open')) {
                var $count = $wrapper.find('.ru-chatbro-bubble-count');
                var current = parseInt($count.text()) || 0;
                $count.text(current + newCount).show();
            }

            // Обновить счётчик участников
            updateUserCount($wrapper, messages);

            scrollToBottom($wrapper);
        });
    }

    function renderMessage(msg, showAvatars) {
        var time = formatTime(msg.created_at);
        var avatarHtml = '';

        if (showAvatars) {
            if (msg.avatar) {
                avatarHtml = '<img class="rcb-avatar" src="' + escHtml(msg.avatar) + '" alt="' + escHtml(msg.username) + '">';
            } else {
                var letter = (msg.username || '?').charAt(0).toUpperCase();
                avatarHtml = '<div class="rcb-avatar">' + letter + '</div>';
            }
        }

        var sourceLabel = '';
        var sourceLabels = { vk: 'ВК', telegram: 'TG', ok: 'ОК', max: 'Макс', website: '' };
        if (msg.source && msg.source !== 'website') {
            sourceLabel = '<span class="rcb-msg-source rcb-source-' + msg.source + '">' + (sourceLabels[msg.source] || msg.source) + '</span>';
        }

        var text = escHtml(msg.message_text || msg.text || '').replace(/\n/g, '<br>');

        return $('<div class="rcb-message" data-id="' + msg.id + '">' +
            avatarHtml +
            '<div class="rcb-msg-content">' +
                '<div class="rcb-msg-meta">' +
                    '<span class="rcb-msg-author">' + escHtml(msg.username) + '</span>' +
                    sourceLabel +
                    '<span class="rcb-msg-time">' + time + '</span>' +
                '</div>' +
                '<div class="rcb-msg-text">' + text + '</div>' +
            '</div>' +
        '</div>');
    }

    function sendMessage($wrapper, chatId) {
        var $input  = $wrapper.find('.ru-chatbro-input');
        var $send   = $wrapper.find('.ru-chatbro-send');
        var $guestName = $wrapper.find('.ru-chatbro-guest-name');
        var text    = $input.val().trim();

        if (!text) return;

        var username = $guestName.length ? ($guestName.val().trim() || 'Гость') : '';

        $send.prop('disabled', true);

        $.post(ajaxUrl, {
            action:   'ru_chatbro_send_message',
            nonce:    nonce,
            chat_id:  chatId,
            text:     text,
            username: username
        }, function(res) {
            $send.prop('disabled', false);
            if (res.success) {
                $input.val('').css('height', 'auto');
                var showAvatars = $wrapper.data('show-avatars') !== false;
                var $msg = renderMessage(res.data, showAvatars);
                $wrapper.find('.ru-chatbro-loading').remove();
                $wrapper.find('.ru-chatbro-messages').append($msg);
                scrollToBottom($wrapper);
            } else {
                var errMsg = (res.data && res.data.message) ? res.data.message : 'Ошибка отправки';
                showError($wrapper, errMsg);
            }
        });
    }

    function scrollToBottom($wrapper) {
        var $messages = $wrapper.find('.ru-chatbro-messages');
        $messages.scrollTop($messages[0].scrollHeight);
    }

    function updateUserCount($wrapper, messages) {
        var users = {};
        $.each(messages, function(i, m) { users[m.username] = true; });
        var count = Object.keys(users).length;
        $wrapper.find('.ru-chatbro-users-count').text(count > 0 ? count + ' ' + pluralForm(count, 'участник', 'участника', 'участников') : '');
    }

    function showError($wrapper, msg) {
        var $err = $('<div class="rcb-error-msg" style="color:#d32f2f;font-size:11px;padding:4px 10px;">' + escHtml(msg) + '</div>');
        $wrapper.find('.ru-chatbro-input-area').before($err);
        setTimeout(function() { $err.fadeOut(300, function() { $(this).remove(); }); }, 3000);
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        if (isNaN(d)) return dateStr;
        var h = String(d.getHours()).padStart(2, '0');
        var m = String(d.getMinutes()).padStart(2, '0');
        return h + ':' + m;
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function pluralForm(n, f1, f2, f5) {
        n = Math.abs(n) % 100;
        var n1 = n % 10;
        if (n > 10 && n < 20) return f5;
        if (n1 > 1 && n1 < 5) return f2;
        if (n1 === 1) return f1;
        return f5;
    }

    // Запуск
    $(document).ready(function() {
        initChats();
    });

})(jQuery);
