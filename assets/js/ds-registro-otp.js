/* global jQuery, ds_otp_vars */
(function ($) {
    'use strict';

    console.log('[DEBUG] DS-OTP: Script de depuração iniciado.');

    if (window.dsOtpInitialized) {
        console.log('[DEBUG] DS-OTP: Script já inicializado, saindo.');
        return;
    }
    window.dsOtpInitialized = true;

    console.log('DS-OTP JS: Script carregado. Aguardando formulário (Versão 3.0.1-debug)');

    function initializeOtpFields() {
        const config = window.ds_otp_vars;
        if (!config) {
            console.error('[DEBUG] DS-OTP: ERRO CRÍTICO - Objeto window.ds_otp_vars não encontrado!');
            return;
        }
        
        console.log('[DEBUG] DS-OTP: Objeto de configuração carregado:', config);

        const $formWrapper = $('#gform_wrapper_' + config.form_id);
        if ($formWrapper.length === 0) {
            return;
        }
        
        if ($formWrapper.data('ds-otp-initialized')) {
            return;
        }
        $formWrapper.data('ds-otp-initialized', true);
        
        console.log('DS-OTP JS: Formulário #' + config.form_id + ' encontrado e inicializado.');

        const selectors = {
            sendButton: '.' + config.css_classes.button,
            phoneField: '.' + config.css_classes.phone,
            codeField:  '.' + config.css_classes.code,
            statusDiv:  '.' + config.css_classes.status,
        };
        
        console.log('[DEBUG] DS-OTP: Seletores definidos:', selectors);

        const $buttonElement = $(selectors.sendButton, $formWrapper);
        if ($buttonElement.length === 0) {
            console.error('[DEBUG] DS-OTP: ERRO - O botão com o seletor "' + selectors.sendButton + '" não foi encontrado dentro do formulário.');
        } else {
            console.log('[DEBUG] DS-OTP: SUCESSO - Botão encontrado no DOM.', $buttonElement.get(0));
        }

        console.log('[DEBUG] DS-OTP: Anexando listener de clique ao wrapper do formulário...');
        
        $formWrapper.on('click.dsOTP', selectors.sendButton, function (e) {
            e.preventDefault();
            console.log('%c[DEBUG] DS-OTP: CLIQUE DETECTADO!', 'color: green; font-weight: bold;');

            const $button = $(this);
            const $phoneInput = $(selectors.phoneField, $formWrapper).find('input');
            const $statusDiv = $(selectors.statusDiv, $formWrapper);
            const phone = $phoneInput.val();

            console.log('[DEBUG] DS-OTP: Valor do campo de telefone:', phone);

            if (!phone || phone.replace(/\D/g, '').length < 10) {
                console.error('[DEBUG] DS-OTP: Falha na validação do telefone no frontend.');
                $statusDiv.html('<span class="error">' + config.i18n.invalidPhone + '</span>').slideDown();
                return;
            }

            console.log('[DEBUG] DS-OTP: Telefone validado. Iniciando chamada AJAX...');
            $button.prop('disabled', true).text(config.i18n.sending);
            $statusDiv.html('<span>' + config.i18n.wait + '</span>').slideDown();

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ds_send_otp_code',
                    security: config.nonce,
                    phone: phone
                },
                timeout: 30000
            })
            .done(function (response) {
                console.log('[DEBUG] DS-OTP: AJAX .done() executado. Resposta:', response);
                if (response && response.success) {
                    $statusDiv.html('<span class="success">' + response.data.message + '</span>');
                    $button.prop('disabled', false).text(config.i18n.sent);
                    $(selectors.codeField, $formWrapper).slideDown();
                } else {
                    const errorMessage = response.data?.message || config.i18n.error;
                    $statusDiv.html('<span class="error">' + errorMessage + '</span>');
                    $button.prop('disabled', false).text(config.i18n.sent);
                }
            })
            .fail(function (xhr) {
                console.error('[DEBUG] DS-OTP: AJAX .fail() executado. XHR:', xhr);
                const serverMessage = xhr.responseJSON?.data?.message || config.i18n.error;
                $statusDiv.html('<span class="error">' + serverMessage + '</span>');
                $button.prop('disabled', false).text(config.i18n.sent);
            });
        });

        $formWrapper.on('input.dsOTP', selectors.phoneField + ' input', function() {
            const $statusDiv = $(selectors.statusDiv, $formWrapper);
            if ($statusDiv.is(':visible')) {
                $statusDiv.slideUp(function() {
                    $(this).html('');
                });
            }
        });

        console.log('[DEBUG] DS-OTP: Todos os listeners foram anexados.');
    }

    $(document).ready(initializeOtpFields);
    $(document).on('gform_post_render', initializeOtpFields);
    $(window).on('load', function() {
        setTimeout(initializeOtpFields, 500);
    });

})(jQuery);