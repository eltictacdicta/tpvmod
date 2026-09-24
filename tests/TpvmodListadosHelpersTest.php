<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Database-free unit coverage for the shared listing helpers in
 * lib/tpvmod_listados.php (search predicate, canonical list URL, pager
 * arithmetic, phone map and date normalization).
 */

declare(strict_types=1);

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_listados.php';

final class TpvmodListadosHelpersTest extends TestCase
{
    public function testSearchTermDecodesFrameworkEscape(): void
    {
        $this->assertSame("o'brien", tpvmod_search_term('O&#039;Brien'));
        $this->assertSame('acme', tpvmod_search_term('  Acme  '));
        $this->assertSame('', tpvmod_search_term(''));
    }

    public function testSearchPredicateMatchesDocumentAndClientFields(): void
    {
        $sql = tpvmod_build_search_predicate('acme', $this->literalEscaper());

        $this->assertStringContainsString('lower(codigo) LIKE', $sql);
        $this->assertStringContainsString('lower(numero2) LIKE', $sql);
        $this->assertStringContainsString('lower(observaciones) LIKE', $sql);
        $this->assertStringContainsString('codcliente IN (SELECT codcliente FROM clientes', $sql);
        $this->assertStringContainsString('lower(nombre) LIKE', $sql);
        $this->assertStringContainsString('lower(razonsocial) LIKE', $sql);
        $this->assertStringContainsString('lower(telefono1) LIKE', $sql);
        $this->assertStringContainsString('lower(telefono2) LIKE', $sql);
        $this->assertStringContainsString("'%acme%'", $sql);
    }

    public function testSearchPredicateEscapesQuotes(): void
    {
        $this->assertSame("o'brien", tpvmod_search_term('O&#039;Brien'));

        $sql = tpvmod_build_search_predicate("o'brien", $this->literalEscaper());
        $this->assertStringContainsString("'%o''brien%'", $sql);
        $this->assertStringNotContainsString('&#39;', $sql);
        $this->assertStringNotContainsString('&lt;', $sql);
        $this->assertStringNotContainsString('&gt;', $sql);
        $this->assertStringNotContainsString('&amp;', $sql);

        $scriptSql = tpvmod_build_search_predicate(
            tpvmod_search_term('&lt;script&gt;'),
            $this->literalEscaper()
        );
        $this->assertStringContainsString("'%<script>%'", $scriptSql);
    }

    public function testSearchPredicateHasNoCifnif(): void
    {
        $sql = tpvmod_build_search_predicate('acme', $this->literalEscaper());

        $this->assertStringNotContainsString('cifnif', $sql);
    }

    public function testSearchPredicateEmptyTerm(): void
    {
        $called = false;
        $escaper = static function (string $value) use (&$called): string {
            $called = true;

            return "'" . $value . "'";
        };

        $this->assertSame('', tpvmod_build_search_predicate('', $escaper));
        $this->assertFalse($called, 'the literal escaper must not run for an empty term');
    }

    public function testListUrlCarriesEveryFilterAndEncodesSpecialChars(): void
    {
        $base = 'index.php?page=tpvmod_presupuestos';
        $url = tpvmod_build_list_url($base, [
            'mostrar' => 'buscar',
            'query' => 'a&b #c d',
            'codserie' => 'SER1',
            'codagente' => 'EMP1',
            'codcliente' => '',
            'desde' => '2026-01-01',
            'hasta' => '2026-01-31',
            'order' => 'fecha_desc',
            'offset' => 0,
        ]);

        $this->assertStringStartsWith($base . '&', $url);
        $this->assertStringContainsString('mostrar=buscar', $url);
        $this->assertStringContainsString('query=a%26b%20%23c%20d', $url);
        $this->assertStringContainsString('codserie=SER1', $url);
        $this->assertStringContainsString('codagente=EMP1', $url);
        $this->assertStringContainsString('desde=2026-01-01', $url);
        $this->assertStringContainsString('hasta=2026-01-31', $url);
        $this->assertStringContainsString('order=fecha_desc', $url);
        $this->assertStringContainsString('offset=0', $url);
        $this->assertStringNotContainsString('codcliente=', $url);
    }

