<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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
require_model('articulo.php');
require_model('cliente.php');
require_model('direccion_cliente.php');
require_model('factura_cliente.php');
require_once dirname(__DIR__) . '/lib/tpvmod_cliente_ajax.php';
require_once dirname(__DIR__) . '/lib/tpvmod_listados.php';

class tpvmod_facturas extends fs_controller
{
   public $agente;
   public $articulo;
   public $buscar_lineas;
   public $cliente;
   public $codagente;
   public $codserie;
   public $desde;
   public $factura;
   public $hasta;
   public $huecos;
   public $lineas;
   public $mostrar;
   public $num_resultados;
   public $offset;
   public $order;
   public $resultados;
   public $serie;
   public $total_resultados;
   public $total_resultados_comision;
   public $total_resultados_txt;
   private $telefonos_map = null;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'TPVMOD Pedidos', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->agente = new agente();
      $this->factura = new factura_cliente();
      $this->huecos = array();
      $this->serie = new serie();
      
      $this->mostrar = 'todo';
      if( isset($_GET['mostrar']) )
      {
         $this->mostrar = $_GET['mostrar'];
         setcookie('ventas_fac_mostrar', $this->mostrar, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['ventas_fac_mostrar']) )
      {
         $this->mostrar = $_COOKIE['ventas_fac_mostrar'];
      }
      
      $this->offset = 0;
      if( isset($_REQUEST['offset']) )
      {
         $this->offset = intval($_REQUEST['offset']);
      }
      
      $this->order = 'fecha DESC';
      if( isset($_GET['order']) )
      {
         if($_GET['order'] == 'fecha_desc')
         {
            $this->order = 'fecha DESC';
         }
         else if($_GET['order'] == 'fecha_asc')
         {
            $this->order = 'fecha ASC';
         }
         else if($_GET['order'] == 'vencimiento_desc')
         {
            $this->order = 'vencimiento DESC';
         }
         else if($_GET['order'] == 'vencimiento_asc')
         {
            $this->order = 'vencimiento ASC';
         }
         
         setcookie('ventas_fac_order', $this->order, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['ventas_fac_order']) )
      {
         $this->order = $_COOKIE['ventas_fac_order'];
      }
      
