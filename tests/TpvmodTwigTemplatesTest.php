<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Unit tests for the Twig view layer inventory and controller CSRF cleanup.
 *
 * This suite is intentionally scaffolded with markTestSkipped() in Wave 1
 * (PR1 — infra). The 3 assertions flip to real checks in Wave 4 once:
 *   - the 19 .html.twig files are in place (Wave 2 + Wave 3),
 *   - the 10 legacy .html files are deleted (Wave 2 + Wave 3),
 *   - the CSRF workaround is removed from controller/tpvmod.php and
 *     controller/tpvmod_settings.php (Wave 2).
 *
 * Strict-TDD RED step: each test currently has a body that does not
 * exercise production code. The body is replaced with real assertions
 * in Wave 4 (the GREEN step).
 */

declare(strict_types=1);

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

class TpvmodTwigTemplatesTest extends TestCase
{
    public function testNoLegacyHtmlFilesRemain(): void
    {
        $this->markTestSkipped('flipped in Wave 4 (template rewrite + .html deletion)');
    }

    public function testAllExpectedTwigTemplatesExist(): void
    {
        $this->markTestSkipped('flipped in Wave 4 (19 .html.twig files in place)');
    }

    public function testControllersDropCsrfWorkaround(): void
    {
        $this->markTestSkipped('flipped in Wave 4 (controller cleanup complete)');
    }
}
