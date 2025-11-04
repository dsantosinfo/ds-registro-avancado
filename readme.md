# DS Registro Avançado com OTP

**Contributors:** DSantos Info  
**Tags:** gravity forms, otp, registration, user registration, whatsapp, woocommerce, billing  
**Requires at least:** 5.0  
**Tested up to:** 6.4  
**Stable tag:** 3.6.1  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Implementa um fluxo completo de registro de usuário com verificação OTP via WhatsApp e integração nativa com WooCommerce.

## Description

Este plugin adiciona um fluxo de registro de usuário em três etapas para o Gravity Forms:

1.  **Registro Inicial:** Formulário com informações básicas (nome, e-mail, telefone, senha)
2.  **Verificação OTP:** Código de verificação enviado via WhatsApp
3.  **Conclusão do Perfil:** Formulário com dados de cobrança do WooCommerce

### Funcionalidades

- ✅ **Verificação por WhatsApp:** Integração com API de WhatsApp para envio de códigos OTP
- ✅ **Integração WooCommerce:** Salva dados nos campos nativos de cobrança
- ✅ **Mapeamento Visual Completo:** Configure todos os campos via interface gráfica
- ✅ **Gerador de JSON:** Crie formulários prontos para importação
- ✅ **Compatibilidade:** Funciona com Elementor e Gravity Forms
- ✅ **Segurança:** Rate limiting e validação de dados
- ✅ **Países Completos:** Lista completa de países do WooCommerce

### Campos do Perfil

- **ID do Usuário na Plataforma:** Campo obrigatório para identificação
- **País:** Lista completa de países do WooCommerce
- **Estado/Região:** Campo de estado
- **Cidade:** Campo de cidade
- **CEP:** Código postal
- **Endereço:** Endereço completo
- **Chave PIX:** Para recebimento de pagamentos
- **Email Wise:** Para transferências internacionais

## Installation

1.  Faça o upload da pasta `ds-registro-avancado` para `/wp-content/plugins/`
2.  Ative o plugin no menu 'Plugins' do WordPress
3.  Configure em 'Configurações > DS Registro Avançado'
4.  Configure a API do WhatsApp (Conector WhatsApp)
5.  Use o gerador de JSON ou configure formulários existentes com mapeamento visual

## Configuration

O plugin utiliza **dois formulários** do Gravity Forms com **mapeamento visual completo**:

### Opção 1: Usar Formulários Existentes

1. **Vá em "Configurações > DS Registro Avançado"**
2. **Selecione seus formulários existentes**
3. **Mapeie todos os campos usando os dropdowns**

### Opção 2: Gerar Formulários Prontos (Recomendado)

1. **Vá em "Configurações > DS Registro Avançado"**
2. **Na seção "Exportar Formulários":**
   - Clique em "Gerar JSON dos Formulários"
   - Baixe os arquivos:
     - `ds-registro-form.json` - Formulário de registro completo
     - `ds-perfil-form.json` - Formulário de perfil completo
3. **No Gravity Forms:**
   - Vá em "Formulários > Importar/Exportar"
   - Importe os arquivos JSON baixados
4. **Volte às configurações do plugin:**
   - Selecione os formulários importados
   - O mapeamento já estará correto!

### Mapeamento de Campos Disponível

**Formulário de Registro:**
- Campo E-mail
- Campo Senha
- Campo Nome
- Campo Telefone
- Campo Código OTP
- Campo Username

**Formulário de Perfil:**
- Campo ID do Usuário (obrigatório)
- Campo País (populado com WooCommerce)
- Campo Estado/Região
- Campo Cidade
- Campo CEP
- Campo Endereço
- Campo PIX
- Campo Wise

**Vantagens do Mapeamento Visual:**
- ✅ Sem classes CSS necessárias
- ✅ Qualquer campo pode ser mapeado
- ✅ Interface intuitiva
- ✅ Configuração em um só lugar
- ✅ Formulários prontos para usar

## Requirements

- WordPress 5.0+
- PHP 8.0+
- Gravity Forms
- WooCommerce
- Plugin Conector WhatsApp (para OTP)

## Changelog

### 3.6.1
* **Correção Campo Nome:** Corrigido mapeamento do campo Name do Gravity Forms
* **Melhoria Estabilidade:** Campo de nome agora funciona corretamente com inputs múltiplos
* **Compatibilidade:** Melhor suporte para campos Name avançados

### 3.6.0
* **Gerador de JSON:** Crie formulários prontos para importação no Gravity Forms
* **Mapeamento Completo:** Todos os campos podem ser mapeados visualmente
* **Campo Username:** Adicionado mapeamento para campo username
* **Formulários Otimizados:** JSON gerado com estrutura ideal
* **Instalação Simplificada:** Importe e use imediatamente

### 3.5.0
* **Mapeamento Visual de Campos:** Configure via interface gráfica
* **Sem Classes CSS:** Não precisa mais adicionar classes nos campos
* **Configuração Simplificada:** Dropdowns para mapear campos
* **Maior Flexibilidade:** Qualquer campo pode ser mapeado
* **Interface Intuitiva:** Configuração em um só lugar

### 3.4.0
* Refatoração completa para integração nativa com WooCommerce
* Removida dependência do ACF
* Dados salvos nos campos de cobrança do WooCommerce
* Lista completa de países do WooCommerce
* Simplificação do código e melhor performance

### 3.3.0
* Implementação completa do sistema de registro
* Adicionado suporte a fuso horário e país
* Integração com ACF para campos personalizados
* Compatibilidade com Elementor
