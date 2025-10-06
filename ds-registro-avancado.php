<?php
/**
 * Plugin Name:       DS Registro Avançado com OTP
 * Plugin URI:        https://dsantosinfo.com.br/
 * Description:       Implementa um fluxo de registro de usuário com verificação de telefone (OTP) via WhatsApp e conclusão de perfil para o Gravity Forms.
 * Version:           3.3.0
 * Author:            DSantos Info
 * Author URI:        https://dsantosinfo.com.br/
 * Requires PHP:      8.0
 * Text Domain:       ds-registro-avancado
 */

if (!defined('ABSPATH')) {
    exit; // Acesso direto negado.
}

// -- Definições de Constantes do Plugin --
define('DS_REGISTRO_AVANCADO_VERSION', '3.3.0');
define('DS_REGISTRO_AVANCADO_FILE', __FILE__);
define('DS_REGISTRO_AVANCADO_PATH', plugin_dir_path(DS_REGISTRO_AVANCADO_FILE));
define('DS_REGISTRO_AVANCADO_URL', plugin_dir_url(DS_REGISTRO_AVANCADO_FILE));

// -- Carregamento dos Arquivos Principais --
require_once DS_REGISTRO_AVANCADO_PATH . 'includes/class-plugin.php';
require_once DS_REGISTRO_AVANCADO_PATH . 'includes/class-admin-settings.php';
// Futuramente, outras classes como WhatsAppService podem ser adicionadas aqui.

/**
 * Função principal para inicializar o plugin.
 * Garante que o plugin seja carregado apenas uma vez.
 *
 * @return DS_Registro_Avancado_Plugin
 */
function ds_registro_avancado_init(): DS_Registro_Avancado_Plugin {
    return DS_Registro_Avancado_Plugin::get_instance();
}

// -- Inicializa o Plugin --
ds_registro_avancado_init();