    public function testBuildListUrlEncodesAndDropsEmpty(): void
    {
        // Key order is preserved and only null / '' are dropped.
        $this->assertSame(
            'index.php?page=x&mostrar=todo&offset=0',
            tpvmod_build_list_url('index.php?page=x', [
                'mostrar' => 'todo',
                'query' => '',
                'codserie' => null,
                'offset' => 0,
            ])
        );

        // Every parameter dropped leaves the base URL untouched.
        $this->assertSame(
            'index.php?page=x',
            tpvmod_build_list_url('index.php?page=x', ['mostrar' => '', 'query' => null])
        );
    }

    public function testBuildListUrlKeepsZero(): void
    {
        $this->assertSame(
            'index.php?page=x&offset=0',
            tpvmod_build_list_url('index.php?page=x', ['offset' => 0])
        );
        $this->assertSame(
            'index.php?page=x&codagente=0',
            tpvmod_build_list_url('index.php?page=x', ['codagente' => '0'])
        );
        $this->assertSame(
            'index.php?page=x&offset=0',
            tpvmod_build_list_url('index.php?page=x', ['offset' => '0'])
        );
    }

    public function testOrderTokenFor(): void
    {
        $this->assertSame('fecha_desc', tpvmod_order_token_for('fecha DESC'));
        $this->assertSame('fecha_asc', tpvmod_order_token_for('fecha ASC'));
        $this->assertSame('codigo_desc', tpvmod_order_token_for('codigo DESC'));
        $this->assertSame('codigo_asc', tpvmod_order_token_for('codigo ASC'));
        $this->assertSame('vencimiento_desc', tpvmod_order_token_for('vencimiento DESC'));
        $this->assertSame('vencimiento_asc', tpvmod_order_token_for('vencimiento ASC'));
        $this->assertSame('fecha_desc', tpvmod_order_token_for('DESC'));
        $this->assertSame('fecha_desc', tpvmod_order_token_for(''));
    }

    public function testPagerLinksBoundsAndPrunes(): void
    {
        $requested = [];
        $urlForOffset = static function (int $offset) use (&$requested): string {
            $requested[] = $offset;

            return '/list?offset=' . $offset;
        };

        // 20 pages of 10, current page index 9 (offset 90).
        $links = tpvmod_pager_links(90, 200, 10, $urlForOffset);

        // Sparse keys preserved byte-for-byte: first, last, middle, current and
        // current +/- 5 survive; the rest is pruned.
        $this->assertSame([0, 1, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 19], array_keys($links));
        $this->assertSame(
            [1, 2, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 20],
            array_column($links, 'num')
        );
        $this->assertSame('/list?offset=0', $links[0]['url']);
        $this->assertSame('/list?offset=190', $links[19]['url']);
        // The legacy loop builds every page URL before pruning.
        $this->assertSame(range(0, 190, 10), $requested);

        $active = array_keys(array_filter(
            $links,
            static fn(array $link): bool => $link['actual'] === true
        ));
        $this->assertSame([9], $active);
    }

    public function testPagerLinksEmptyWhenSinglePage(): void
    {
        $urlForOffset = static fn(int $offset): string => '/list?offset=' . $offset;

        $this->assertSame([], tpvmod_pager_links(0, 10, 10, $urlForOffset));
        $this->assertSame([], tpvmod_pager_links(0, 0, 10, $urlForOffset));
        // A non-positive limit must not loop forever.
        $this->assertSame([], tpvmod_pager_links(0, 200, 0, $urlForOffset));

        $twoPages = tpvmod_pager_links(10, 20, 10, $urlForOffset);
        $this->assertSame([1, 2], array_column($twoPages, 'num'));
        $this->assertSame('/list?offset=10', $twoPages[1]['url']);
        $this->assertTrue($twoPages[1]['actual']);
    }

    public function testResolvePhonePrefersPrimary(): void
    {
        $this->assertSame('600111222', tpvmod_resolve_phone('600111222', '900333444'));
        $this->assertSame('900333444', tpvmod_resolve_phone('', '900333444'));
        $this->assertSame('900333444', tpvmod_resolve_phone('   ', '900333444'));
        $this->assertSame('900333444', tpvmod_resolve_phone(null, '900333444'));
        $this->assertSame('', tpvmod_resolve_phone('', ''));
        $this->assertSame('', tpvmod_resolve_phone(null, null));
    }

