/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014-2015  Javier Trujillo Jimenez javier.trujillo.jimenez@gmail.com
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

var fs_nf0 = 2;
var numlineas = 0;
var tpv_url = '';
var siniva = false;
var irpf = 0;
var all_impuestos = [];
var all_series = [];
var cliente = false;
var nueva_venta_url = '';

//para evitar que cuando le des al intro te mande el formulario
function stopRKey(evt) {
var evt = (evt) ? evt : ((event) ? event : null);
var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
if ((evt.keyCode == 13) && (node.type=="text")) {
    if (node.id && node.id.indexOf('total_') === 0) {
       ajustar_total();
    } else if (node.id && node.id.indexOf('neto_') === 0) {
       ajustar_neto(node.id.replace('neto_', ''));
    } else {
       recalcular();
    }
    return false;}
}
document.onkeypress = stopRKey; 

function tpvmod_cliente_discounts()
{
   if(!cliente)
   {
      return {d1: 0, d2: 0, d3: 0, d4: 0};
   }

   return {
      d1: parseFloat(cliente.d1) || 0,
      d2: parseFloat(cliente.d2) || 0,
      d3: parseFloat(cliente.d3) || 0,
      d4: parseFloat(cliente.d4) || 0
   };
}

function tpvmod_due_multiplier(d1, d2, d3, d4)
{
   return (1 - d1/100) * (1 - d2/100) * (1 - d3/100) * (1 - d4/100);
}

function tpvmod_line_neto(cantidad, pvp, discounts)
{
   return cantidad * pvp * tpvmod_due_multiplier(
      discounts.d1, discounts.d2, discounts.d3, discounts.d4
   );
}

function usar_cliente(codcliente)
{
   if(nueva_venta_url !== '')
   {
      $.getJSON(nueva_venta_url, 'datoscliente='+codcliente, function(json) {
         cliente = json;
         document.f_buscar_articulos.codcliente.value = cliente.codcliente;
         if(cliente.regimeniva == 'Exento')
         {
            irpf = 0;
            for(var j=0; j<numlineas; j++)
            {
               if($("#linea_"+j).length > 0)
               {
                  $("#iva_"+j).val(0);
                  $("#recargo_"+j).val(0);
                  $("#irpf_"+j).html( show_numero(irpf) );
               }
            }
         }
         recalcular();
      });
   }
}

function usar_serie()
{
   for(var i=0; i<all_series.length; i++)
   {
      if(all_series[i].codserie == $("#codserie").val())
      {
         siniva = all_series[i].siniva;
         irpf = all_series[i].irpf;
         
         for(var j=0; j<numlineas; j++)
         {
            if($("#linea_"+j).length > 0)
            {
               $("#irpf_"+j).html( show_numero(irpf) );
               
               if(siniva)
               {
                  $("#iva_"+j).val(0);
                  $("#recargo_"+j).val(0);
               }
            }
         }
         
         break;
      }
   }
}

function tpvmod_line_tax_total(l_neto, l_iva, l_irpf, l_recargo)
{
   return l_neto + (l_neto * (l_iva - l_irpf + l_recargo) / 100);
}

function tpvmod_insert_linea_before_totals(html)
{
   var totalsRow = $("#lineas_albaran tr.bg-info").first();
   if(totalsRow.length > 0)
      totalsRow.before(html);
   else
      $("#lineas_albaran").append(html);
}

function tpvmod_line_rows()
{
   return $("#lineas_albaran > tr[id^='linea_']");
}

function tpvmod_line_actions_cell(lineNum)
{
   return "<td class=\"tpvmod-line-actions text-nowrap\">\n\
      <span class=\"tpvmod-line-handle btn btn-xs btn-default\" title=\"Arrastrar para reordenar\">\n\
         <i class=\"fa fa-arrows-v\"></i></span>\n\
      <button class=\"btn btn-xs btn-danger\" type=\"button\" onclick=\"return tpvmod_eliminar_linea('"+lineNum+"');\">\n\
         <span class=\"glyphicon glyphicon-trash\"></span></button></td>";
}

