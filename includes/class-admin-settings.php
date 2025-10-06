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

        if (isset($input['reg_form_id'])) {
            $sanitized_input['reg_form_id'] = absint($input['reg_form_id']);
        }
        if (isset($input['profile_form_id'])) {
            $sanitized_input['profile_form_id'] = absint($input['profile_form_id']);
        }

        return $sanitized_input;
    }
}

// -- Inicializa a classe de Configurações --
DS_Registro_Avancado_Admin_Settings::get_instance();