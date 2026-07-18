<?php
/**
 * This file is part of tpvmod
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

declare(strict_types=1);

/**
 * Plugin tpvmod — Composer autoload shim.
 *
 * The plugin ships a versioned `vendor/` tree (per AGENTS.md
 * "Plugin Composer Dependencies — vendor/ MUST be committed").
 * When `vendor/autoload.php` is present we load it; when missing
 * (a fresh checkout that forgot `composer install`) we emit an
 * `error_log` directive so the operator sees the recovery path.
 */
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    error_log('tpvmod: vendor/autoload.php not found. Run `ddev exec composer install` in plugins/tpvmod/');
}
