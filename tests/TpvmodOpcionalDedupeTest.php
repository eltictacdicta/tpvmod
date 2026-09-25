<?php
declare(strict_types=1);
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * DB-free source contracts for the TPV cart-add dedupe (AD-12) and the
 * bridge-backed grouped detection (AD-13) introduced by
 * `opcional-en-varios-grupos` (WU-5).
 *
 * The opcional is presented under every group it belongs to (OPG-09 scenario
 * 2), so selecting the same opcional id under a second group must be deduped
 * BEFORE the exclusive-group replacement branch, or the TPV would charge it
 * twice (OPG-09 scenario 3).
 */

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

final class TpvmodOpcionalDedupeTest extends TestCase
{
    private function source(string $relative): string
    {
        $path = FS_FOLDER . '/plugins/tpvmod/' . $relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * Extracts a function body by brace matching from its declaration.
     */
    private function functionBody(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        if ($start === false) {
            self::fail('missing function: ' . $name);
        }

        $open = (int) strpos($source, '{', $start);
        $depth = 0;
        $length = strlen($source);
        for ($i = $open; $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        self::fail('unterminated function body: ' . $name);
    }

    public function test_pick_opcional_dedupes_before_the_exclusive_replacement_branch(): void
    {
        $body = $this->functionBody($this->source('view/js/tpvmod.js'), 'tpvmod_pick_opcional');

        $dedupePos = strpos($body, 'tpvmod_get_added_opcional_ids(');
        $replacePos = strpos($body, 'tpvmod_remove_opcional_in_grupo(');

        $this->assertNotFalse($dedupePos, 'the dedupe-by-id check must run at cart-add time');
        $this->assertNotFalse($replacePos, 'the exclusive-group replacement branch must exist');

        $dedupeStatement = strpos($body, 'return;', (int) $dedupePos);
        $this->assertNotFalse($dedupeStatement, 'a duplicate selection must early-return');
        $this->assertLessThan(
            $replacePos,
            $dedupeStatement,
            'OPG-09 s3: the dedupe-by-id early return must precede the exclusive-group replacement'
        );
    }

    public function test_grouped_detection_never_reads_the_removed_id_grupo_property(): void
    {
        $src = $this->source('lib/tpvmod_opcionales_ajax.php');

        $this->assertStringNotContainsString(
            'id_grupo',
            $src,
            'the quick-create AJAX layer must read grouped membership through the bridge, not the removed property'
        );
    }

    public function test_match_opcional_by_nombre_skips_grouped_candidates_via_the_flag(): void
    {
        $src = $this->source('lib/tpvmod_opcionales_ajax.php');
        $body = $this->functionBody($src, 'tpvmod_match_opcional_by_nombre');

        $this->assertStringContainsString(
            'grouped',
            $body,
            'the reuse-skip must key on the bridge-backed grouped flag'
        );
        $this->assertStringNotContainsString('id_grupo', $body);
    }

    public function test_normalized_quick_create_input_carries_no_removed_group_key(): void
    {
        $src = $this->source('lib/tpvmod_opcionales.php');

        $this->assertStringNotContainsString(
            "'id_grupo'",
            $src,
            'the quick-create payload must not resurrect the removed single-valued group key'
        );
        $this->assertStringContainsString("'grouped' => false", $src);
    }
}