      if( isset($_POST['buscar_lineas']) )
      {
         $this->buscar_lineas();
      }
      else if (tpvmod_cliente_ajax_dispatch($this)) {
         return;
      }
      else if( isset($_GET['ref']) )
      {
         $this->template = 'extension/ventas_facturas_articulo';
         
         $articulo = new articulo();
         $this->articulo = $articulo->get($_GET['ref']);
         
         $linea = new linea_factura_cliente();
         $this->resultados = $linea->all_from_articulo($_GET['ref'], $this->offset);
      }
      else
      {
         $this->share_extension();
         $this->huecos = $this->factura->huecos();
         $this->cliente = FALSE;
         $this->codagente = '';
         $this->codserie = '';
         $this->desde = '';
         $this->hasta = '';
         $this->num_resultados = '';
         $this->total_resultados = '';
         $this->total_resultados_comision = 0;
         $this->total_resultados_txt = '';
         
         if( isset($_GET['delete']) )
         {
            $this->delete_factura();
         }
         else
         {
            if( !isset($_GET['mostrar']) AND (isset($_REQUEST['codagente']) OR isset($_REQUEST['codcliente']) OR isset($_REQUEST['codserie'])) )
            {
               /**
                * si obtenermos un codagente, un codcliente o un codserie pasamos direcatemente
                * a la pestaña de búsqueda, a menos que tengamos un mostrar, que
                * entonces nos indica donde tenemos que estar.
                */
               $this->mostrar = 'buscar';
            }
            
            if( isset($_REQUEST['codcliente']) )
            {
               if($_REQUEST['codcliente'] != '')
               {
                  $cli0 = new cliente();
                  $this->cliente = $cli0->get($_REQUEST['codcliente']);
               }
            }
            
            if( isset($_REQUEST['codagente']) )
            {
               $this->codagente = $_REQUEST['codagente'];
            }
            
            if( isset($_REQUEST['codserie']) )
            {
               $this->codserie = $_REQUEST['codserie'];
            }
            
            if( isset($_REQUEST['desde']) )
            {
               $this->desde = tpvmod_normalize_date($_REQUEST['desde'] ?? '');
               $this->hasta = tpvmod_normalize_date($_REQUEST['hasta'] ?? '');
            }
         }
         
         /// añadimos segundo nivel de ordenación
         $order2 = '';
         if( substr($this->order, -4) == 'DESC' )
         {
            $order2 = ', hora DESC, numero DESC';
         }
         else
         {
            $order2 = ', hora ASC, numero ASC';
         }
         
         if($this->mostrar == 'sinpagar')
         {
            $this->resultados = $this->factura->all_sin_pagar($this->offset, FS_ITEM_LIMIT, $this->order.$order2);
            
            if($this->offset == 0)
            {
               $this->total_resultados = 0;
               $this->total_resultados_txt = 'Suma total de esta página:';
               foreach($this->resultados as $fac)
               {
                  $this->total_resultados += $fac->total;
               }
            }
         }
         else if($this->mostrar == 'buscar')
         {
            $this->buscar($order2);
         }
         else
            $this->resultados = $this->factura->all($this->offset, FS_ITEM_LIMIT, $this->order.$order2);
      }
   }
   
   public function paginas(): array
   {
      return tpvmod_pager_links(
          (int) $this->offset,
          $this->list_total(),
          FS_ITEM_LIMIT,
          fn(int $offset): string => $this->list_url(['offset' => $offset])
      );
   }

   /**
    * Total rows for the active tab, extracted verbatim from the legacy paginas()
    * switch. Each module keeps its own switch; only the arithmetic is shared.
    */
   private function list_total(): int
   {
      if($this->mostrar == 'sinpagar')
      {
         return $this->total_sinpagar();
      }
      
      if($this->mostrar == 'buscar')
      {
         return (int) $this->num_resultados;
      }
      
      return $this->total_registros();
   }
   
   /**
    * @return array<string, scalar|null>
    */
   private function list_params(): array
   {
      return [
          'mostrar' => $this->mostrar,
          'query' => (string) $this->query,
          'codserie' => $this->codserie,
          'codagente' => $this->codagente,
          'codcliente' => $this->cliente ? $this->cliente->codcliente : '',
          'desde' => $this->desde,
          'hasta' => $this->hasta,
          'order' => tpvmod_order_token_for($this->order),
          'offset' => (int) $this->offset,
      ];
   }
   
   /**
    * Canonical listing URL. The same builder feeds the rendered href and the
    * htmx attributes.
    *
    * @param array<string, scalar|null> $overrides
    * @param list<string> $omit
    */
   public function list_url(array $overrides = array(), array $omit = array()): string
   {
      $params = array_merge($this->list_params(), $overrides);
      foreach($omit as $key)
      {
         unset($params[$key]);
      }
      
      return tpvmod_build_list_url($this->url(), $params);
   }
   
   /**
    * Phone per customer for the rendered page, memoized. Resolved with one
    * batched clientes lookup for the page's codcliente set (LHT-06).
    *
    * @return array<string, string>
    */
   private function telefonos_pagina(): array
   {
      if($this->telefonos_map === null)
      {
         $codes = array_map(fn($d) => (string) $d->codcliente, $this->resultados ?: array());
         $this->telefonos_map = tpvmod_phone_map($codes, function (array $set): array {
            $escaped = array_map(fn(string $c): string => $this->var2str($c), $set);
            $sql = 'SELECT codcliente, telefono1, telefono2 FROM clientes'
                 . ' WHERE codcliente IN (' . implode(',', $escaped) . ');';
            
            $map = array();
            foreach($this->db->select($sql) ?: array() as $row)
            {
               $map[(string) $row['codcliente']] = $row;
            }
            
            return $map;
         });
      }
      
      return $this->telefonos_map;
   }
   
   public function telefono_cliente(string $codcliente): string
   {
      return $this->telefonos_pagina()[$codcliente] ?? '';
   }
   
   public function buscar_lineas()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/ventas_lineas_facturas';
      
      $this->buscar_lineas = $_POST['buscar_lineas'];
      $linea = new linea_factura_cliente();
      
      if( isset($_POST['codcliente']) )
      {
         $this->lineas = $linea->search_from_cliente2($_POST['codcliente'], $this->buscar_lineas, $_POST['buscar_lineas_o']);
      }
      else
      {
         $this->lineas = $linea->search($this->buscar_lineas);
      }
   }
   
   private function share_extension()
   {
      /// añadimos las extensiones para clientes, agentes y artículos
      $extensiones = array(
          array(
              'name' => 'facturas_cliente',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_cliente',
              'type' => 'button',
              'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas',
              'params' => ''
          ),
          array(
              'name' => 'facturas_agente',
              'page_from' => __CLASS__,
              'page_to' => 'admin_agente',
              'type' => 'button',
              'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas de cliente',
              'params' => ''
          ),
          array(
              'name' => 'facturas_articulo',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_articulo',
              'type' => 'tab_button',
              'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas de cliente',
              'params' => ''
          ),
      );
      foreach($extensiones as $ext)
      {
         $fsext0 = new fs_extension($ext);
         if( !$fsext0->save() )
         {
            $this->new_error_msg('Imposible guardar los datos de la extensión '.$ext['name'].'.');
         }
      }
   }
   
   public function total_sinpagar()
   {
      $data = $this->db->select("SELECT COUNT(idfactura) as total FROM facturascli WHERE pagada = false;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
   
   private function total_registros()
   {
      $data = $this->db->select("SELECT COUNT(idfactura) as total FROM facturascli;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
   
   private function buscar($order2)
   {
      $this->resultados = array();
      $this->num_resultados = 0;
      $term = tpvmod_search_term((string) $this->query);
      $sql = " FROM facturascli ";
      $where = 'WHERE ';
      
      $predicate = tpvmod_build_search_predicate($term, fn(string $v): string => $this->var2str($v));
      if($predicate !== '')
      {
         $sql .= $where.$predicate;
         $where = ' AND ';
      }
      
      if($this->codagente != '')
      {
         $sql .= $where."codagente = ".$this->agente->var2str($this->codagente);
         $where = ' AND ';
      }
      
      if($this->cliente)
      {
         $sql .= $where."codcliente = ".$this->agente->var2str($this->cliente->codcliente);
         $where = ' AND ';
      }
      
      if($this->codserie != '')
      {
         $sql .= $where."codserie = ".$this->agente->var2str($this->codserie);
         $where = ' AND ';
      }
      
      if($this->desde != '')
      {
         $sql .= $where."fecha >= ".$this->agente->var2str($this->desde);
         $where = ' AND ';
      }
      
      if($this->hasta != '')
      {
         $sql .= $where."fecha <= ".$this->agente->var2str($this->hasta);
         $where = ' AND ';
      }
      
      $data = $this->db->select("SELECT COUNT(idfactura) as total".$sql);
      if($data)
      {
         $this->num_resultados = intval($data[0]['total']);
         
         $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$this->order.$order2, FS_ITEM_LIMIT, $this->offset);
         if($data2)
         {
            foreach($data2 as $d)
            {
               $this->resultados[] = new factura_cliente($d);
            }
         }
         
         $data2 = $this->db->select("SELECT SUM(total) as total".$sql);
         if($data2)
         {
            $this->total_resultados = floatval($data2[0]['total']);
            $this->total_resultados_txt = 'Suma total de los resultados:';
         }
         
         if($this->codagente !== '')
         {
            /// calculamos la comisión del empleado
            $data2 = $this->db->select("SELECT SUM(neto*porcomision/100) as total".$sql);
            if($data2)
            {
               $this->total_resultados_comision = floatval($data2[0]['total']);
            }
         }
      }
   }
   
   private function delete_factura()
   {
      $fact = $this->factura->get($_GET['delete']);
      if($fact)
      {
         /// ¿Sumamos stock?
         $art0 = new articulo();
         foreach($fact->get_lineas() as $linea)
         {
            if( is_null($linea->idalbaran) )
            {
               $articulo = $art0->get($linea->referencia);
               if($articulo)
               {
                  $articulo->sum_stock($fact->codalmacen, $linea->cantidad);
               }
            }
         }
         
         if( $fact->delete() )
         {
            $this->new_message("Factura eliminada correctamente.");
         }
         else
            $this->new_error_msg("¡Imposible eliminar la factura!");
      }
      else
         $this->new_error_msg("Factura no encontrada.");
   }
}
