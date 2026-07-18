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

namespace FSFramework\Plugins\tpvmod;

/**
 * Boot entry point for the tpvmod plugin.
 *
 * Per the framework's plugin-loader convention, the kernel calls
 * `Init::init()` after autoload is ready. This implementation only
 * requires the Composer autoload shim — there are no listeners,
 * extensions, or DI registrations yet. The class is in place as
 * a stable surface for future changes to populate.
 *
 * @see plugins/tpvmod/openspec/changes/modernize-m3/proposal.md §3
 */
class Init
{
    /**
     * Boot hook invoked by the framework's plugin loader.
     */
    public function init(): void
    {
        require_once __DIR__ . '/composer_autoload.php';
    }
}
