# DS Registro Avançado com OTP v3.2.2

Este plugin implementa um fluxo de registro de usuário robusto e seguro para o WordPress, utilizando o Gravity Forms. A funcionalidade principal é a verificação de telefone do usuário através de um código OTP (One-Time Password) enviado via WhatsApp, seguida pela criação automática de conta, login e um redirecionamento para a conclusão do perfil.

## Funcionalidades Principais

-   **Fluxo de Registro em Múltiplas Etapas:** Integrado a um formulário de várias páginas do Gravity Forms.
-   **Verificação de Telefone (OTP):** Envia um código de 6 dígitos para o WhatsApp do usuário para validar o número de telefone.
-   **Envio de Código via AJAX:** O código é solicitado e enviado sem a necessidade de recarregar a página.
-   **Criação e Login Automáticos:** Após a validação bem-sucedida, o usuário é criado no WordPress e logado automaticamente na plataforma.
-   **Salva Dados de Perfil Adicionais:** Processa um segundo formulário para salvar informações customizadas no perfil do usuário (via ACF).
-   **Forçar Conclusão de Perfil:** Redireciona usuários recém-registrados que ainda não completaram seu perfil para uma página específica.
-   **Segurança:** Inclui proteção contra abuso com _rate limiting_ por endereço IP e uso de _nonces_ do WordPress para segurança nas requisições.
-   **Carregamento Otimizado de Scripts:** Os assets (JavaScript) são carregados condicionalmente, apenas nas páginas onde são necessários, garantindo a máxima performance.

## Requisitos

-   WordPress 6.0 ou superior
-   PHP 8.0 ou superior
-   Plugin **Gravity Forms** instalado e ativo.
-   Plugin **Advanced Custom Fields (ACF)** instalado e ativo.
-   Uma **API de conexão com o WhatsApp** cujas credenciais estejam salvas nas opções do WordPress.

## Instalação

1.  Faça o upload da pasta `ds-registro-avancado` para o diretório `/wp-content/plugins/`.
2.  Ative o plugin através do menu 'Plugins' no painel do WordPress.

## Configuração

Para o correto funcionamento, as seguintes configurações são **essenciais**:

### 1. Formulário de Registro (ID 1)

-   **Campo de Telefone:** Adicione a **CSS Class Name**: `ds-otp-phone-field`.
-   **Campo de Código OTP:** Adicione a **CSS Class Name**: `ds-otp-code-field`.
-   **Botão de Envio de Código:** Adicione um campo **HTML** com o conteúdo:
    ```html
    <button type="button" class="gform_button button ds-otp-send-button">Enviar Código</button>
    <div class="ds-otp-status-div"></div>
    ```
-   **Campos de Usuário:** Adicione as seguintes classes CSS aos campos correspondentes: `ds-reg-email-field`, `ds-reg-password-field`, `ds-reg-name-field`.
-   **Confirmação do Formulário:** Configure a confirmação para redirecionar para a página de conclusão de perfil (ex: `/complete-seu-perfil`).

### 2. Formulário de Conclusão de Perfil (ID 3)

-   Este formulário deve conter os campos que o usuário precisa preencher após o registro.
-   O plugin está configurado para salvar o valor do campo de ID `1` deste formulário no campo ACF com a chave `field_68b042dc15604`.

### 3. API do WhatsApp

-   As credenciais devem estar salvas em `wp_options` com as chaves: `conector_whatsapp_url`, `conector_whatsapp_apikey`, `conector_whatsapp_instance`.

### 4. Página de Conclusão de Perfil

-   Crie uma página com o slug `complete-seu-perfil`.
-   O plugin verifica o campo ACF `user_studio_heros_ID` para determinar se o perfil está completo.

---

## Próximos Passos (Roadmap)

-   **Página de Configurações:** Desenvolver uma área no painel de administração do WordPress para que os IDs dos formulários e o mapeamento de campos (telefone, código, campo ACF, etc.) possam ser configurados via interface gráfica, eliminando a necessidade de IDs e classes CSS fixas no código.