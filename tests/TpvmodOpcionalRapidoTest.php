<?php
declare(strict_types=1);
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Unit tests for the opcionales quick-create flow (tpvmod-opcional-rapido).
 * Pure helpers live in lib/tpvmod_opcionales.php and the AJAX orchestration in
 * lib/tpvmod_opcionales_ajax.php. No database access is required.
 */

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

final class TpvmodOpcionalRapidoTest extends TestCase
{
    protected function setUp(): void
    {
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_opcionales.php';

        $ajaxLib = FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_opcionales_ajax.php';
        if (is_file($ajaxLib)) {
            require_once $ajaxLib;
        }
    }

    // ---------------------------------------------------------------------
    // Phase 1 — pure helper contracts
    // ---------------------------------------------------------------------

    public function testBuildAdHocOpcionalFixedPricePassthrough(): void
    {
        $result = tpvmod_build_ad_hoc_opcional([
            'nombre' => 'Toallero',
            'descripcion' => '',
            'tipo_precio' => 'fijo',
            'valor' => '12.50',
        ], 25.0);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['errors']);
        $this->assertNotNull($result['opcional']);
        $this->assertNull($result['opcional']['id']);
        $this->assertNull($result['opcional']['grupo_id']);
        $this->assertTrue($result['opcional']['ad_hoc']);
        $this->assertSame('Toallero', $result['opcional']['descripcion']);
        $this->assertSame(12.5, $result['opcional']['precio']);
    }

    public function testBuildAdHocOpcionalPercentageUsesParentPvp(): void
    {
        $result = tpvmod_build_ad_hoc_opcional([
            'nombre' => 'Grabado',
            'descripcion' => 'Grabado láser',
            'tipo_precio' => 'porcentaje',
            'valor' => '10',
        ], 25.0);

        $this->assertTrue($result['ok']);
        $this->assertSame(bround(25.0 * 10 / 100), $result['opcional']['precio']);
        $this->assertSame('Grabado láser', $result['opcional']['descripcion']);
    }

    public function testBuildAdHocOpcionalRejectsEmptyNombre(): void
    {
        $result = tpvmod_build_ad_hoc_opcional([
            'nombre' => '   ',
            'descripcion' => '',
            'tipo_precio' => 'fijo',
            'valor' => '1',
        ], 10.0);

        $this->assertFalse($result['ok']);
        $this->assertNotSame([], $result['errors']);
        $this->assertNull($result['opcional']);
    }

    public function testBuildAdHocOpcionalRejectsNegativeValor(): void
    {
        $result = tpvmod_build_ad_hoc_opcional([
            'nombre' => 'Toallero',
            'descripcion' => '',
            'tipo_precio' => 'fijo',
            'valor' => '-3',
        ], 10.0);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['opcional']);
    }

    public function testBuildAdHocOpcionalRejectsNonNumericValor(): void
    {
        $result = tpvmod_build_ad_hoc_opcional([
            'nombre' => 'Toallero',
            'descripcion' => '',
            'tipo_precio' => 'fijo',
            'valor' => 'abc',
        ], 10.0);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['opcional']);
    }

    public function testOpcionalIsAdHocPostReadsMarker(): void
    {
        $post = ['tpvmod_opcional_ad_hoc_3' => '1'];

        $this->assertTrue(tpvmod_opcional_is_ad_hoc_post($post, 3));
        $this->assertFalse(tpvmod_opcional_is_ad_hoc_post($post, 4));
        $this->assertFalse(tpvmod_opcional_is_ad_hoc_post([], 1));
        $this->assertFalse(tpvmod_opcional_is_ad_hoc_post(['tpvmod_opcional_ad_hoc_1' => '0'], 1));
    }

    public function testNormalizeOpcionalInputMapsFixedFields(): void
    {
        $result = tpvmod_normalize_opcional_input([
            'nombre' => '  Toallero  ',
            'descripcion' => ' Cromo ',
            'tipo_precio' => 'fijo',
            'valor' => '12,50',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['errors']);
        $data = $result['data'];
        $this->assertSame('Toallero', $data['nombre']);
        $this->assertSame('Cromo', $data['descripcion']);
        $this->assertSame('fijo', $data['tipo_precio']);
        $this->assertSame(12.5, $data['precio']);
        $this->assertNull($data['porcentaje']);
        $this->assertTrue($data['activo']);
        $this->assertNull($data['id_grupo']);
    }

    public function testNormalizeOpcionalInputMapsPercentageFields(): void
    {
        $result = tpvmod_normalize_opcional_input([
            'nombre' => 'Grabado',
            'descripcion' => '',
            'tipo_precio' => 'porcentaje',
            'valor' => '10',
        ]);

        $this->assertTrue($result['ok']);
        $data = $result['data'];
        $this->assertSame('porcentaje', $data['tipo_precio']);
        $this->assertSame(0.0, $data['precio']);
        $this->assertSame(10.0, $data['porcentaje']);
        $this->assertTrue($data['activo']);
        $this->assertNull($data['id_grupo']);
    }

    public function testNormalizeOpcionalInputUnknownTipoFallsBackToFijo(): void
    {
        $result = tpvmod_normalize_opcional_input([
            'nombre' => 'Toallero',
            'descripcion' => '',
            'tipo_precio' => 'raro',
            'valor' => '5',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('fijo', $result['data']['tipo_precio']);
        $this->assertNull($result['data']['porcentaje']);
    }

    public function testNormalizeOpcionalInputRejectsEmptyNombre(): void
    {
        $result = tpvmod_normalize_opcional_input([
            'nombre' => '',
            'descripcion' => '',
            'tipo_precio' => 'fijo',
            'valor' => '5',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNotSame([], $result['errors']);
        $this->assertNull($result['data']);
    }

    public function testNormalizeOpcionalInputRejectsInvalidValor(): void
    {
        foreach (['-1', 'abc', ''] as $valor) {
            $result = tpvmod_normalize_opcional_input([
                'nombre' => 'Toallero',
                'descripcion' => '',
                'tipo_precio' => 'fijo',
                'valor' => $valor,
            ]);

            $this->assertFalse($result['ok'], 'valor rejected: ' . $valor);
            $this->assertNull($result['data']);
        }
    }

    public function testNormalizeOpcionalInputRejectsTooLongNombre(): void
    {
        $result = tpvmod_normalize_opcional_input([
            'nombre' => str_repeat('a', 101),
            'descripcion' => '',
            'tipo_precio' => 'fijo',
            'valor' => '5',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['data']);
    }

    // ---------------------------------------------------------------------
    // Phase 3 — AJAX seams: match, codigo retry, target resolution
    // ---------------------------------------------------------------------

    public function testMatchOpcionalByNombreUsesNormalizedEquality(): void
    {
        $candidates = [
            ['id' => 1, 'nombre' => '  Toallero   CROMO '],
            ['id' => 2, 'nombre' => 'Percha'],
        ];

        $match = tpvmod_match_opcional_by_nombre($candidates, 'toallero cromo');

        $this->assertNotNull($match);
        $this->assertSame(1, (int) $match['id']);
        $this->assertNull(tpvmod_match_opcional_by_nombre($candidates, 'nada'));
    }

    public function testMatchOpcionalByNombreSkipsGroupedCandidates(): void
    {
        $grouped = ['id' => 1, 'nombre' => '  Toallero  ', 'id_grupo' => 3];
        $ungrouped = ['id' => 2, 'nombre' => 'Toallero', 'id_grupo' => null];

        $match = tpvmod_match_opcional_by_nombre([$grouped, $ungrouped], 'toallero');

        $this->assertNotNull($match);
        $this->assertSame(2, (int) $match['id']);

        // A grouped opcional must never be reused (OD-5/OD-8).
        $this->assertNull(tpvmod_match_opcional_by_nombre([$grouped], 'toallero'));
    }

    public function testOpcionalCandidateArrayCarriesIdGrupo(): void
    {
        $grouped = tpvmod_opcional_candidate_array((object) [
            'id' => 1,
            'nombre' => 'X',
            'id_grupo' => 7,
        ]);

        $this->assertNotNull($grouped);
        $this->assertSame(7, (int) $grouped['id_grupo']);

        $ungrouped = tpvmod_opcional_candidate_array((object) [
            'id' => 2,
            'nombre' => 'Y',
        ]);

        $this->assertNotNull($ungrouped);
        $this->assertArrayHasKey('id_grupo', $ungrouped);
        $this->assertNull($ungrouped['id_grupo']);
    }

    public function testBumpOpcionalCodigoKeepsShape(): void
    {
        $this->assertSame('OPC0005', tpvmod_bump_opcional_codigo('OPC0005', 0));
        $this->assertSame('OPC0006', tpvmod_bump_opcional_codigo('OPC0005', 1));
        $this->assertSame('OPC0010', tpvmod_bump_opcional_codigo('OPC0005', 5));
    }

    public function testNextOpcionalCodigoReturnsFirstFreeCandidate(): void
    {
        $exists = static fn (string $codigo): bool => in_array($codigo, ['OPC0005', 'OPC0006'], true);

        $this->assertSame('OPC0007', tpvmod_next_opcional_codigo('OPC0005', $exists, 5));
        $this->assertSame('OPC0005', tpvmod_next_opcional_codigo('OPC0005', static fn (): bool => false, 5));
    }

    public function testNextOpcionalCodigoReturnsNullAfterExhaustion(): void
    {
        $this->assertNull(tpvmod_next_opcional_codigo('OPC0005', static fn (): bool => true, 5));
    }

    public function testResolveAsociacionTargetAcceptsProducto(): void
    {
        $result = tpvmod_resolve_asociacion_target(['asociacion' => 'producto'], '');

        $this->assertTrue($result['ok']);
        $this->assertSame('producto', $result['target']);
    }

    public function testResolveAsociacionTargetRejectsFamiliaWithoutCodfamilia(): void
    {
        $result = tpvmod_resolve_asociacion_target(['asociacion' => 'familia'], '');

        $this->assertFalse($result['ok']);
        $this->assertNotSame([], $result['errors']);
        $this->assertNull($result['target']);
    }

    public function testResolveAsociacionTargetAcceptsFamiliaWithCodfamilia(): void
    {
        $result = tpvmod_resolve_asociacion_target(['asociacion' => 'familia'], 'FAM01');

        $this->assertTrue($result['ok']);
        $this->assertSame('familia', $result['target']);
    }

    // ---------------------------------------------------------------------
    // Phase 3 — dispatch, emit, save gate, payload, persist orchestration
    // ---------------------------------------------------------------------

    public function testOpcionalesAjaxDispatchRoutesOnlyTheWriteAction(): void
    {
        $_POST = [];
        $ctrl = $this->makeControllerDouble(false);
        $this->assertFalse(tpvmod_opcionales_ajax_dispatch($ctrl));

        $_POST = ['guardar_opcional_tpv' => '1'];
        $ctrl = $this->makeControllerDouble(false);
        ob_start();
        $handled = tpvmod_opcionales_ajax_dispatch($ctrl);
        $output = (string) ob_get_clean();
        $_POST = [];

        $this->assertTrue($handled);
        $this->assertStringContainsString('"ok":false', $output);
    }

    public function testOpcionalesAjaxEmitJsonWritesBodyWithoutExit(): void
    {
        ob_start();
        tpvmod_opcionales_ajax_emit_json(['ok' => true, 'codfamilia' => 'FAM01']);
        $output = (string) ob_get_clean();

        $this->assertSame('{"ok":true,"codfamilia":"FAM01"}', $output);

        $source = file_get_contents(FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_opcionales_ajax.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('application/json', $source);
    }

    public function testOpcionalesAjaxSaveRejectsInvalidCsrfAndPersistsNothing(): void
    {
        $_POST = [
            'guardar_opcional_tpv' => '1',
            'nombre' => 'Toallero',
            'tipo_precio' => 'fijo',
            'valor' => '10',
            'referencia' => 'REF1',
        ];

        $ctrl = $this->makeControllerDouble(false);

        ob_start();
        tpvmod_opcionales_ajax_save($ctrl);
        $output = (string) ob_get_clean();
        $_POST = [];

        $this->assertFalse($ctrl->template);
        $this->assertSame(
            ['ok' => false, 'errors' => ['Token CSRF inválido.']],
            json_decode($output, true)
        );
    }

    public function testOpcionalesAjaxSaveWithValidCsrfEmitsSuccessEnvelope(): void
    {
        $_POST = [
            'guardar_opcional_tpv' => '1',
            'nombre' => 'Toallero',
            'descripcion' => 'Cromo',
            'tipo_precio' => 'fijo',
            'valor' => '12.50',
            'referencia' => 'REF1',
            'asociacion' => 'producto',
        ];

        $opcional = $this->makeOpcionalDouble(['new_codigo' => 'OPC0009', 'new_id' => 12]);
        $relation = $this->makeRelationDouble();
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;
        $models = $this->modelsFactory($opcional, $relation, $familyRelation, [], 'DEF');

        $ctrl = $this->makeControllerDouble(true);

        ob_start();
        tpvmod_opcionales_ajax_save($ctrl, $models);
        $output = (string) ob_get_clean();
        $_POST = [];

        $this->assertFalse($ctrl->template);
        $this->assertSame(
            [
                'ok' => true,
                'opcional' => [
                    'id' => 12,
                    'codigo' => 'OPC0009',
                    'nombre' => 'Toallero',
                    'descripcion' => 'Cromo',
                    'precio' => 12.5,
                    'tipo_precio' => 'fijo',
                    'porcentaje' => null,
                    'grupo_id' => null,
                ],
                'codfamilia' => '',
            ],
            json_decode($output, true)
        );

        $this->assertSame(1, $opcional->saveCalls);
        $this->assertSame('OPC0009', $opcional->codigo);
        $this->assertSame(['REF1', 12, false], $relation->addArgs);
    }

    public function testOpcionalesAjaxOpcionalPayloadShape(): void
    {
        $payload = tpvmod_opcionales_ajax_opcional_payload((object) [
            'id' => 12,
            'codigo' => 'OPC0012',
            'nombre' => 'Toallero',
            'descripcion' => 'Cromo',
            'precio' => 12.5,
            'tipo_precio' => 'fijo',
            'porcentaje' => null,
            'id_grupo' => null,
        ]);

        $this->assertSame(12, $payload['id']);
        $this->assertSame('OPC0012', $payload['codigo']);
        $this->assertSame('Toallero', $payload['nombre']);
        $this->assertSame('Cromo', $payload['descripcion']);
        $this->assertSame(12.5, $payload['precio']);
        $this->assertSame('fijo', $payload['tipo_precio']);
        $this->assertNull($payload['porcentaje']);
        $this->assertNull($payload['grupo_id']);
    }

    public function testPersistReusesMatchingOpcionalWithoutSaving(): void
    {
        $opcional = $this->makeOpcionalDouble();
        $relation = $this->makeRelationDouble();
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;

        $result = tpvmod_opcionales_ajax_persist(
            $this->normalizedData('Toallero', 'fijo', 3.0, null),
            'REF1',
            'producto',
            '',
            $this->modelsFactory($opcional, $relation, $familyRelation, [[
                'id' => 7,
                'codigo' => 'OPC0007',
                'nombre' => ' Toallero ',
                'descripcion' => 'Toallero cromo',
                'precio' => 3.0,
                'tipo_precio' => 'fijo',
                'porcentaje' => null,
            ]], 'DEF')
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $opcional->saveCalls);
        $this->assertSame(1, $relation->addCalls);
        $this->assertSame(['REF1', 7, false], $relation->addArgs);
        $this->assertSame(0, $opcional->precioListaCalls);
        $this->assertSame(7, $result['opcional']['id']);
        $this->assertSame('Toallero cromo', $result['opcional']['descripcion']);
        $this->assertSame(3.0, $result['opcional']['precio']);
    }

    public function testPersistCreatesNewUngroupedOpcionalWhenOnlyCandidateIsGrouped(): void
    {
        $opcional = $this->makeOpcionalDouble(['new_codigo' => 'OPC0009', 'new_id' => 12]);
        $relation = $this->makeRelationDouble();
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;

        $result = tpvmod_opcionales_ajax_persist(
            $this->normalizedData('Toallero', 'fijo', 12.5, null),
            'REF1',
            'producto',
            '',
            $this->modelsFactory($opcional, $relation, $familyRelation, [[
                'id' => 7,
                'codigo' => 'OPC0007',
                'nombre' => ' Toallero ',
                'descripcion' => 'Toallero agrupado',
                'precio' => 3.0,
                'tipo_precio' => 'fijo',
                'porcentaje' => null,
                'id_grupo' => 4,
            ]], 'DEF')
        );

        // The grouped candidate is not reusable, so a new ungrouped opcional is created.
        $this->assertTrue($result['ok']);
        $this->assertSame(1, $opcional->saveCalls);
        $this->assertSame(12, $result['opcional']['id']);
        $this->assertNull($opcional->id_grupo);
        $this->assertNull($result['opcional']['grupo_id']);
        $this->assertSame(['REF1', 12, false], $relation->addArgs);
    }

    public function testPersistCreatesAndSavesFixedOpcionalWithListaParity(): void
    {
        $opcional = $this->makeOpcionalDouble(['new_codigo' => 'OPC0009', 'new_id' => 12]);
        $relation = $this->makeRelationDouble();
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;

        $result = tpvmod_opcionales_ajax_persist(
            $this->normalizedData('Toallero', 'fijo', 12.5, null),
            'REF1',
            'producto',
            '',
            $this->modelsFactory($opcional, $relation, $familyRelation, [], 'DEF')
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $opcional->saveCalls);
        $this->assertSame('OPC0009', $opcional->codigo);
        $this->assertTrue($opcional->activo);
        $this->assertNull($opcional->id_grupo);
        $this->assertSame(1, $opcional->precioListaCalls);
        $this->assertSame(['DEF', 12.5], $opcional->precioListaArgs);
        $this->assertSame(0, $opcional->porcentajeListaCalls);
        $this->assertSame(1, $relation->addCalls);
        $this->assertSame(['REF1', 12, false], $relation->addArgs);
        $this->assertSame(0, $opcional->familiaOnlyCalls);
    }

    public function testPersistCreatesPercentageOpcionalWithPercentageParity(): void
    {
        $opcional = $this->makeOpcionalDouble(['new_codigo' => 'OPC0010', 'new_id' => 13]);
        $relation = $this->makeRelationDouble();
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;

        $result = tpvmod_opcionales_ajax_persist(
            $this->normalizedData('Grabado', 'porcentaje', 0.0, 10.0),
            'REF1',
            'producto',
            '',
            $this->modelsFactory($opcional, $relation, $familyRelation, [], 'DEF')
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $opcional->precioListaCalls);
        $this->assertSame(1, $opcional->porcentajeListaCalls);
        $this->assertSame(['DEF', 10.0], $opcional->porcentajeListaArgs);
    }

    public function testPersistFamiliaTargetLinksFamilyOnlyWithoutPropagation(): void
    {
        $opcional = $this->makeOpcionalDouble(['new_codigo' => 'OPC0011', 'new_id' => 14]);
        $relation = $this->makeRelationDouble();
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;

        $result = tpvmod_opcionales_ajax_persist(
            $this->normalizedData('Toallero', 'fijo', 5.0, null),
            'REF1',
            'familia',
            'FAM01',
            $this->modelsFactory($opcional, $relation, $familyRelation, [], 'DEF')
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $opcional->familiaOnlyCalls);
        $this->assertSame(['FAM01'], $opcional->familiaOnlyArgs);
        $this->assertSame(0, $opcional->familiaCalls);
        $this->assertSame(1, $familyRelation->addCalls);
        $this->assertSame(0, $relation->addCalls);
    }

    public function testPersistRetryExhaustionFailsWithoutSaving(): void
    {
        $opcional = $this->makeOpcionalDouble(['existing_codigos' => ['OPC0001', 'OPC0002', 'OPC0003', 'OPC0004', 'OPC0005']]);
        $relation = $this->makeRelationDouble();
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;

        $result = tpvmod_opcionales_ajax_persist(
            $this->normalizedData('Toallero', 'fijo', 5.0, null),
            'REF1',
            'producto',
            '',
            $this->modelsFactory($opcional, $relation, $familyRelation, [], 'DEF')
        );

        $this->assertFalse($result['ok']);
        $this->assertNotSame([], $result['errors']);
        $this->assertSame(0, $opcional->saveCalls);
        $this->assertSame(0, $relation->addCalls);
        $this->assertNull($result['opcional']);
    }

    public function testPersistAssociationFailureReturnsStructuredError(): void
    {
        $opcional = $this->makeOpcionalDouble(['new_codigo' => 'OPC0020', 'new_id' => 20]);
        $relation = $this->makeRelationDouble(['add_result' => false]);
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;

        $result = tpvmod_opcionales_ajax_persist(
            $this->normalizedData('Toallero', 'fijo', 5.0, null),
            'REF1',
            'producto',
            '',
            $this->modelsFactory($opcional, $relation, $familyRelation, [], 'DEF')
        );

        $this->assertFalse($result['ok']);
        $this->assertNotSame([], $result['errors']);
        $this->assertSame(1, $opcional->saveCalls);
    }

    public function testPersistParityFailureReturnsStructuredError(): void
    {
        $opcional = $this->makeOpcionalDouble(['new_codigo' => 'OPC0021', 'new_id' => 21, 'parity_ok' => false]);
        $relation = $this->makeRelationDouble();
        $familyRelation = $this->makeFamilyRelationDouble();
        $opcional->familyRelation = $familyRelation;

        $result = tpvmod_opcionales_ajax_persist(
            $this->normalizedData('Toallero', 'fijo', 5.0, null),
            'REF1',
            'producto',
            '',
            $this->modelsFactory($opcional, $relation, $familyRelation, [], 'DEF')
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $relation->addCalls);
    }

    public function testControllerDispatchesOpcionalesBesideClienteDispatch(): void
    {
        $controller = (string) file_get_contents(FS_FOLDER . '/plugins/tpvmod/controller/tpvmod.php');

        $clientePos = strpos($controller, 'tpvmod_cliente_ajax_dispatch');
        $opcionalesPos = strpos($controller, 'tpvmod_opcionales_ajax_dispatch');

        $this->assertNotFalse($clientePos);
        $this->assertNotFalse($opcionalesPos);
        $this->assertLessThan($opcionalesPos, $clientePos);
    }

    // ---------------------------------------------------------------------
    // Helpers / doubles
    // ---------------------------------------------------------------------

    private function makeControllerDouble(bool $csrfValid): object
    {
        if (!defined('FS_LAZY_MODELS')) {
            define('FS_LAZY_MODELS', true);
        }
        if (!class_exists('fs_controller')) {
            require_once FS_FOLDER . '/base/fs_controller.php';
        }

        $controller = new class() extends \fs_controller {
            public function __construct()
            {
                // Skip full fs_controller bootstrap for DB-free tests.
            }
        };

        $property = new \ReflectionProperty(\fs_controller::class, 'csrf_valid');
        $property->setAccessible(true);
        $property->setValue($controller, $csrfValid);

        return $controller;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedData(string $nombre, string $tipo, float $precio, ?float $porcentaje): array
    {
        return [
            'nombre' => $nombre,
            'descripcion' => '',
            'tipo_precio' => $tipo,
            'precio' => $precio,
            'porcentaje' => $porcentaje,
            'activo' => true,
            'id_grupo' => null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $candidates
     */
    private function modelsFactory(object $opcional, object $relation, object $familyRelation, array $candidates, string $lista): callable
    {
        return static fn (): array => [
            'opcional' => $opcional,
            'relation' => $relation,
            'familyRelation' => $familyRelation,
            'candidates' => $candidates,
            'lista' => $lista,
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function makeOpcionalDouble(array $options = []): object
    {
        return new class($options) {
            public $id = null;
            public $codigo = '';
            public $nombre = '';
            public $descripcion = '';
            public $precio = 0.0;
            public $tipo_precio = 'fijo';
            public $porcentaje = null;
            public $activo = true;
            public $id_grupo = null;

            public int $saveCalls = 0;
            public int $precioListaCalls = 0;
            public int $porcentajeListaCalls = 0;
            public int $familiaOnlyCalls = 0;
            public int $familiaCalls = 0;
            public array $precioListaArgs = [];
            public array $porcentajeListaArgs = [];
            public array $familiaOnlyArgs = [];
            public $familyRelation = null;

            /** @param array<string, mixed> $options */
            public function __construct(private array $options)
            {
            }

            public function get_new_codigo(): string
            {
                return (string) ($this->options['new_codigo'] ?? 'OPC0001');
            }

            public function get_by_codigo($codigo): bool
            {
                return in_array((string) $codigo, $this->options['existing_codigos'] ?? [], true);
            }

            public function save(): bool
            {
                $this->saveCalls++;
                if ($this->id === null) {
                    $this->id = (int) ($this->options['new_id'] ?? 12);
                }

                return (bool) ($this->options['save_ok'] ?? true);
            }

            public function set_precio_lista($codlista, $precio): bool
            {
                $this->precioListaCalls++;
                $this->precioListaArgs = [$codlista, $precio];

                return (bool) ($this->options['parity_ok'] ?? true);
            }

            public function set_porcentaje_lista($codlista, $porcentaje): bool
            {
                $this->porcentajeListaCalls++;
                $this->porcentajeListaArgs = [$codlista, $porcentaje];

                return (bool) ($this->options['parity_ok'] ?? true);
            }

            public function add_familia_only($codfamilia): bool
            {
                $this->familiaOnlyCalls++;
                $this->familiaOnlyArgs = [$codfamilia];
                if ($this->familyRelation) {
                    $this->familyRelation->add($this->id, $codfamilia);
                }

                return true;
            }

            public function add_familia($codfamilia): bool
            {
                $this->familiaCalls++;

                return true;
            }

            /** @return list<string> */
            public function get_errors(): array
            {
                return $this->options['errors'] ?? [];
            }
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function makeRelationDouble(array $options = []): object
    {
        return new class($options) {
            public int $addCalls = 0;
            public array $addArgs = [];

            /** @param array<string, mixed> $options */
            public function __construct(private array $options)
            {
            }

            public function add($referencia, $id_opcional, bool $obligatorio = false): bool
            {
                $this->addCalls++;
                $this->addArgs = [$referencia, $id_opcional, $obligatorio];

                return (bool) ($this->options['add_result'] ?? true);
            }
        };
    }

    private function makeFamilyRelationDouble(): object
    {
        return new class() {
            public int $addCalls = 0;

            public function add($id_opcional, $codfamilia): bool
            {
                $this->addCalls++;

                return true;
            }
        };
    }
}
