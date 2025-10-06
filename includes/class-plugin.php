<?php
/**
 * Classe principal do plugin DS Registro Avançado.
 *
 * @package DS_Registro_Avancado
 */

if (!defined('ABSPATH')) {
    exit; // Acesso direto negado.
}

/**
 * Class DS_Registro_Avancado_Plugin
 */
final class DS_Registro_Avancado_Plugin {

    // -- Constantes do Plugin --
    const PHONE_CSS       = 'ds-otp-phone-field';
    const CODE_CSS        = 'ds-otp-code-field';
    const OTP_NONCE       = 'ds_otp_nonce';
    const PROFILE_FIELD_KEY = 'field_68b042dc15604'; // Chave do campo ACF para o ID do Studio Heros

    /**
     * Instância da classe (Singleton).
     * @var DS_Registro_Avancado_Plugin|null
     */
    private static ?DS_Registro_Avancado_Plugin $instance = null;

    /**
     * Configurações do plugin carregadas do banco de dados.
     * @var array
     */
    private array $settings = [];

    /**
     * Garante que apenas uma instância da classe seja criada.
     *
     * @return DS_Registro_Avancado_Plugin
     */
    public static function get_instance(): DS_Registro_Avancado_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor. Carrega configurações e adiciona os hooks.
     */
    private function __construct() {
        $this->load_settings();
        $this->add_hooks();
    }

    /**
     * Carrega as configurações salvas na página de opções.
     */
    private function load_settings(): void {
        // Carrega as opções salvas pela classe DS_Registro_Avancado_Admin_Settings
        $this->settings = get_option(DS_Registro_Avancado_Admin_Settings::OPTION_NAME, []);
    }
    
    /**
     * Adiciona todos os hooks (actions e filters) do WordPress.
     */
    private function add_hooks(): void {
        // Hooks de AJAX para o envio de OTP (não dependem de ID de formulário)
        add_action('wp_ajax_ds_send_otp_code', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_nopriv_ds_send_otp_code', [$this, 'ajax_send_otp']);

        // Hook de redirecionamento para completar perfil
        add_action('template_redirect', [$this, 'force_profile_completion']);

        // Obtém os IDs dos formulários das configurações
        $reg_form_id = $this->settings['reg_form_id'] ?? 0;
        $profile_form_id = $this->settings['profile_form_id'] ?? 0;

        // Adiciona hooks apenas se os IDs dos formulários estiverem definidos
        if ($reg_form_id > 0) {
            add_filter('gform_field_validation_' . $reg_form_id, [$this, 'validate_otp_field'], 10, 4);
            add_action('gform_after_submission_' . $reg_form_id, [$this, 'create_user_after_submission'], 10, 2);
            add_action('gform_enqueue_scripts', [$this, 'enqueue_registration_form_scripts'], 10, 2);
        }

        if ($profile_form_id > 0) {
            add_action('gform_after_submission_' . $profile_form_id, [$this, 'update_user_profile_after_submission'], 10, 2);
        }
    }

    /**
     * Enfileira os scripts JS para o formulário de registro.
     */
    public function enqueue_registration_form_scripts(array $form, bool $is_ajax): void {
        $reg_form_id = $this->settings['reg_form_id'] ?? 0;
        if ($form['id'] != $reg_form_id) {
            return;
        }

        wp_enqueue_script(
            'ds-registro-otp-js',
            DS_REGISTRO_AVANCADO_URL . 'assets/js/ds-registro-otp.js',
            ['jquery', 'gform_gravityforms'],
            DS_REGISTRO_AVANCADO_VERSION,
            true
        );

        wp_localize_script('ds-registro-otp-js', 'ds_otp_vars', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce(self::OTP_NONCE),
            'form_id'     => $reg_form_id,
            'css_classes' => ['phone' => self::PHONE_CSS, 'code' => self::CODE_CSS, 'button' => 'ds-otp-send-button', 'status' => 'ds-otp-status-div'],
            'i18n'        => ['sending' => 'Enviando...', 'sent' => 'Reenviar Código', 'error' => 'Erro de comunicação.', 'invalidPhone' => 'Telefone inválido.', 'wait' => 'Aguarde...'],
        ]);
    }

