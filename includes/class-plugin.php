<?php
/**
 * Classe principal do plugin DS Registro Avançado.
 *
 * @package DS_Registro_Avancado
 */

if (!defined('ABSPATH')) {
    exit; // Acesso direto negado. [cite: 1890]
}

/**
 * Class DS_Registro_Avancado_Plugin
 */
final class DS_Registro_Avancado_Plugin {

    // -- Constantes do Plugin --
    const PHONE_CSS         = 'ds-otp-phone-field'; // [cite: 1891]
    const CODE_CSS          = 'ds-otp-code-field'; // [cite: 1892]
    const OTP_NONCE         = 'ds_otp_nonce'; // [cite: 1893]
    // Campos do WooCommerce
    const WC_BILLING_COUNTRY = 'billing_country';
    const WC_BILLING_STATE = 'billing_state';
    const WC_BILLING_CITY = 'billing_city';
    const WC_BILLING_POSTCODE = 'billing_postcode';
    const WC_BILLING_ADDRESS_1 = 'billing_address_1';
    const WC_BILLING_ADDRESS_2 = 'billing_address_2';
    // Campos personalizados
    const CUSTOM_PIX_KEY = 'pix_key';
    const CUSTOM_WISE_EMAIL = 'wise_email';
    const CUSTOM_USER_ID = 'platform_user_id';

    private static ?DS_Registro_Avancado_Plugin $instance = null; // [cite: 1898]
    private array $settings = []; // [cite: 1898]
    public static function get_instance(): DS_Registro_Avancado_Plugin { // [cite: 1899]
        if (self::$instance === null) { // [cite: 1899]
            self::$instance = new self(); // [cite: 1899]
        }
        return self::$instance; // [cite: 1900]
    }

    private function __construct() {
        $this->load_settings(); // [cite: 1901]
        $this->add_hooks(); // [cite: 1901]
    }

    private function load_settings(): void {
        $this->settings = get_option(DS_Registro_Avancado_Admin_Settings::OPTION_NAME, []); // [cite: 1902]
    }
    
    private function add_hooks(): void {
        add_action('wp_ajax_ds_send_otp_code', [$this, 'ajax_send_otp']); // [cite: 1903]
        add_action('wp_ajax_nopriv_ds_send_otp_code', [$this, 'ajax_send_otp']); // [cite: 1904]
        add_action('wp_ajax_ds_verify_otp_code', [$this, 'ajax_verify_otp']); // [cite: 1904]
        add_action('wp_ajax_nopriv_ds_verify_otp_code', [$this, 'ajax_verify_otp']); // [cite: 1904]
        add_action('wp_ajax_ds_export_forms', [$this, 'ajax_export_forms']);
        add_action('template_redirect', [$this, 'force_profile_completion']); // [cite: 1904]

        $reg_form_id = $this->settings['reg_form_id'] ?? 0; // [cite: 1904]
        $profile_form_id = $this->settings['profile_form_id'] ?? 0; // [cite: 1905]

        if ($reg_form_id > 0) {
            add_filter('gform_confirmation_' . $reg_form_id, [$this, 'registration_redirect'], 10, 4); // [cite: 1905]
            add_action('gform_after_submission_' . $reg_form_id, [$this, 'create_user_after_submission'], 10, 2); // [cite: 1906]
            add_action('gform_enqueue_scripts', [$this, 'enqueue_registration_form_scripts'], 10, 2); // [cite: 1906]
        }

        if ($profile_form_id > 0) {
            add_action('gform_after_submission_' . $profile_form_id, [$this, 'update_user_profile_after_submission'], 10, 2);
            add_filter('gform_pre_render_' . $profile_form_id, [$this, 'populate_wc_country_field'], 20);
            add_filter('gform_admin_pre_render_' . $profile_form_id, [$this, 'populate_wc_country_field'], 20);
        }
    }

    /**
     * Carrega scripts e estilos para o formulário de perfil.
     */
    public function enqueue_profile_form_scripts(array $form, bool $is_ajax): void {
        $profile_form_id = $this->settings['profile_form_id'] ?? 0;
        if ($form['id'] != $profile_form_id) {
            return;
        }

        wp_enqueue_script(
            'ds-profile-fields-js',
            DS_REGISTRO_AVANCADO_URL . 'assets/js/ds-profile-fields.js',
            ['jquery'],
            filemtime(DS_REGISTRO_AVANCADO_PATH . 'assets/js/ds-profile-fields.js'),
            true
        );

        wp_enqueue_style(
            'ds-profile-fields-css',
            DS_REGISTRO_AVANCADO_URL . 'assets/css/ds-profile-fields.css',
            [],
            filemtime(DS_REGISTRO_AVANCADO_PATH . 'assets/css/ds-profile-fields.css')
        );
    }

