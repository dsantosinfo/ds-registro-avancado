<?php
/**
 * Classe responsável pela página de configurações do plugin no painel de administração.
 *
 * @package DS_Registro_Avancado
 */

if (!defined('ABSPATH')) {
    exit; // Acesso direto negado.
}

/**
 * Class DS_Registro_Avancado_Admin_Settings
 */
final class DS_Registro_Avancado_Admin_Settings {

    /**
     * Instância da classe (Singleton).
     * @var DS_Registro_Avancado_Admin_Settings|null
     */
    private static ?DS_Registro_Avancado_Admin_Settings $instance = null;

    /**
     * ID da página de opções.
     * @var string
     */
    private string $options_slug = 'ds-registro-avancado';

    /**
     * Nome da option no banco de dados.
     * @var string
     */
    public const OPTION_NAME = 'ds_registro_avancado_settings';

    /**
     * Garante que apenas uma instância da classe seja criada.
     *
     * @return DS_Registro_Avancado_Admin_Settings
     */
    public static function get_instance(): DS_Registro_Avancado_Admin_Settings {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado para registrar os hooks.
     */
    private function __construct() {
        add_action('admin_menu', [$this, 'add_options_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Adiciona a página de opções ao menu de Configurações do WordPress.
     */
    public function add_options_page(): void {
        add_options_page(
            'DS Registro Avançado',
            'Registro Avançado',
            'manage_options',
            $this->options_slug,
            [$this, 'render_options_page']
        );
    }

    /**
     * Renderiza o HTML da página de opções.
     */
    public function render_options_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->options_slug . '_group');
                do_settings_sections($this->options_slug);
                submit_button('Salvar Configurações');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registra as seções e campos da página de configurações.
     */
    public function register_settings(): void {
        register_setting(
            $this->options_slug . '_group',
            self::OPTION_NAME,
            [$this, 'sanitize_options']
        );

        add_settings_section(
            'forms_section',
            'Configuração dos Formulários',
            null,
            $this->options_slug
        );

        add_settings_field(
            'reg_form_id',
            'Formulário de Registro',
            [$this, 'render_forms_dropdown'],
            $this->options_slug,
            'forms_section',
            ['field' => 'reg_form_id']
        );

        add_settings_field(
            'profile_form_id',
            'Formulário de Conclusão de Perfil',
            [$this, 'render_forms_dropdown'],
            $this->options_slug,
            'forms_section',
            ['field' => 'profile_form_id']
        );

        add_settings_section(
            'reg_mapping_section',
            'Mapeamento de Campos do Registro',
            [$this, 'render_reg_mapping_section_description'],
            $this->options_slug
        );

        add_settings_field(
            'reg_email_field',
            'Campo E-mail',
            [$this, 'render_reg_field_dropdown'],
            $this->options_slug,
            'reg_mapping_section',
            ['field' => 'reg_email_field']
        );

        add_settings_field(
            'reg_password_field',
            'Campo Senha',
            [$this, 'render_reg_field_dropdown'],
            $this->options_slug,
            'reg_mapping_section',
            ['field' => 'reg_password_field']
        );

        add_settings_field(
            'reg_name_field',
            'Campo Nome',
            [$this, 'render_reg_field_dropdown'],
            $this->options_slug,
            'reg_mapping_section',
            ['field' => 'reg_name_field']
        );

        add_settings_field(
            'reg_phone_field',
            'Campo Telefone',
            [$this, 'render_reg_field_dropdown'],
            $this->options_slug,
            'reg_mapping_section',
            ['field' => 'reg_phone_field']
        );

        add_settings_field(
            'reg_otp_field',
            'Campo Código OTP',
            [$this, 'render_reg_field_dropdown'],
            $this->options_slug,
            'reg_mapping_section',
            ['field' => 'reg_otp_field']
        );

        add_settings_field(
            'reg_username_field',
            'Campo Username',
            [$this, 'render_reg_field_dropdown'],
            $this->options_slug,
            'reg_mapping_section',
            ['field' => 'reg_username_field']
        );

        add_settings_section(
            'field_mapping_section',
            'Mapeamento de Campos do Perfil',
            [$this, 'render_mapping_section_description'],
            $this->options_slug
        );

        add_settings_field(
            'user_id_field',
            'Campo ID do Usuário',
            [$this, 'render_field_dropdown'],
            $this->options_slug,
            'field_mapping_section',
            ['field' => 'user_id_field']
        );

        add_settings_field(
            'country_field',
            'Campo País',
            [$this, 'render_field_dropdown'],
            $this->options_slug,
            'field_mapping_section',
            ['field' => 'country_field']
        );

        add_settings_field(
            'state_field',
            'Campo Estado',
            [$this, 'render_field_dropdown'],
            $this->options_slug,
            'field_mapping_section',
            ['field' => 'state_field']
        );

        add_settings_field(
            'city_field',
            'Campo Cidade',
            [$this, 'render_field_dropdown'],
            $this->options_slug,
            'field_mapping_section',
            ['field' => 'city_field']
        );

        add_settings_field(
            'postcode_field',
            'Campo CEP',
            [$this, 'render_field_dropdown'],
            $this->options_slug,
            'field_mapping_section',
            ['field' => 'postcode_field']
        );

        add_settings_field(
            'address_field',
            'Campo Endereço',
            [$this, 'render_field_dropdown'],
            $this->options_slug,
            'field_mapping_section',
            ['field' => 'address_field']
        );

        add_settings_field(
            'pix_field',
            'Campo PIX',
            [$this, 'render_field_dropdown'],
            $this->options_slug,
            'field_mapping_section',
            ['field' => 'pix_field']
        );

        add_settings_field(
            'wise_field',
            'Campo Wise',
            [$this, 'render_field_dropdown'],
            $this->options_slug,
            'field_mapping_section',
            ['field' => 'wise_field']
        );

        add_settings_section(
            'export_section',
            'Exportar Formulários',
            [$this, 'render_export_section_description'],
            $this->options_slug
        );

        add_settings_field(
            'export_forms',
            'Gerar JSON dos Formulários',
            [$this, 'render_export_button'],
            $this->options_slug,
            'export_section'
        );
    }

    /**
     * Renderiza o campo de seleção de formulários (dropdown).
     *
     * @param array $args Argumentos passados para a função de callback.
     */
    public function render_forms_dropdown(array $args): void {
        $options = get_option(self::OPTION_NAME, []);
        $field_name = $args['field'];
        $current_value = $options[$field_name] ?? '';

        // Verifica se o Gravity Forms está ativo
        if (!class_exists('GFAPI')) {
            echo '<input type="number" name="' . esc_attr(self::OPTION_NAME . '[' . $field_name . ']') . '" value="' . esc_attr($current_value) . '" class="regular-text" />';
            echo '<p class="description">Plugin Gravity Forms não encontrado. Por favor, insira o ID do formulário manualmente.</p>';
            return;
        }

        $forms = GFAPI::get_forms();
        ?>
        <select name="<?php echo esc_attr(self::OPTION_NAME . '[' . $field_name . ']'); ?>">
            <option value="">— Selecione um formulário —</option>
            <?php foreach ($forms as $form) : ?>
                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($current_value, $form['id']); ?>>
                    <?php echo esc_html($form['title']); ?> (ID: <?php echo esc_attr($form['id']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Selecione o formulário que será usado para esta finalidade.</p>
        <?php
    }

    /**
     * Renderiza descrição da seção de mapeamento do registro.
     */
    public function render_reg_mapping_section_description(): void {
        echo '<p>Selecione os campos do formulário de registro que correspondem a cada informação.</p>';
    }

    /**
     * Renderiza descrição da seção de mapeamento.
     */
    public function render_mapping_section_description(): void {
        echo '<p>Selecione os campos do formulário de perfil que correspondem a cada informação.</p>';
    }

    /**
     * Renderiza dropdown de campos do formulário.
     */
    public function render_field_dropdown(array $args): void {
        $options = get_option(self::OPTION_NAME, []);
        $field_name = $args['field'];
        $current_value = $options[$field_name] ?? '';
        $profile_form_id = $options['profile_form_id'] ?? 0;

        if (!class_exists('GFAPI') || !$profile_form_id) {
            echo '<select disabled><option>Selecione primeiro o formulário de perfil</option></select>';
            return;
        }

        $form = GFAPI::get_form($profile_form_id);
        if (!$form) {
            echo '<select disabled><option>Formulário não encontrado</option></select>';
            return;
        }

        ?>
        <select name="<?php echo esc_attr(self::OPTION_NAME . '[' . $field_name . ']'); ?>">
            <option value="">— Selecione um campo —</option>
            <?php foreach ($form['fields'] as $field) : ?>
                <option value="<?php echo esc_attr($field->id); ?>" <?php selected($current_value, $field->id); ?>>
                    <?php echo esc_html($field->label); ?> (ID: <?php echo esc_attr($field->id); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Renderiza dropdown de campos do formulário de registro.
     */
    public function render_reg_field_dropdown(array $args): void {
        $options = get_option(self::OPTION_NAME, []);
        $field_name = $args['field'];
        $current_value = $options[$field_name] ?? '';
        $reg_form_id = $options['reg_form_id'] ?? 0;

        if (!class_exists('GFAPI') || !$reg_form_id) {
            echo '<select disabled><option>Selecione primeiro o formulário de registro</option></select>';
            return;
        }

        $form = GFAPI::get_form($reg_form_id);
        if (!$form) {
            echo '<select disabled><option>Formulário não encontrado</option></select>';
            return;
        }

        ?>
        <select name="<?php echo esc_attr(self::OPTION_NAME . '[' . $field_name . ']'); ?>">
            <option value="">— Selecione um campo —</option>
            <?php foreach ($form['fields'] as $field) : ?>
                <option value="<?php echo esc_attr($field->id); ?>" <?php selected($current_value, $field->id); ?>>
                    <?php echo esc_html($field->label); ?> (ID: <?php echo esc_attr($field->id); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Sanitiza os valores das opções antes de salvar no banco.
     *
     * @param array|null $input Os dados brutos do formulário.
     * @return array Os dados sanitizados.
     */
    public function sanitize_options(?array $input): array {
        $sanitized_input = [];
        if (empty($input)) {
            return $sanitized_input;
        }

        $fields = ['reg_form_id', 'profile_form_id', 'user_id_field', 'country_field', 
                  'state_field', 'city_field', 'postcode_field', 'address_field', 
                  'pix_field', 'wise_field', 'reg_email_field', 'reg_password_field',
                  'reg_name_field', 'reg_phone_field', 'reg_otp_field', 'reg_username_field'];

        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $sanitized_input[$field] = absint($input[$field]);
            }
        }

        return $sanitized_input;
    }

    /**
     * Renderiza descrição da seção de exportação.
     */
    public function render_export_section_description(): void {
        echo '<p>Gere arquivos JSON dos formulários configurados para importação no Gravity Forms.</p>';
    }

    /**
     * Renderiza botão de exportação.
     */
    public function render_export_button(): void {
        $options = get_option(self::OPTION_NAME, []);
        $reg_form_id = $options['reg_form_id'] ?? 0;
        $profile_form_id = $options['profile_form_id'] ?? 0;
        
        if (!class_exists('GFAPI') || (!$reg_form_id && !$profile_form_id)) {
            echo '<p>Configure os formulários primeiro.</p>';
            return;
        }
        
        echo '<button type="button" class="button" onclick="exportForms()">Gerar JSON dos Formulários</button>';
        echo '<div id="export-result" style="margin-top: 10px;"></div>';
        
        ?>
        <script>
        function exportForms() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=ds_export_forms&nonce=<?php echo wp_create_nonce('ds_export_forms'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('export-result').innerHTML = 
                        '<p style="color: green;">Arquivos gerados com sucesso!</p>' +
                        '<p><strong>Registro:</strong> <a href="' + data.data.registro_url + '" download>ds-registro-form.json</a></p>' +
                        '<p><strong>Perfil:</strong> <a href="' + data.data.perfil_url + '" download>ds-perfil-form.json</a></p>';
                } else {
                    document.getElementById('export-result').innerHTML = 
                        '<p style="color: red;">Erro: ' + data.data.message + '</p>';
                }
            });
        }
        </script>
        <?php
    }
}

// -- Inicializa a classe de Configurações --
DS_Registro_Avancado_Admin_Settings::get_instance();