    /**
     * Manipula a requisição AJAX para enviar o código OTP.
     */
    public function ajax_send_otp(): void {
        // A lógica interna desta função permanece a mesma.
        if (!check_ajax_referer(self::OTP_NONCE, 'security', false)) { wp_send_json_error(['message' => 'Falha na verificação de segurança.'], 403); return; }
        $raw_phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        if (empty($raw_phone)) { wp_send_json_error(['message' => 'Número de telefone é obrigatório.'], 400); return; }
        $phone_number = $this->normalize_phone($raw_phone);
        if (strlen($phone_number) < 12) { wp_send_json_error(['message' => 'Número de telefone inválido.'], 400); return; }
        $ip_address = $this->get_ip_address();
        if ($this->is_rate_limited($ip_address)) { wp_send_json_error(['message' => 'Você fez muitas tentativas. Por favor, aguarde 3 minutos.'], 429); return; }
        $otp_code = wp_rand(100000, 999999);
        $message  = "Seu código de verificação é: {$otp_code}";
        set_transient('ds_otp_' . $phone_number, $otp_code, 3 * MINUTE_IN_SECONDS);
        $this->log_rate_limit_attempt($ip_address);
        // NOTA: A lógica de envio do WhatsApp será externalizada em um passo futuro.
        $result = $this->send_whatsapp_message($phone_number, $message);
        if (is_wp_error($result)) { wp_send_json_error(['message' => 'Falha ao enviar o código. Tente novamente mais tarde.'], 500); }  
        else { wp_send_json_success(['message' => 'Código enviado para seu WhatsApp!']); }
    }

    /**
     * Valida o campo de OTP durante a submissão do formulário.
     */
    public function validate_otp_field(array $result, $value, array $form, $field): array {
        // A lógica interna desta função permanece a mesma.
        if (!is_string($field->cssClass) || !str_contains($field->cssClass, self::CODE_CSS)) { return $result; }
        $phone_number_raw = '';
        foreach ($form['fields'] as $form_field) {
            if (is_string($form_field->cssClass) && str_contains($form_field->cssClass, self::PHONE_CSS)) {
                $phone_number_raw = rgpost("input_{$form_field->id}");
                break;
            }
        }
        if (empty($phone_number_raw)) {
            $result['is_valid'] = false; $result['message']  = 'Erro interno: Não foi possível encontrar o campo de telefone.';
            return $result;
        }
        $phone_number = $this->normalize_phone($phone_number_raw);
        $stored_otp_code = get_transient('ds_otp_' . $phone_number);
        if ($stored_otp_code === false) { $result['is_valid'] = false; $result['message']  = 'Código de verificação expirado.'; }  
        elseif ((string) $stored_otp_code !== (string) $value) { $result['is_valid'] = false; $result['message']  = 'O código de verificação está incorreto.'; }
        return $result;
    }

    /**
     * Cria o usuário após a submissão do formulário de registro.
     */
    public function create_user_after_submission(array $entry, array $form): void {
        // A lógica interna desta função permanece a mesma.
        $field_map = $this->get_field_map($form['fields']);
        $email = rgar($entry, $field_map['email'] ?? '');
        if (empty($email) || !is_email($email) || email_exists($email)) return;
        $phone_raw = rgar($entry, $field_map['phone'] ?? '');
        $user_data = [
            'user_login' => $email, 'user_email' => $email, 'user_pass'  => rgar($entry, $field_map['password'] ?? null),
            'first_name' => rgar($entry, $field_map['name.first'] ?? ''), 'last_name'  => rgar($entry, $field_map['name.last'] ?? ''),
            'role' => get_option('default_role', 'subscriber'),
        ];
        $user_id = wp_insert_user($user_data);
        if (is_wp_error($user_id)) return;
        update_user_meta($user_id, 'billing_phone', $phone_raw);
        wp_set_current_user($user_id, $email);
        wp_set_auth_cookie($user_id, true, is_ssl());
        delete_transient('ds_otp_' . $this->normalize_phone($phone_raw));
    }