function tpvmod_replace_line_index(str, oldNum, newNum)
{
   if(String(oldNum) === String(newNum))
      return str;

   var o = String(oldNum);
   var n = String(newNum);
   var pairs = [
      ['linea_' + o, 'linea_' + n],
      ['neto_' + o, 'neto_' + n],
      ['pvp_' + o, 'pvp_' + n],
      ['cantidad_' + o, 'cantidad_' + n],
      ['total_' + o, 'total_' + n],
      ['iva_' + o, 'iva_' + n],
      ['recargo_' + o, 'recargo_' + n],
      ['irpf_' + o, 'irpf_' + n],
      ['desc_' + o, 'desc_' + n],
      ["ajustar_neto('" + o + "')", "ajustar_neto('" + n + "')"],
      ["ajustar_iva('" + o + "')", "ajustar_iva('" + n + "')"],
      ["tpvmod_eliminar_linea('" + o + "')", "tpvmod_eliminar_linea('" + n + "')"],
      ["$('#linea_" + o + "')", "$('#linea_" + n + "')"]
   ];
   var result = str;
   for(var p = 0; p < pairs.length; p++)
      result = result.split(pairs[p][0]).join(pairs[p][1]);

   return result;
}

function tpvmod_update_row_index($row, oldNum, newNum)
{
   if(String(oldNum) === String(newNum))
      return;

   $row.attr('id', 'linea_' + newNum);

   $row.find('[id]').each(function() {
      var $el = $(this);
      var id = $el.attr('id');
      var suffix = '_' + oldNum;
      if(id && id.length >= suffix.length && id.slice(-suffix.length) === suffix)
         $el.attr('id', id.slice(0, -suffix.length) + '_' + newNum);
   });

   $row.find('[name]').each(function() {
      var $el = $(this);
      var name = $el.attr('name');
      var suffix = '_' + oldNum;
      if(name && name.length >= suffix.length && name.slice(-suffix.length) === suffix)
         $el.attr('name', name.slice(0, -suffix.length) + '_' + newNum);
   });

   ['onclick', 'onchange', 'onkeyup'].forEach(function(attr) {
      $row.find('[' + attr + ']').each(function() {
         var $el = $(this);
         var val = $el.attr(attr);
         if(val)
            $el.attr(attr, tpvmod_replace_line_index(val, oldNum, newNum));
      });
   });
}

function tpvmod_renumber_lineas()
{
   var rows = tpvmod_line_rows();
   var newNum = 0;

   rows.each(function() {
      newNum++;
      var $row = $(this);
      var oldNum = $row.attr('id').replace('linea_', '');
      tpvmod_update_row_index($row, oldNum, newNum);
   });

   numlineas = newNum;
   $("#numlineas").val(numlineas);
}

function tpvmod_eliminar_linea(lineNum)
{
   if(!confirm('¿Eliminar esta línea?'))
      return false;

   $("#linea_"+lineNum).remove();
   tpvmod_renumber_lineas();
   recalcular();
   return false;
}

function tpvmod_submit_guardar(btn)
{
   if(!$('input[name="tipo"]:checked').length)
   {
      alert('Selecciona el tipo de documento a guardar.');
      return false;
   }

   tpvmod_renumber_lineas();
   $('#tpv_total').prop('disabled', false);
   btn.disabled = true;
   btn.form.submit();
   return false;
}

function tpvmod_init_lineas_sortable()
{
   var tbody = $("#lineas_albaran");
   if(!tbody.length || typeof tbody.sortable !== 'function')
      return;

   if(tbody.hasClass('ui-sortable'))
      tbody.sortable('destroy');

   tbody.sortable({
      items: 'tr[id^="linea_"]',
      handle: '.tpvmod-line-handle',
      axis: 'y',
      containment: 'parent',
      tolerance: 'pointer',
      stop: function() {
         tpvmod_renumber_lineas();
      }
   });
}

