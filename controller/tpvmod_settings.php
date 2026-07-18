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
   public $terminal_mode;
   public $terminal_settings_available;

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

      if ($_SERVER['REQUEST_METHOD'] === 'POST')
      {
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
}