    /**
     * Atualiza o perfil do usuário após a submissão do formulário de perfil.
     */
    public function update_user_profile_after_submission(array $entry, array $form): void {
        if (!function_exists('update_field')) return;
        $user_id = get_current_user_id();
        if ($user_id === 0) return;

        $studio_heros_id = rgar($entry, '1'); // Assumindo que o campo tem ID 1 no form de perfil.

        if (!empty($studio_heros_id)) {
            $sanitized_value = sanitize_text_field($studio_heros_id);
            update_field(self::PROFILE_FIELD_KEY, $sanitized_value, 'user_' . $user_id);
        }
    }

    /**
     * Força o usuário a completar o perfil se um campo específico não estiver preenchido.
     */
    public function force_profile_completion(): void {
        if (!function_exists('get_field')) return;
        
        $page_slug = 'complete-seu-perfil'; // Idealmente, isso também seria uma opção na página de configurações.
        
        if (!is_user_logged_in() || is_admin() || is_page($page_slug) || current_user_can('manage_options')) return;
        
        $user_id = get_current_user_id();
        
        // CORREÇÃO: Usando a mesma chave (PROFILE_FIELD_KEY) para ler e escrever, garantindo consistência.
        $profile_data = get_field(self::PROFILE_FIELD_KEY, 'user_' . $user_id);

        if (empty($profile_data)) {
            wp_safe_redirect(home_url('/' . $page_slug . '/'));
            exit;
        }
    }

    // -- Métodos Utilitários Privados (sem alterações) --
    private function send_whatsapp_message(string $number, string $message) { $api_url = get_option('conector_whatsapp_url'); $api_key = get_option('conector_whatsapp_apikey'); $instance = get_option('conector_whatsapp_instance'); if (empty($api_url) || empty($api_key) || empty($instance)) return new WP_Error('conector_not_configured', 'Configurações da API não encontradas.'); $full_url = rtrim($api_url, '/') . '/message/sendText/' . $instance; $response = wp_remote_post($full_url, ['timeout' => 30, 'headers' => ['Content-Type' => 'application/json', 'apikey' => $api_key], 'body' => wp_json_encode(['number' => $number, 'text' => $message]),]); if (is_wp_error($response)) return $response; $code = wp_remote_retrieve_response_code($response); if ($code === 200 || $code === 201) return true; $error = json_decode(wp_remote_retrieve_body($response), true); return new WP_Error('api_error', "Erro na API ($code): " . ($error['message'] ?? 'Erro desconhecido.')); }
    private function normalize_phone(string $raw_phone): string { $digits_only = preg_replace('/[^0-9]/', '', $raw_phone); if (str_starts_with($digits_only, '55')) { $digits_only = substr($digits_only, 2); } return '55' . $digits_only; }
    private function is_rate_limited(string $ip): bool { return (get_transient('ds_otp_limit_' . $ip) ?: 0) >= 5; }
    private function log_rate_limit_attempt(string $ip): void { $key = 'ds_otp_limit_' . $ip; set_transient($key, (get_transient($key) ?: 0) + 1, 3 * MINUTE_IN_SECONDS); }
    private function get_ip_address(): string { foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) { if (!empty($_SERVER[$key])) { foreach (explode(',', $_SERVER[$key]) as $ip) { if (filter_var(trim($ip), FILTER_VALIDATE_IP)) return trim($ip); } } } return 'unknown'; }
    private function get_field_map(array $fields): array { $map = []; foreach ($fields as $field) { if (!is_string($field->cssClass)) continue; if (str_contains($field->cssClass, 'ds-reg-email-field')) $map['email'] = $field->id; if (str_contains($field->cssClass, 'ds-reg-password-field')) $map['password'] = $field->id; if (str_contains($field->cssClass, self::PHONE_CSS)) $map['phone'] = $field->id; if (str_contains($field->cssClass, 'ds-reg-name-field') && is_array($field->inputs)) { foreach ($field->inputs as $input) { if ($input['label'] === 'First') $map['name.first'] = $input['id']; if ($input['label'] === 'Last') $map['name.last'] = $input['id']; } } } return $map; }
}