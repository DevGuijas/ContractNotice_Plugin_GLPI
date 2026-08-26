<?php

require_once __DIR__ . '/../../../inc/includes.php';

use GlpiPlugin\Contractnotice\AnnouncementRepository;
use GlpiPlugin\Contractnotice\Manager;
use GlpiPlugin\Contractnotice\Menu;

global $CFG_GLPI;

Session::checkLoginUser();
Manager::checkCanManage();

$managementUrl = $CFG_GLPI['root_doc'] . '/plugins/contractnotice/front/announcements.php';
$saveUrl = $CFG_GLPI['root_doc'] . '/plugins/contractnotice/front/action-2.1.1.php';
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$flash = $_SESSION['plugin_contractnotice_flash'] ?? null;
unset($_SESSION['plugin_contractnotice_flash']);
$flashMarkup = '';
if (is_array($flash) && isset($flash['message'])) {
    $flashClass = ($flash['type'] ?? '') === 'error' ? 'danger' : 'success';
    $flashMarkup = '<div class="alert alert-' . $flashClass . '" role="alert">'
        . $escape($flash['message']) . '</div>';
}

Html::header(__('Disparar aviso', 'contractnotice'), '', 'admin', Menu::class, 'contractnotice');

try {
    if (!AnnouncementRepository::isInstalled()) {
        echo '<div class="container-fluid"><div class="card"><div class="card-body">'
            . '<div class="alert alert-warning mb-0" role="alert">'
            . '<i class="ti ti-alert-triangle me-2"></i>'
            . '<strong>Atualização necessária.</strong> As tabelas da Central de Avisos ainda não foram criadas. '
            . '<a class="alert-link" href="' . $escape($CFG_GLPI['root_doc'] . '/front/plugin.php') . '">'
            . 'Abra Configuração &gt; Plugins, clique em Atualizar e mantenha o plugin ativo.</a>'
            . '</div></div></div></div>';
    } else {
        $editId = (int) ($_GET['id'] ?? 0);
        $form = $editId > 0 ? AnnouncementRepository::get($editId) : null;
        if ($form === null) {
            $form = AnnouncementRepository::getBlankFormData();
        } else {
            $form['start_at'] = substr(str_replace(' ', 'T', (string) $form['start_at']), 0, 16);
            $form['end_at'] = $form['end_at'] === null
                ? ''
                : substr(str_replace(' ', 'T', (string) $form['end_at']), 0, 16);
        }

        $groups = AnnouncementRepository::getGroups();
        $profiles = AnnouncementRepository::getProfiles();
        $announcements = AnnouncementRepository::getForManagement();
        $targetIds = array_map('intval', (array) $form['target_ids']);
        // GLPI validates every POST before running the legacy endpoint, so each form
        // must include its official token as well as the plugin-scoped token below.
        $glpiCsrfToken = Session::getNewCSRFToken(true);
        if (empty($_SESSION['plugin_contractnotice_csrf_token'])) {
            $_SESSION['plugin_contractnotice_csrf_token'] = bin2hex(random_bytes(32));
        }
        $pluginToken = (string) $_SESSION['plugin_contractnotice_csrf_token'];
        $selected = static fn (bool $state): string => $state ? ' selected' : '';
        $checked = static fn (bool $state): string => $state ? ' checked' : '';

        echo '<div class="container-fluid">'
            . $flashMarkup
            . '<div class="alert alert-info" role="alert"><i class="ti ti-info-circle me-2"></i>'
            . 'Esta central é visível somente quando o perfil ativo é <strong>'
            . $escape(PLUGIN_CONTRACTNOTICE_MANAGER_PROFILE)
            . '</strong>. Avisos imediatos são consultados a cada 30 segundos; avisos “ao logar” aparecem no início de cada sessão.</div>'
            . '<div class="card mb-4"><div class="card-header"><h2 class="card-title mb-0">'
            . ((int) $form['id'] > 0 ? 'Editar aviso' : 'Novo aviso')
            . '</h2></div><div class="card-body">'
            . '<form method="post" action="' . $escape($saveUrl) . '" id="contractnotice-form">'
            . '<input type="hidden" name="_glpi_csrf_token" value="' . $escape($glpiCsrfToken) . '">'
            . '<input type="hidden" name="plugin_contractnotice_token" value="' . $escape($pluginToken) . '">'
            . '<input type="hidden" name="id" value="' . (int) $form['id'] . '">'
            . '<input type="hidden" name="action" value="save">'
            . '<div class="mb-3"><label class="form-label" for="contractnotice-name">Título</label>'
            . '<input class="form-control" id="contractnotice-name" name="name" maxlength="255" required value="'
            . $escape($form['name']) . '"></div>'
            . '<div class="mb-3"><label class="form-label" for="contractnotice-content">Mensagem</label>'
            . '<textarea class="form-control" id="contractnotice-content" name="content" rows="8" required>'
            . $escape($form['content']) . '</textarea>'
            . '<div class="form-hint">O texto é exibido de forma segura, preservando as quebras de linha.</div></div>'
            . '<div class="row"><div class="col-md-4 mb-3"><label class="form-label" for="contractnotice-target-type">Público-alvo</label>'
            . '<select class="form-select" id="contractnotice-target-type" name="target_type">'
            . '<option value="all"' . $selected($form['target_type'] === 'all') . '>Todos os usuários</option>'
            . '<option value="groups"' . $selected($form['target_type'] === 'groups') . '>Grupo(s)</option>'
            . '<option value="profiles"' . $selected($form['target_type'] === 'profiles') . '>Perfil(is)</option>'
            . '</select></div>'
            . '<div class="col-md-4 mb-3 contractnotice-target contractnotice-groups"><label class="form-label" for="contractnotice-groups-filter">Grupos</label>'
            . '<input class="form-control mb-2" id="contractnotice-groups-filter" type="search" placeholder="Pesquisar grupos">'
            . '<div id="contractnotice-groups" class="border rounded p-2 overflow-auto" style="max-height:12rem">';

        foreach ($groups as $id => $name) {
            $isChecked = in_array((int) $id, $targetIds, true);
            echo '<label class="form-check d-block mb-1" data-contractnotice-option>'
                . '<input class="form-check-input" type="checkbox" name="group_target_ids[]" value="' . (int) $id . '"'
                . $checked($isChecked) . '><span class="form-check-label">' . $escape($name) . '</span></label>';
        }

        echo '</div><div class="form-hint">Marque um ou mais grupos.</div></div>'
            . '<div class="col-md-4 mb-3 contractnotice-target contractnotice-profiles">'
            . '<label class="form-label" for="contractnotice-profiles-filter">Perfis</label>'
            . '<input class="form-control mb-2" id="contractnotice-profiles-filter" type="search" placeholder="Pesquisar perfis">'
            . '<div id="contractnotice-profiles" class="border rounded p-2 overflow-auto" style="max-height:12rem">';

        foreach ($profiles as $id => $name) {
            $isChecked = in_array((int) $id, $targetIds, true);
            echo '<label class="form-check d-block mb-1" data-contractnotice-option>'
                . '<input class="form-check-input" type="checkbox" name="profile_target_ids[]" value="' . (int) $id . '"'
                . $checked($isChecked) . '><span class="form-check-label">' . $escape($name) . '</span></label>';
        }

        echo '</div><div class="form-hint">Marque um ou mais perfis.</div></div>'
            . '<div class="col-md-4 mb-3"><label class="form-label" for="contractnotice-delivery-mode">Disparo</label>'
            . '<select class="form-select" id="contractnotice-delivery-mode" name="delivery_mode">'
            . '<option value="immediate"' . $selected($form['delivery_mode'] === 'immediate') . '>Imediato</option>'
            . '<option value="login"' . $selected($form['delivery_mode'] === 'login') . '>Sempre ao logar</option>'
            . '</select></div><div class="col-md-4 mb-3"><label class="form-label" for="contractnotice-start">Início</label>'
            . '<input class="form-control" id="contractnotice-start" name="start_at" type="datetime-local" required value="'
            . $escape($form['start_at']) . '"></div><div class="col-md-4 mb-3">'
            . '<label class="form-label" for="contractnotice-end">Encerramento (opcional)</label>'
            . '<input class="form-control" id="contractnotice-end" name="end_at" type="datetime-local" value="'
            . $escape($form['end_at']) . '"></div></div>'
            . '<label class="form-check mb-3"><input class="form-check-input" name="is_active" type="checkbox" value="1"'
            . $checked((bool) $form['is_active']) . '><span class="form-check-label">Aviso ativo</span></label>'
            . '<div class="d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Salvar aviso</button>';

        if ((int) $form['id'] > 0) {
            echo '<a class="btn btn-outline-secondary" href="' . $escape($managementUrl) . '">Cancelar edição</a>';
        }

        echo '</div></form></div></div>'
            . '<div class="card"><div class="card-header"><h2 class="card-title mb-0">Avisos existentes</h2></div>'
            . '<div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr>'
            . '<th>Aviso</th><th>Público</th><th>Disparo</th><th>Período</th><th>Status</th><th class="w-1">Ações</th>'
            . '</tr></thead><tbody>';

        if ($announcements === []) {
            echo '<tr><td colspan="6" class="text-secondary text-center py-4">Nenhum aviso cadastrado.</td></tr>';
        }

        foreach ($announcements as $announcement) {
            $preview = (string) $announcement['content'];
            if (strlen($preview) > 160) {
                $preview = substr($preview, 0, 157) . '...';
            }
            $id = (int) $announcement['id'];
            $statusClass = $announcement['is_active'] ? 'bg-success' : 'bg-secondary';
            echo '<tr><td><strong>' . $escape($announcement['name']) . '</strong><br>'
                . '<span class="text-secondary text-truncate d-inline-block" style="max-width:30rem">'
                . $escape($preview) . '</span></td><td>' . $escape($announcement['audience_label'])
                . '</td><td>' . $escape($announcement['delivery_label']) . '</td><td>De '
                . $escape($announcement['start_at']);
            if (!empty($announcement['end_at'])) {
                echo '<br>até ' . $escape($announcement['end_at']);
            }
            echo '</td><td><span class="badge ' . $statusClass . '">' . $escape($announcement['status'])
                . '</span></td><td><div class="btn-list flex-nowrap">'
                . '<a class="btn btn-sm btn-outline-primary" href="' . $escape($managementUrl . '?id=' . $id)
                . '" title="Editar"><i class="ti ti-pencil"></i></a>'
                . '<form method="post" action="' . $escape($saveUrl) . '" class="d-inline">'
                . '<input type="hidden" name="_glpi_csrf_token" value="' . $escape($glpiCsrfToken) . '">'
            . '<input type="hidden" name="plugin_contractnotice_token" value="' . $escape($pluginToken) . '">'
                . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="toggle">'
                . '<button class="btn btn-sm btn-outline-secondary" type="submit" title="Ativar ou desativar"><i class="ti '
                . ($announcement['is_active'] ? 'ti-player-pause' : 'ti-player-play') . '"></i></button></form>'
                . '<form method="post" action="' . $escape($saveUrl) . '" class="d-inline" onsubmit="return confirm(\'Apagar este aviso?\');">'
                . '<input type="hidden" name="_glpi_csrf_token" value="' . $escape($glpiCsrfToken) . '">'
            . '<input type="hidden" name="plugin_contractnotice_token" value="' . $escape($pluginToken) . '">'
                . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="delete">'
                . '<button class="btn btn-sm btn-outline-danger" type="submit" title="Apagar"><i class="ti ti-trash"></i></button></form>'
                . '</div></td></tr>';
        }

        echo '</tbody></table></div></div></div>';
        echo <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
   var type = document.getElementById('contractnotice-target-type');
   var groupBox = document.querySelector('.contractnotice-groups');
   var profileBox = document.querySelector('.contractnotice-profiles');
   var groupFilter = document.getElementById('contractnotice-groups-filter');
   var profileFilter = document.getElementById('contractnotice-profiles-filter');
   if (!type || !groupBox || !profileBox) {
      return;
   }
   function toggleInputs(box, enabled) {
      var inputs = box.querySelectorAll('input[type="checkbox"]');
      for (var index = 0; index < inputs.length; index++) {
         inputs[index].disabled = !enabled;
      }
   }
   function refresh() {
      var groups = type.value === 'groups';
      var profiles = type.value === 'profiles';
      groupBox.style.display = groups ? '' : 'none';
      profileBox.style.display = profiles ? '' : 'none';
      toggleInputs(groupBox, groups);
      toggleInputs(profileBox, profiles);
   }
   function addFilter(input, box) {
      if (!input) {
         return;
      }
      input.addEventListener('input', function () {
         var term = input.value.toLocaleLowerCase();
         var options = box.querySelectorAll('[data-contractnotice-option]');
         for (var index = 0; index < options.length; index++) {
            options[index].style.display = options[index].textContent.toLocaleLowerCase().indexOf(term) === -1 ? 'none' : '';
         }
      });
   }
   type.addEventListener('change', refresh);
   addFilter(groupFilter, groupBox);
   addFilter(profileFilter, profileBox);
   refresh();
});
</script>
HTML;
    }
} catch (\Throwable $exception) {
    Toolbox::logInFile(
        'php-errors',
        sprintf("contractnotice: unable to display the announcement manager: %s\n", $exception->getMessage())
    );
    echo '<div class="container-fluid"><div class="alert alert-danger" role="alert">'
        . '<i class="ti ti-alert-triangle me-2"></i>'
        . '<strong>' . __('Não foi possível carregar a Central de Avisos.', 'contractnotice') . '</strong> '
        . __('Detalhe técnico para diagnóstico:', 'contractnotice') . ' <code>' . $escape($exception->getMessage()) . '</code>'
        . '</div></div>';
}

Html::footer();
