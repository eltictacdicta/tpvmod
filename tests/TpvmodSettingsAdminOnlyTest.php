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

declare(strict_types=1);

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

// Keep the framework's lazy model loading on, exactly like the other DB-free
// controller tests, so requiring fs_controller does not pull every core model
// into the shared Plugins-suite process mid-run.
if (!defined('FS_LAZY_MODELS')) {
    define('FS_LAZY_MODELS', true);
}

require_once FS_FOLDER . '/model/fs_page.php';
require_once FS_FOLDER . '/plugins/tpvmod/controller/tpvmod_settings.php';

/**
 * `tpvmod_settings` must be administrator-only through the framework's real
 * mechanism: the class-level `#[AdminOnly]` attribute, resolved by
 * `fs_page::is_admin_only_class()` through the attribute *name string*.
 *
 * The 4th `$admin` constructor argument is obsolete and ignored by
 * `fs_controller::__construct()`, so the previous docblock claim that
 * `folder='admin'` gated the page was false. These tests pin the declaration
 * and the corrected documentation.
 */
final class TpvmodSettingsAdminOnlyTest extends TestCase
{
    private const CONTROLLER_PATH = 'plugins/tpvmod/controller/tpvmod_settings.php';

    public function testSettingsControllerResolvesAsAdminOnly(): void
    {
        self::assertTrue(class_exists('tpvmod_settings'), 'the controller must be loadable');
        self::assertTrue(
            \fs_page::is_admin_only_class(\tpvmod_settings::class),
            'tpvmod_settings must resolve as admin-only from its #[AdminOnly] attribute'
        );
    }

    public function testSettingsControllerDeclaresTheAdminOnlyAttribute(): void
    {
        $reflection = new \ReflectionClass('tpvmod_settings');

        $attributeNames = array_map(
            static fn (\ReflectionAttribute $attribute): string => $attribute->getName(),
            $reflection->getAttributes()
        );

        self::assertContains(
            \FSFramework\Attribute\AdminOnly::class,
            $attributeNames,
            'the attribute name must be exactly the one fs_page::is_admin_only_class() matches'
        );
    }

    public function testDocblockDescribesTheAttributeNotTheObsoleteFolderArgument(): void
    {
        $source = (string) file_get_contents(FS_FOLDER . '/' . self::CONTROLLER_PATH);

        self::assertStringNotContainsString(
            "admin-gated via the constructor's `folder='admin'` arg",
            $source,
            'the misleading folder-gating claim must be gone'
        );
        self::assertStringNotContainsString(
            'blocks non-admins at the framework level',
            $source,
            'the obsolete $admin argument must not be described as an access gate'
        );

        self::assertStringContainsString('#[\FSFramework\Attribute\AdminOnly]', $source);
        self::assertStringContainsString('is_admin_only_class', $source);
        self::assertStringContainsStringIgnoringCase('obsolete', $source);
    }
}
