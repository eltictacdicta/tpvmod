<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Pure, database-free helpers shared by the four tpvmod document listings.
 *
 * Every function here is pure or takes an injected callable: no DB connection,
 * no Twig and no controller reference. The four listing controllers delegate
 * their search predicate, canonical listing URL, pager arithmetic, phone map
 * and date normalization to this single file so the near-clone implementations
 * cannot drift (LHT-11).
 */

declare(strict_types=1);

/**
 * Decode the framework's input HTML-escape, then lowercase + trim.
 *
 * `fs_filter_input_req()` runs the raw request value through
 * FILTER_SANITIZE_FULL_SPECIAL_CHARS, so `$this->query` arrives as
 * `O&#039;Brien`. The SQL predicate must not carry HTML entities (LSS-02), so
 * this helper reverses that escape before the term is used as a pattern.
 * An empty string stays an empty string.
 */
function tpvmod_search_term(string $raw): string
{
    return trim(strtolower(htmlspecialchars_decode($raw, ENT_QUOTES)));
}

/**
 * Parenthesised SQL fragment WITHOUT a leading WHERE.
 *
 * Returns '' when $term is empty, so the caller appends nothing.
 *
 * $sqlString converts a full literal (wildcards included) to a quoted SQL
 * literal; production passes fn(string $v): string => $ctrl->var2str($v).
 *
 * Matches the document `codigo`, `numero2` and `observaciones` columns plus the
 * live customer `nombre`, `razonsocial`, `telefono1` and `telefono2` columns
 * through a `codcliente IN (subquery)` so a document is never duplicated.
 * `cifnif` is intentionally absent (LHT-05). The legacy is_numeric() branch is
 * dropped as redundant: digits are case-invariant.
 */
function tpvmod_build_search_predicate(string $term, callable $sqlString): string
{
    if ($term === '') {
        return '';
    }

    $raw = '%' . $term . '%';
    $space = '%' . str_replace(' ', '%', $term) . '%';

    return '('
        . '(lower(codigo) LIKE ' . $sqlString($raw)
        . ' OR lower(numero2) LIKE ' . $sqlString($raw)
        . ' OR lower(observaciones) LIKE ' . $sqlString($space) . ')'
        . ' OR codcliente IN (SELECT codcliente FROM clientes'
        . ' WHERE lower(nombre) LIKE ' . $sqlString($space)
        . ' OR lower(razonsocial) LIKE ' . $sqlString($space)
        . ' OR lower(telefono1) LIKE ' . $sqlString($space)
        . ' OR lower(telefono2) LIKE ' . $sqlString($space) . '))';
}

/**
 * Canonical listing URL.
 *
 * Drops null and '' values (keeps 0 and '0'), preserves the caller's key order
 * and encodes with http_build_query(..., PHP_QUERY_RFC3986). The query string is
 * appended with '&' because $base already carries "?page=...". When every
 * parameter is dropped the base URL is returned untouched.
 *
 * @param array<string, scalar|null> $params
 */
function tpvmod_build_list_url(string $base, array $params): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $filtered[$key] = $value;
    }

    $query = http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);

    return $query === '' ? $base : $base . '&' . $query;
}

/**
 * SQL order ('fecha DESC') -> URL order token ('fecha_desc').
 * Unknown forms fall back to 'fecha_desc'.
 */
function tpvmod_order_token_for(string $sqlOrder): string
{
    return match ($sqlOrder) {
        'fecha ASC' => 'fecha_asc',
        'codigo DESC' => 'codigo_desc',
        'codigo ASC' => 'codigo_asc',
        'vencimiento DESC' => 'vencimiento_desc',
        'vencimiento ASC' => 'vencimiento_asc',
        default => 'fecha_desc',
    };
}

/**
 * Offset pager arithmetic, byte-for-byte the legacy paginas() loop: pages of
 * $limit, the first, last, middle, current and current +/- 5 page indexes kept,
 * [] when at most one page survives. The returned keys stay sparse exactly as
 * the legacy array did. A non-positive $limit returns [] instead of looping.
 *
 * @param callable(int): string $urlForOffset
 * @return list<array{url: string, num: int, actual: bool}>
 */
function tpvmod_pager_links(int $offset, int $total, int $limit, callable $urlForOffset): array
{
    if ($limit < 1) {
        return [];
    }

    $paginas = [];
    $i = 0;
    $num = 0;
    $actual = 1;

    while ($num < $total) {
        $paginas[$i] = [
            'url' => $urlForOffset($i * $limit),
            'num' => $i + 1,
            'actual' => ($num == $offset),
        ];

        if ($num == $offset) {
            $actual = $i;
        }

        $i++;
        $num += $limit;
    }

    foreach ($paginas as $j => $value) {
        $enmedio = intval($i / 2);

        // Keep only the first, last and middle pages plus the current page and
        // its 5 neighbours on each side.
        if (($j > 1 && $j < $actual - 5 && $j != $enmedio) || ($j > $actual + 5 && $j < $i - 1 && $j != $enmedio)) {
            unset($paginas[$j]);
        }
    }

    return count($paginas) > 1 ? $paginas : [];
}

/**
 * telefono1 when non-blank, else telefono2 when non-blank, else ''.
 */
function tpvmod_resolve_phone(?string $telefono1, ?string $telefono2): string
{
    $primary = trim((string) $telefono1);
    if ($primary !== '') {
        return $primary;
    }

    $secondary = trim((string) $telefono2);

    return $secondary !== '' ? $secondary : '';
}

/**
 * Deduplicate and drop empty codes BEFORE calling $fetch; an empty set returns
 * [] without calling $fetch (zero queries). $fetch returns rows keyed by
 * codcliente and is invoked exactly once, so a rendered page needs at most one
 * `clientes` lookup (LHT-06).
 *
 * @param list<string> $codclientes
 * @param callable(list<string>): array<string, array<string, mixed>> $fetch
 * @return array<string, string> codcliente => phone
 */
function tpvmod_phone_map(array $codclientes, callable $fetch): array
{
    $codes = [];
    foreach ($codclientes as $codcliente) {
        $code = trim((string) $codcliente);
        if ($code === '' || in_array($code, $codes, true)) {
            continue;
        }

        $codes[] = $code;
    }

    if ($codes === []) {
        return [];
    }

    $map = [];
    foreach ($fetch($codes) as $codcliente => $row) {
        $map[(string) $codcliente] = tpvmod_resolve_phone(
            isset($row['telefono1']) ? (string) $row['telefono1'] : null,
            isset($row['telefono2']) ? (string) $row['telefono2'] : null
        );
    }

    return $map;
}

/**
 * Normalize a date request value to the canonical 'Y-m-d' form used by the
 * `date` columns.
 *
 * Accepts a native ISO 'Y-m-d' (what <input type="date"> submits) and the
 * framework's formatted 'd-m-Y' value, both validated with checkdate(). Empty,
 * malformed or impossible dates return '' so the caller emits no date
 * predicate. This mirrors the core `date_iso` Twig filter (`Html::dateIsoValue`).
 */
function tpvmod_normalize_date(?string $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $iso)
        && checkdate((int) $iso[2], (int) $iso[3], (int) $iso[1])
    ) {
        return sprintf('%04d-%02d-%02d', (int) $iso[1], (int) $iso[2], (int) $iso[3]);
    }

    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $raw, $formatted)
        && checkdate((int) $formatted[2], (int) $formatted[1], (int) $formatted[3])
    ) {
        return sprintf('%04d-%02d-%02d', (int) $formatted[3], (int) $formatted[2], (int) $formatted[1]);
    }

    return '';
}