    /**
     * Carrega scripts condicionalmente para páginas com formulário de perfil.
     */
    public function maybe_enqueue_profile_scripts(): void {
        global $post;
        
        // Verifica se é a página de perfil ou se contém o formulário
        if (is_page('complete-seu-perfil') || 
            (isset($post->post_content) && has_shortcode($post->post_content, 'gravityform'))) {
            
            wp_enqueue_script(
                'ds-profile-fields-js',
                DS_REGISTRO_AVANCADO_URL . 'assets/js/ds-profile-fields.js',
                ['jquery'],
                filemtime(DS_REGISTRO_AVANCADO_PATH . 'assets/js/ds-profile-fields.js'),
                true
            );

            wp_enqueue_style(
                'ds-profile-fields-css',
                DS_REGISTRO_AVANCADO_URL . 'assets/css/ds-profile-fields.css',
                [],
                filemtime(DS_REGISTRO_AVANCADO_PATH . 'assets/css/ds-profile-fields.css')
            );
        }
    }

    /**
     * Popula campo de país usando países do WooCommerce.
     */
    public function populate_wc_country_field($form) {
        if (!class_exists('WC_Countries')) {
            return $form;
        }

        $country_field_id = $this->settings['country_field'] ?? 0;
        if ($country_field_id === 0) {
            return $form;
        }

        foreach ($form['fields'] as &$field) {
            if ($field->id != $country_field_id) {
                continue;
            }

            // Se for campo select, popular com opções
            if ($field->type === 'select') {
                $wc_countries = new WC_Countries();
                $countries = $wc_countries->get_countries();
                
                $choices = [];
                $current_country = get_user_meta(get_current_user_id(), self::WC_BILLING_COUNTRY, true);

                foreach ($countries as $code => $name) {
                    $choices[] = [
                        'text' => $name,
                        'value' => $code,
                        'isSelected' => ($current_country === $code)
                    ];
                }

                $field->choices = $choices;
            }
            // Se for campo texto, definir valor padrão
            else if ($field->type === 'text') {
                $current_country_code = get_user_meta(get_current_user_id(), self::WC_BILLING_COUNTRY, true);
                if ($current_country_code && class_exists('WC_Countries')) {
                    $wc_countries = new WC_Countries();
                    $countries = $wc_countries->get_countries();
                    if (isset($countries[$current_country_code])) {
                        $field->defaultValue = $countries[$current_country_code];
                    }
                }
            }
        }
        return $form;
    }

    /**
     * Lógica de redirecionamento pós-registro. [cite: 1918]
     */
    public function registration_redirect($confirmation, array $form, array $entry, bool $ajax): array|string
    {
        $target_url = home_url('/complete-seu-perfil/'); // [cite: 1919]
        return ['redirect' => esc_url_raw($target_url)]; // [cite: 1920]
    }
    
    /**
     * Cria o usuário e realiza o login automático após a submissão do formulário. [cite: 1920]
     */
    public function create_user_after_submission(array $entry, array $form): void {
        $field_map = $this->get_field_map($form['fields']); // [cite: 1921]
        $email = rgar($entry, $field_map['email'] ?? ''); // [cite: 1921]
        
        if (empty($email) || !is_email($email) || email_exists($email)) { // [cite: 1922]
            return; // [cite: 1922]
        }

        $phone_raw = rgar($entry, $field_map['phone'] ?? ''); // [cite: 1923]
        $user_data = [ // [cite: 1924]
            'user_login' => $email, // [cite: 1924]
            'user_email' => $email, // [cite: 1924]
            'user_pass'  => rgar($entry, $field_map['password'] ?? null), // [cite: 1924]
            'first_name' => rgar($entry, $field_map['name.first'] ?? ''), // [cite: 1924]
            'last_name'  => rgar($entry, $field_map['name.last'] ?? ''), // [cite: 1924]
            'role'       => get_option('default_role', 'subscriber'), // [cite: 1925]
        ];

        $user_id = wp_insert_user($user_data); // [cite: 1925]
        if (is_wp_error($user_id)) { // [cite: 1926]
            return; // [cite: 1926]
        }

        wp_set_current_user($user_id, $email); // [cite: 1927]
        wp_set_auth_cookie($user_id, true, is_ssl()); // [cite: 1927]

        update_user_meta($user_id, 'billing_phone', $phone_raw); // [cite: 1927]
        
        delete_transient('ds_otp_' . $this->normalize_phone($phone_raw)); // [cite: 1927]
    }
    