    public function testPhoneMapPrefersTelefono1(): void
    {
        $fetched = [];
        $map = tpvmod_phone_map(['C1', 'C2'], function (array $codes) use (&$fetched): array {
            $fetched[] = $codes;

            return [
                'C1' => ['telefono1' => '600111222', 'telefono2' => '900333444'],
                'C2' => ['telefono1' => '', 'telefono2' => '955555555'],
            ];
        });

        $this->assertSame(['C1' => '600111222', 'C2' => '955555555'], $map);
        $this->assertSame([['C1', 'C2']], $fetched, 'exactly one batched fetch for the page set');
    }

    public function testPhoneMapFallsBackToTelefono2(): void
    {
        $map = tpvmod_phone_map(['C1'], static fn(array $codes): array => [
            'C1' => ['telefono1' => '   ', 'telefono2' => ' 955555555 '],
        ]);

        $this->assertSame(['C1' => '955555555'], $map);
    }

    public function testPhoneMapEmptyPhones(): void
    {
        $map = tpvmod_phone_map(['C1', 'C2'], static fn(array $codes): array => [
            'C1' => ['telefono1' => '', 'telefono2' => null],
            'C2' => ['telefono1' => null, 'telefono2' => ''],
        ]);

        $this->assertSame(['C1' => '', 'C2' => ''], $map);
    }

    public function testPhoneMapSkipsFetchForEmptySet(): void
    {
        $calls = 0;
        $fetch = function (array $codes) use (&$calls): array {
            $calls++;

            return [];
        };

        $this->assertSame([], tpvmod_phone_map([], $fetch));
        $this->assertSame([], tpvmod_phone_map(['', '  ', ''], $fetch));
        $this->assertSame(0, $calls, 'an empty code set must issue zero queries');
    }

    public function testPhoneMapDeduplicatesCodesBeforeFetch(): void
    {
        $captured = [];
        tpvmod_phone_map(['C1', 'C1', 'C2', 'C1'], function (array $codes) use (&$captured): array {
            $captured = $codes;

            return [];
        });

        $this->assertSame(['C1', 'C2'], $captured);
    }

    public function testNormalizeDateAcceptsIsoAndDmY(): void
    {
        $this->assertSame('2026-03-15', tpvmod_normalize_date('2026-03-15'));
        $this->assertSame('2026-03-15', tpvmod_normalize_date('15-03-2026'));
        $this->assertSame('2024-02-29', tpvmod_normalize_date('29-02-2024'));
        $this->assertSame('2024-02-29', tpvmod_normalize_date('2024-02-29'));
        $this->assertSame('2026-01-05', tpvmod_normalize_date(' 5-1-2026 '));
    }

    public function testNormalizeDateRejectsEmptyAndInvalid(): void
    {
        $this->assertSame('', tpvmod_normalize_date(''));
        $this->assertSame('', tpvmod_normalize_date(null));
        $this->assertSame('', tpvmod_normalize_date('not-a-date'));
        $this->assertSame('', tpvmod_normalize_date('99-99-9999'));
        // 2023 is not a leap year.
        $this->assertSame('', tpvmod_normalize_date('2023-02-29'));
        // April has 30 days.
        $this->assertSame('', tpvmod_normalize_date('31-04-2026'));
    }

    public function testNormalizeDateParityWithHtmlFilter(): void
    {
        foreach (['15-03-2026', '01-12-2025', '2026-03-15'] as $stored) {
            $expected = \FSFramework\Core\Html::dateIsoValue($stored);
            $this->assertSame($expected, tpvmod_normalize_date($stored), $stored);
        }

        // The helper must not introduce a new Twig filter.
        $lib = (string) file_get_contents(FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_listados.php');
        $this->assertStringNotContainsString('TwigFilter', $lib);
        $this->assertStringNotContainsString("'date_iso'", $lib);
    }

    /**
     * SQL literal escaper that mirrors fs_db2::escape_string() quoting rules.
     */
    private function literalEscaper(): callable
    {
        return static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'";
    }
}
