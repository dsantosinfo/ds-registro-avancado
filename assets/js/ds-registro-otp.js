/* global jQuery, ds_otp_vars */
(function ($) {
    'use strict';

    console.log('DS-OTP JS: Script carregado. (Versão 4.3.3 - Readonly Fix)');
    console.log('Uma peuna correção foi aplicada para garantir que os campos de telefone e código OTP sejam enviados corretamente para validação.');
    window.dsOtpPageInitialized = false;

    function setupOtpFlow(form_id, current_page) {
        const OTP_PAGE_NUMBER = 2;
        const config = window.ds_otp_vars;
        if (!config || config.form_id != form_id) { return; }
        const $formWrapper = $('#gform_wrapper_' + form_id);
        if ($formWrapper.length === 0) { return; }

        if (current_page != OTP_PAGE_NUMBER) {
            return;
        }
        
        if ($formWrapper.data('ds-otp-initialized')) { return; }
        $formWrapper.data('ds-otp-initialized', true);

        console.log('DS-OTP: Página de OTP (' + current_page + ') detectada. Inicializando fluxo...');

        const selectors = {
            phoneField: '.' + config.css_classes.phone,
            codeField: '.' + config.css_classes.code,
            statusDiv: '.' + config.css_classes.status,
            sendButton: '.' + config.css_classes.send_button,
            verifyButton: '.' + config.css_classes.verify_button,
            nextButton: '.gform_page_footer .gform_next_button',
        };

        const $phoneField   = $(selectors.phoneField, $formWrapper);
        const $codeField    = $(selectors.codeField, $formWrapper);
        const $statusDiv    = $(selectors.statusDiv, $formWrapper);
        const $sendButton   = $(selectors.sendButton, $formWrapper);
        const $verifyButton = $(selectors.verifyButton, $formWrapper);
        const $nextButton   = $formWrapper.find(selectors.nextButton);

        $codeField.hide();
        $verifyButton.hide();
        $nextButton.hide();

        $formWrapper.on('click.dsOTP.send', selectors.sendButton, function (e) {
            e.preventDefault();
            const phone = $phoneField.find('input').val();
            if (!phone || phone.replace(/\D/g, '').length < 10) { $statusDiv.html('<span class="error">' + config.i18n.invalidPhone + '</span>').slideDown(); return; }
            $sendButton.prop('disabled', true).text(config.i18n.sending);
            $statusDiv.html('<span>' + config.i18n.wait + '</span>').slideDown();
            
            $.ajax({ 
                url: config.ajax_url, type: 'POST', dataType: 'json', 
                data: { action: 'ds_send_otp_code', security: config.nonce, phone: phone } 
            })
            .done(function (response) {
                if (response.success) {
                    $statusDiv.html('<span class="success">' + response.data.message + '</span>');
                    $codeField.slideDown();
                    $codeField.find('input').prop('disabled', false);
                    $verifyButton.slideDown();
                    $('input[name="otp_solicitado"]', $formWrapper).val('1');
                } else { 
                    $statusDiv.html('<span class="error">' + (response.data.message || config.i18n.error) + '</span>'); 
                }
            }).fail(function () { 
                $statusDiv.html('<span class="error">' + config.i18n.error + '</span>');
            }).always(function () { 
                $sendButton.prop('disabled', false).text(config.i18n.sent); 
            });
        });

        $formWrapper.on('click.dsOTP.verify', selectors.verifyButton, function(e) {
            e.preventDefault();
            const phone = $phoneField.find('input').val(); 
            const code = $codeField.find('input').val();
            if (!code) { $statusDiv.html('<span class="error">Por favor, insira o código.</span>').slideDown(); return; }
            $verifyButton.prop('disabled', true).text(config.i18n.verifying); 
            $statusDiv.slideUp();
            
            $.ajax({ 
                url: config.ajax_url, type: 'POST', dataType: 'json', 
                data: { action: 'ds_verify_otp_code', security: config.nonce, phone: phone, code: code } 
            })
            .done(function (response) {
                if (response.success) {
                    $statusDiv.html('<span class="success">' + response.data.message + '</span>').slideDown();

                    // --- CORREÇÃO: Usar 'readonly' em vez de 'disabled' ---
                    // Isso impede a edição, mas garante que os valores sejam enviados para a validação do Gravity Forms.
                    $phoneField.find('input').prop('readonly', true);
                    $codeField.find('input').prop('readonly', true);
                    // --- FIM DA CORREÇÃO ---

                    $sendButton.prop('disabled', true);
                    $verifyButton.hide();
                    
                    $('input[name="otp_validado"]', $formWrapper).val('1');

                    $nextButton.slideDown();
                } else { 
                    $statusDiv.html('<span class="error">' + (response.data.message || config.i18n.error) + '</span>').slideDown(); 
                    $verifyButton.prop('disabled', false).text('Verificar Código'); 
                }
            }).fail(function() { 
                $statusDiv.html('<span class="error">' + config.i18n.error + '</span>').slideDown(); 
                $verifyButton.prop('disabled', false).text('Verificar Código'); 
            });
        });
    }

    $(document).on('gform_post_render', function(event, form_id, current_page){
        $('#gform_wrapper_' + form_id).data('ds-otp-initialized', false);
        setupOtpFlow(form_id, current_page);
    });

    $(document).ready(function(){
        if (window.ds_otp_vars && window.ds_otp_vars.form_id) {
            var form_id = window.ds_otp_vars.form_id;
            var currentPage = $('#gform_source_page_number_' + form_id).val() || 1;
            setupOtpFlow(form_id, currentPage);
        }
    });

})(jQuery);