    public function ajax_verify_otp(): void { if (!check_ajax_referer(self::OTP_NONCE, 'security', false)) { wp_send_json_error(['message' => 'Falha na verificação de segurança.'], 403); // [cite: 1928]
     return; } $raw_phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : ''; $otp_code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : ''; // [cite: 1929]
     if (empty($raw_phone) || empty($otp_code)) { wp_send_json_error(['message' => 'Telefone e código são obrigatórios.'], 400); return; } $phone_number = $this->normalize_phone($raw_phone); // [cite: 1930]
     $stored_otp_code = get_transient('ds_otp_' . $phone_number); if ($stored_otp_code === false) { wp_send_json_error(['message' => 'Código de verificação expirado ou inválido.']); return; // [cite: 1931]
     } if ((string) $stored_otp_code === (string) $otp_code) { delete_transient('ds_otp_' . $phone_number); wp_send_json_success(['message' => 'Código verificado com sucesso!']); // [cite: 1932]
     } else { wp_send_json_error(['message' => 'O código de verificação está incorreto.']); // [cite: 1933]
     } }
    public function enqueue_registration_form_scripts(array $form, bool $is_ajax): void { $reg_form_id = $this->settings['reg_form_id'] ?? 0; // [cite: 1934]
     if ($form['id'] != $reg_form_id) { return; } wp_enqueue_script( 'ds-registro-otp-js', DS_REGISTRO_AVANCADO_URL . 'assets/js/ds-registro-otp.js', ['jquery', 'gform_gravityforms'], '4.2.0', true ); // [cite: 1935]
     wp_localize_script('ds-registro-otp-js', 'ds_otp_vars', [ 'ajax_url'    => admin_url('admin-ajax.php'), 'nonce'       => wp_create_nonce(self::OTP_NONCE), 'form_id'     => $reg_form_id, 'field_ids' => ['phone' => $this->settings['reg_phone_field'] ?? 0, 'code' => $this->settings['reg_otp_field'] ?? 0], 'css_classes' => ['phone' => self::PHONE_CSS, 'code' => self::CODE_CSS, 'send_button' => 'ds-otp-send-button', 'verify_button' => 'ds-otp-verify-button', 'status'  => 'ds-otp-status-div'], 'i18n'        => ['sending' => 'Enviando...', 'sent' => 'Reenviar Código', 'verifying' => 'Verificando...', 'verified' => 'Verificado', 'error' => 'Erro.', 'invalidPhone' => 'Telefone inválido.', 'wait' => 'Aguarde...'], ]); // [cite: 1936]
     }
    public function ajax_send_otp(): void { if (!check_ajax_referer(self::OTP_NONCE, 'security', false)) { wp_send_json_error(['message' => 'Falha na verificação de segurança.'], 403); // [cite: 1937]
     return; } $raw_phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : ''; if (empty($raw_phone)) { wp_send_json_error(['message' => 'Número de telefone é obrigatório.'], 400); // [cite: 1938]
     return; } $phone_number = $this->normalize_phone($raw_phone); if (strlen($phone_number) < 12) { wp_send_json_error(['message' => 'Número de telefone inválido.'], 400); return; // [cite: 1939]
     } $ip_address = $this->get_ip_address(); if ($this->is_rate_limited($ip_address)) { wp_send_json_error(['message' => 'Você fez muitas tentativas. Por favor, aguarde alguns minutos.'], 429); return; // [cite: 1940]
     } $otp_code = wp_rand(100000, 999999); $message  = "Seu código de verificação é: {$otp_code}"; // [cite: 1941]
     set_transient('ds_otp_' . $phone_number, $otp_code, 5 * MINUTE_IN_SECONDS); $this->log_rate_limit_attempt($ip_address); $result = $this->send_whatsapp_message($phone_number, $message); // [cite: 1942]
     if (is_wp_error($result)) { wp_send_json_error(['message' => 'Falha ao enviar o código. Tente novamente mais tarde.'], 500); // [cite: 1943]
     } else { wp_send_json_success(['message' => 'Código enviado para seu WhatsApp!']); // [cite: 1944]
     } }
    
    /**
     * Atualiza perfil usando mapeamento de campos configurado.
     */
    public function update_user_profile_after_submission(array $entry, array $form): void {
        $user_id = get_current_user_id();
        if ($user_id === 0) return;

        // Mapear campos usando configuração
        $field_mappings = [
            'user_id_field' => self::CUSTOM_USER_ID,
            'country_field' => self::WC_BILLING_COUNTRY,
            'state_field' => self::WC_BILLING_STATE,
            'city_field' => self::WC_BILLING_CITY,
            'postcode_field' => self::WC_BILLING_POSTCODE,
            'address_field' => self::WC_BILLING_ADDRESS_1,
            'pix_field' => self::CUSTOM_PIX_KEY,
            'wise_field' => self::CUSTOM_WISE_EMAIL
        ];

        foreach ($field_mappings as $config_key => $meta_key) {
            $field_id = $this->settings[$config_key] ?? 0;
            if ($field_id > 0) {
                $value = rgar($entry, $field_id);
                if (!empty($value)) {
                    update_user_meta($user_id, $meta_key, sanitize_text_field($value));
                }
            }
        }
    }

