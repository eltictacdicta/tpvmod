<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__DIR__, 3) . '/base/fs_controller.php';
require_once dirname(__DIR__, 3) . '/base/fs_settings.php';
require_once dirname(__DIR__) . '/lib/tpvmod_modules.php';
require_once dirname(__DIR__) . '/lib/tpvmod_sede_mapping.php';

/**
 * Admin-only controller for the tpvmod global toggle
 * `tpvmod_terminal_mode` (with_terminal | without_terminal).
 *
 * The page is admin-gated via the constructor's `folder='admin'` arg
 * (matches the pattern used by business_data/admin_empresa.php and
 * system_updater/admin_updater.php). Non-admin users have no
 * `fs_rol_access` row pointing to this page, so fs_user::get_menu()
 * excludes it and have_access_to() returns false; fs_controller then
 * routes them to the access_denied template.
 */
class tpvmod_settings extends fs_controller
{
   /**
    * POST marker owned by the sede-mapping form. It must match the hidden
    * field rendered in view/tpvmod_settings.html.twig so a mapping POST is
    * never mistaken for a terminal-mode POST.
    */
   private const SEDE_MAPPING_POST_MARKER = 'save_sede_mapping';

   public $terminal_mode;
   public $terminal_settings_available;

   /** @var array<int, object> Sedes available for the mapping selectors. */
   public $sedes = [];

   /** @var array<string, ?string> tipo => ?codsede, from empresa_sede::mapping(). */
   public $sede_mapping = [];

   /** Whether business_data's empresa_sede model could be loaded. */
   public $sede_mapping_available = FALSE;

   public function __construct()
   {
      // require_admin=TRUE, only_admin=TRUE: blocks non-admins at the framework level.
      parent::__construct(__CLASS__, 'TPVMOD settings', 'admin', TRUE, TRUE);
   }

   protected function private_core()
   {
      $settings = new fs_settings();
      $this->terminal_settings_available = tpvmod_terminal_settings_available();
      $this->terminal_mode = tpvmod_terminal_mode_effective(
         (string) $settings->get('tpvmod_terminal_mode', 'with_terminal')
      );
      $this->loadSedeMapping();

      if ($_SERVER['REQUEST_METHOD'] === 'POST')
      {
         $post = is_array($_POST) ? $_POST : [];

         // The mapping form has its own marker and is NOT gated by the
         // terminal settings (which depend on facturacion_base).
         if (tpvmod_sede_mapping_submitted($post, self::SEDE_MAPPING_POST_MARKER))
         {
            $this->saveSedeMapping($post);
            return;
         }

         if (!$this->terminal_settings_available)
         {
            $this->new_error_msg('La configuración de terminal requiere el plugin facturacion_base activo.');
            return;
         }

         if (!$this->isCsrfValid())
         {
            $this->new_error_msg('Token de seguridad inválido.');
            return;
         }

         $posted = (string) ($_POST['tpvmod_terminal_mode'] ?? '');
         if (!in_array($posted, ['with_terminal', 'without_terminal'], TRUE))
         {
            $this->new_error_msg('Modo de terminal no válido.');
            return;
         }

         $settings->set('tpvmod_terminal_mode', $posted);
         if ($settings->save())
         {
            $this->terminal_mode = $posted;
            $this->new_message('Configuración guardada.');
         }
         else
         {
            $this->new_error_msg('No se pudo guardar la configuración.');
         }
      }
   }

   /**
    * Persists the sede mapping, with its own CSRF check. The mapping form is
    * admin-gated by the constructor and CSRF-gated here.
    *
    * @param array<string, mixed> $post
    */
   private function saveSedeMapping(array $post)
   {
      if (!$this->isCsrfValid())
      {
         $this->new_error_msg('Token de seguridad inválido.');
         return;
      }

      $result = tpvmod_save_sede_mapping($post);

      foreach ($result['errors'] as $error)
      {
         $this->new_error_msg($error);
      }

      if ($result['ok'])
      {
         $this->sede_mapping = empresa_sede::mapping();
         $this->new_message('Mapeo de sedes guardado.');
      }
   }

   /**
    * Loads the sedes and the current mapping when business_data's empresa_sede
    * model is available. When it is not, the page renders an informative note
    * instead of failing.
    */
   private function loadSedeMapping()
   {
      $this->sede_mapping_available = $this->requireEmpresaSedeModel();
      if (!$this->sede_mapping_available)
      {
         return;
      }

      $this->sede_mapping = empresa_sede::mapping();

      $sede = new empresa_sede();
      $this->sedes = $sede->all();
   }

   /**
    * Loads the business_data `empresa_sede` model by path.
    *
    * The framework model autoloader does not resolve plugin models outside the
    * app bootstrap (`fs_model_autoloader::register()` snapshots
    * `$GLOBALS['plugins']`), so an explicit, guarded `require_once` by path is
    * the convention already used by factura_pdf1 for `empresa`/`forma_pago`.
    *
    * @return bool TRUE when the class is usable.
    */
   private function requireEmpresaSedeModel(): bool
   {
      if (class_exists('empresa_sede', FALSE))
      {
         return TRUE;
      }

      $path = dirname(__DIR__, 3) . '/plugins/business_data/model/empresa_sede.php';
      if (!is_file($path))
      {
         return FALSE;
      }

      require_once $path;

      return class_exists('empresa_sede', FALSE);
   }
}
