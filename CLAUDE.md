# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## O que é

Plugin WordPress de agendamento (clínicas/consultórios), instalado em um WordPress local do XAMPP (`C:\xampp\htdocs\harritec`). Versão 2.0.0 em `agendapro.php` (o `readme.txt` ainda diz 1.2.0). Textos de interface em pt-BR.

## Comandos

Não há `package.json`, `composer.json`, testes, lint nem build. Os arquivos JS e CSS são servidos como estão — editar e recarregar a página.

Validação disponível (somente sintaxe):

```bash
php -l includes/API/Endpoints.php     # um arquivo PHP
node --check admin/js/admin.js        # um arquivo JS
```

O `php` do PATH é 8.5; o XAMPP usa `C:\xampp\php\php.exe` (8.2). O plugin declara PHP ≥ 8.0, então use o do XAMPP para validar compatibilidade com o que realmente serve o site.

phpcs, phpunit, phpstan, eslint e wp-cli não estão instalados.

## Arquitetura

### Não há React

Apesar de o painel se comportar como SPA, o admin é JavaScript puro em um IIFE único (`admin/js/admin.js`): estado em um objeto `state`, telas montadas com template strings + `innerHTML`. O formulário público (`public/js/public.js`) usa jQuery. FullCalendar 6.1.11 vem da CDN jsDelivr (`includes/Assets.php`).

### Fluxo de uma requisição

1. `includes/Admin/Menu.php` registra 7 submenus (`agendapro`, `agendapro-appointments`, `-services`, `-professionals`, `-clients`, `-financial`, `-settings`); todos renderizam o mesmo `<div id="agendapro-root">`.
2. `admin.js` lê `?page=` e escolhe a tela em `render()`. Trocar de módulo é um reload do WordPress, não navegação client-side.
3. `refresh()` → `loadAll()` busca `/services`, `/professionals`, `/packages`, `/settings` e `/clients` e guarda em `state`. Isso roda de novo após **cada** submit de formulário.
4. `request()` faz `fetch` para `agendapro/v1` com `X-WP-Nonce` (vindo de `window.agendaproAdmin`, injetado por `wp_localize_script`).
5. `includes/API/Endpoints.php` contém todas as rotas (método `register()`), a sanitização, boa parte das regras de negócio e algum SQL direto. O helper `route($ns, $path, $methods, $callback, $auth)` define a permissão: `true` = `manage_options`, `false` = público.
6. Os models em `includes/models/` são classes estáticas finas sobre `$wpdb`, retornando arrays associativos.

### Eventos no admin

Toda interação passa por três listeners delegados em `document` (`click`, `change`, `submit`) no fim de `admin.js`, que despacham por atributos `data-*`.

- O listener de `click` só processa elementos dentro de um `<button>` (exceto três casos tratados antes: `[data-appt-open]`, `[data-client-package-open]` e seleção de cliente). Um `data-*` de ação colocado em `<tr>`, `<th>` ou `<span>` não dispara nada.
- Formulários são identificados por `data-form="<tipo>"` e `data-id`; o `submit` tem um `if/else` por tipo. Novo formulário = novo ramo ali.

### Carregamento de classes

O autoloader de `agendapro.php` mapeia `AgendaPro\X\Y` → `includes/X/Y.php`. Duas exceções que não seguem a convenção:

- Os models têm namespace `AgendaPro\Models`, mas a pasta é `models/` (minúscula). Eles são carregados por `require_once` explícito no topo de `Endpoints.php`. Um model novo precisa ser adicionado a essa lista.
- `AgendaPro\Webhook\Handler` está implementado em `includes/Handler.php`; `includes/Webhook/Handler.php` é só um arquivo de `require_once`.

`Plugin.php` na raiz é uma cópia antiga e nunca é carregado — o singleton real é `includes/Plugin.php`.

### Banco de dados

18 tabelas `{prefix}agendapro_*` definidas em `Installer::create_tables()` via `dbDelta`. `includes/Plugin.php` reexecuta `create_tables()` sempre que a option `agendapro_db_version` difere de `AGENDAPRO_VERSION`, então mudar esquema exige subir a versão.