function ajustar_neto(lineNum)
{
   if(!lineNum || !$("#linea_"+lineNum).length)
      return;

   var l_neto = parseFloat( $("#neto_"+lineNum).val() );
   if( isNaN(l_neto) )
      l_neto = 0;

   var l_uds = parseFloat( $("#cantidad_"+lineNum).val() );
   if( isNaN(l_uds) || l_uds === 0 )
      l_uds = 0;

   var discounts = tpvmod_cliente_discounts();
   var due = tpvmod_due_multiplier(discounts.d1, discounts.d2, discounts.d3, discounts.d4);
   var l_pvp = 0;

   if(l_uds !== 0 && due !== 0)
      l_pvp = l_neto / (l_uds * due);

   $("#pvp_"+lineNum).val(l_pvp);
   recalcular();
}

function ajustar_iva(lineNum)
{
   recalcular();
}

function recalcular()
{
   var l_uds = 0;
   var l_pvp = 0;
   var l_dto = 0;
   var l_neto = 0;
   var l_iva = 0;
   var l_irpf = 0;
   var l_recargo = 0;
   var neto = 0;
   var total_iva = 0;
   var total_irpf = 0;
   var total_recargo = 0;
   var discounts = tpvmod_cliente_discounts();
   
   for(var i=1; i<=numlineas; i++)
   {
      if($("#linea_"+i).length > 0)
      {
         
         l_uds = parseFloat( $("#cantidad_"+i).val() );
         l_pvp = parseFloat( $("#pvp_"+i).val() );
         l_neto = tpvmod_line_neto(l_uds, l_pvp, discounts);
         l_iva = parseFloat( $("#iva_"+i).val() );
         l_irpf = irpf;
         
         if(cliente && cliente.recargo)
         {
            l_recargo = parseFloat( $("#recargo_"+i).val() );
         }
         else
         {
            l_recargo = 0;
            $("#recargo_"+i).val(0);
         }
         
         $("#neto_"+i).val( l_neto );
         $("#total_"+i).val( number_format(
            tpvmod_line_tax_total(l_neto, l_iva, l_irpf, l_recargo),
            fs_nf0, '.', ''
         ) );
         
         neto += l_neto;
         total_iva += l_neto * l_iva/100;
         total_irpf += l_neto * l_irpf/100;
         total_recargo += l_neto * l_recargo/100;
         console.log("Ajuste recalcular: "+i+" cantidad: "+l_uds+" pvp: "+l_pvp+" neto: "+l_neto+" dto: "+l_dto+" irpf: "+l_irpf);
      }
   }
   
   neto = fs_round(neto, fs_nf0);
   total_iva = fs_round(total_iva, fs_nf0);
   total_irpf = fs_round(total_irpf, fs_nf0);
   total_recargo = fs_round(total_recargo, fs_nf0);
   $("#aneto").html( show_numero(neto) );
   $("#aiva").html( show_numero(total_iva) );
   $("#are").html( show_numero(total_recargo) );
   $("#airpf").html( '-'+show_numero(total_irpf) );
   $("#atotal").html( neto + total_iva - total_irpf + total_recargo );
   
   if(total_recargo == 0)
   {
      $(".recargo").hide();
   }
   else
   {
      $(".recargo").show();
   }
   
   if(total_irpf == 0)
   {
      $(".irpf").hide();
   }
   else
   {
      $(".irpf").show();
   }
   
   $("#tpv_total").val( show_precio(neto + total_iva - total_irpf + total_recargo) );
   $("#tpv_total2").val(neto + total_iva - total_irpf + total_recargo);
   var tpv_efectivo = parseFloat( $("#tpv_efectivo").val() );
   $("#tpv_cambio").val( show_precio(tpv_efectivo - (neto + total_iva - total_irpf + total_recargo)) );
}

function tpvmod_calc_neto_from_total_js(l_total, l_iva, l_irpf, l_recargo)
{
   var taxFactor = 100 + l_iva - l_irpf + l_recargo;
   return taxFactor !== 0 ? (100 * l_total / taxFactor) : 0;
}

