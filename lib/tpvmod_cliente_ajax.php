<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Shared AJAX dispatch for TPV client modals (search, form, save).
 */

declare(strict_types=1);

require_once __DIR__ . '/tpvmod_modules.php';
require_once __DIR__ . '/tpvmod_cliente.php';

function tpvmod_cliente_ajax_ensure_models(): void
{
    require_model('cliente.php');
    require_model('direccion_cliente.php');
    require_model('grupo_clientes.php');
    if (!class_exists('grupo_descuentos', false)) {
        require_model('grupo_descuentos.php');
    }
}

/**
 * Handle TPV client modal AJAX requests. Returns true when the request was handled
 * and the controller should stop further private_core() processing.
 */
function tpvmod_cliente_ajax_dispatch(fs_controller $ctrl): bool
{
    tpvmod_cliente_ajax_ensure_models();

    if (isset($_POST['buscar_cliente_modal'])) {
        tpvmod_cliente_ajax_buscar($ctrl);
        return true;
    }

    if (isset($_REQUEST['cliente_form'])) {
        tpvmod_cliente_ajax_form($ctrl);
        return true;
    }

    if (isset($_POST['save_cliente_tpv'])) {
        tpvmod_cliente_ajax_save_cliente($ctrl);
        return true;
    }

    if (isset($_POST['save_direccion_tpv'])) {
        tpvmod_cliente_ajax_save_direccion($ctrl);
        return true;
    }

    if (isset($_POST['delete_direccion_tpv'])) {
        tpvmod_cliente_ajax_delete_direccion($ctrl);
        return true;
    }

    return false;
}

function tpvmod_cliente_ajax_buscar(fs_controller $ctrl): void
{
    $ctrl->template = 'ajax/tpv_clientes';
    $query = fs_filter_input_req('query', '');
    $ctrl->query = $query;

    $model = new cliente();
    $ctrl->results = $model->search($query);
}

function tpvmod_cliente_ajax_form(fs_controller $ctrl): void
{
    $ctrl->template = 'ajax/tpv_cliente_form';

    $cod = trim((string) (fs_filter_input_req('codcliente', '') ?? ''));
    $model = new cliente();

    if ($cod !== '') {
        $ctrl->tpv_cliente = $model->get($cod);
        if (!$ctrl->tpv_cliente) {
            $ctrl->new_error_msg('Cliente no encontrado.');
            $ctrl->tpv_cliente = new cliente();
            $ctrl->tpv_cliente_nuevo = true;
        } else {
            $ctrl->tpv_cliente_nuevo = false;
        }
    } else {
        $ctrl->tpv_cliente = new cliente();
        $ctrl->tpv_cliente->regimeniva = 'General';
        $ctrl->tpv_cliente->personafisica = true;
        $ctrl->tpv_cliente_nuevo = true;
    }

    tpvmod_cliente_ajax_load_form_catalogs($ctrl);

    if ($ctrl->tpv_cliente->codcliente) {
        $ctrl->tpv_direcciones = $ctrl->tpv_cliente->get_direcciones();
    } else {
        $ctrl->tpv_direcciones = [];
    }
}

function tpvmod_cliente_ajax_load_form_catalogs(fs_controller $ctrl): void
{
    $grupoModel = new grupo_clientes();
    $ctrl->tpv_grupos = $grupoModel->all();

    $ctrl->tpv_grupos_descuentos = [];
    if (class_exists('grupo_descuentos')) {
        $grupoDescModel = new grupo_descuentos();
        $ctrl->tpv_grupos_descuentos = $grupoDescModel->all();
    }

    $regimenes = ['General', 'Exento'];
    if ($ctrl->tpv_cliente && method_exists($ctrl->tpv_cliente, 'regimenes_iva')) {
        $regimenes = $ctrl->tpv_cliente->regimenes_iva();
    }
    $ctrl->tpv_regimenes_iva = $regimenes;
}

function tpvmod_cliente_ajax_save_cliente(fs_controller $ctrl): void
{
    $ctrl->template = false;

    if (!$ctrl->isCsrfValid()) {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Token CSRF inválido.']));
        return;
    }

    $cod = trim((string) (fs_filter_input_req('codcliente', '') ?? ''));
    $model = new cliente();
    $cliente = ($cod !== '') ? $model->get($cod) : new cliente();
    if ($cod !== '' && !$cliente) {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Cliente no encontrado.']));
        return;
    }

    tpvmod_cliente_apply_from_post($cliente, $_POST);

    if (!$cliente->nombre) {
        $cliente->razonsocial = $cliente->razonsocial ?: $cliente->nombre;
    } elseif (!$cliente->razonsocial) {
        $cliente->razonsocial = $cliente->nombre;
    }

    if (!$cliente->save()) {
        $errors = method_exists($cliente, 'get_errors') ? $cliente->get_errors() : [];
        if ($errors === []) {
            $errors = ['Error al guardar el cliente. Verifique los datos e inténtelo de nuevo.'];
        }
        foreach ($errors as $error) {
            $ctrl->new_error_msg((string) $error);
        }
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response($errors));
        return;
    }

    tpvmod_cliente_ajax_emit_json(tpvmod_cliente_json_response($cliente));
}

function tpvmod_cliente_ajax_save_direccion(fs_controller $ctrl): void
{
    $ctrl->template = false;

    if (!$ctrl->isCsrfValid()) {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Token CSRF inválido.']));
        return;
    }

    $codcliente = trim((string) (fs_filter_input_req('codcliente', '') ?? ''));
    if ($codcliente === '') {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Cliente no especificado.']));
        return;
    }

    $clienteModel = new cliente();
    $cliente = $clienteModel->get($codcliente);
    if (!$cliente) {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Cliente no encontrado.']));
        return;
    }

    $dirId = filter_input(INPUT_POST, 'dir_id', FILTER_VALIDATE_INT);
    $dirModel = new direccion_cliente();

    if ($dirId) {
        $dir = $dirModel->get($dirId);
        if (!$dir || $dir->codcliente !== $codcliente) {
            tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Dirección no encontrada.']));
            return;
        }
    } else {
        $dir = new direccion_cliente();
    }

    tpvmod_direccion_apply_from_post($dir, $_POST, $codcliente);

    if (!$dir->save()) {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Error al guardar la dirección.']));
        return;
    }

    tpvmod_cliente_ajax_emit_json([
        'ok' => true,
        'dir_id' => $dir->id,
        'codcliente' => $codcliente,
    ]);
}

function tpvmod_cliente_ajax_delete_direccion(fs_controller $ctrl): void
{
    $ctrl->template = false;

    if (!$ctrl->isCsrfValid()) {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Token CSRF inválido.']));
        return;
    }

    $codcliente = trim((string) (fs_filter_input_req('codcliente', '') ?? ''));
    $dirId = filter_input(INPUT_POST, 'dir_id', FILTER_VALIDATE_INT);

    if (!$dirId || $codcliente === '') {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Parámetros inválidos.']));
        return;
    }

    $dirModel = new direccion_cliente();
    $dir = $dirModel->get($dirId);
    if (!$dir || $dir->codcliente !== $codcliente) {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Dirección no encontrada.']));
        return;
    }

    if (!$dir->delete()) {
        tpvmod_cliente_ajax_emit_json(tpvmod_cliente_error_response(['Error al eliminar la dirección.']));
        return;
    }

    tpvmod_cliente_ajax_emit_json(['ok' => true, 'codcliente' => $codcliente]);
}

/**
 * @param array<string, mixed> $payload
 */
function tpvmod_cliente_ajax_emit_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}