- Não há foreign keys nem cascata; exclusões deixam órfãos, exceto onde o model apaga pivôs manualmente.
- `appointments.package_id` referencia `client_packages.id` (o pacote comprado pelo cliente), não `packages.id`.
- Status de agendamento: apenas `pending`, `confirmed`, `cancelled`. `completed` e `no_show` foram removidos do ENUM (o instalador os migra para `confirmed`), mas ainda aparecem em algumas consultas e em `public.js`.
- Criar um agendamento cria também uma linha em `financial_receivables` (`create_receivable_from_appointment`); editar, cancelar ou excluir o agendamento não atualiza essa linha.
- Ao adicionar tabela ou option, atualizar também `uninstall.php`.

### Configurações

Options `agendapro_*` em `wp_options`. Os valores padrão estão repetidos em `Installer::seed_options()`, `Endpoints::get_settings()`, `Endpoints::update_settings()` e `Assets::public_custom_css()`.

`update_settings()` regrava **todas** as options a partir do corpo da requisição: qualquer campo ausente no formulário é salvo vazio. Ao mexer no formulário de configurações (`renderGeneralSettings` em `admin.js`) ou nesse endpoint, manter os dois lados com o mesmo conjunto de campos.

### Disponibilidade e agendamento

`Appointment::available_slots()` gera os horários a partir de `working_hours` + duração do serviço + intervalo (o maior entre o do período e a option global). `Endpoints::validate_booking_slot()` só aceita um horário que essa função tenha oferecido, e é usada tanto pelo formulário público quanto pelo admin e pelo webhook.

`GET /availability` responde em formato aninhado (`dates[].professionals[].slots[]`); os dois frontends extraem os slots desse formato.

### Respostas diferentes para admin e visitante

`GET /services` e `GET /professionals` devolvem o registro completo para quem tem `manage_options` e uma versão reduzida para os demais (`compact_professional()` devolve só `id`, `name`, `specialties`, `photo_url`). `public.js` é consumido por visitantes: testar o formulário público logado como admin não reproduz o que o visitante recebe.

### Webhooks

`includes/Handler.php`:

- Saída: `dispatch_outbound($event, $appointment)` faz `wp_remote_post` síncrono para `agendapro_webhook_url`, assinado com HMAC-SHA256 no header `X-AgendaPro-Signature`. Eventos: `appointment.created`, `.rescheduled`, `.status_changed`, `.confirmed`, `.cancelled`, `.notification`.
- Entrada: `POST /webhook/inbound` com `secret` + `action`; as ações `book` e `reschedule` reaproveitam `create_appointment` / `update_appointment` de `Endpoints`.

O formato do payload é consumido por um sistema externo; tratar como contrato.

### CSS

`admin/css/admin.css` e `public/css/public.css` são os arquivos enfileirados e começam com `@import` de `assets/css/admin.css` / `assets/css/public.css` (tokens e componentes base). O cache-busting por `filemtime` cobre só os arquivos enfileirados, não os importados.

## Problemas conhecidos

Levantados por leitura de código, ainda não corrigidos:

- Formulário público: `public.js` filtra profissionais por `active` e `services`, campos que a resposta reduzida de `GET /professionals` não traz.
- Salvar Configurações esvazia `rescue_template` e os rótulos `menu_*` (ver seção Configurações).
- Financeiro: a linha de conta a receber (`<tr data-receivable-detail>`) não abre o detalhe, pela regra do `<button>` descrita acima; idem cabeçalhos ordenáveis.
- `orderby` por `client_name`, `professional_name` ou `package_name` gera coluna inexistente nos models (`a.client_name` etc.).
- `get_availability` e `create_client` declaram retorno `\WP_REST_Response` mas podem retornar `WP_Error`.
- `GET /clients/lookup` é público e devolve o cadastro do cliente e os tokens de confirmação/cancelamento.
- `public.js` concatena nomes vindos da API em HTML sem escape (no admin existe o helper `escape()`).