function ajustar_total()
{
   var l_uds = 0;
   var l_pvp = 0;
   var l_iva = 0;
   var l_irpf = 0;
   var l_recargo = 0;
   var l_neto = 0;
   var l_total = 0;
   var discounts = tpvmod_cliente_discounts();
   var due = tpvmod_due_multiplier(discounts.d1, discounts.d2, discounts.d3, discounts.d4);
   
   for(var i=1; i<=numlineas; i++)
   {
      if($("#linea_"+i).length > 0)
      {
         l_uds = parseFloat( $("#cantidad_"+i).val() );
         if( isNaN(l_uds) || l_uds === 0 )
            l_uds = 0;

         l_iva = parseFloat( $("#iva_"+i).val() );
         if( isNaN(l_iva) )
            l_iva = 0;

         l_irpf = irpf;
         if(l_iva <= 0)
            l_irpf = 0;

         l_recargo = 0;
         if(cliente && cliente.recargo)
         {
            l_recargo = parseFloat( $("#recargo_"+i).val() );
            if( isNaN(l_recargo) )
               l_recargo = 0;
         }

         l_total = parseFloat( $("#total_"+i).val() );
         if( isNaN(l_total) )
            l_total = 0;

         // total = neto * (1 + (iva - irpf + recargo) / 100)
         l_neto = tpvmod_calc_neto_from_total_js(l_total, l_iva, l_irpf, l_recargo);

         // neto = uds * pvp * descuentos  =>  pvp = neto / (uds * due)
         if(l_uds !== 0 && due !== 0)
            l_pvp = l_neto / (l_uds * due);
         else
            l_pvp = 0;

         $("#pvp_"+i).val(l_pvp);
      }
   }
   
   recalcular();
}

function tpvmodCsrfToken()
{
   var input = document.querySelector('form[name=f_tpv] input[name="_csrf_token"]');
   if (input && input.value) {
      return input.value;
   }
   var meta = document.querySelector('meta[name="csrf-token"]');
   return meta ? meta.getAttribute('content') : '';
}

function get_precios(ref)
{
   var data = "referencia4precios="+ref+"&codcliente="+document.f_tpv.cliente.value;
   var csrf = tpvmodCsrfToken();
   if (csrf) {
      data += "&_csrf_token="+encodeURIComponent(csrf);
   }

   $.ajax({
      type: 'POST',
      url: tpv_url,
      dataType: 'html',
      data: data,
      success: function(datos) {
         $("#search_results").html(datos);
      }
   });
}

function add_linea_libre()
{
   numlineas += 1;
   $("#numlineas").val(numlineas);
   codimpuesto = false;
   for(var i=0; i<all_impuestos.length; i++)
   {
      codimpuesto = all_impuestos[i].codimpuesto;
      break;
   }
   
   
   
   var lineHtml = "<tr id=\"linea_"+numlineas+"\">\n\
      <td><input type=\"hidden\" name=\"idlinea_"+numlineas+"\" value=\"-1\"/>\n\
         <input type=\"hidden\" name=\"referencia_"+numlineas+"\"/>\n\
         <div class=\"form-control input-sm\"></div></td>\n\
      <td><textarea class=\"form-control input-sm\" id=\"desc_"+numlineas+"\" name=\"desc_"+numlineas+"\" rows=\"1\" onclick=\"this.select()\"></textarea></td>\n\
      <td><input type=\"number\" step=\"any\" id=\"cantidad_"+numlineas+"\" class=\"form-control text-right input-sm\" name=\"cantidad_"+numlineas+
         "\" onchange=\"recalcular()\" onkeyup=\"recalcular()\" autocomplete=\"off\" value=\"1\"/></td>\n\
      "+tpvmod_line_actions_cell(numlineas)+"\n\
      <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"pvp_"+numlineas+"\" name=\"pvp_"+numlineas+"\" value=\"0\"\n\
          onkeyup=\"recalcular()\" onchange=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"neto_"+numlineas+"\" name=\"neto_"+numlineas+
         "\" onchange=\"ajustar_neto('"+numlineas+"')\" onkeyup=\"ajustar_neto('"+numlineas+"')\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      "+aux_all_impuestos(numlineas,codimpuesto)+"\n\
      <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"total_"+numlineas+"\" name=\"total_"+numlineas+
         "\" onchange=\"ajustar_total()\" onclick=\"this.select()\" autocomplete=\"off\"/></td></tr>";

   tpvmod_insert_linea_before_totals(lineHtml);
   recalcular();
   tpvmod_init_lineas_sortable();
   
   $("#desc_"+numlineas).select();
   return false;
}

