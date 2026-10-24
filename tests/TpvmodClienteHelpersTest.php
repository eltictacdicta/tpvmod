<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * TPV client helpers. The mapping authority is `clientes_core`:
 * `FSFramework\Plugins\clientes_core\ClienteForm::apply()`. The plugin keeps a
 * single seam, `tpvmod_cliente_apply()`, and these tests verify the delegation
 * instead of re-testing a private copy of the form mapping.
 */

declare(strict_types=1);

namespace Tests\Tpvmod;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_modules.php';
require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_cliente.php';

/**
 * Discount-group double registered as FSFramework\model\grupo_descuentos so
 * ClienteForm::apply() resolves groups without the DB-backed model. Same
 * strategy as plugins/clientes_core/tests/ClienteFormAuthorityTest.php.
 */
final class TpvmodGrupoDescuentosDouble
{
    public ?string $codgrupo_descuento = null;
    public ?string $nombre = null;
    public ?float $d1 = null;
    public ?float $d2 = null;
    public ?float $d3 = null;
    public ?float $d4 = null;

    /** @var array<string, TpvmodGrupoDescuentosDouble> */
    public static array $groups = [];

    public function __construct($data = false)
    {
    }

    public function get($cod)
    {
        return self::$groups[$cod] ?? false;
    }
}

/**
 * Cliente double declaring every field the shared authority maps, plus the
 * `applyGroupDiscounts()` hook the authority calls on a group change.
 */
final class TpvmodClienteDouble
{
    public string $codcliente = '000001';
    public string $nombre = '';
    public string $razonsocial = '';
    public string $tipoidfiscal = '';
    public string $cifnif = '';
    public string $telefono1 = '';
    public string $telefono2 = '';
    public string $fax = '';
    public string $email = '';
    public string $web = '';
    public string $regimeniva = '';
    public string $diaspago = '';
    public string $observaciones = '';
    public ?string $coddivisa = null;
    public bool $recargo = false;
    public bool $personafisica = false;
    public bool $debaja = false;
    public ?string $codgrupo = null;
    public ?string $codgrupo_descuento = null;
    public ?float $d1 = null;
    public ?float $d2 = null;
    public ?float $d3 = null;
    public ?float $d4 = null;
    public bool $descuentos_modified = false;

    /** @var array<int, object> */
    public array $appliedGroups = [];

    public function applyGroupDiscounts(object $grupoDescuentos): void
    {
        $this->appliedGroups[] = $grupoDescuentos;

        foreach (['d1', 'd2', 'd3', 'd4'] as $field) {
            $this->{$field} = (float) $grupoDescuentos->{$field};
        }

        $this->descuentos_modified = false;
    }

    public function getEffectiveDiscounts(): array
    {
        return [
            'd1' => (float) ($this->d1 ?? 0),
            'd2' => (float) ($this->d2 ?? 0),
            'd3' => (float) ($this->d3 ?? 0),
            'd4' => (float) ($this->d4 ?? 0),
        ];
    }
}

class TpvmodClienteHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('FSFramework\\model\\grupo_descuentos', false)) {
            class_alias(TpvmodGrupoDescuentosDouble::class, 'FSFramework\\model\\grupo_descuentos');
        }

        TpvmodGrupoDescuentosDouble::$groups = [];
    }

    // -------------------------------------------------------------------------
    // Preserved display/response helpers
    // -------------------------------------------------------------------------

    public function testCampoDisplayIncludesPhones(): void
    {
        $cliente = (object) [
            'nombre' => 'Acme SL',
            'telefono1' => '600111222',
            'telefono2' => '900333444',
        ];

        $this->assertSame(
            'Acme SL Tlf:600111222 Tlf2:900333444',
            tpvmod_cliente_campo_display($cliente)
        );
    }

    public function testTelefonoDisplayPrefersPrimary(): void
    {
        $cliente = (object) ['telefono1' => '111', 'telefono2' => '222'];
        $this->assertSame('111', tpvmod_cliente_telefono_display($cliente));

        $soloSecundario = (object) ['telefono1' => '', 'telefono2' => '222'];
        $this->assertSame('222', tpvmod_cliente_telefono_display($soloSecundario));
    }

    public function testErrorResponseShape(): void
    {
        $payload = tpvmod_cliente_error_response(['Campo obligatorio']);

        $this->assertFalse($payload['ok']);
        $this->assertSame(['Campo obligatorio'], $payload['errors']);
    }

    public function testClienteJsonResponseShape(): void
    {
        $cliente = $this->cliente();
        $cliente->codcliente = '000042';
        $cliente->nombre = 'Acme SL';
        $cliente->regimeniva = 'General';

        $payload = tpvmod_cliente_json_response($cliente);

        $this->assertTrue($payload['ok']);
        $this->assertSame('000042', $payload['codcliente']);
        $this->assertSame('Acme SL', $payload['label']);
        $this->assertSame('000042', $payload['cliente']['codcliente']);
        $this->assertSame('General', $payload['cliente']['regimeniva']);
    }

    // -------------------------------------------------------------------------
    // Delegation: empty group codes never become a fallback code
    // -------------------------------------------------------------------------

    public function testApplyDelegatesEmptyCodgrupoToNull(): void
    {
        $cliente = $this->cliente();

        $result = tpvmod_cliente_apply($cliente, [
            'nombre' => 'Sin grupo',
            'codgrupo' => '',
            'codgrupo_descuento' => '',
        ]);

        $this->assertSame($cliente, $result);
        $this->assertNull($cliente->codgrupo);
        $this->assertNotSame('000000', $cliente->codgrupo);
    }

    public function testApplyNeverWritesFallbackGroupCodes(): void
    {
        $cliente = $this->cliente();

        tpvmod_cliente_apply($cliente, [
            'codgrupo' => '  000042  ',
            'codgrupo_descuento' => '',
        ]);

        $this->assertSame('000042', $cliente->codgrupo);
        $this->assertNotSame('000000', $cliente->codgrupo);
    }

    public function testApplyMapsEmptyCodgrupoDescuentoToNull(): void
    {
        $cliente = $this->cliente();
        $cliente->codgrupo_descuento = '000001';

        tpvmod_cliente_apply($cliente, ['codgrupo_descuento' => '']);

        $this->assertNull($cliente->codgrupo_descuento);
    }

    // -------------------------------------------------------------------------
    // Delegation: group defaults and the modified flag
    // -------------------------------------------------------------------------

    public function testApplyAppliesGroupDefaultsOnFirstAssignment(): void
    {
        $this->registerGroup('000001', 30.0, 0.0, 0.0, 0.0);

        $cliente = $this->cliente();

        tpvmod_cliente_apply($cliente, [
            'nombre' => 'Nuevo',
            'codgrupo_descuento' => '000001',
            'd1' => '0',
            'd2' => '0',
            'd3' => '0',
            'd4' => '0',
        ]);

        $this->assertSame('000001', $cliente->codgrupo_descuento);
        $this->assertSame(30.0, $cliente->d1);
        $this->assertCount(1, $cliente->appliedGroups);
        $this->assertFalse($cliente->descuentos_modified);
    }

    public function testApplyDiscardsSubmittedDiscountsWhenGroupChanges(): void
    {
        $this->registerGroup('000002', 20.0, 10.0, 5.0, 0.0);

        $cliente = $this->cliente();
        $cliente->codgrupo_descuento = '000001';
        $cliente->d1 = 15.0;
        $cliente->d2 = 8.0;

        tpvmod_cliente_apply($cliente, [
            'nombre' => 'Editado',
            'codgrupo_descuento' => '000002',
            // Stale values from the previous group: must be discarded.
            'd1' => '15',
            'd2' => '8',
            'd3' => '0',
            'd4' => '0',
        ]);

        $this->assertSame('000002', $cliente->codgrupo_descuento);
        $this->assertSame(20.0, $cliente->d1);
        $this->assertSame(10.0, $cliente->d2);
        $this->assertSame(5.0, $cliente->d3);
        $this->assertSame(0.0, $cliente->d4);
        $this->assertFalse($cliente->descuentos_modified);
    }

    public function testApplyKeepsSubmittedOverridesWhenGroupIsUnchanged(): void
    {
        $this->registerGroup('000001', 10.0, 0.0, 0.0, 0.0);

        $cliente = $this->cliente();
        $cliente->codgrupo_descuento = '000001';
        $cliente->d1 = 10.0;

        tpvmod_cliente_apply($cliente, [
            'nombre' => 'Editado',
            'codgrupo_descuento' => '000001',
            'd1' => '15',
            'd2' => '0',
            'd3' => '0',
            'd4' => '0',
        ]);

        $this->assertSame('Editado', $cliente->nombre);
        $this->assertSame(15.0, $cliente->d1);
        $this->assertTrue($cliente->descuentos_modified);
        $this->assertCount(0, $cliente->appliedGroups);
    }

    public function testApplyRecomputesModifiedFlagFromScratch(): void
    {
        $this->registerGroup('000001', 10.0, 0.0, 0.0, 0.0);

        $cliente = $this->cliente();
        $cliente->codgrupo_descuento = '000001';
        // Stale value from a previous edit: the authority must recompute it.
        $cliente->descuentos_modified = true;

        tpvmod_cliente_apply($cliente, [
            'codgrupo_descuento' => '000001',
            'd1' => '10',
            'd2' => '0',
            'd3' => '0',
            'd4' => '0',
        ]);

        $this->assertFalse($cliente->descuentos_modified);
    }

    public function testApplyTreatsNullGroupDiscountsAsZero(): void
    {
        // The "Personalizado" group stores NULL d1-d4: it must not flag the
        // client as modified.
        $this->registerGroup('000000', null, null, null, null);

        $cliente = $this->cliente();
        $cliente->codgrupo_descuento = '000000';
        $cliente->d1 = 0.0;
        $cliente->d2 = 0.0;
        $cliente->d3 = 0.0;
        $cliente->d4 = 0.0;

        tpvmod_cliente_apply($cliente, [
            'codgrupo_descuento' => '000000',
            'd1' => '0',
            'd2' => '0',
            'd3' => '0',
            'd4' => '0',
        ]);

        $this->assertFalse($cliente->descuentos_modified);
    }

    public function testApplyRemovingGroupKeepsSubmittedDiscounts(): void
    {
        $cliente = $this->cliente();
        $cliente->codgrupo_descuento = '000001';
        $cliente->d1 = 15.0;
        $cliente->d2 = 8.0;

        tpvmod_cliente_apply($cliente, [
            'codgrupo_descuento' => '',
            'd1' => '15',
            'd2' => '8',
            'd3' => '0',
            'd4' => '0',
        ]);

        // Delegated semantics: with no group there is nothing to differ from,
        // so the submitted discounts survive and the flag stays false.
        $this->assertNull($cliente->codgrupo_descuento);
        $this->assertSame(15.0, $cliente->d1);
        $this->assertSame(8.0, $cliente->d2);
        $this->assertFalse($cliente->descuentos_modified);
    }

    public function testApplyLeavesAbsentKeysUntouched(): void
    {
        $cliente = $this->cliente();
        $cliente->nombre = 'Persisted';
        $cliente->cifnif = 'B00000000';
        $cliente->telefono1 = '600000000';
        $cliente->email = 'persisted@example.com';
        $cliente->regimeniva = 'General';
        $cliente->diaspago = '30';
        $cliente->observaciones = 'keep me';
        $cliente->recargo = true;
        $cliente->coddivisa = 'EUR';
        $cliente->codgrupo = '000001';
        $cliente->codgrupo_descuento = '000001';
        $cliente->d1 = 10.0;

        tpvmod_cliente_apply($cliente, ['nombre' => 'Updated']);

        $this->assertSame('Updated', $cliente->nombre);
        $this->assertSame('B00000000', $cliente->cifnif);
        $this->assertSame('600000000', $cliente->telefono1);
        $this->assertSame('persisted@example.com', $cliente->email);
        $this->assertSame('General', $cliente->regimeniva);
        $this->assertSame('30', $cliente->diaspago);
        $this->assertSame('keep me', $cliente->observaciones);
        $this->assertTrue($cliente->recargo);
        $this->assertSame('EUR', $cliente->coddivisa);
        $this->assertSame('000001', $cliente->codgrupo);
        $this->assertSame('000001', $cliente->codgrupo_descuento);
        $this->assertSame(10.0, $cliente->d1);
    }

    public function testApplyResolvesEmptyRazonSocialThroughTheAuthority(): void
    {
        $cliente = $this->cliente();

        tpvmod_cliente_apply($cliente, [
            'nombre' => 'Acme S.L.',
            'razonsocial' => '',
        ]);

        // Owned by ClienteForm: the TPV does not duplicate the fallback.
        $this->assertSame('Acme S.L.', $cliente->razonsocial);
    }

    // -------------------------------------------------------------------------
    // Degraded state: no authority, no mutation
    // -------------------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testApplyDoesNotMutateWhenAuthorityIsUnavailable(): void
    {
        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->setPsr4('FSFramework\\Plugins\\', []);
        }

        $this->assertFalse(
            class_exists('FSFramework\\Plugins\\clientes_core\\ClienteForm'),
            'Precondition: the shared authority must be unreachable'
        );

        $cliente = $this->cliente();
        $cliente->nombre = 'Persisted';
        $cliente->codgrupo = '000001';

        $result = tpvmod_cliente_apply($cliente, ['nombre' => 'Ignored']);

        $this->assertSame($cliente, $result);
        $this->assertSame('Persisted', $cliente->nombre);
        $this->assertSame('000001', $cliente->codgrupo);
        $this->assertNull($cliente->codgrupo_descuento);
    }

    // -------------------------------------------------------------------------
    // Endpoint contract: single seam, no duplicated razonsocial fallback
    // -------------------------------------------------------------------------

    public function testSaveClienteEndpointUsesTheSharedAuthoritySeam(): void
    {
        $source = (string) file_get_contents(FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_cliente_ajax.php');

        $this->assertStringContainsString('tpvmod_cliente_apply(', $source);
        $this->assertStringNotContainsString('tpvmod_cliente_apply_from_post(', $source);
        $this->assertStringNotContainsString('razonsocial', $source);
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function cliente(): TpvmodClienteDouble
    {
        return new TpvmodClienteDouble();
    }

    private function registerGroup(
        string $cod,
        ?float $d1,
        ?float $d2,
        ?float $d3,
        ?float $d4
    ): void {
        $grupo = new TpvmodGrupoDescuentosDouble();
        $grupo->codgrupo_descuento = $cod;
        $grupo->nombre = 'Grupo ' . $cod;
        $grupo->d1 = $d1;
        $grupo->d2 = $d2;
        $grupo->d3 = $d3;
        $grupo->d4 = $d4;

        TpvmodGrupoDescuentosDouble::$groups[$cod] = $grupo;
    }
}
