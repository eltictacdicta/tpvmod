<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2015-2016  Francisco Javier Trujillo Jimenez  javier.trujillo.jimenez@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('agente.php');
require_model('albaran_cliente.php');
require_model('pedido_cliente.php');
require_model('presupuesto_cliente.php');
require_model('almacen.php');
require_model('articulo.php');
require_model('cliente.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('familia.php');
require_model('forma_pago.php');
require_model('grupo_clientes.php');
require_model('impuesto.php');
require_model('serie.php');
require_model('tarifa.php');
require_once dirname(__DIR__, 3) . '/base/fs_settings.php';
require_once dirname(__DIR__) . '/lib/tpvmod_modules.php';
require_once dirname(__DIR__) . '/lib/tpvmod_opcionales.php';
require_once dirname(__DIR__) . '/lib/tpvmod_cliente_ajax.php';
require_model('direccion_cliente.php');

class tpvmod extends fs_controller
{
   public $agente;
   public $almacen;
   public $allow_delete;
   public $articulo;
   public $caja;
   public $caja_module_enabled = FALSE;
   public $cliente;
   public $cliente_s;
   public $divisa;
   public $ejercicio;
   public $equivalentes;
   public $familia;
   public $forma_pago;
   public $imprimir_descripciones;
   public $imprimir_observaciones;
   public $impuesto;
   public $results;
   public $serie;
   public $terminal;
   public $terminal_mode = 'with_terminal';
   public $auto_d_inicial;   /// TRUE when 0 terminals + without_terminal → show d_inicial form directly (spec #17)
   public $ultimas_compras;
   public $ultimas_ventas;
   public $tipo="factura";
   public $vienede;
   public $id_documento;
   public $url_documento;
   public $url_imprimir;
   public $url_listado;
   public $clientedefault="000001";
   public $documento;
   public $nom_documento;
   public $tpv_defaults = [];
   /*la opcion por defecto aqui la inicializo a facturas. Pero si esta activado
    * Los presupuestos la cambio a presupuestos en el controlador
    */


   public function __construct()
   {
      parent::__construct(__CLASS__, 'TPVMOD', 'TPV');
   }

   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);

      $this->articulo = new articulo();
      $this->cliente = new cliente();
      $this->cliente_s = $this->cliente->get($this->clientedefault);//cargo el cliente por defecto que en mi caso es cliente de contado con codcliente 000001 si no tienes cliente por defecto pon false
      $this->familia = new familia();
      $this->impuesto = new impuesto();
      $this->results = array();

      $this->caja_module_enabled = tpvmod_caja_module_enabled();

      /// tpvmod-opcional: read terminal mode once per request, before any
      /// terminal branching. Persisted via fs_settings (ini-backed config2).
      /// Ignored when facturacion_base is inactive (direct sales mode).
      $settings0 = new fs_settings();
      $candidate0 = (string) $settings0->get('tpvmod_terminal_mode', 'with_terminal');
      $this->terminal_mode = tpvmod_terminal_mode_effective($candidate0);

      if (tpvmod_cliente_ajax_dispatch($this)) {
         return;
      }
      else if( isset($_REQUEST['datoscliente']) )
      {
         $this->datos_cliente();
      }
      else if($this->query != '')
      {
         $this->new_search();
      }
      else if( isset($_REQUEST['referencia4precios']) )
      {
         $this->get_precios_articulo();
      }
      else if( isset($_REQUEST['opcionales_articulo']) )
      {
         $this->get_opcionales_articulo();
      }
      else
      {
         if( isset($_GET['edita']) )
         {
             if($_GET['id'])
             {
                 $this->id_documento=$_GET['id'];
                 $this->template="tpvmodedita";
                 if($_GET['edita']=="albaran")
                 {
                     $this->vienede="albaran";

                     $albaran=new albaran_cliente();
                     $this->documento = $albaran->get($_GET['id']);
                     if($this->documento)
                      {
                         $this->page->title = $this->documento->codigo;
                          $this->url_listado="./index.php?page=tpvmod_albaranes";
                          $urlAlbaran = tpvmod_imprimir_url('albaran');
                          $this->url_imprimir = $urlAlbaran !== null ? $urlAlbaran : './index.php?page=factura_detallada&tipo=albaran&id=';
                         $this->nom_documento=FS_ALBARANES;
                         /// cargamos el cliente
                         $this->cliente_s = $this->cliente->get($this->documento->codcliente);

                         /// comprobamos el presupuesto
                         $this->documento->full_test();
                      }
                      else
                         $this->new_error_msg("¡".FS_ALBARAN." de cliente no encontrado!");
                 }
                 elseif($_GET['edita']=="presupuesto")
                 {
                     $this->vienede="presupuesto";
                     //$this->template="tpvmodpresupuesto";
                     $presupuesto=new presupuesto_cliente();
                     $this->documento = $presupuesto->get($_GET['id']);
                     if($this->documento)
                      {
                         $this->page->title = $this->documento->codigo;
                          $this->url_listado="./index.php?page=tpvmod_presupuestos";
                          $urlPresu = tpvmod_imprimir_url('presupuesto');
                          $this->url_imprimir = $urlPresu !== null ? $urlPresu : './index.php?page=factura_detallada&tipo=presupuesto&id=';
                         /// cargamos el cliente
                         $this->nom_documento=FS_PRESUPUESTOS;
                         $this->cliente_s = $this->cliente->get($this->documento->codcliente);

                         /// comprobamos el albarán
                         $this->documento->full_test();
                      }
                      else
                         $this->new_error_msg("¡".FS_PRESUPUESTO." de cliente no encontrado!");
                 }
                 elseif($_GET['edita']=="pedido")
                 {
                     $this->vienede="pedido";
                     //$this->template="tpvmodpedido";
                     $pedido=new pedido_cliente();
                     $this->documento = $pedido->get($_GET['id']);
                     if($this->documento)
                      {
                         $this->page->title = $this->documento->codigo;
                          $this->url_listado="./index.php?page=tpvmod_pedidos";
                          $urlPedido = tpvmod_imprimir_url('pedido');
                          $this->url_imprimir = $urlPedido !== null ? $urlPedido : './index.php?page=factura_detallada&tipo=pedido&id=';
                         $this->nom_documento=FS_PEDIDOS;
                         /// cargamos el cliente
                         $this->cliente_s = $this->cliente->get($this->documento->codcliente);

                         /// comprobamos el albarán
                         $this->documento->full_test();
                      }
                      else
                         $this->new_error_msg("¡".FS_PEDIDO." de cliente no encontrado!");
                 }
                 elseif($_GET['edita']=="factura")
                 {
                     $this->vienede="factura";
                     //$this->template="tpvmodfactura";
                     $factura=new factura_cliente();
                     $this->documento = $factura->get($_GET['id']);
                     if($this->documento)
                      {
                         $this->page->title = $this->documento->codigo;
                         $this->url_listado="./index.php?page=tpvmod_facturas";
                         $urlFactura = tpvmod_imprimir_url('factura');
                         $this->url_imprimir = $urlFactura !== null ? $urlFactura : './index.php?page=factura_detallada&id=';
                         $this->nom_documento=FS_FACTURAS;
                         /// cargamos el cliente
                         $this->cliente_s = $this->cliente->get($this->documento->codcliente);

                         /// comprobamos el albarán
                         $this->documento->full_test();
                      }
                      else
                         $this->new_error_msg("¡".FS_FACTURA." de cliente no encontrado!");
                 }
                 else
                 {
                     $this->new_error_msg("No se ha pasado ningun id en el factura");
                 }
             }
             else
             {
                $this->cliente_s = $this->cliente->get($this->clientedefault);
             }
         }

         $this->agente = $this->user->get_agente();
         $this->almacen = new almacen();
         $this->divisa = new divisa();
         $this->ejercicio = new ejercicio();
         $this->forma_pago = new forma_pago();
         $this->serie = new serie();
         $this->tpv_defaults = $this->calcular_tpv_defaults();
         $this->avisar_datos_maestros_faltantes();

         $this->imprimir_descripciones = FALSE;
         if( isset($_REQUEST['imprimir_desc']) )
         {
            $this->imprimir_descripciones = TRUE;
         }

         $this->imprimir_observaciones = FALSE;
         if( isset($_REQUEST['imprimir_obs']) )
         {
            $this->imprimir_observaciones = TRUE;
         }

         if($this->agente)
         {
            $this->caja = FALSE;
            $this->terminal = FALSE;

            if( $this->caja_module_enabled )
            {
            $caja = new caja();
            $terminal0 = new terminal_caja();
            foreach($caja->all_by_agente($this->agente->codagente) as $cj)
            {
               if( $cj->abierta() )
               {
                  $this->caja = $cj;
                  $this->terminal = $terminal0->get($cj->fs_id);
                  break;
               }
            }

            if(!$this->caja)
            {
               /// tpvmod-opcional: no-terminal branch (mode=without_terminal only).
               if( $this->terminal_mode === 'without_terminal'
                  && ( isset($_GET['no_terminal'])
                     || ( isset($_POST['d_inicial'])
                        && count($terminal0->all()) === 0 ) ) )
               {
                  $this->caja = new caja();
                  $this->caja->fs_id = 0;   /// sentinel: no terminal — see openspec/changes/terminal-opcional/proposal.md §Risks
                  $this->caja->codagente = $this->agente->codagente;
                  $this->caja->dinero_inicial = floatval($_POST['d_inicial'] ?? 0);
                  $this->caja->dinero_fin     = floatval($_POST['d_inicial'] ?? 0);
                  if( $this->caja->save() )
                  {
                     $this->new_message("Caja iniciada sin terminal.");
                  }
                  else
                  {
                     $this->new_error_msg("¡Imposible guardar los datos de caja!");
                     $this->caja = FALSE;   /// fall through to pick screen
                  }
               }
               /// tpvmod-opcional: spec #17 — bypass pick screen, show d_inicial form directly
               /// when 0 terminals + without_terminal + GET (no params).
               else if( $this->terminal_mode === 'without_terminal' && count($terminal0->all()) === 0 && $_SERVER['REQUEST_METHOD'] === 'GET' )
               {
                  $this->auto_d_inicial = TRUE;
                  $this->terminal = FALSE;
               }
               else if( isset($_POST['terminal']) )
               {
                  $this->terminal = $terminal0->get($_POST['terminal']);
                  if(!$this->terminal)
                  {
                     $this->new_error_msg('Terminal no encontrado.');
                  }
                  else if( $this->terminal->disponible() )
                  {
                     $this->caja = new caja();
                     $this->caja->fs_id = $this->terminal->id;
                     $this->caja->codagente = $this->agente->codagente;
                     $this->caja->dinero_inicial = floatval($_POST['d_inicial']);
                     $this->caja->dinero_fin = floatval($_POST['d_inicial']);
                     if( $this->caja->save() )
                     {
                        $this->new_message("Caja iniciada con ".$this->show_precio($this->caja->dinero_inicial) );
                     }
                     else
                        $this->new_error_msg("¡Imposible guardar los datos de caja!");
                  }
                  else
                     $this->new_error_msg('El terminal ya no está disponible.');
               }
               else if( isset($_GET['terminal']) )
               {
                  $this->terminal = $terminal0->get($_GET['terminal']);
                  if($this->terminal)
                  {
                     $this->terminal->abrir_cajon();
                     $this->terminal->save();
                  }
                  else
                     $this->new_error_msg('Terminal no encontrado.');
               }
               /// tpvmod-opcional: d_inicial form submit in no-terminal mode (no hidden terminal field).
               else if( isset($_POST['d_inicial']) && $this->terminal_mode === 'without_terminal' && !isset($_POST['terminal']) )
               {
                  /// No-terminal caja: create with fs_id = 0 sentinel.
                  $this->caja = new caja();
                  $this->caja->fs_id = 0;   /// sentinel: no terminal — see openspec/changes/terminal-opcional/proposal.md §Risks
                  $this->caja->codagente = $this->agente->codagente;
                  $this->caja->dinero_inicial = floatval($_POST['d_inicial']);
                  $this->caja->dinero_fin = floatval($_POST['d_inicial']);
                  if( $this->caja->save() )
                  {
                     $this->new_message("Caja iniciada sin terminal con ".$this->show_precio($this->caja->dinero_inicial) );
                  }
                  else
                     $this->new_error_msg("¡Imposible guardar los datos de caja!");
               }
            }
            }

            $tpv_active = $this->caja || !$this->caja_module_enabled;

            if($tpv_active)
            {
               /*if( isset($_POST['cliente']) )
               {
                  $this->cliente_s = $this->cliente->get($_POST['cliente']);
               }
               else if($this->terminal)
               {
                  $this->cliente_s = $this->cliente->get($this->terminal->codcliente);
               }

               if(!$this->cliente_s)
               {
                  foreach($this->cliente->all() as $cli)
                  {
                     $this->cliente_s = $cli;
                     break;
                  }
               }*/

               if( $this->caja_module_enabled && isset($_GET['abrir_caja']) )
               {
                  $this->abrir_caja();
               }
               else if( $this->caja_module_enabled && isset($_GET['cerrar_caja']) )
               {
                  $this->cerrar_caja();
               }
               else if( isset($_POST['cliente']) )
               {
                  if( intval($_POST['numlineas']) > 0 )
                  {
                     $obligatorioErrors = tpvmod_validate_obligatorios_post($_POST);
                     if ($obligatorioErrors !== []) {
                        foreach ($obligatorioErrors as $msg) {
                           $this->new_error_msg($msg);
                        }
                     }
                     else if( isset($_POST['tipo']) )
			{
                            if($_POST['tipo'] == 'presupuesto')
                            {
                                $this->nuevo_presupuesto_cliente();
                            }
                            else if($_POST['tipo'] == 'pedido')
                            {
               			$this->nuevo_pedido_cliente();
                            }
                            else if($_POST['tipo'] == 'albaran')
                            {
                                $this->nuevo_albaran_cliente();
                            }
                            else if($_POST['tipo'] == 'factura')
                            {
                                $this->nueva_factura_cliente();
                            }
                            else if($_POST['tipo'] == 'guardar_albaran')
                            {
                                $this->edita_albaran_cliente();
                            }
                            else if($_POST['tipo'] == 'guardar_presupuesto')
                            {
                                $this->edita_presupuesto_cliente();
                            }
                            else if($_POST['tipo'] == 'guardar_pedido')
                            {
                                $this->edita_pedido_cliente();
                            }
                            else if($_POST['tipo'] == 'guardar_factura')
                            {
                                $this->edita_factura_cliente();
                            }
         		}
                        else
                        {
                           $this->new_error_msg('Debes seleccionar el tipo de documento a guardar.');
                        }
                  }
               }
               else if( isset($_GET['reticket']) )
               {
                  $this->reimprimir_ticket();
               }
               else if( isset($_GET['delete']) )
               {
                  $this->borrar_ticket();
               }
            }
            else if( $this->caja_module_enabled )
            {
               $terminal0 = new terminal_caja();
               $this->results = $terminal0->disponibles();
            }
         }
         else
         {
            $this->new_error_msg('No tienes un <a href="'.$this->user->url().'">agente asociado</a>
               a tu usuario, y por tanto no puedes hacer tickets.');
         }
      }
   }

   private function datos_cliente()
   {
      $this->template = FALSE;

      $cli = $this->cliente->get($_REQUEST['datoscliente']);
      if(!$cli)
      {
         header('Content-Type: application/json');
         echo json_encode(false);
         return;
      }

      header('Content-Type: application/json');
      echo json_encode(tpvmod_datos_cliente_payload($cli));
   }

   public function busca_articulos($query='', $offset=0, $codfamilia='', $con_stock=FALSE, $codfabricante='', $bloqueados=FALSE)
   {
      return $this->articulo->search($query, $offset, $codfamilia, $con_stock, $codfabricante, $bloqueados);
   }

   /**
    * Cliente payload for TPV JavaScript (includes effective D1–D4).
    *
    * @return array<string, mixed>|false
    */
   public function cliente_tpv_json()
   {
      if( !$this->cliente_s )
      {
         return FALSE;
      }

      return tpvmod_datos_cliente_payload($this->cliente_s);
   }

   /**
    * @return list<array{codimpuesto: string, descripcion: string, iva: float, recargo: float}>
    */
   public function impuestos_tpv_json()
   {
      return tpvmod_impuestos_tpv_json($this->impuesto->all());
   }
   
   function debug_to_console( $data ) {

    if ( is_array( $data ) )
        $output = "<script>console.log( 'Debug Objects: " . implode( ',', $data) . "' );</script>";
    else
        $output = "<script>console.log( 'Debug Objects: " . $data . "' );</script>";

    echo $output;
}

   private function new_search()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/tpv_recambios';
      //$this->template=false;

      $codfamilia = '';
      if( isset($_POST['codfamilia']) )
      {
         $codfamilia = $_POST['codfamilia'];
      }

      $con_stock = isset($_POST['con_stock']);
      $this->results = $this->busca_articulos($this->query, 0, $codfamilia, $con_stock);

      /// añadimos el descuento y la cantidad
      $clientDiscounts = ['d1' => 0.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0];
      if( isset($_POST['codcliente']) )
      {
         $clienteBusqueda = $this->cliente->get($_POST['codcliente']);
         if($clienteBusqueda)
         {
            $clientDiscounts = tpvmod_resolve_cliente_descuentos($clienteBusqueda);
         }
      }

      foreach($this->results as $i => $value)
      {
         $this->results[$i]->dtopor = $clientDiscounts['d1'];
         $this->results[$i]->dtopor2 = $clientDiscounts['d2'];
         $this->results[$i]->dtopor3 = $clientDiscounts['d3'];
         $this->results[$i]->dtopor4 = $clientDiscounts['d4'];
         $this->results[$i]->cantidad = 1;
         $this->results[$i]->pvp_con_dto = $value->pvp * tpvmod_calc_due_multiplier($clientDiscounts);
      }

      /// ejecutamos las funciones de las extensiones
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'function' AND $ext->params == 'new_search')
         {
            $name = $ext->text;
            $name($this->db, $this->results);
         }
      }

      $cliente = $this->cliente->get($_POST['codcliente']);
      if($cliente)
      {
         if($cliente->regimeniva == 'Exento')
         {
            foreach($this->results as $i => $value)
               $this->results[$i]->iva = 0;
         }

         if($cliente->codgrupo)
         {
            $grupo0 = new grupo_clientes();
            $tarifa0 = new tarifa();

            $grupo = $grupo0->get($cliente->codgrupo);
            if($grupo)
            {
               $tarifa = $tarifa0->get($grupo->codtarifa);
               if($tarifa)
               {
                  $tarifa->set_precios($this->results);
               }
            }
         }
      }
      //header('Content-Type: application/json');
      //echo json_encode($this->results);
   }



   private function get_precios_articulo()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/tpv_cambios_precios';
      $this->articulo = $this->articulo->get($_REQUEST['referencia4precios']);
   }

   private function get_opcionales_articulo()
   {
      $this->template = false;

      $referencia = trim((string) filter_input(INPUT_GET, 'opcionales_articulo', FILTER_UNSAFE_RAW));
      if ($referencia === '') {
         $referencia = trim((string) filter_input(INPUT_POST, 'opcionales_articulo', FILTER_UNSAFE_RAW));
      }

      $pvpRaw = filter_input(INPUT_GET, 'pvp', FILTER_UNSAFE_RAW);
      if ($pvpRaw === null || $pvpRaw === false) {
         $pvpRaw = filter_input(INPUT_POST, 'pvp', FILTER_UNSAFE_RAW);
      }
      $pvp = (float) str_replace(',', '.', (string) ($pvpRaw ?? '0'));

      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(tpvmod_opcionales_for_articulo($referencia, $pvp));
      exit;
   }

   public function get_tarifas_articulo($ref)
   {
      $tarlist = array();
      $articulo = new articulo();
      $tarifa = new tarifa();

      foreach($tarifa->all() as $tar)
      {
         $art = $articulo->get($ref);
         if($art)
         {
            $art->dtopor = 0;
            $aux = array($art);
            $tar->set_precios($aux);
            $tarlist[] = $aux[0];
         }
      }

      return $tarlist;
   }




   private function nuevo_presupuesto_cliente()
   {
      $continuar = TRUE;

      $cliente = $this->cliente->get($_POST['cliente']);
      if( !$cliente )
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }

      $ctx = $this->cargar_contexto_documento($continuar);
      $almacen = $ctx['almacen'];
      $ejercicio = $ctx['ejercicio'];
      $serie = $ctx['serie'];
      $forma_pago = $ctx['forma_pago'];
      $divisa = $ctx['divisa'];

      $presupuesto = new presupuesto_cliente();

      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="'.$presupuesto->url().'">Presupuestos</a>
               para ver si el presupuesto se ha guardado correctamente.');
         $continuar = FALSE;
      }

      if($continuar)
      {
         $presupuesto->fecha = $this->today();
         $presupuesto->finoferta = date("Y-m-d", strtotime($_POST['fecha']." +30 days"));
         $presupuesto->codalmacen = $almacen->codalmacen;
         $presupuesto->codejercicio = $ejercicio->codejercicio;
         $presupuesto->codserie = $serie->codserie;
         $presupuesto->codpago = $forma_pago->codpago;
         $presupuesto->coddivisa = $divisa->coddivisa;
         $presupuesto->tasaconv = $divisa->tasaconv;
         $presupuesto->codagente = $this->agente->codagente;
         $presupuesto->observaciones = $_POST['observaciones'];
         $presupuesto->numero2 = $_POST['numero2'];
         $presupuesto->irpf = $serie->irpf;
         $presupuesto->porcomision = $this->agente->porcomision;

         tpvmod_aplicar_cliente_a_documento($presupuesto, $cliente);

         if( $presupuesto->save() )
         {
            $art0 = new articulo();
            $n = intval($_POST['numlineas']);
            for($i = 1; $i <= $n; $i++)
            {
               if( isset($_POST['referencia_'.$i]) || isset($_POST['desc_'.$i]) )
               {
                   $linea = new linea_presupuesto_cliente();
                     $linea->idpresupuesto = $presupuesto->idpresupuesto;
                     $linea->descripcion = $_POST['desc_'.$i];
                     $articulo = $art0->get($_POST['referencia_'.$i]);
                     if($articulo)
                     {
                        $linea->referencia = $articulo->referencia;
                     }
                     if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                     {
                        $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$i]);
                        if($imp0)
                        {
                           $linea->codimpuesto = $imp0->codimpuesto;
                           $linea->iva = floatval($_POST['iva_'.$i]);
                           $linea->recargo = floatval($_POST['recargo_'.$i]);
                        }
                        else
                        {
                           $linea->iva = floatval($_POST['iva_'.$i]);
                           $linea->recargo = floatval($_POST['recargo_'.$i]);
                        }
                     }

                     if($linea->iva > 0)
                        $linea->irpf = $presupuesto->irpf;

                     tpvmod_populate_linea_descuentos(
                        $linea,
                        $cliente,
                        floatval($_POST['cantidad_'.$i]),
                        floatval($_POST['pvp_'.$i])
                     );

                     tpvmod_apply_line_orden($linea, $i, $n);
                     if( $linea->save() )
                     {
                        $presupuesto->neto += $linea->pvptotal;
                        $presupuesto->totaliva += ($linea->pvptotal * $linea->iva/100);
                        $presupuesto->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                        $presupuesto->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                     }
                     else
                     {
                        $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                        $continuar = FALSE;
                     }

               }
            }

            if($continuar)
            {
               /// redondeamos
               $presupuesto->neto = round($presupuesto->neto, FS_NF0);
               $presupuesto->totaliva = round($presupuesto->totaliva, FS_NF0);
               $presupuesto->totalirpf = round($presupuesto->totalirpf, FS_NF0);
               $presupuesto->totalrecargo = round($presupuesto->totalrecargo, FS_NF0);
               $presupuesto->total = $presupuesto->neto + $presupuesto->totaliva - $presupuesto->totalirpf + $presupuesto->totalrecargo;

					if( $presupuesto->save() )
               {
                  $this->new_message(tpvmod_documento_guardado_message('presupuesto', $presupuesto->idpresupuesto, FS_PRESUPUESTO));
                  $this->new_change(ucfirst(FS_PRESUPUESTO).' a Cliente '.$presupuesto->codigo, $presupuesto->url(), TRUE);
				  $this->cliente_s = $this->cliente->get($this->clientedefault);//reseteo el cliente
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$presupuesto->url()."'>".FS_PRESUPUESTO."</a>!");
            }
            else if( $presupuesto->delete() )
            {
               $this->new_message(ucfirst(FS_PRESUPUESTO)." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$presupuesto->url()."'>".FS_PRESUPUESTO."</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar el ".FS_PRESUPUESTO."!");
      }
   }

   private function nueva_factura_cliente()
   {
      $continuar = TRUE;
      $cliente = $this->cliente->get($_POST['cliente']);
      if( !$cliente )
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }

      $ctx = $this->cargar_contexto_documento($continuar);
      $almacen = $ctx['almacen'];
      $ejercicio = $ctx['ejercicio'];
      $serie = $ctx['serie'];
      $forma_pago = $ctx['forma_pago'];
      $divisa = $ctx['divisa'];

      $factura = new factura_cliente();



      if($continuar)
      {
         $factura->fecha = $this->today();
         $factura->codalmacen = $almacen->codalmacen;
         $factura->codejercicio = $ejercicio->codejercicio;
         $factura->codserie = $serie->codserie;
         $factura->codpago = $forma_pago->codpago;
         $factura->coddivisa = $divisa->coddivisa;
         $factura->tasaconv = $divisa->tasaconv;
         $factura->codagente = $this->agente->codagente;
         $factura->observaciones = $_POST['observaciones'];
         $factura->numero2 = $_POST['numero2'];
         $factura->irpf = $serie->irpf;
         $factura->porcomision = $this->agente->porcomision;

         tpvmod_aplicar_cliente_a_documento($factura, $cliente);

         if( $factura->save() )
         {
            $art0 = new articulo();
            $n = floatval($_POST['numlineas']);
            for($i = 1; $i <= $n; $i++)
            {
                     $linea = new linea_factura_cliente();
                     $linea->idfactura = $factura->idfactura;
                     if( isset($_POST['referencia_'.$i]) )
                     {
                        $articulo = $art0->get($_POST['referencia_'.$i]);
                        if($articulo)
                        {
                            $linea->referencia = $articulo->referencia;
                        }
                     }
                     $linea->descripcion = $_POST['desc_'.$i];

                     if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                     {
                        $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$i]);
                        if($imp0)
                        {
                           $linea->codimpuesto = $imp0->codimpuesto;
                           $linea->iva = floatval($_POST['iva_'.$i]);
                           $linea->recargo = floatval($_POST['recargo_'.$i]);
                        }
                        else
                        {
                           $linea->iva = floatval($_POST['iva_'.$i]);
                           $linea->recargo = floatval($_POST['recargo_'.$i]);
                        }
                     }

                     if($linea->iva > 0)
                        $linea->irpf = $factura->irpf;

                     tpvmod_populate_linea_descuentos(
                        $linea,
                        $cliente,
                        floatval($_POST['cantidad_'.$i]),
                        floatval($_POST['pvp_'.$i])
                     );

                     tpvmod_apply_line_orden($linea, $i, (int) $n);
                     if( $linea->save() )
                     {
                        /// descontamos del stock
                        if($articulo)
                            $articulo->sum_stock($factura->codalmacen, 0 - $linea->cantidad);

                        $factura->neto += $linea->pvptotal;
                        $factura->totaliva += ($linea->pvptotal * $linea->iva/100);
                        $factura->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                        $factura->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                     }
                     else
                     {
                        $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                        $continuar = FALSE;
                     }
            }

            if($continuar)
            {
               /// redondeamos
               $factura->neto = round($factura->neto, FS_NF0);
               $factura->totaliva = round($factura->totaliva, FS_NF0);
               $factura->totalirpf = round($factura->totalirpf, FS_NF0);
               $factura->totalrecargo = round($factura->totalrecargo, FS_NF0);
               $factura->total = $factura->neto + $factura->totaliva - $factura->totalirpf + $factura->totalrecargo;

	       if( $factura->save() )
               {
                  $factura->get_lineas_iva();
                  $this->new_message(tpvmod_documento_guardado_message('factura', $factura->idfactura, 'Factura', true));
                  $this->new_change('Factura Cliente '.$factura->codigo, $factura->url(), TRUE);
		  $this->cliente_s = $this->cliente->get($this->clientedefault);
               }
               else
                  $this->new_error_msg("¡Imposible actualizar la <a href='".$factura->url()."'>Factura</a>!");
            }
            else if( $factura->delete() )
            {
               $this->new_message("Factura eliminada correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar la <a href='".$factura->url()."'>Factura</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar la Factura!");
      }
   }

   private function nuevo_pedido_cliente()
   {
      $continuar = TRUE;

      $cliente = $this->cliente->get($_POST['cliente']);
      if( !$cliente )
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }

      $ctx = $this->cargar_contexto_documento($continuar);
      $almacen = $ctx['almacen'];
      $ejercicio = $ctx['ejercicio'];
      $serie = $ctx['serie'];
      $forma_pago = $ctx['forma_pago'];
      $divisa = $ctx['divisa'];

      $pedido = new pedido_cliente();

      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="'.$pedido->url().'">Pedidos</a>
               para ver si el pedido se ha guardado correctamente.');
         $continuar = FALSE;
      }

      if($continuar)
      {

         $pedido->fecha = $this->today();
         $pedido->codalmacen = $almacen->codalmacen;
         $pedido->codejercicio = $ejercicio->codejercicio;
         $pedido->codserie = $serie->codserie;
         $pedido->codpago = $forma_pago->codpago;
         $pedido->coddivisa = $divisa->coddivisa;
         $pedido->tasaconv = $divisa->tasaconv;
         $pedido->codagente = $this->agente->codagente;
         $pedido->observaciones = $_POST['observaciones'];
         $pedido->numero2 = $_POST['numero2'];
         $pedido->irpf = $serie->irpf;
         $pedido->porcomision = $this->agente->porcomision;

         tpvmod_aplicar_cliente_a_documento($pedido, $cliente);

         if( $pedido->save() )
         {
            $art0 = new articulo();
            $n = floatval($_POST['numlineas']);
            for($i = 1; $i <= $n; $i++)
            {

                 $linea = new linea_pedido_cliente();
                 $linea->idpedido = $pedido->idpedido;
                 if( isset($_POST['referencia_'.$i]) )
                 {
                    $articulo = $art0->get($_POST['referencia_'.$i]);
                    if($articulo)
                    {
                        $linea->referencia = $articulo->referencia;
                    }
                 }
                 $linea->descripcion = $_POST['desc_'.$i];

                 if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                 {
                    $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$i]);
                    if($imp0)
                    {
                       $linea->codimpuesto = $imp0->codimpuesto;
                       $linea->iva = floatval($_POST['iva_'.$i]);
                       $linea->recargo = floatval($_POST['recargo_'.$i]);
                    }
                    else
                    {
                       $linea->iva = floatval($_POST['iva_'.$i]);
                       $linea->recargo = floatval($_POST['recargo_'.$i]);
                    }
                 }

                 if($linea->iva > 0)
                    $linea->irpf = $pedido->irpf;
                 //corregido de lo pasado en ventas
                 tpvmod_populate_linea_descuentos(
                    $linea,
                    $cliente,
                    floatval($_POST['cantidad_'.$i]),
                    floatval($_POST['pvp_'.$i])
                 );

                 tpvmod_apply_line_orden($linea, $i, (int) $n);
                 if( $linea->save() )
                 {
                    $pedido->neto += $linea->pvptotal;
                    $pedido->totaliva += ($linea->pvptotal * $linea->iva/100);
                    $pedido->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                    $pedido->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                 }
                 else
                 {
                    $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                    $continuar = FALSE;
                 }

            }

            if($continuar)
            {
               /// redondeamos
               $pedido->neto = round($pedido->neto, FS_NF0);
               $pedido->totaliva = round($pedido->totaliva, FS_NF0);
               $pedido->totalirpf = round($pedido->totalirpf, FS_NF0);
               $pedido->totalrecargo = round($pedido->totalrecargo, FS_NF0);
               $pedido->total = $pedido->neto + $pedido->totaliva - $pedido->totalirpf + $pedido->totalrecargo;

                if( $pedido->save() )
               {
                  $this->new_message(tpvmod_documento_guardado_message('pedido', $pedido->idpedido, FS_PEDIDO));
                  $this->new_change(ucfirst(FS_PEDIDO)." a Cliente ".$pedido->codigo, $pedido->url(), TRUE);
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$pedido->url()."'>".FS_PEDIDO."</a>!");
            }
            else if( $pedido->delete() )
            {
               $this->new_message(ucfirst(FS_PEDIDO)." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$pedido->url()."'>".FS_PEDIDO."</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar el ".FS_PEDIDO."!");
      }
   }

   private function nuevo_albaran_cliente()
   {
      $continuar = TRUE;
      $cliente = $this->cliente->get($_POST['cliente']);
      if( !$cliente )
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      $ctx = $this->cargar_contexto_documento($continuar);
      $almacen = $ctx['almacen'];
      $ejercicio = $ctx['ejercicio'];
      $serie = $ctx['serie'];
      $forma_pago = $ctx['forma_pago'];
      $divisa = $ctx['divisa'];

      if( isset($_POST['imprimir_desc']) )
      {
         $this->imprimir_descripciones = TRUE;
         setcookie('imprimir_desc', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_descripciones = FALSE;
         setcookie('imprimir_desc', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      if( isset($_POST['imprimir_obs']) )
      {
         $this->imprimir_observaciones = TRUE;
         setcookie('imprimir_obs', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_observaciones = FALSE;
         setcookie('imprimir_obs', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      $albaran = new albaran_cliente();

      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$albaran->url().'">'.FS_ALBARANES.'</a>
               para ver si el '.FS_ALBARAN.' se ha guardado correctamente.');
         $continuar = FALSE;
      }

      if( $continuar )
      {
         $albaran->fecha = $this->today();
         $albaran->codalmacen = $almacen->codalmacen;
         $albaran->codejercicio = $ejercicio->codejercicio;
         $albaran->codserie = $serie->codserie;
         $albaran->codpago = $forma_pago->codpago;
         $albaran->coddivisa = $divisa->coddivisa;
         $albaran->tasaconv = $divisa->tasaconv;
         $albaran->codagente = $this->agente->codagente;
         $albaran->observaciones = $_POST['observaciones'];
         $albaran->numero2 = $_POST['numero2'];
         $albaran->irpf = $serie->irpf;
         $albaran->porcomision = $this->agente->porcomision;

         tpvmod_aplicar_cliente_a_documento($albaran, $cliente);

         if( $albaran->save() )
         {
            $n = floatval($_POST['numlineas']);
            for($i = 1; $i <= $n; $i++)
            {
               if( isset($_POST['referencia_'.$i]) )
               {

                 $linea = new linea_albaran_cliente();
                 $linea->idalbaran = $albaran->idalbaran;
                 $articulo = $this->articulo->get($_POST['referencia_'.$i]);
                 if($articulo)
                 {
                    $linea->referencia = $articulo->referencia;
                 }
                 $linea->descripcion = $_POST['desc_'.$i];

                 if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                 {
                    $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$i]);
                    if($imp0)
                    {
                       $linea->codimpuesto = $imp0->codimpuesto;
                       $linea->iva = floatval($_POST['iva_'.$i]);
                       $linea->recargo = floatval($_POST['recargo_'.$i]);
                    }
                    else
                    {
                       $linea->iva = floatval($_POST['iva_'.$i]);
                       $linea->recargo = floatval($_POST['recargo_'.$i]);
                    }
                 }


                 $linea->irpf = floatval($_POST['irpf_'.$i]);
                 tpvmod_populate_linea_descuentos(
                    $linea,
                    $cliente,
                    floatval($_POST['cantidad_'.$i]),
                    floatval($_POST['pvp_'.$i])
                 );

                 tpvmod_apply_line_orden($linea, $i, (int) $n);
                 if( $linea->save() )
                 {
                    /// descontamos del stock
                    if($articulo)
                        $articulo->sum_stock($albaran->codalmacen, 0 - $linea->cantidad);

                    $albaran->neto += $linea->pvptotal;
                    $albaran->totaliva += ($linea->pvptotal * $linea->iva/100);
                    $albaran->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                    $albaran->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                 }
                 else
                 {
                    $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                    $continuar = FALSE;
                 }

               }
            }

            if($continuar)
            {
               /// redondeamos
               $albaran->neto = round($albaran->neto, FS_NF0);
               $albaran->totaliva = round($albaran->totaliva, FS_NF0);
               $albaran->totalirpf = round($albaran->totalirpf, FS_NF0);
               $albaran->totalrecargo = round($albaran->totalrecargo, FS_NF0);
               $albaran->total = $albaran->neto + $albaran->totaliva - $albaran->totalirpf + $albaran->totalrecargo;

               if( abs(floatval($_POST['tpv_total2']) - $albaran->total) >= .02 )
               {
                  $this->new_error_msg("El total difiere entre la vista y el controlador (".$_POST['tpv_total2'].
                          " frente a ".$albaran->total."). Debes informar del error.");
                  $albaran->delete();
               }
               else if( $albaran->save() )
               {
                  $this->new_message(tpvmod_documento_guardado_message('albaran', $albaran->idalbaran, FS_ALBARAN));
	          $this->cliente_s = $this->cliente->get($this->clientedefault);


                  /// actualizamos la caja
                  $this->registrar_venta_en_caja($albaran->total);
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$albaran->url()."'>".FS_ALBARAN."</a>!");
            }
            else if( $albaran->delete() )
            {
               $this->new_message(FS_ALBARAN." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$albaran->url()."'>".FS_ALBARAN."</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar el ".FS_ALBARAN."!");
      }
   }

      private function edita_albaran_cliente()
   {
      $continuar = TRUE;
      $this->cliente_s = $this->cliente->get($_POST['cliente']);
      if( !$this->cliente_s)
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      $ctx = $this->cargar_contexto_documento($continuar);
      $almacen = $ctx['almacen'];
      $ejercicio = $ctx['ejercicio'];
      $serie = $ctx['serie'];
      $forma_pago = $ctx['forma_pago'];
      $divisa = $ctx['divisa'];

      if( isset($_POST['imprimir_desc']) )
      {
         $this->imprimir_descripciones = TRUE;
         setcookie('imprimir_desc', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_descripciones = FALSE;
         setcookie('imprimir_desc', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      if( isset($_POST['imprimir_obs']) )
      {
         $this->imprimir_observaciones = TRUE;
         setcookie('imprimir_obs', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_observaciones = FALSE;
         setcookie('imprimir_obs', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      $albaran = new albaran_cliente();
      $albaran = $albaran->get($_POST['id']);


      if( $continuar )
      {
         $albaran->fecha = $_POST['fecha'];
         $albaran->codalmacen = $almacen->codalmacen;
         $albaran->codejercicio = $ejercicio->codejercicio;
         $albaran->codserie = $serie->codserie;
         $albaran->codpago = $forma_pago->codpago;
         $albaran->coddivisa = $divisa->coddivisa;
         $albaran->tasaconv = $divisa->tasaconv;
         $albaran->codagente = $this->agente->codagente;
         $albaran->observaciones = $_POST['observaciones'];
         $albaran->numero2 = $_POST['numero2'];
         $albaran->irpf = $serie->irpf;
         $albaran->porcomision = $this->agente->porcomision;

         tpvmod_aplicar_cliente_a_documento($albaran, $this->cliente_s);
            if( isset($_POST['numlineas']) )
             {
                $numlineas = intval($_POST['numlineas']);

                $albaran->neto = 0;
                $albaran->totaliva = 0;
                $albaran->totalirpf = 0;
                $albaran->totalrecargo = 0;
                $lineas = $albaran->get_lineas();
                $articulo = new articulo();

                /// eliminamos las líneas que no encontremos en el $_POST
                foreach($lineas as $l)
                {
                   $encontrada = FALSE;
                   for($num = 0; $num <= $numlineas; $num++)
                   {
                      if( isset($_POST['idlinea_'.$num]) )
                      {
                         if($l->idlinea == intval($_POST['idlinea_'.$num]))
                         {
                            $encontrada = TRUE;
                            break;
                         }
                      }
                   }
                   if( !$encontrada )
                   {
                      if( $l->delete() )
                      {
                         /// actualizamos el stock
                         $art0 = $articulo->get($l->referencia);
                         if($art0)
                            $art0->sum_stock($albaran->codalmacen, $l->cantidad);
                      }
                      else
                         $this->new_error_msg("¡Imposible eliminar la línea del artículo ".$l->referencia."!");
                   }
                }

                /// modificamos y/o añadimos las demás líneas
                $linePosition = 0;
                for($num = 0; $num <= $numlineas; $num++)
                {
                   $encontrada = FALSE;
                   if( isset($_POST['idlinea_'.$num]) )
                   {
                      $linePosition++;
                      foreach($lineas as $k => $value)
                      {
                         /// modificamos la línea
                         if($value->idlinea == intval($_POST['idlinea_'.$num]))
                         {

                            $encontrada = TRUE;
                            $cantidad_old = $value->cantidad;
                            tpvmod_populate_linea_descuentos(
                               $lineas[$k],
                               $this->cliente_s,
                               floatval($_POST['cantidad_'.$num]),
                               floatval($_POST['pvp_'.$num])
                            );
                            $lineas[$k]->descripcion = $_POST['desc_'.$num];

                            $lineas[$k]->codimpuesto = NULL;
                            $lineas[$k]->iva = 0;
                            $lineas[$k]->recargo = 0;
                            $lineas[$k]->irpf = floatval($_POST['irpf_'.$num]);
                            if( !$serie->siniva AND $this->cliente_s->regimeniva != 'Exento' )
                            {
                               $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                               if($imp0)
                                  $lineas[$k]->codimpuesto = $imp0->codimpuesto;

                               $lineas[$k]->iva = floatval($_POST['iva_'.$num]);
                               $lineas[$k]->recargo = floatval($_POST['recargo_'.$num]);
                            }

                            tpvmod_apply_line_orden($lineas[$k], $linePosition, $numlineas);
                            if( $lineas[$k]->save() )
                            {
                               $albaran->neto += $value->pvptotal;
                               $albaran->totaliva += $value->pvptotal * $value->iva/100;
                               $albaran->totalirpf += $value->pvptotal * $value->irpf/100;
                               $albaran->totalrecargo += $value->pvptotal * $value->recargo/100;

                               if($lineas[$k]->cantidad != $cantidad_old)
                               {
                                  /// actualizamos el stock
                                  $art0 = $articulo->get($value->referencia);
                                  if($art0)
                                     $art0->sum_stock($albaran->codalmacen, $cantidad_old - $lineas[$k]->cantidad);
                               }
                            }
                            else
                               $this->new_error_msg("¡Imposible modificar la línea del artículo ".$value->referencia."!");
                            break;
                         }
                      }

                      /// añadimos la línea
                      if(!$encontrada AND intval($_POST['idlinea_'.$num]) == -1 AND isset($_POST['referencia_'.$num]))
                      {
                         $linea = new linea_albaran_cliente();
                         $linea->idalbaran = $albaran->idalbaran;
                         $linea->descripcion = $_POST['desc_'.$num];

                         if( !$serie->siniva AND $this->cliente_s->regimeniva != 'Exento' )
                         {
                            $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                            if($imp0)
                               $linea->codimpuesto = $imp0->codimpuesto;

                            $linea->iva = floatval($_POST['iva_'.$num]);
                            $linea->recargo = floatval($_POST['recargo_'.$num]);
                         }

                         $linea->irpf = floatval($_POST['irpf_'.$num]);
                         tpvmod_populate_linea_descuentos(
                            $linea,
                            $this->cliente_s,
                            floatval($_POST['cantidad_'.$num]),
                            floatval($_POST['pvp_'.$num])
                         );

                         $art0 = $articulo->get( $_POST['referencia_'.$num] );
                         if($art0)
                         {
                            $linea->referencia = $art0->referencia;
                         }

                         tpvmod_apply_line_orden($linea, $linePosition, $numlineas);
                         if( $linea->save() )
                         {
                            if($art0)
                            {
                               /// actualizamos el stock
                               $art0->sum_stock($albaran->codalmacen, 0 - $linea->cantidad);
                            }

                            $albaran->neto += $linea->pvptotal;
                            $albaran->totaliva += $linea->pvptotal * $linea->iva/100;
                            $albaran->totalirpf += $linea->pvptotal * $linea->irpf/100;
                            $albaran->totalrecargo += $linea->pvptotal * $linea->recargo/100;
                         }
                         else
                            $this->new_error_msg("¡Imposible guardar la línea del artículo ".$linea->referencia."!");
                      }
                   }
                }


             }



            if($continuar)
            {
               /// redondeamos
               $albaran->neto = round($albaran->neto, FS_NF0);
               $albaran->totaliva = round($albaran->totaliva, FS_NF0);
               $albaran->totalirpf = round($albaran->totalirpf, FS_NF0);
               $albaran->totalrecargo = round($albaran->totalrecargo, FS_NF0);
               $albaran->total = $albaran->neto + $albaran->totaliva - $albaran->totalirpf + $albaran->totalrecargo;

               if( $albaran->save() )
               {
                  $this->new_message(tpvmod_documento_guardado_message('albaran', $albaran->idalbaran, FS_ALBARAN));



                  /// actualizamos la caja
                  $this->registrar_venta_en_caja($albaran->total);
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$albaran->url()."'>".FS_ALBARAN."</a>!");
            }
            else if( $albaran->delete() )
            {
               $this->new_message(FS_ALBARAN." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$albaran->url()."'>".FS_ALBARAN."</a>!");
      }
      $this->cliente_s = $this->cliente->get($this->clientedefault);
   }

   private function edita_pedido_cliente()
   {
      $continuar = TRUE;
      $this->cliente_s = $this->cliente->get($_POST['cliente']);
      if( !$this->cliente_s)
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      $ctx = $this->cargar_contexto_documento($continuar);
      $almacen = $ctx['almacen'];
      $ejercicio = $ctx['ejercicio'];
      $serie = $ctx['serie'];
      $forma_pago = $ctx['forma_pago'];
      $divisa = $ctx['divisa'];

      if( isset($_POST['imprimir_desc']) )
      {
         $this->imprimir_descripciones = TRUE;
         setcookie('imprimir_desc', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_descripciones = FALSE;
         setcookie('imprimir_desc', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      if( isset($_POST['imprimir_obs']) )
      {
         $this->imprimir_observaciones = TRUE;
         setcookie('imprimir_obs', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_observaciones = FALSE;
         setcookie('imprimir_obs', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      $pedido = new pedido_cliente();
      $pedido = $pedido->get($_POST['id']);


      if( $continuar )
      {
         $pedido->fecha = $_POST['fecha'];
         $pedido->codalmacen = $almacen->codalmacen;
         $pedido->codejercicio = $ejercicio->codejercicio;
         $pedido->codserie = $serie->codserie;
         $pedido->codpago = $forma_pago->codpago;
         $pedido->coddivisa = $divisa->coddivisa;
         $pedido->tasaconv = $divisa->tasaconv;
         $pedido->codagente = $this->agente->codagente;
         $pedido->observaciones = $_POST['observaciones'];
         $pedido->numero2 = $_POST['numero2'];
         $pedido->irpf = $serie->irpf;
         $pedido->porcomision = $this->agente->porcomision;

         tpvmod_aplicar_cliente_a_documento($pedido, $this->cliente_s);
            if( isset($_POST['numlineas']) )
             {
                $numlineas = intval($_POST['numlineas']);

                $pedido->neto = 0;
                $pedido->totaliva = 0;
                $pedido->totalirpf = 0;
                $pedido->totalrecargo = 0;
                $lineas = $pedido->get_lineas();
                $articulo = new articulo();

                /// eliminamos las líneas que no encontremos en el $_POST
                foreach($lineas as $l)
                {
                   $encontrada = FALSE;
                   for($num = 0; $num <= $numlineas; $num++)
                   {
                      if( isset($_POST['idlinea_'.$num]) )
                      {
                         if($l->idlinea == intval($_POST['idlinea_'.$num]))
                         {
                            $encontrada = TRUE;
                            break;
                         }
                      }
                   }
                   if( !$encontrada )
                   {
                      if( !$l->delete() )
                         $this->new_error_msg("¡Imposible eliminar la línea del artículo ".$l->referencia."!");
                   }
                }

                /// modificamos y/o añadimos las demás líneas
                $linePosition = 0;
                for($num = 0; $num <= $numlineas; $num++)
                {
                   $encontrada = FALSE;
                   if( isset($_POST['idlinea_'.$num]) )
                   {
                      $linePosition++;
                      foreach($lineas as $k => $value)
                      {
                         /// modificamos la línea
                         if($value->idlinea == intval($_POST['idlinea_'.$num]))
                         {

                            $encontrada = TRUE;
                            $cantidad_old = $value->cantidad;
                            tpvmod_populate_linea_descuentos(
                               $lineas[$k],
                               $this->cliente_s,
                               floatval($_POST['cantidad_'.$num]),
                               floatval($_POST['pvp_'.$num])
                            );
                            $lineas[$k]->descripcion = $_POST['desc_'.$num];

                            $lineas[$k]->codimpuesto = NULL;
                            $lineas[$k]->iva = 0;
                            $lineas[$k]->recargo = 0;
                            $lineas[$k]->irpf = floatval($_POST['irpf_'.$num]);
                            if( !$serie->siniva AND $this->cliente_s->regimeniva != 'Exento' )
                            {
                               $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                               if($imp0)
                                  $lineas[$k]->codimpuesto = $imp0->codimpuesto;

                               $lineas[$k]->iva = floatval($_POST['iva_'.$num]);
                               $lineas[$k]->recargo = floatval($_POST['recargo_'.$num]);
                            }

                            tpvmod_apply_line_orden($lineas[$k], $linePosition, $numlineas);
                            if( $lineas[$k]->save() )
                            {
                               $pedido->neto += $value->pvptotal;
                               $pedido->totaliva += $value->pvptotal * $value->iva/100;
                               $pedido->totalirpf += $value->pvptotal * $value->irpf/100;
                               $pedido->totalrecargo += $value->pvptotal * $value->recargo/100;



                            }
                            else
                               $this->new_error_msg("¡Imposible modificar la línea del artículo ".$value->referencia."!");
                            break;
                         }
                      }

                      /// añadimos la línea
                      if(!$encontrada AND intval($_POST['idlinea_'.$num]) == -1 AND isset($_POST['referencia_'.$num]))
                      {
                         $linea = new linea_pedido_cliente();
                         $linea->idpedido = $pedido->idpedido;
                         $linea->descripcion = $_POST['desc_'.$num];

                         if( !$serie->siniva AND $this->cliente_s->regimeniva != 'Exento' )
                         {
                            $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                            if($imp0)
                               $linea->codimpuesto = $imp0->codimpuesto;

                            $linea->iva = floatval($_POST['iva_'.$num]);
                            $linea->recargo = floatval($_POST['recargo_'.$num]);
                         }

                         $linea->irpf = floatval($_POST['irpf_'.$num]);
                         tpvmod_populate_linea_descuentos(
                            $linea,
                            $this->cliente_s,
                            floatval($_POST['cantidad_'.$num]),
                            floatval($_POST['pvp_'.$num])
                         );

                         $art0 = $articulo->get( $_POST['referencia_'.$num] );
                         if($art0)
                         {
                            $linea->referencia = $art0->referencia;
                         }

                         tpvmod_apply_line_orden($linea, $linePosition, $numlineas);
                         if( $linea->save() )
                         {


                            $pedido->neto += $linea->pvptotal;
                            $pedido->totaliva += $linea->pvptotal * $linea->iva/100;
                            $pedido->totalirpf += $linea->pvptotal * $linea->irpf/100;
                            $pedido->totalrecargo += $linea->pvptotal * $linea->recargo/100;
                         }
                         else
                            $this->new_error_msg("¡Imposible guardar la línea del artículo ".$linea->referencia."!");
                      }
                   }
                }


             }



            if($continuar)
            {
               /// redondeamos
               $pedido->neto = round($pedido->neto, FS_NF0);
               $pedido->totaliva = round($pedido->totaliva, FS_NF0);
               $pedido->totalirpf = round($pedido->totalirpf, FS_NF0);
               $pedido->totalrecargo = round($pedido->totalrecargo, FS_NF0);
               $pedido->total = $pedido->neto + $pedido->totaliva - $pedido->totalirpf + $pedido->totalrecargo;

               if( $pedido->save() )
               {
                  $this->new_message(tpvmod_documento_guardado_message('pedido', $pedido->idpedido, FS_PEDIDO));



                  /// actualizamos la caja
                  $this->registrar_venta_en_caja($pedido->total);
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$pedido->url()."'>".FS_ALBARAN."</a>!");
            }
            else if( $pedido->delete() )
            {
               $this->new_message(FS_ALBARAN." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$pedido->url()."'>".FS_ALBARAN."</a>!");
      }
      $this->cliente_s = $this->cliente->get($this->clientedefault);
   }


   private function edita_presupuesto_cliente()
   {
      $continuar = TRUE;
      $this->cliente_s = $this->cliente->get($_POST['cliente']);
      if( !$this->cliente_s)
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      $ctx = $this->cargar_contexto_documento($continuar);
      $almacen = $ctx['almacen'];
      $ejercicio = $ctx['ejercicio'];
      $serie = $ctx['serie'];
      $forma_pago = $ctx['forma_pago'];
      $divisa = $ctx['divisa'];

      if( isset($_POST['imprimir_desc']) )
      {
         $this->imprimir_descripciones = TRUE;
         setcookie('imprimir_desc', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_descripciones = FALSE;
         setcookie('imprimir_desc', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      if( isset($_POST['imprimir_obs']) )
      {
         $this->imprimir_observaciones = TRUE;
         setcookie('imprimir_obs', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_observaciones = FALSE;
         setcookie('imprimir_obs', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      $presupuesto = new presupuesto_cliente();
      $presupuesto = $presupuesto->get($_POST['id']);


      if( $continuar )
      {
         $presupuesto->fecha = $_POST['fecha'];
         $presupuesto->codalmacen = $almacen->codalmacen;
         $presupuesto->codejercicio = $ejercicio->codejercicio;
         $presupuesto->codserie = $serie->codserie;
         $presupuesto->codpago = $forma_pago->codpago;
         $presupuesto->coddivisa = $divisa->coddivisa;
         $presupuesto->tasaconv = $divisa->tasaconv;
         $presupuesto->codagente = $this->agente->codagente;
         $presupuesto->observaciones = $_POST['observaciones'];
         $presupuesto->numero2 = $_POST['numero2'];
         $presupuesto->irpf = $serie->irpf;
         $presupuesto->porcomision = $this->agente->porcomision;

         tpvmod_aplicar_cliente_a_documento($presupuesto, $this->cliente_s);
            if( isset($_POST['numlineas']) )
             {
                $numlineas = intval($_POST['numlineas']);

                $presupuesto->neto = 0;
                $presupuesto->totaliva = 0;
                $presupuesto->totalirpf = 0;
                $presupuesto->totalrecargo = 0;
                $lineas = $presupuesto->get_lineas();
                $articulo = new articulo();

                /// eliminamos las líneas que no encontremos en el $_POST
                foreach($lineas as $l)
                {
                   $encontrada = FALSE;
                   for($num = 0; $num <= $numlineas; $num++)
                   {
                      if( isset($_POST['idlinea_'.$num]) )
                      {
                         if($l->idlinea == intval($_POST['idlinea_'.$num]))
                         {
                            $encontrada = TRUE;
                            break;
                         }
                      }
                   }
                   if( !$encontrada )
                   {
                      if( !$l->delete() )
                         $this->new_error_msg("¡Imposible eliminar la línea del artículo ".$l->referencia."!");
                   }
                }

                /// modificamos y/o añadimos las demás líneas
                $linePosition = 0;
                for($num = 0; $num <= $numlineas; $num++)
                {
                   $encontrada = FALSE;
                   if( isset($_POST['idlinea_'.$num]) )
                   {
                      $linePosition++;
                      foreach($lineas as $k => $value)
                      {
                         /// modificamos la línea
                         if($value->idlinea == intval($_POST['idlinea_'.$num]))
                         {

                            $encontrada = TRUE;
                            $cantidad_old = $value->cantidad;
                            tpvmod_populate_linea_descuentos(
                               $lineas[$k],
                               $this->cliente_s,
                               floatval($_POST['cantidad_'.$num]),
                               floatval($_POST['pvp_'.$num])
                            );
                            $lineas[$k]->descripcion = $_POST['desc_'.$num];

                            $lineas[$k]->codimpuesto = NULL;
                            $lineas[$k]->iva = 0;
                            $lineas[$k]->recargo = 0;
                            $lineas[$k]->irpf = floatval($_POST['irpf_'.$num]);
                            if( !$serie->siniva AND $this->cliente_s->regimeniva != 'Exento' )
                            {
                               $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                               if($imp0)
                                  $lineas[$k]->codimpuesto = $imp0->codimpuesto;

                               $lineas[$k]->iva = floatval($_POST['iva_'.$num]);
                               $lineas[$k]->recargo = floatval($_POST['recargo_'.$num]);
                            }

                            tpvmod_apply_line_orden($lineas[$k], $linePosition, $numlineas);
                            if( $lineas[$k]->save() )
                            {
                               $presupuesto->neto += $value->pvptotal;
                               $presupuesto->totaliva += $value->pvptotal * $value->iva/100;
                               $presupuesto->totalirpf += $value->pvptotal * $value->irpf/100;
                               $presupuesto->totalrecargo += $value->pvptotal * $value->recargo/100;


                            }
                            else
                               $this->new_error_msg("¡Imposible modificar la línea del artículo ".$value->referencia."!");
                            break;
                         }
                      }

                      /// añadimos la línea
                      if(!$encontrada AND intval($_POST['idlinea_'.$num]) == -1 AND isset($_POST['referencia_'.$num]))
                      {
                         $linea = new linea_presupuesto_cliente();
                         $linea->idpresupuesto = $presupuesto->idpresupuesto;
                         $linea->descripcion = $_POST['desc_'.$num];

                         if( !$serie->siniva AND $this->cliente_s->regimeniva != 'Exento' )
                         {
                            $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                            if($imp0)
                               $linea->codimpuesto = $imp0->codimpuesto;

                            $linea->iva = floatval($_POST['iva_'.$num]);
                            $linea->recargo = floatval($_POST['recargo_'.$num]);
                         }

                         $linea->irpf = floatval($_POST['irpf_'.$num]);
                         tpvmod_populate_linea_descuentos(
                            $linea,
                            $this->cliente_s,
                            floatval($_POST['cantidad_'.$num]),
                            floatval($_POST['pvp_'.$num])
                         );

                         $art0 = $articulo->get( $_POST['referencia_'.$num] );
                         if($art0)
                         {
                            $linea->referencia = $art0->referencia;
                         }

                         tpvmod_apply_line_orden($linea, $linePosition, $numlineas);
                         if( $linea->save() )
                         {

                            $presupuesto->neto += $linea->pvptotal;
                            $presupuesto->totaliva += $linea->pvptotal * $linea->iva/100;
                            $presupuesto->totalirpf += $linea->pvptotal * $linea->irpf/100;
                            $presupuesto->totalrecargo += $linea->pvptotal * $linea->recargo/100;
                         }
                         else
                            $this->new_error_msg("¡Imposible guardar la línea del artículo ".$linea->referencia."!");
                      }
                   }
                }


             }



            if($continuar)
            {
               /// redondeamos
               $presupuesto->neto = round($presupuesto->neto, FS_NF0);
               $presupuesto->totaliva = round($presupuesto->totaliva, FS_NF0);
               $presupuesto->totalirpf = round($presupuesto->totalirpf, FS_NF0);
               $presupuesto->totalrecargo = round($presupuesto->totalrecargo, FS_NF0);
               $presupuesto->total = $presupuesto->neto + $presupuesto->totaliva - $presupuesto->totalirpf + $presupuesto->totalrecargo;

               if( $presupuesto->save() )
               {
                  $this->new_message(tpvmod_documento_guardado_message('presupuesto', $presupuesto->idpresupuesto, FS_PRESUPUESTO));



                  /// actualizamos la caja
                  $this->registrar_venta_en_caja($presupuesto->total);
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$presupuesto->url()."'>".FS_ALBARAN."</a>!");
            }
            else if( $presupuesto->delete() )
            {
               $this->new_message(FS_ALBARAN." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$presupuesto->url()."'>".FS_ALBARAN."</a>!");
      }
      $this->cliente_s = $this->cliente->get($this->clientedefault);
   }

     private function edita_factura_cliente()
   {
      $continuar = TRUE;
      $this->cliente_s = $this->cliente->get($_POST['cliente']);

      if( !$this->cliente_s)
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      $ctx = $this->cargar_contexto_documento($continuar);
      $almacen = $ctx['almacen'];
      $ejercicio = $ctx['ejercicio'];
      $serie = $ctx['serie'];
      $forma_pago = $ctx['forma_pago'];
      $divisa = $ctx['divisa'];

      if( isset($_POST['imprimir_desc']) )
      {
         $this->imprimir_descripciones = TRUE;
         setcookie('imprimir_desc', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_descripciones = FALSE;
         setcookie('imprimir_desc', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      if( isset($_POST['imprimir_obs']) )
      {
         $this->imprimir_observaciones = TRUE;
         setcookie('imprimir_obs', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_observaciones = FALSE;
         setcookie('imprimir_obs', FALSE, time()-FS_COOKIES_EXPIRE);
      }

      $factura = new factura_cliente();
      $factura = $factura->get($_POST['id']);
      foreach($factura->get_lineas_iva() as $liva)
      {
         $liva->delete();
      }
      $factura->neto = 0;
      $factura->totaliva = 0;
      $factura->totalirpf = 0;
      $factura->totalrecargo = 0;


      if( $continuar )
      {
         $factura->fecha = $_POST['fecha'];
         $factura->codalmacen = $almacen->codalmacen;
         $factura->codejercicio = $ejercicio->codejercicio;
         $factura->codserie = $serie->codserie;
         $factura->codpago = $forma_pago->codpago;
         $factura->coddivisa = $divisa->coddivisa;
         $factura->tasaconv = $divisa->tasaconv;
         $factura->codagente = $this->agente->codagente;
         $factura->observaciones = $_POST['observaciones'];
         $factura->numero2 = $_POST['numero2'];
         $factura->irpf = $serie->irpf;
         $factura->porcomision = $this->agente->porcomision;

         tpvmod_aplicar_cliente_a_documento($factura, $this->cliente_s);
            if( isset($_POST['numlineas']) )
             {
                $numlineas = intval($_POST['numlineas']);

                $factura->neto = 0;
                $factura->totaliva = 0;
                $factura->totalirpf = 0;
                $factura->totalrecargo = 0;
                $lineas = $factura->get_lineas();
                $articulo = new articulo();
                $lineas_iva = array();
                $lineas_valoriva = array();
                $lineas_totaliva = array();
                $lineas_total = array();
                /// eliminamos las líneas que no encontremos en el $_POST
                foreach($lineas as $l)
                {
                   $encontrada = FALSE;
                   for($num = 0; $num <= $numlineas; $num++)
                   {
                      if( isset($_POST['idlinea_'.$num]) )
                      {
                         if($l->idlinea == intval($_POST['idlinea_'.$num]))
                         {
                            $encontrada = TRUE;
                            break;
                         }
                      }
                   }
                   if( !$encontrada )
                   {
                      if( $l->delete() )
                      {
                         /// actualizamos el stock
                         $art0 = $articulo->get($l->referencia);
                         if($art0)
                            $art0->sum_stock($factura->codalmacen, $l->cantidad);
                      }
                      else
                         $this->new_error_msg("¡Imposible eliminar la línea del artículo ".$l->referencia."!");
                   }
                }

                /// modificamos y/o añadimos las demás líneas
                $linePosition = 0;
                for($num = 0; $num <= $numlineas; $num++)
                {
                   $encontrada = FALSE;
                   if( isset($_POST['idlinea_'.$num]) )
                   {
                      $linePosition++;
                      foreach($lineas as $k => $value)
                      {
                         /// modificamos la línea
                         if($value->idlinea == intval($_POST['idlinea_'.$num]))
                         {

                            $encontrada = TRUE;
                            $cantidad_old = $value->cantidad;
                            tpvmod_populate_linea_descuentos(
                               $lineas[$k],
                               $this->cliente_s,
                               floatval($_POST['cantidad_'.$num]),
                               floatval($_POST['pvp_'.$num])
                            );
                            $lineas[$k]->descripcion = $_POST['desc_'.$num];

                            $lineas[$k]->codimpuesto = NULL;
                            $lineas[$k]->iva = 0;
                            $lineas[$k]->recargo = 0;
                            $lineas[$k]->irpf = floatval($_POST['irpf_'.$num]);
                            if( !$serie->siniva AND $this->cliente_s->regimeniva != 'Exento' )
                            {
                               $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                               if($imp0)
                                  $lineas[$k]->codimpuesto = $imp0->codimpuesto;

                               $lineas[$k]->iva = floatval($_POST['iva_'.$num]);
                               $lineas[$k]->recargo = floatval($_POST['recargo_'.$num]);
                            }

                            tpvmod_apply_line_orden($lineas[$k], $linePosition, $numlineas);
                            if( $lineas[$k]->save() )
                            {
                               if(!isset($lineas_iva[$lineas[$k]->codimpuesto])){
                                   $lineas_iva[$lineas[$k]->codimpuesto] = 0;
                                   $lineas_valoriva[$lineas[$k]->codimpuesto] = 0;
                                   $lineas_totaliva[$lineas[$k]->codimpuesto] = 0;
                                   $lineas_total[$lineas[$k]->codimpuesto] = 0;
                               }
                               else
                               {
                                   $lineas_iva[$lineas[$k]->codimpuesto] += $lineas[$k]->pvptotal;
                                   $lineas_valoriva[$lineas[$k]->codimpuesto] += $lineas[$k]->iva;
                                   $lineas_totaliva[$lineas[$k]->codimpuesto] += ($lineas[$k]->pvptotal * ($lineas[$k]->iva/100));
                                   $lineas_total[$lineas[$k]->codimpuesto] += ($lineas[$k]->pvptotal * (($lineas[$k]->iva/100)+1));
                               }

                               $factura->neto += $value->pvptotal;
                               $factura->totaliva += $value->pvptotal * $value->iva/100;
                               $factura->totalirpf += $value->pvptotal * $value->irpf/100;
                               $factura->totalrecargo += $value->pvptotal * $value->recargo/100;

                               if($lineas[$k]->cantidad != $cantidad_old)
                               {
                                  /// actualizamos el stock
                                  $art0 = $articulo->get($value->referencia);
                                  if($art0)
                                     $art0->sum_stock($factura->codalmacen, $cantidad_old - $lineas[$k]->cantidad);
                               }
                            }
                            else
                               $this->new_error_msg("¡Imposible modificar la línea del artículo ".$value->referencia."!");
                            break;
                         }
                      }

                      /// añadimos la línea
                      if(!$encontrada AND intval($_POST['idlinea_'.$num]) == -1 AND isset($_POST['referencia_'.$num]))
                      {
                         $linea = new linea_factura_cliente();
                         $linea->idfactura = $factura->idfactura;
                         $linea->descripcion = $_POST['desc_'.$num];

                         if( !$serie->siniva AND $this->cliente_s->regimeniva != 'Exento' )
                         {
                            $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                            if($imp0)
                               $linea->codimpuesto = $imp0->codimpuesto;

                            $linea->iva = floatval($_POST['iva_'.$num]);
                            $linea->recargo = floatval($_POST['recargo_'.$num]);
                         }

                         $linea->irpf = floatval($_POST['irpf_'.$num]);
                         tpvmod_populate_linea_descuentos(
                            $linea,
                            $this->cliente_s,
                            floatval($_POST['cantidad_'.$num]),
                            floatval($_POST['pvp_'.$num])
                         );

                         $art0 = $articulo->get( $_POST['referencia_'.$num] );
                         if($art0)
                         {
                            $linea->referencia = $art0->referencia;
                         }

                         tpvmod_apply_line_orden($linea, $linePosition, $numlineas);
                         if( $linea->save() )
                         {
                             if(!isset($lineas_iva[$linea->codimpuesto])){
                                $lineas_iva[$linea->codimpuesto] = 0;
                                $lineas_valoriva[$linea->codimpuesto] = 0;
                                $lineas_totaliva[$linea->codimpuesto] = 0;
                                $lineas_total[$linea->codimpuesto] = 0;
                            }
                            $lineas_iva[$linea->codimpuesto] += $linea->pvptotal;
                            $lineas_valoriva[$linea->codimpuesto] += $linea->iva;
                            $lineas_totaliva[$linea->codimpuesto] += ($linea->pvptotal * ($linea->iva/100));
                            $lineas_total[$linea->codimpuesto] += ($linea->pvptotal * (($linea->iva/100))+1);
                            if($art0)
                            {
                               /// actualizamos el stock
                               $art0->sum_stock($factura->codalmacen, 0 - $linea->cantidad);
                            }

                            $factura->neto += $linea->pvptotal;
                            $factura->totaliva += $linea->pvptotal * $linea->iva/100;
                            $factura->totalirpf += $linea->pvptotal * $linea->irpf/100;
                            $factura->totalrecargo += $linea->pvptotal * $linea->recargo/100;
                         }
                         else
                            $this->new_error_msg("¡Imposible guardar la línea del artículo ".$linea->referencia."!");
                      }
                   }
                }


             }



            if($continuar)
            {
               /// redondeamos
               $factura->neto = round($factura->neto, FS_NF0);
               $factura->totaliva = round($factura->totaliva, FS_NF0);
               $factura->totalirpf = round($factura->totalirpf, FS_NF0);
               $factura->totalrecargo = round($factura->totalrecargo, FS_NF0);
               $factura->total = $factura->neto + $factura->totaliva - $factura->totalirpf + $factura->totalrecargo;
               if( $factura->save() )
               {
                  $factura->get_lineas_iva();
                  //Actualizamos las lineas del IVA
                  /*$lista_lineas_iva = new linea_iva_factura_cliente();
                  $lineasiva0 = $lista_lineas_iva->all_from_factura($factura->idfactura);
                  foreach($lineasiva0 as $linea){
                    if($lineas_iva[$linea->codimpuesto]){
                        $linea->neto = $lineas_iva[$linea->codimpuesto];
                        $linea->totaliva = $lineas_totaliva[$linea->codimpuesto];
                        $linea->totallinea = $lineas_total[$linea->codimpuesto];
                        $linea->save();
                    }else{
                        $linea->delete();
                    }
                  }*/
                  $this->new_message(tpvmod_documento_guardado_message('factura', $factura->idfactura, FS_FACTURA, true));

                  /// actualizamos la caja
                  $this->registrar_venta_en_caja($factura->total);
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$factura->url()."'>".FS_ALBARAN."</a>!");
            }
            else if( $factura->delete() )
            {
               $this->new_message(FS_ALBARAN." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$factura->url()."'>".FS_ALBARAN."</a>!");
      }
      $this->cliente_s = $this->cliente->get($this->clientedefault);
   }

   private function abrir_caja()
   {
      if($this->user->admin)
      {
         if($this->terminal)
         {
            $this->terminal->abrir_cajon();
            $this->terminal->save();
         }
      }
      else
         $this->new_error_msg('Sólo un administrador puede abrir la caja.');
   }



   private function cerrar_caja()
   {
      $this->caja->fecha_fin = Date('d-m-Y H:i:s');
      if( $this->caja->save() )
      {
         if( $this->terminal )
         {
            $this->terminal->add_linea_big("\nCIERRE DE CAJA:\n");
            $this->terminal->add_linea("Empleado: ".$this->user->codagente." ".$this->agente->get_fullname()."\n");
            $this->terminal->add_linea("Caja: ".$this->caja->fs_id."\n");
            $this->terminal->add_linea("Fecha inicial: ".$this->caja->fecha_inicial."\n");
            $this->terminal->add_linea("Dinero inicial: ".$this->show_precio($this->caja->dinero_inicial, FALSE, FALSE)."\n");
            $this->terminal->add_linea("Fecha fin: ".$this->caja->show_fecha_fin()."\n");
            $this->terminal->add_linea("Dinero fin: ".$this->show_precio($this->caja->dinero_fin, FALSE, FALSE)."\n");
            $this->terminal->add_linea("Diferencia: ".$this->show_precio($this->caja->diferencia(), FALSE, FALSE)."\n");
            $this->terminal->add_linea("Tickets: ".$this->caja->tickets."\n\n");
            $this->terminal->add_linea("Dinero pesado:\n\n\n");
            $this->terminal->add_linea("Observaciones:\n\n\n\n");
            $this->terminal->add_linea("Firma:\n\n\n\n\n\n\n");

            /// encabezado común para los tickets
            $this->terminal->add_linea_big( $this->terminal->center_text($this->empresa->nombre, 16)."\n");
            $this->terminal->add_linea( $this->terminal->center_text($this->empresa->lema) . "\n\n");
            $this->terminal->add_linea( $this->terminal->center_text($this->empresa->direccion . " - " . $this->empresa->ciudad) . "\n");
            $this->terminal->add_linea( $this->terminal->center_text("CIF: " . $this->empresa->cifnif) . chr(27).chr(105) . "\n\n"); /// corta el papel
            $this->terminal->add_linea( $this->terminal->center_text($this->empresa->horario) . "\n");

            $this->terminal->abrir_cajon();
            $this->terminal->save();

            /// recargamos la página
            header('location: '.$this->url().'&terminal='.$this->terminal->id);
         }
         else
         {
            /// recargamos la página
            header('location: '.$this->url());
         }
      }
      else
         $this->new_error_msg("¡Imposible cerrar la caja!");
   }

   private function reimprimir_ticket()
   {
      $albaran = new albaran_cliente();

      if($_GET['reticket'] == '')
      {
         foreach($albaran->all() as $alb)
         {
            $alb0 = $alb;
            break;
         }
      }
      else
         $alb0 = $albaran->get_by_codigo($_GET['reticket']);

      if($alb0)
      {
         $this->imprimir_ticket($alb0, 1, FALSE);
      }
      else
         $this->new_error_msg("Ticket no encontrado.");
   }

   private function borrar_ticket()
   {
      $albaran = new albaran_cliente();
      $alb = $albaran->get_by_codigo($_GET['delete']);
      if($alb)
      {
         if($alb->ptefactura)
         {
            $articulo = new articulo();
            foreach($alb->get_lineas() as $linea)
            {
               $art0 = $articulo->get($linea->referencia);
               if($art0)
               {
                  $art0->sum_stock($alb->codalmacen, $linea->cantidad);
                  $art0->save();
               }
            }

            if( $alb->delete() )
            {
               $this->new_message("Ticket ".$_GET['delete']." borrado correctamente.");

               /// actualizamos la caja
               $this->revertir_venta_en_caja($alb->total);
            }
            else
               $this->new_error_msg("¡Imposible borrar el ticket ".$_GET['delete']."!");
         }
         else
            $this->new_error_msg('No se ha podido borrar este '.FS_ALBARAN.' porque ya está facturado.');
      }
      else
         $this->new_error_msg("Ticket no encontrado.");
   }


   private function registrar_venta_en_caja($total)
   {
      if( !$this->caja_module_enabled || !$this->caja )
      {
         return;
      }

      $this->caja->dinero_fin += $total;
      $this->caja->tickets += 1;
      $this->caja->ip = $_SERVER['REMOTE_ADDR'];
      if( !$this->caja->save() )
      {
         $this->new_error_msg("¡Imposible actualizar la caja!");
      }
   }

   private function revertir_venta_en_caja($total)
   {
      if( !$this->caja_module_enabled || !$this->caja )
      {
         return;
      }

      $this->caja->dinero_fin -= $total;
      $this->caja->tickets -= 1;
      if( !$this->caja->save() )
      {
         $this->new_error_msg("¡Imposible actualizar la caja!");
      }
   }

   public function tipos_a_guardar()
   {
      if (!$this->user->have_access_to('tpvmod') && !$this->user->admin) {
         return [];
      }

      return tpvmod_tipos_a_guardar();
   }

   /**
    * Metadata for optional lines loaded from an existing document (edit screen).
    *
    * @return array{opcional_id: int, grupo_id: int, parent_ref: string}
    */
   public function opcional_line_meta(string $parentRef, string $descripcion, $pvp = 0.0): array
   {
      $resolved = tpvmod_resolve_opcional_line_metadata($parentRef, $descripcion, (float) $pvp);
      if ($resolved === null) {
         return [
            'opcional_id' => 0,
            'grupo_id' => 0,
            'parent_ref' => $parentRef,
         ];
      }

      return $resolved;
   }

   /**
    * Loads warehouse, series, currency, payment method and fiscal year for a new/edited document.
    * Uses POST when present; otherwise modular defaults from business_data / catalogo_core
    * (and terminal only when facturacion_base TPV terminal is active).
    *
    * @return array{almacen: almacen|false, ejercicio: ejercicio|false, serie: serie|false, forma_pago: forma_pago|false, divisa: divisa|false}
    */
   private function cargar_contexto_documento(bool &$continuar): array
   {
      $empresa0 = new empresa();
      $empresa = $empresa0->get();

      $codigos = tpvmod_resolve_documento_codigos(
         $_POST,
         $this->terminal ? $this->terminal : null,
         $this->default_items,
         $empresa ? $empresa : null
      );

      $almacen = $this->tpvmod_cargar_modelo($this->almacen, $codigos['almacen'], 'Almacén', $continuar);
      if ($almacen) {
         $this->save_codalmacen($almacen->codalmacen);
      }

      $fecha = $_POST['fecha'] ?? $this->today();
      $ejercicio = $this->ejercicio->get_by_fecha($fecha);
      if (!$ejercicio) {
         $this->new_error_msg('Ejercicio no encontrado.');
         $continuar = FALSE;
      }

      $serie = $this->tpvmod_cargar_modelo($this->serie, $codigos['serie'], 'Serie', $continuar);

      $forma_pago = $this->tpvmod_cargar_modelo($this->forma_pago, $codigos['forma_pago'], 'Forma de pago', $continuar);
      if ($forma_pago) {
         $this->save_codpago($forma_pago->codpago);
      }

      $divisa = $this->tpvmod_cargar_modelo($this->divisa, $codigos['divisa'], 'Divisa', $continuar);
      if ($divisa) {
         $this->save_coddivisa($divisa->coddivisa);
      }

      return [
         'almacen' => $almacen,
         'ejercicio' => $ejercicio,
         'serie' => $serie,
         'forma_pago' => $forma_pago,
         'divisa' => $divisa,
      ];
   }

   /**
    * @param almacen|divisa|forma_pago|serie $model
    * @return almacen|divisa|forma_pago|serie|false
    */
   private function tpvmod_cargar_modelo($model, ?string $codigo, string $label, bool &$continuar)
   {
      if ($codigo) {
         $item = $model->get($codigo);
         if ($item) {
            return $item;
         }
      }

      foreach ($model->all() as $item) {
         if (method_exists($item, 'is_default') && $item->is_default()) {
            return $item;
         }
      }

      if ($label === 'Divisa') {
         $empresaCoddivisa = ($this->empresa && !empty($this->empresa->coddivisa))
            ? (string) $this->empresa->coddivisa
            : 'EUR';
         $item = $model->get($empresaCoddivisa);
         if ($item) {
            return $item;
         }
      }

      foreach ($model->all() as $item) {
         return $item;
      }

      $adminUrl = match ($label) {
         'Almacén' => 'index.php?page=admin_almacenes',
         'Serie' => 'index.php?page=contabilidad_series',
         'Forma de pago' => 'index.php?page=contabilidad_formas_pago',
         'Divisa' => 'index.php?page=admin_divisas',
         default => '',
      };
      $hint = $adminUrl !== ''
         ? ' <a href="' . $adminUrl . '">Configurar ' . strtolower($label) . '</a>'
         : '';

      $this->new_error_msg($label . ' no encontrado.' . $hint);
      $continuar = FALSE;

      return FALSE;
   }

   /**
    * @return array{almacen:?string,serie:?string,divisa:?string,forma_pago:?string}
    */
   private function calcular_tpv_defaults(): array
   {
      $empresa0 = new empresa();
      $empresa = $empresa0->get();

      return tpvmod_resolve_documento_codigos(
         [],
         $this->terminal ? $this->terminal : null,
         $this->default_items,
         $empresa ? $empresa : null
      );
   }

   private function avisar_datos_maestros_faltantes(): void
   {
      if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
         return;
      }

      $gaps = tpvmod_master_data_gaps(
         count($this->almacen->all()),
         count($this->serie->all()),
         count($this->forma_pago->all()),
         count($this->divisa->all()),
         count($this->ejercicio->all())
      );

      foreach ($gaps as $gap) {
         $this->new_error_msg(
            'Falta configurar ' . $gap['label'] . ' (' . $gap['plugin'] . ').'
            . ' <a href="' . $gap['url'] . '">Ir a administración</a>'
         );
      }
   }

}