function resolve_iva_from_codimpuesto(codimpuesto, fallbackIva)
{
   var iva = 0;
   var recargo = 0;

   if(cliente && cliente.regimeniva != 'Exento' && !siniva && all_impuestos && all_impuestos.length)
   {
      for(var i=0; i<all_impuestos.length; i++)
      {
         if(!all_impuestos[i]) {
            continue;
         }
         if(all_impuestos[i].codimpuesto == codimpuesto)
         {
            iva = all_impuestos[i].iva;
            if(cliente.recargo)
            {
               recargo = all_impuestos[i].recargo;
            }
            break;
         }
      }
   }

   if(iva === 0 && fallbackIva !== undefined && fallbackIva !== null && fallbackIva !== '')
   {
      iva = parseFloat(fallbackIva) || 0;
   }

   return { iva: iva, recargo: recargo };
}

function aux_all_impuestos(num,codimpuesto)
{
   var imp = resolve_iva_from_codimpuesto(codimpuesto);
   var iva = imp.iva;
   var recargo = imp.recargo;
   
   var html = "<td><select id=\"iva_"+num+"\" class=\"form-control input-sm\" name=\"iva_"+num+"\" onchange=\"ajustar_iva('"+num+"')\">";
   for(var i=0; i<all_impuestos.length; i++)
   {
      if(!all_impuestos[i]) {
         continue;
      }
      if(iva == all_impuestos[i].iva)
      {
         html += "<option value=\""+all_impuestos[i].iva+"\" selected=\"selected\">"+all_impuestos[i].descripcion+"</option>";
      }
      else
         html += "<option value=\""+all_impuestos[i].iva+"\">"+all_impuestos[i].descripcion+"</option>";
   }
   html += "</select></td>";
   
   html += "<td class=\"recargo\"><input type=\"text\" class=\"form-control text-right input-sm\" id=\"recargo_"+num+"\" name=\"recargo_"+num+
           "\" value=\""+recargo+"\" onclick=\"this.select()\" onkeyup=\"recalcular()\" autocomplete=\"off\"/></td>";
   
   html += "<td class=\"irpf\"><input type=\"text\" class=\"form-control text-right input-sm\" id=\"irpf_"+num+"\" name=\"irpf_"+num+
         "\" value=\""+irpf+"\" onclick=\"this.select()\" onkeyup=\"recalcular()\" autocomplete=\"off\"/></td>";
   
   return html;
}