    /**
     * Força conclusão do perfil verificando campos do WooCommerce.
     */
    public function force_profile_completion(): void {
        $page_slug = 'complete-seu-perfil';
        if (!is_user_logged_in() || is_admin() || is_page($page_slug) || current_user_can('manage_options')) return;

        $user_id = get_current_user_id();
        $user_platform_id = get_user_meta($user_id, self::CUSTOM_USER_ID, true);
        $country = get_user_meta($user_id, self::WC_BILLING_COUNTRY, true);
        $pix = get_user_meta($user_id, self::CUSTOM_PIX_KEY, true);
        $wise = get_user_meta($user_id, self::CUSTOM_WISE_EMAIL, true);
        
        // Verificar se campos obrigatórios estão preenchidos
        if (empty($user_platform_id) || empty($country) || (empty($pix) && empty($wise))) {
            wp_safe_redirect(home_url('/' . $page_slug . '/'));
            exit;
        }
    }

    private function send_whatsapp_message(string $number, string $message) { $api_url = get_option('conector_whatsapp_url'); // [cite: 1968]
     $api_key = get_option('conector_whatsapp_apikey'); $instance = get_option('conector_whatsapp_instance'); if (empty($api_url) || empty($api_key) || empty($instance)) return new WP_Error('conector_not_configured', 'Configurações da API não encontradas.'); // [cite: 1969]
     $full_url = rtrim($api_url, '/') . '/message/sendText/' . $instance; $response = wp_remote_post($full_url, ['timeout' => 30, 'headers' => ['Content-Type' => 'application/json', 'apikey' => $api_key], 'body' => wp_json_encode(['number' => $number, 'text' => $message]),]); // [cite: 1970]
     if (is_wp_error($response)) return $response; $code = wp_remote_retrieve_response_code($response); if ($code === 200 || $code === 201) return true; // [cite: 1971]
     $error = json_decode(wp_remote_retrieve_body($response), true); return new WP_Error('api_error', "Erro na API ($code): " . ($error['message'] ?? 'Erro desconhecido.')); // [cite: 1972]
     }
    private function normalize_phone(string $raw_phone): string { $digits_only = preg_replace('/[^0-9]/', '', $raw_phone); // [cite: 1973]
     if (strlen($digits_only) > 11 && str_starts_with($digits_only, '55')) { return $digits_only; } if (strlen($digits_only) <= 11) { return '55' . $digits_only; // [cite: 1974]
     } return $digits_only; }
    private function is_rate_limited(string $ip): bool { return (get_transient('ds_otp_limit_' . $ip) ?: 0) >= 100; // [cite: 1975]
     }
    private function log_rate_limit_attempt(string $ip): void { $key = 'ds_otp_limit_' . $ip; // [cite: 1976]
     set_transient($key, (get_transient($key) ?: 0) + 1, 5 * MINUTE_IN_SECONDS); } // [cite: 1977]
    private function get_ip_address(): string { foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) { if (!empty($_SERVER[$key])) { foreach (explode(',', $_SERVER[$key]) as $ip) { if (filter_var(trim($ip), FILTER_VALIDATE_IP)) return trim($ip); // [cite: 1977]
     } } } return 'unknown'; }
    private function get_field_map(array $fields): array {
        $map = [];
        
        // Usar mapeamento configurado
        if (!empty($this->settings['reg_email_field'])) {
            $map['email'] = $this->settings['reg_email_field'];
        }
        if (!empty($this->settings['reg_password_field'])) {
            $map['password'] = $this->settings['reg_password_field'];
        }
        if (!empty($this->settings['reg_phone_field'])) {
            $map['phone'] = $this->settings['reg_phone_field'];
        }
        
        // Para campo de nome, verificar se tem inputs (first/last)
        if (!empty($this->settings['reg_name_field'])) {
            foreach ($fields as $field) {
                if ($field->id == $this->settings['reg_name_field']) {
                    if (is_array($field->inputs) && !empty($field->inputs)) {
                        // Campo Name com múltiplos inputs
                        foreach ($field->inputs as $input) {
                            if (strpos($input['id'], '.3') !== false) {
                                $map['name.first'] = $input['id'];
                            }
                            if (strpos($input['id'], '.6') !== false) {
                                $map['name.last'] = $input['id'];
                            }
                        }
                    } else {
                        // Campo de texto simples
                        $map['name'] = $field->id;
                    }
                    break;
                }
            }
        }
        
        return $map;
    }

    /**
     * AJAX para exportar formulários como JSON.
     */
    public function ajax_export_forms(): void {
        if (!check_ajax_referer('ds_export_forms', 'nonce', false)) {
            wp_send_json_error(['message' => 'Falha na verificação de segurança.']);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada.']);
            return;
        }

        if (!class_exists('GFAPI')) {
            wp_send_json_error(['message' => 'Gravity Forms não encontrado.']);
            return;
        }

        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/ds-forms-export/';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $reg_form_id = $this->settings['reg_form_id'] ?? 0;
        $profile_form_id = $this->settings['profile_form_id'] ?? 0;
        
        $files = [];

        // Exportar formulário de registro
        if ($reg_form_id > 0) {
            $reg_form = $this->generate_registration_form_json();
            $reg_file = $export_dir . 'ds-registro-form.json';
            file_put_contents($reg_file, wp_json_encode($reg_form, JSON_PRETTY_PRINT));
            $files['registro_url'] = $upload_dir['baseurl'] . '/ds-forms-export/ds-registro-form.json';
        }

        // Exportar formulário de perfil
        if ($profile_form_id > 0) {
            $profile_form = $this->generate_profile_form_json();
            $profile_file = $export_dir . 'ds-perfil-form.json';
            file_put_contents($profile_file, wp_json_encode($profile_form, JSON_PRETTY_PRINT));
            $files['perfil_url'] = $upload_dir['baseurl'] . '/ds-forms-export/ds-perfil-form.json';
        }

        wp_send_json_success($files);
    }

    /**
     * Gera JSON do formulário de registro baseado no formato real do Gravity Forms.
     */
    private function generate_registration_form_json(): array {
        return [
            '0' => [
                'title' => 'Registro de Usuário',
                'description' => 'Formulário de registro com verificação OTP',
                'labelPlacement' => 'top_label',
                'descriptionPlacement' => 'above',
                'button' => [
                    'type' => 'text',
                    'text' => 'Enviar',
                    'imageUrl' => '',
                    'conditionalLogic' => null,
                    'width' => 'auto',
                    'location' => 'bottom',
                    'layoutGridColumnSpan' => 12,
                    'id' => 'submit'
                ],
                'fields' => [
                    [
                        'type' => 'username',
                        'id' => 3,
                        'formId' => 1,
                        'label' => 'Username',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'medium',
                        'errorMessage' => '',
                        'inputs' => null,
                        'description' => '',
                        'allowsPrepopulate' => true,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '{user:user_email}',
                        'visibility' => 'hidden',
                        'noDuplicates' => false,
                        'defaultValue' => '{user:user_email}',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'enablePasswordInput' => '',
                        'maxLength' => '',
                        'displayOnly' => '',
                        'fields' => '',
                        'inputMaskIsCustom' => false,
                        'layoutGroupId' => '11f2d63c',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'layoutGridColumnSpan' => 12,
                        'personalDataExport' => true,
                        'personalDataErase' => true
                    ],
                    [
                        'type' => 'name',
                        'id' => 1,
                        'formId' => 1,
                        'label' => 'Nome Completo',
                        'adminLabel' => '',
                        'isRequired' => true,
                        'size' => 'medium',
                        'errorMessage' => '',
                        'nameFormat' => 'advanced',
                        'inputs' => [
                            [
                                'id' => '1.3',
                                'label' => 'Primeiro Nome',
                                'name' => '',
                                'autocompleteAttribute' => 'given-name'
                            ],
                            [
                                'id' => '1.6',
                                'label' => 'Último Nome',
                                'name' => '',
                                'autocompleteAttribute' => 'family-name'
                            ]
                        ],
                        'description' => '',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'visibility' => 'visible',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'displayOnly' => '',
                        'fields' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'layoutGroupId' => '3540de4e',
                        'enableAutocomplete' => true,
                        'autocompleteAttribute' => '',
                        'personalDataExport' => true,
                        'personalDataErase' => true,
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false
                    ],
                    [
                        'type' => 'email',
                        'id' => 2,
                        'formId' => 1,
                        'label' => 'E-mail',
                        'adminLabel' => '',
                        'isRequired' => true,
                        'size' => 'medium',
                        'errorMessage' => '',
                        'inputs' => [
                            [
                                'id' => '2',
                                'label' => 'Digite o E-mail',
                                'name' => '',
                                'autocompleteAttribute' => 'email'
                            ],
                            [
                                'id' => '2.2',
                                'label' => 'Confirme o E-mail',
                                'name' => '',
                                'autocompleteAttribute' => 'email'
                            ]
                        ],
                        'description' => '',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'visibility' => 'visible',
                        'noDuplicates' => true,
                        'defaultValue' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'emailConfirmEnabled' => true,
                        'displayOnly' => '',
                        'fields' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'layoutGroupId' => '530ff557',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'autocompleteAttribute' => 'email',
                        'useRichTextEditor' => false,
                        'enableAutocomplete' => true,
                        'personalDataExport' => true,
                        'personalDataErase' => true,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false
                    ],
                    [
                        'type' => 'page',
                        'id' => 8,
                        'formId' => 1,
                        'label' => '',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'displayOnly' => true,
                        'nextButton' => [
                            'type' => 'text',
                            'text' => 'Próximo',
                            'imageUrl' => '',
                            'id' => 8
                        ],
                        'previousButton' => [
                            'type' => 'text',
                            'text' => 'Anterior',
                            'imageUrl' => ''
                        ],
                        'description' => '',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => '4140891c',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'fields' => '',
                        'personalDataExport' => false,
                        'personalDataErase' => false
                    ],
                    [
                        'type' => 'phone',
                        'id' => 5,
                        'formId' => 1,
                        'label' => 'Telefone',
                        'adminLabel' => '',
                        'isRequired' => true,
                        'size' => 'large',
                        'errorMessage' => 'Este não é um número válido',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'phoneFormat' => 'international',
                        'autocompleteAttribute' => 'tel',
                        'description' => '',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => true,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 6,
                        'enableDisplayInColumns' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => '7eb49f3a',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'fields' => '',
                        'displayOnly' => '',
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'personalDataExport' => true,
                        'personalDataErase' => true
                    ],
                    [
                        'type' => 'html',
                        'id' => 7,
                        'formId' => 1,
                        'label' => '',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'displayOnly' => true,
                        'description' => '',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'content' => '<button type="button" class="gform_button button ds-otp-send-button">Enviar Código</button><div class="ds-otp-status-div"></div>',
                        'disableMargins' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 6,
                        'enableDisplayInColumns' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => '7eb49f3a',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'errors' => [],
                        'fields' => '',
                        'personalDataExport' => false,
                        'personalDataErase' => false
                    ],
                    [
                        'type' => 'text',
                        'id' => 10,
                        'formId' => 1,
                        'label' => 'Código de Verificação',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'description' => 'Código recebido no WhatsApp',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 6,
                        'enableDisplayInColumns' => '',
                        'enablePasswordInput' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => '6e94971a',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'errors' => [],
                        'fields' => '',
                        'displayOnly' => '',
                        'personalDataExport' => true,
                        'personalDataErase' => true
                    ],
                    [
                        'type' => 'html',
                        'id' => 11,
                        'formId' => 1,
                        'label' => '',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'displayOnly' => true,
                        'description' => '',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'content' => '<button type="button" class="gform_button button ds-otp-verify-button">Verificar Código</button>',
                        'disableMargins' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 6,
                        'enableDisplayInColumns' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => '6e94971a',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'errors' => [],
                        'fields' => '',
                        'personalDataExport' => false,
                        'personalDataErase' => false
                    ],
                    [
                        'type' => 'page',
                        'id' => 9,
                        'formId' => 1,
                        'label' => '',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'displayOnly' => true,
                        'nextButton' => [
                            'type' => 'text',
                            'text' => 'Finalizar',
                            'imageUrl' => '',
                            'id' => 9,
                            'conditionalLogic' => ''
                        ],
                        'previousButton' => [
                            'type' => 'text',
                            'text' => 'Anterior',
                            'imageUrl' => ''
                        ],
                        'description' => '',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => '48705c2d',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'fields' => '',
                        'checkboxLabel' => '',
                        'personalDataExport' => false,
                        'personalDataErase' => false
                    ],
                    [
                        'type' => 'password',
                        'id' => 4,
                        'formId' => 1,
                        'label' => 'Senha',
                        'adminLabel' => '',
                        'isRequired' => true,
                        'size' => 'medium',
                        'errorMessage' => '',
                        'inputs' => [
                            [
                                'id' => '4',
                                'label' => 'Digite a Senha',
                                'name' => ''
                            ],
                            [
                                'id' => '4.2',
                                'label' => 'Confirme a Senha',
                                'name' => ''
                            ]
                        ],
                        'displayOnly' => true,
                        'description' => '',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'visibility' => 'visible',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'passwordStrengthEnabled' => '',
                        'fields' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'layoutGroupId' => 'd1af5582',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'layoutGridColumnSpan' => 12,
                        'personalDataExport' => false,
                        'personalDataErase' => false
                    ]
                ],
                'version' => '2.9.17.1',
                'useCurrentUserAsAuthor' => true,
                'postContentTemplateEnabled' => false,
                'postTitleTemplateEnabled' => false,
                'postTitleTemplate' => '',
                'postContentTemplate' => '',
                'lastPageButton' => [
                    'type' => 'text',
                    'text' => 'Anterior',
                    'imageUrl' => ''
                ],
                'pagination' => [
                    'type' => 'percentage',
                    'pages' => ['', '', ''],
                    'style' => 'blue',
                    'backgroundColor' => null,
                    'color' => null,
                    'display_progressbar_on_confirmation' => false,
                    'progressbar_completion_text' => null
                ],
                'firstPageCssClass' => '',
                'nextFieldId' => 14,
                'subLabelPlacement' => 'above',
                'cssClass' => '',
                'enableHoneypot' => false,
                'honeypotAction' => 'spam',
                'enableAnimation' => false,
                'save' => [
                    'enabled' => false,
                    'button' => [
                        'type' => 'link',
                        'text' => 'Save and Continue Later'
                    ]
                ],
                'limitEntries' => false,
                'limitEntriesCount' => '',
                'limitEntriesPeriod' => '',
                'limitEntriesMessage' => '',
                'scheduleForm' => false,
                'scheduleStart' => '',
                'scheduleStartHour' => '',
                'scheduleStartMinute' => '',
                'scheduleStartAmpm' => '',
                'scheduleEnd' => '',
                'scheduleEndHour' => '',
                'scheduleEndMinute' => '',
                'scheduleEndAmpm' => '',
                'schedulePendingMessage' => '',
                'scheduleMessage' => '',
                'requireLogin' => false,
                'requireLoginMessage' => '',
                'markupVersion' => 2,
                'validationSummary' => '1',
                'requiredIndicator' => 'text',
                'customRequiredIndicator' => '',
                'template_id' => 'user_registration',
                'id' => 1,
                'validationPlacement' => 'below',
                'saveButtonText' => 'Save and Continue Later',
                'deprecated' => '',
                'saveEnabled' => '',
                'gfaa' => [
                    'gfaa_type' => 'basic',
                    'gfaa_upgrade_to_pro' => ''
                ],
                'is_active' => '1',
                'date_created' => date('Y-m-d H:i:s'),
                'is_trash' => '0',
                'personalData' => [
                    'preventIP' => false,
                    'retention' => [
                        'policy' => 'trash',
                        'retain_entries_days' => '1'
                    ],
                    'exportingAndErasing' => [
                        'enabled' => true,
                        'identificationField' => '2',
                        'columns' => [
                            'ip' => [
                                'export' => true,
                                'erase' => true
                            ],
                            'source_url' => [
                                'export' => true,
                                'erase' => true
                            ],
                            'user_agent' => [
                                'export' => true,
                                'erase' => true
                            ]
                        ]
                    ]
                ],
                'confirmations' => [
                    [
                        'id' => '59de6bab448e6',
                        'name' => 'Default Confirmation',
                        'isDefault' => true,
                        'type' => 'redirect',
                        'message' => 'Obrigado pelo registro!',
                        'url' => home_url('/complete-seu-perfil/'),
                        'pageId' => '0',
                        'queryString' => '',
                        'disableAutoformat' => false,
                        'conditionalLogic' => [],
                        'event' => '',
                        'page' => '0'
                    ]
                ],
                'notifications' => [
                    [
                        'id' => '59de6bab43fe8',
                        'to' => '{admin_email}',
                        'name' => 'Admin Notification',
                        'event' => 'form_submission',
                        'toType' => 'email',
                        'subject' => 'Novo registro de usuário',
                        'message' => '{all_fields}'
                    ]
                ]
            ],
            'version' => '2.9.17.1'
        ];
    }

    /**
     * Gera JSON do formulário de perfil baseado no formato real do Gravity Forms.
     */
    private function generate_profile_form_json(): array {
        return [
            '0' => [
                'title' => 'Completar Perfil',
                'description' => 'Complete seu perfil para acessar a plataforma',
                'labelPlacement' => 'top_label',
                'button' => [
                    'type' => 'text',
                    'text' => 'Salvar e Continuar',
                    'imageUrl' => '',
                    'width' => 'auto',
                    'location' => 'bottom',
                    'layoutGridColumnSpan' => 12,
                    'id' => 'submit'
                ],
                'fields' => [
                    [
                        'type' => 'text',
                        'id' => 1,
                        'formId' => 3,
                        'label' => 'ID do Usuário na Plataforma',
                        'adminLabel' => '',
                        'isRequired' => true,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'description' => 'Seu identificador único na plataforma',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => '',
                        'enableDisplayInColumns' => '',
                        'enablePasswordInput' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => '2e0edcca',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'hideNextButton' => false,
                        'hidePreviousButton' => false,
                        'errors' => [],
                        'fields' => '',
                        'displayOnly' => ''
                    ],
                    [
                        'type' => 'select',
                        'id' => 2,
                        'formId' => 3,
                        'label' => 'País',
                        'adminLabel' => '',
                        'isRequired' => true,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'validateState' => true,
                        'inputs' => null,
                        'choices' => [],
                        'description' => 'Será populado automaticamente com países do WooCommerce',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'conditionalLogic' => '',
                        'enableEnhancedUI' => false,
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'enablePrice' => '',
                        'layoutGroupId' => '5c6d32ac',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'errors' => [],
                        'fields' => '',
                        'displayOnly' => ''
                    ],
                    [
                        'type' => 'text',
                        'id' => 3,
                        'formId' => 3,
                        'label' => 'Estado/Região',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'description' => 'Seu estado ou região',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'enablePasswordInput' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => 'b6ff02d3',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'errors' => [],
                        'fields' => ''
                    ],
                    [
                        'type' => 'text',
                        'id' => 4,
                        'formId' => 3,
                        'label' => 'Cidade',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'description' => 'Sua cidade',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'enablePasswordInput' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => 'c0890abb',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'errors' => [],
                        'fields' => ''
                    ],
                    [
                        'type' => 'text',
                        'id' => 5,
                        'formId' => 3,
                        'label' => 'CEP',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'description' => 'Código postal',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'enablePasswordInput' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => 'a1b2c3d4',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'errors' => [],
                        'fields' => ''
                    ],
                    [
                        'type' => 'text',
                        'id' => 6,
                        'formId' => 3,
                        'label' => 'Endereço',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'description' => 'Endereço completo',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'enablePasswordInput' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => 'e1f2g3h4',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'errors' => [],
                        'fields' => ''
                    ],
                    [
                        'type' => 'text',
                        'id' => 7,
                        'formId' => 3,
                        'label' => 'Chave PIX',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'description' => 'Sua chave PIX para recebimentos (obrigatório PIX ou Wise)',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'autocompleteAttribute' => '',
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'enablePasswordInput' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => 'f1g2h3i4',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'errors' => [],
                        'fields' => ''
                    ],
                    [
                        'type' => 'email',
                        'id' => 8,
                        'formId' => 3,
                        'label' => 'E-mail Wise',
                        'adminLabel' => '',
                        'isRequired' => false,
                        'size' => 'large',
                        'errorMessage' => '',
                        'visibility' => 'visible',
                        'inputs' => null,
                        'autocompleteAttribute' => 'email',
                        'description' => 'Seu e-mail do Wise para transferências (obrigatório PIX ou Wise)',
                        'allowsPrepopulate' => false,
                        'inputMask' => false,
                        'inputMaskValue' => '',
                        'inputMaskIsCustom' => false,
                        'maxLength' => '',
                        'labelPlacement' => '',
                        'descriptionPlacement' => '',
                        'subLabelPlacement' => '',
                        'placeholder' => '',
                        'cssClass' => '',
                        'inputName' => '',
                        'noDuplicates' => false,
                        'defaultValue' => '',
                        'enableAutocomplete' => false,
                        'choices' => '',
                        'conditionalLogic' => '',
                        'productField' => '',
                        'layoutGridColumnSpan' => 12,
                        'enableDisplayInColumns' => '',
                        'emailConfirmEnabled' => '',
                        'enableEnhancedUI' => 0,
                        'layoutGroupId' => 'g1h2i3j4',
                        'multipleFiles' => false,
                        'maxFiles' => '',
                        'calculationFormula' => '',
                        'calculationRounding' => '',
                        'enableCalculation' => '',
                        'disableQuantity' => false,
                        'displayAllCategories' => false,
                        'useRichTextEditor' => false,
                        'errors' => [],
                        'fields' => ''
                    ]
                ],
                'version' => '2.9.17.1',
                'id' => 3,
                'markupVersion' => 2,
                'nextFieldId' => 9,
                'useCurrentUserAsAuthor' => true,
                'postContentTemplateEnabled' => false,
                'postTitleTemplateEnabled' => false,
                'postTitleTemplate' => '',
                'postContentTemplate' => '',
                'lastPageButton' => null,
                'pagination' => null,
                'firstPageCssClass' => null,
                'confirmations' => [
                    [
                        'id' => '68b0499568714',
                        'name' => 'Confirmação padrão',
                        'isDefault' => true,
                        'type' => 'redirect',
                        'message' => 'Perfil atualizado com sucesso!',
                        'url' => home_url('/minha-conta/'),
                        'pageId' => '',
                        'queryString' => '',
                        'event' => '',
                        'disableAutoformat' => false,
                        'page' => '',
                        'conditionalLogic' => []
                    ]
                ],
                'notifications' => [
                    [
                        'id' => '68b04995685bf',
                        'isActive' => true,
                        'to' => '{admin_email}',
                        'name' => 'Notificação da administração',
                        'event' => 'form_submission',
                        'toType' => 'email',
                        'subject' => 'Perfil atualizado - {form_title}',
                        'message' => '{all_fields}'
                    ]
                ]
            ],
            'version' => '2.9.17.1'
        ];
    }
}