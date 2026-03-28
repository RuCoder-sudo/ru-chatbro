jQuery(function($) {
    'use strict';

    var ajaxUrl = ruChatbroAdmin.ajaxUrl;
    var nonce   = ruChatbroAdmin.nonce;

    // === Форма чата ===
    $('#rcb-chat-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('[type="submit"]');
        var $msg  = $('#rcb-form-message');
        var action = $form.find('[name="action"]').val();

        $btn.prop('disabled', true).text('Сохранение...');
        $msg.hide();

        var data = $form.serializeArray();
        data.push({ name: 'nonce', value: nonce });

        $.post(ajaxUrl, data, function(res) {
            $btn.prop('disabled', false).text(action.includes('create') ? '✅ Создать чат' : '💾 Сохранить изменения');
            if (res.success) {
                $msg.removeClass('rcb-notice-error').addClass('rcb-notice rcb-notice-success')
                    .text('✅ ' + res.data.message).show();
                if (action.includes('create') && res.data.id) {
                    setTimeout(function() {
                        window.location.href = ajaxUrl.replace('admin-ajax.php', 'admin.php?page=ru-chatbro-chats&edit=' + res.data.id);
                    }, 1000);
                }
            } else {
                $msg.removeClass('rcb-notice-success').addClass('rcb-notice rcb-notice-error')
                    .text('❌ ' + (res.data ? res.data.message : 'Ошибка')).show();
            }
        });
    });

    // === Форма настроек ===
    $('#rcb-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('[type="submit"]');
        var $msg  = $('#rcb-settings-message');

        $btn.prop('disabled', true).text('Сохранение...');
        $msg.hide();

        var data = $form.serializeArray();
        data.push({ name: 'nonce', value: nonce });

        $.post(ajaxUrl, data, function(res) {
            $btn.prop('disabled', false).text('💾 Сохранить настройки');
            if (res.success) {
                $msg.removeClass('rcb-notice-error').addClass('rcb-notice rcb-notice-success')
                    .html('✅ ' + res.data.message + ' <a href="" onclick="location.reload();return false;">Обновить страницу</a>').show();
                setTimeout(function() { $msg.fadeOut(); }, 5000);
            } else {
                $msg.removeClass('rcb-notice-success').addClass('rcb-notice rcb-notice-error')
                    .text('❌ ' + (res.data ? res.data.message : 'Ошибка')).show();
            }
        });
    });

    // === Удаление чата ===
    $(document).on('click', '.rcb-delete-chat', function() {
        var $btn  = $(this);
        var id    = $btn.data('id');
        var name  = $btn.data('name');
        if (!confirm('Удалить чат «' + name + '»?\n\nВсе сообщения этого чата будут удалены безвозвратно.')) return;
        $btn.prop('disabled', true).text('Удаление...');
        $.post(ajaxUrl, { action: 'ru_chatbro_admin_delete_chat', nonce: nonce, id: id }, function(res) {
            if (res.success) {
                $('#rcb-chat-row-' + id).fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('Ошибка: ' + (res.data ? res.data.message : 'Не удалось удалить'));
                $btn.prop('disabled', false).text('🗑️ Удалить');
            }
        });
    });

    // === Интеграции: подсветка при выборе ===
    $(document).on('change', '.rcb-integration-item input', function() {
        $(this).closest('.rcb-integration-item').toggleClass('rcb-integration-checked', this.checked);
    });

    // === Переключатель показать/скрыть пароль ===
    window.rcbTogglePass = function(id) {
        var input = document.getElementById(id);
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    };
});