function add_articulo(ref,desc,pvp,dto,codimpuesto,cantidad,ivaArticulo)
{
   numlineas += 1;
   $("#numlineas").val(numlineas);
   desc = Base64.decode(desc);
   var imp = resolve_iva_from_codimpuesto(codimpuesto, ivaArticulo);
   var iva = imp.iva;
   var recargo = imp.recargo;
   
   var lineHtml = "<tr id=\"linea_"+numlineas+"\">\n\
         <td><input type=\"hidden\" name=\"referencia_"+numlineas+"\" value=\""+ref+"\"/>\n\
            <input type=\"hidden\" name=\"idlinea_"+numlineas+"\" value=\"-1\"/>\n\
            <input type=\"hidden\" id=\"iva_"+numlineas+"\" name=\"iva_"+numlineas+"\" value=\""+iva+"\"/>\n\
            <input type=\"hidden\" id=\"recargo_"+numlineas+"\" name=\"recargo_"+numlineas+"\" value=\""+recargo+"\"/>\n\
            <input type=\"hidden\" id=\"irpf_"+numlineas+"\" name=\"irpf_"+numlineas+"\" value=\""+irpf+"\"/>\n\
            <div class=\"form-control input-sm\"><a target=\"_blank\" href=\"index.php?page=ventas_articulo&ref="+ref+"\">"+ref+"</a></div></td>\n\
         <td><textarea class=\"form-control input-sm\" id=\"desc_"+numlineas+"\" name=\"desc_"+numlineas+"\" rows=\"1\" onclick=\"this.select()\">"+desc+"</textarea></td>\n\
         <td><input type=\"number\" step=\"any\" id=\"cantidad_"+numlineas+"\" class=\"form-control text-right input-sm\" name=\"cantidad_"+numlineas+
            "\" onchange=\"recalcular()\" onkeyup=\"recalcular()\" autocomplete=\"off\" value=\""+cantidad+"\"/></td>\n\
         "+tpvmod_line_actions_cell(numlineas)+"\n\
         <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"pvp_"+numlineas+"\" name=\"pvp_"+numlineas+"\" value=\""+pvp+
            "\" onkeyup=\"recalcular()\" onchange=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
         <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"neto_"+numlineas+"\" name=\"neto_"+numlineas+
            "\" onchange=\"ajustar_neto('"+numlineas+"')\" onkeyup=\"ajustar_neto('"+numlineas+"')\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
         <td class=\"text-right\"><div class=\"form-control input-sm\">"+iva+"</div></td>\n\
         <td class=\"text-right recargo\"><div class=\"form-control input-sm\">"+recargo+"</div></td>\n\
         <td class=\"text-right irpf\"><div class=\"form-control input-sm\">"+irpf+"</div></td>\n\
         <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"total_"+numlineas+"\" name=\"total_"+numlineas+
            "\" onchange=\"ajustar_total()\" onclick=\"this.select()\" autocomplete=\"off\"/></td></tr>";

   tpvmod_insert_linea_before_totals(lineHtml);
   recalcular();
   tpvmod_init_lineas_sortable();
   $("#modal_articulos").modal('hide');
   
   $("#pvp_"+(numlineas)).focus();
}

function buscar_articulos()
{
   if(document.f_buscar_articulos.query.value == '')
   {
      $("#search_results").html('');
   }
   else
   {
      document.f_buscar_articulos.codcliente.value = document.f_tpv.cliente.value;
      
      $.ajax({
         type: 'POST',
         url: tpv_url,
         dataType: 'html',
         data: $("form[name=f_buscar_articulos]").serialize(),
         success: function(datos) {
            var re = /<!--(.*?)-->/g;
            var m = re.exec( datos );
            if( m && m[1] == document.f_buscar_articulos.query.value )
            {
               $("#search_results").html(datos);
            }
         }
      });
   }
}

$(document).ready(function() {
   $("#b_reticket").click(function() {
      window.location.href = tpv_url+"&reticket="+prompt('Introduce el código del ticket (o déjalo en blanco para re-imprimir el último):');
   });
   
   $("#b_borrar_ticket").click(function() {
      window.location.href = tpv_url+"&delete="+prompt('Introduce el código del ticket:');
   });
   
   $("#b_cerrar_caja").click(function() {
      if( confirm("¿Realmente deseas cerrar la caja?") )
         window.location.href = tpv_url+"&cerrar_caja=TRUE";
   });
   
   $("#i_new_line").click(function() {
      $("#i_new_line").val("");
      document.f_buscar_articulos.query.value = "";
      $("#search_results").html("");
      $("#modal_articulos").modal('show');
      document.f_buscar_articulos.query.focus();
   });
   
   $("#i_new_line").keyup(function() {
      document.f_buscar_articulos.query.value = $("#i_new_line").val();
      buscar_articulos();
   });
   
   $("#f_buscar_articulos").keyup(function() {
      buscar_articulos();
   });
   
   $("#f_buscar_articulos").submit(function(event) {
      event.preventDefault();
      buscar_articulos();
   });
   
   $("#b_tpv_guardar").click(function() {
      $("#modal_guardar").modal('show');
      document.f_tpv.tpv_efectivo.focus();
   });

   tpvmod_init_lineas_sortable();

   $("#f_tpv").on("submit", function() {
      tpvmod_renumber_lineas();
   });
});