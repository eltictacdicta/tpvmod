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
var tpvmod_opcional_prefix = 'Opcional: ';
var tpvmod_line_uid_seq = 0;
var tpvmod_obligatorios_by_ref = {};
var tpvmod_divisas = {};
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

function tpvmod_cliente_tiene_recargo()
{
   return !!(cliente && (cliente.recargo === true || cliente.recargo === 1 || cliente.recargo === '1'));
}

function tpvmod_selected_coddivisa()
{
   var selected = $('select[name="divisa"]').val();
   if(selected)
      return selected;

   return (typeof empresa_coddivisa !== 'undefined' ? empresa_coddivisa : 'EUR');
}

function tpvmod_format_precio(precio)
{
   var coddivisa = tpvmod_selected_coddivisa();
   var simbolo = tpvmod_divisas[coddivisa]
      || (typeof empresa_simbolo !== 'undefined' ? empresa_simbolo : '€');
   var nf0 = (typeof FS_NF0 !== 'undefined' ? FS_NF0 : fs_nf0);
   var nf1 = (typeof FS_NF1 !== 'undefined' ? FS_NF1 : ',');
   var nf2 = (typeof FS_NF2 !== 'undefined' ? FS_NF2 : '.');
   var formatted = number_format(precio, nf0, nf1, nf2);

   if(typeof FS_POS_DIVISA !== 'undefined' && FS_POS_DIVISA === 'left')
      return simbolo + formatted;

   return formatted + ' ' + simbolo;
}

function tpvmod_new_line_uid()
{
   tpvmod_line_uid_seq += 1;
   return 'tpvl_' + tpvmod_line_uid_seq;
}

function tpvmod_assign_line_uid($row)
{
   var uid = $row.attr('data-line-uid');
   if(!uid)
   {
      uid = tpvmod_new_line_uid();
      $row.attr('data-line-uid', uid);
   }

   return uid;
}

function tpvmod_find_product_row_by_uid(parentUid)
{
   return tpvmod_line_rows().filter('[data-line-uid="'+parentUid+'"]').first();
}

function tpvmod_escape_html(text)
{
   return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
}

function tpvmod_format_opcional_desc(text, grupoNombre)
{
   var clean = String(text || '').trim();
   if(String(grupoNombre || '').trim() !== '')
      clean = String(grupoNombre).trim() + ': ' + clean;
   if(clean.indexOf(tpvmod_opcional_prefix) === 0)
      return clean;

   return tpvmod_opcional_prefix + clean;
}

function tpvmod_is_opcional_row($row)
{
   return $row.hasClass('tpvmod-line-opcional');
}

function tpvmod_opcional_actions_cell(lineNum)
{
   return "<td class=\"tpvmod-line-actions text-nowrap\">\n\
      <span class=\"btn btn-xs btn-default disabled\" title=\"Opcional vinculado al producto\">\n\
         <i class=\"fa fa-link\"></i></span>\n\
      <button class=\"btn btn-xs btn-danger\" type=\"button\" onclick=\"return tpvmod_eliminar_linea('"+lineNum+"');\">\n\
         <span class=\"glyphicon glyphicon-trash\"></span></button></td>";
}

function tpvmod_reorder_opcionales()
{
   tpvmod_line_rows().filter(':not(.tpvmod-line-opcional)').each(function() {
      var parentUid = $(this).attr('data-line-uid');
      if(!parentUid)
         return;

      var $insertAfter = $(this);
      tpvmod_line_rows()
         .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"]')
         .each(function() {
            $insertAfter.after($(this));
            $insertAfter = $(this);
         });
   });
}

function tpvmod_init_opcional_lines_from_dom()
{
   var parentUid = '';

   tpvmod_line_rows().each(function() {
      var $row = $(this);
      var desc = $.trim($row.find('[name^="desc_"]').val() || '');
      var isOpcional = $row.hasClass('tpvmod-line-opcional') || desc.indexOf(tpvmod_opcional_prefix) === 0;

      if(isOpcional)
      {
         $row.addClass('tpvmod-line-opcional');
         if(parentUid !== '')
            $row.attr('data-parent-uid', parentUid);
      }
      else
      {
         parentUid = tpvmod_assign_line_uid($row);
      }
   });

   tpvmod_reorder_opcionales();
}

function tpvmod_sync_opcional_quantities(parentLineNum)
{
   var $parent = $('#linea_'+parentLineNum);
   var parentUid = $parent.attr('data-line-uid');
   if(!parentUid)
      return;

   var qty = $('#cantidad_'+parentLineNum).val();
   tpvmod_line_rows()
      .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"]')
      .each(function() {
         var opNum = $(this).attr('id').replace('linea_', '');
         $('#cantidad_'+opNum).val(qty);
      });
}

function tpvmod_bind_opcional_quantity_sync()
{
   $(document).off('change.tpvmodOpcional keyup.tpvmodOpcional', '[id^="cantidad_"]');
   $(document).on('change.tpvmodOpcional keyup.tpvmodOpcional', '[id^="cantidad_"]', function() {
      var lineNum = this.id.replace('cantidad_', '');
      var $row = $('#linea_'+lineNum);
      if(!$row.length || tpvmod_is_opcional_row($row))
         return;

      tpvmod_sync_opcional_quantities(lineNum);
      recalcular();
   });
}

function tpvmod_add_opcional_linea(parentUid, opcional, cantidad, codimpuesto, ivaArticulo)
{
   var ctx = tpvmod_get_parent_line_context_by_uid(parentUid);
   if(!ctx)
      return;

   var parentLineNum = ctx.parentLineNum;
   numlineas += 1;
   $("#numlineas").val(numlineas);

   var imp = resolve_iva_from_codimpuesto(codimpuesto, ivaArticulo);
   var iva = imp.iva;
   var recargo = imp.recargo;
   var desc = tpvmod_format_opcional_desc(
      opcional.descripcion || opcional.nombre || '',
      opcional.grupo_nombre || ''
   );
   var pvp = opcional.precio;
   var grupoId = opcional.grupo_id ? String(opcional.grupo_id) : '';

   var lineHtml = "<tr id=\"linea_"+numlineas+"\" class=\"tpvmod-line-opcional\" data-parent-uid=\""+parentUid+"\" data-opcional-id=\""+(opcional.id || '')+"\" data-grupo-id=\""+grupoId+"\">\n\
         <td><input type=\"hidden\" name=\"referencia_"+numlineas+"\" value=\"\"/>\n\
            <input type=\"hidden\" name=\"idlinea_"+numlineas+"\" value=\"-1\"/>\n\
            <input type=\"hidden\" name=\"tpvmod_opcional_id_"+numlineas+"\" value=\""+(opcional.id || '')+"\"/>\n\
            <input type=\"hidden\" name=\"tpvmod_opcional_grupo_id_"+numlineas+"\" value=\""+grupoId+"\"/>\n\
            <input type=\"hidden\" name=\"tpvmod_parent_ref_"+numlineas+"\" value=\""+tpvmod_escape_html(ctx.ref)+"\"/>\n\
            <input type=\"hidden\" id=\"iva_"+numlineas+"\" name=\"iva_"+numlineas+"\" value=\""+iva+"\"/>\n\
            <input type=\"hidden\" id=\"recargo_"+numlineas+"\" name=\"recargo_"+numlineas+"\" value=\""+recargo+"\"/>\n\
            <input type=\"hidden\" id=\"irpf_"+numlineas+"\" name=\"irpf_"+numlineas+"\" value=\""+irpf+"\"/>\n\
            <div class=\"form-control input-sm text-muted\"><em>Opc.</em></div></td>\n\
         <td><textarea class=\"form-control input-sm\" id=\"desc_"+numlineas+"\" name=\"desc_"+numlineas+"\" rows=\"1\" onclick=\"this.select()\">"+tpvmod_escape_html(desc)+"</textarea></td>\n\
         <td><input type=\"number\" step=\"any\" id=\"cantidad_"+numlineas+"\" class=\"form-control text-right input-sm\" name=\"cantidad_"+numlineas+
            "\" onchange=\"recalcular()\" onkeyup=\"recalcular()\" autocomplete=\"off\" value=\""+cantidad+"\"/></td>\n\
         "+tpvmod_opcional_actions_cell(numlineas)+"\n\
         <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"pvp_"+numlineas+"\" name=\"pvp_"+numlineas+"\" value=\""+pvp+
            "\" onkeyup=\"recalcular()\" onchange=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
         <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"neto_"+numlineas+"\" name=\"neto_"+numlineas+
            "\" onchange=\"ajustar_neto('"+numlineas+"')\" onkeyup=\"ajustar_neto('"+numlineas+"')\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
         <td class=\"text-right\"><div class=\"form-control input-sm\">"+iva+"</div></td>\n\
         <td class=\"text-right recargo\"><div class=\"form-control input-sm\">"+recargo+"</div></td>\n\
         <td class=\"text-right irpf\"><div class=\"form-control input-sm\">"+irpf+"</div></td>\n\
         <td><input type=\"text\" class=\"form-control text-right input-sm\" id=\"total_"+numlineas+"\" name=\"total_"+numlineas+
            "\" onchange=\"ajustar_total()\" onclick=\"this.select()\" autocomplete=\"off\"/></td></tr>";

   var $parentRow = tpvmod_find_product_row_by_uid(parentUid);
   if($parentRow.length)
   {
      var $following = tpvmod_line_rows()
         .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"]')
         .last();
      if($following.length)
         $following.after(lineHtml);
      else
         $parentRow.after(lineHtml);
   }
   else
   {
      tpvmod_insert_linea_before_totals(lineHtml);
   }
}

function tpvmod_normalize_opcionales_payload(data)
{
   if(!data)
      return {grupos: [], sueltos: []};

   if(Array.isArray(data))
   {
      return {grupos: [], sueltos: data};
   }

   return {
      grupos: data.grupos || [],
      sueltos: data.sueltos || []
   };
}

function tpvmod_store_obligatorios_requirements(ref, payload)
{
   ref = $.trim(String(ref || ''));
   if(ref === '')
      return;

   payload = tpvmod_normalize_opcionales_payload(payload);
   var req = {grupos: {}, sueltos: {}};
   var g;
   var i;

   for(g = 0; g < payload.grupos.length; g++)
   {
      var grupo = payload.grupos[g];
      if(grupo.obligatorio)
         req.grupos[String(grupo.id)] = String(grupo.nombre || '');
   }

   for(i = 0; i < payload.sueltos.length; i++)
   {
      var suelto = payload.sueltos[i];
      if(suelto.obligatorio)
         req.sueltos[String(suelto.id)] = String(suelto.descripcion || suelto.codigo || '');
   }

   tpvmod_obligatorios_by_ref[ref] = req;
   tpvmod_refresh_obligatorio_warnings();
}

function tpvmod_prefetch_obligatorios_for_ref(ref, pvp)
{
   ref = $.trim(String(ref || ''));
   if(ref === '' || typeof tpv_url === 'undefined' || tpv_url === '')
      return;

   var cacheKey = ref + '|' + (pvp || 0);
   if(tpvmod_opcionales_cache[cacheKey])
   {
      tpvmod_store_obligatorios_requirements(ref, tpvmod_opcionales_cache[cacheKey]);
      return;
   }

   $.getJSON(tpv_url, {
      opcionales_articulo: ref,
      pvp: pvp || 0
   }, function(opcionales) {
      tpvmod_opcionales_cache[cacheKey] = tpvmod_normalize_opcionales_payload(opcionales);
      tpvmod_store_obligatorios_requirements(ref, tpvmod_opcionales_cache[cacheKey]);
   });
}

function tpvmod_collect_missing_obligatorios()
{
   var errors = [];

   tpvmod_line_rows().filter(':not(.tpvmod-line-opcional)').each(function() {
      var $row = $(this);
      var ref = $.trim($row.attr('data-product-ref') || $row.find('input[name^="referencia_"]').val() || '');
      if(ref === '')
         return;

      var req = tpvmod_obligatorios_by_ref[ref];
      if(!req)
         return;

      var parentUid = $row.attr('data-line-uid');
      if(!parentUid)
         return;

      var selectedGrupos = {};
      var selectedSueltos = {};
      tpvmod_line_rows()
         .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"]')
         .each(function() {
            var grupoId = $(this).attr('data-grupo-id') || '';
            var opcionalId = $(this).attr('data-opcional-id') || '';
            if(grupoId !== '')
               selectedGrupos[grupoId] = true;
            else if(opcionalId !== '')
               selectedSueltos[opcionalId] = true;
         });

      var grupoId;
      for(grupoId in req.grupos)
      {
         if(!req.grupos.hasOwnProperty(grupoId))
            continue;
         if(!selectedGrupos[grupoId])
            errors.push('Artículo ' + ref + ': debes elegir el grupo "' + req.grupos[grupoId] + '".');
      }

      var opcionalId;
      for(opcionalId in req.sueltos)
      {
         if(!req.sueltos.hasOwnProperty(opcionalId))
            continue;
         if(!selectedSueltos[opcionalId])
            errors.push('Artículo ' + ref + ': debes elegir el opcional "' + req.sueltos[opcionalId] + '".');
      }
   });

   return errors;
}

function tpvmod_validate_obligatorios_before_save()
{
   var errors = tpvmod_collect_missing_obligatorios();
   if(errors.length === 0)
      return true;

   alert(errors.join('\n'));
   return false;
}

function tpvmod_row_missing_obligatorios(ref, parentUid)
{
   var req = tpvmod_obligatorios_by_ref[ref];
   if(!req || !parentUid)
      return false;

   var selectedGrupos = {};
   var selectedSueltos = {};
   tpvmod_line_rows()
      .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"]')
      .each(function() {
         var grupoId = $(this).attr('data-grupo-id') || '';
         var opcionalId = $(this).attr('data-opcional-id') || '';
         if(grupoId !== '')
            selectedGrupos[grupoId] = true;
         else if(opcionalId !== '')
            selectedSueltos[opcionalId] = true;
      });

   var grupoId;
   for(grupoId in req.grupos)
   {
      if(req.grupos.hasOwnProperty(grupoId) && !selectedGrupos[grupoId])
         return true;
   }

   var opcionalId;
   for(opcionalId in req.sueltos)
   {
      if(req.sueltos.hasOwnProperty(opcionalId) && !selectedSueltos[opcionalId])
         return true;
   }

   return false;
}

function tpvmod_refresh_obligatorio_warnings()
{
   tpvmod_line_rows().filter(':not(.tpvmod-line-opcional)').each(function() {
      var $row = $(this);
      var ref = $.trim($row.attr('data-product-ref') || $row.find('input[name^="referencia_"]').val() || '');
      var parentUid = $row.attr('data-line-uid') || '';
      var missing = tpvmod_row_missing_obligatorios(ref, parentUid);

      $row.toggleClass('tpvmod-missing-obligatorios', missing);
      $row.find('.btn-info[title="Añadir opcional"]').toggleClass('btn-warning', missing);
   });
}

function tpvmod_opcionales_flat_list(payload)
{
   payload = tpvmod_normalize_opcionales_payload(payload);
   var flat = [];
   var g;
   var i;

   for(g = 0; g < payload.grupos.length; g++)
   {
      var ops = payload.grupos[g].opcionales || [];
      for(i = 0; i < ops.length; i++)
         flat.push(ops[i]);
   }

   for(i = 0; i < payload.sueltos.length; i++)
      flat.push(payload.sueltos[i]);

   return flat;
}

function tpvmod_get_added_opcional_ids(parentUid)
{
   var ids = {};
   tpvmod_line_rows()
      .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"]')
      .each(function() {
         var id = $(this).attr('data-opcional-id');
         if(id)
            ids[id] = true;
      });

   return ids;
}

function tpvmod_get_selected_grupo_opcional(parentUid, grupoId)
{
   if(!grupoId)
      return '';

   var selected = '';
   tpvmod_line_rows()
      .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"][data-grupo-id="'+grupoId+'"]')
      .each(function() {
         selected = $(this).attr('data-opcional-id') || '';
      });

   return selected;
}

function tpvmod_remove_opcional_in_grupo(parentUid, grupoId)
{
   if(!grupoId)
      return;

   tpvmod_line_rows()
      .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"][data-grupo-id="'+grupoId+'"]')
      .remove();
}

function tpvmod_get_parent_line_context_by_uid(parentUid)
{
   var $row = tpvmod_find_product_row_by_uid(parentUid);
   if(!$row.length || tpvmod_is_opcional_row($row))
      return null;

   var lineNum = $row.attr('id').replace('linea_', '');

   return {
      parentUid: parentUid,
      parentLineNum: lineNum,
      ref: $row.attr('data-product-ref') || $.trim($row.find('input[name^="referencia_"]').val() || ''),
      pvp: parseFloat($('#pvp_'+lineNum).val()) || 0,
      cantidad: $('#cantidad_'+lineNum).val() || 1,
      codimpuesto: $row.attr('data-codimpuesto') || '',
      ivaArticulo: $('#iva_'+lineNum).val() || 0
   };
}

function tpvmod_render_opcionales_modal(payload, parentUid)
{
   payload = tpvmod_normalize_opcionales_payload(payload);
   var added = tpvmod_get_added_opcional_ids(parentUid);
   var html = '';
   var available = 0;
   var g;
   var i;

   if((!payload.grupos || !payload.grupos.length) && (!payload.sueltos || !payload.sueltos.length))
   {
      $('#tpvmod_opcionales_list').html('<p class="text-muted">Este artículo no tiene opcionales disponibles.</p>');
      return;
   }

   for(g = 0; g < payload.grupos.length; g++)
   {
      var grupo = payload.grupos[g];
      var ops = grupo.opcionales || [];
      if(!ops.length)
         continue;

      html += '<div class="tpvmod-opcional-grupo" style="margin-bottom:12px;">';
      html += '<h5 class="text-muted" style="margin-top:0;">'+tpvmod_escape_html(grupo.nombre || 'Grupo');
      if(grupo.obligatorio)
         html += ' <small class="label label-warning">obligatorio</small>';
      if(grupo.exclusivo)
         html += ' <small class="label label-info">elige uno</small>';
      html += '</h5><div class="list-group">';

      var selectedInGrupo = tpvmod_get_selected_grupo_opcional(parentUid, String(grupo.id));

      for(i = 0; i < ops.length; i++)
      {
         var op = ops[i];
         if(!grupo.exclusivo && added[String(op.id)])
            continue;

         available++;
         var activeClass = (selectedInGrupo === String(op.id)) ? ' list-group-item-info' : '';
         html += '<button type="button" class="list-group-item'+activeClass+'" onclick="tpvmod_pick_opcional(\''+parentUid+'\', '+op.id+');">'
            + '<strong>'+tpvmod_escape_html(op.descripcion || op.codigo || '')+'</strong>';
         if(selectedInGrupo === String(op.id))
            html += ' <span class="label label-success">actual</span>';
         html += '<span class="pull-right">'+tpvmod_format_precio(op.precio)+'</span>'
            + '</button>';
      }

      html += '</div></div>';
   }

   if(payload.sueltos && payload.sueltos.length)
   {
      html += '<div class="tpvmod-opcional-sueltos"><h5 class="text-muted">Otros opcionales</h5><div class="list-group">';
      for(i = 0; i < payload.sueltos.length; i++)
      {
         var suelto = payload.sueltos[i];
         if(added[String(suelto.id)])
            continue;

         available++;
         html += '<button type="button" class="list-group-item" onclick="tpvmod_pick_opcional(\''+parentUid+'\', '+suelto.id+');">'
            + '<strong>'+tpvmod_escape_html(suelto.descripcion || suelto.codigo || '')+'</strong>'
            + '<span class="pull-right">'+tpvmod_format_precio(suelto.precio)+'</span>'
            + '</button>';
      }
      html += '</div></div>';
   }

   if(available === 0)
      html = '<p class="text-muted">Ya has añadido todos los opcionales de este artículo.</p>';

   $('#tpvmod_opcionales_list').html(html);
}

var tpvmod_opcionales_cache = {};

function tpvmod_show_opcionales_modal(parentUid)
{
   var ctx = tpvmod_get_parent_line_context_by_uid(parentUid);
   if(!ctx || !ctx.ref)
   {
      alert('Solo puedes añadir opcionales a líneas de producto.');
      return false;
   }

   $('#modal_opcionales').data('parent-uid', parentUid);
   $('#tpvmod_opcionales_list').html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Cargando opcionales...</p>');
   $('#modal_opcionales').modal('show');

   var cacheKey = ctx.ref+'|'+ctx.pvp;
   if(tpvmod_opcionales_cache[cacheKey])
   {
      tpvmod_opcionales_modal_data = tpvmod_opcionales_cache[cacheKey];
      tpvmod_store_obligatorios_requirements(ctx.ref, tpvmod_opcionales_modal_data);
      tpvmod_render_opcionales_modal(tpvmod_opcionales_modal_data, parentUid);
      return false;
   }

   $.getJSON(tpv_url, {
      opcionales_articulo: ctx.ref,
      pvp: ctx.pvp
   }, function(opcionales) {
      tpvmod_opcionales_cache[cacheKey] = tpvmod_normalize_opcionales_payload(opcionales);
      tpvmod_opcionales_modal_data = tpvmod_opcionales_cache[cacheKey];
      tpvmod_store_obligatorios_requirements(ctx.ref, tpvmod_opcionales_modal_data);
      tpvmod_render_opcionales_modal(tpvmod_opcionales_modal_data, parentUid);
   });

   return false;
}

var tpvmod_opcionales_modal_data = {grupos: [], sueltos: []};

function tpvmod_pick_opcional(parentUid, opcionalId)
{
   var ctx = tpvmod_get_parent_line_context_by_uid(parentUid);
   if(!ctx)
      return;

   var opcional = null;
   var flat = tpvmod_opcionales_flat_list(tpvmod_opcionales_modal_data);
   for(var i = 0; i < flat.length; i++)
   {
      if(parseInt(flat[i].id, 10) === parseInt(opcionalId, 10))
      {
         opcional = flat[i];
         break;
      }
   }

   if(!opcional)
      return;

   if(opcional.grupo_id && opcional.grupo_exclusivo)
      tpvmod_remove_opcional_in_grupo(parentUid, String(opcional.grupo_id));
   else if(tpvmod_get_added_opcional_ids(parentUid)[String(opcional.id)])
      return;

   tpvmod_add_opcional_linea(parentUid, opcional, ctx.cantidad, ctx.codimpuesto, ctx.ivaArticulo);
   tpvmod_reorder_opcionales();
   tpvmod_renumber_lineas();
   recalcular();
   tpvmod_init_lineas_sortable();
   tpvmod_render_opcionales_modal(tpvmod_opcionales_modal_data, parentUid);
   tpvmod_refresh_obligatorio_warnings();
}

function tpvmod_with_cliente_sync(callback)
{
   if(typeof callback !== 'function')
      return;

   if(!document.f_tpv || !document.f_tpv.cliente || nueva_venta_url === '')
   {
      callback();
      return;
   }

   var cod = document.f_tpv.cliente.value;
   if(!cod || (cliente && cliente.codcliente == cod))
   {
      callback();
      return;
   }

   $.getJSON(nueva_venta_url, 'datoscliente='+encodeURIComponent(cod), function(json) {
      if(json)
         cliente = json;
      callback();
   });
}

function tpvmod_init_tpv_calculos()
{
   usar_serie();
   tpvmod_with_cliente_sync(function() {
      recalcular();
      tpvmod_reset_unsaved_baseline();
   });
}

var tpvmod_unsaved_baseline = '';
var tpvmod_unsaved_allow_leave = false;

function tpvmod_unsaved_guard_enabled()
{
   return $('#f_tpv').length > 0;
}

function tpvmod_capture_form_snapshot()
{
   if(!tpvmod_unsaved_guard_enabled())
      return '';

   var parts = [];
   var $form = $('#f_tpv');
   var headerSelector = [
      'input[name="cliente"]',
      'input[name="fecha"]',
      'textarea[name="observaciones"]',
      'select[name="almacen"]',
      'select[name="serie"]',
      'select[name="divisa"]',
      'input[name="numero2"]',
      'input[name="id"]',
      'input[name="vienede"]'
   ].join(', ');

   $form.find(headerSelector).each(function() {
      if(!this.name)
         return;
      parts.push(this.name + '=' + String($(this).val() || ''));
   });

   $form.find('#tab_opciones :input, #tab_tickets :input').each(function() {
      if(!this.name || this.type === 'button' || this.type === 'submit')
         return;
      if(this.type === 'checkbox')
         parts.push(this.name + '=' + (this.checked ? '1' : '0'));
      else
         parts.push(this.name + '=' + String($(this).val() || ''));
   });

   $('#lineas_albaran tr[id^="linea_"]').each(function() {
      parts.push('row:' + (this.id || ''));
      $(this).find(':input').each(function() {
         if(!this.name)
            return;
         parts.push(this.name + '=' + String($(this).val() || ''));
      });
   });

   parts.push('numlineas=' + String($('#numlineas').val() || '0'));
   return parts.join('\n');
}

function tpvmod_reset_unsaved_baseline()
{
   if(!tpvmod_unsaved_guard_enabled())
      return;

   tpvmod_unsaved_baseline = tpvmod_capture_form_snapshot();
   tpvmod_unsaved_allow_leave = false;
}

function tpvmod_has_unsaved_changes()
{
   if(!tpvmod_unsaved_guard_enabled() || tpvmod_unsaved_allow_leave)
      return false;

   if(tpvmod_unsaved_baseline === '')
      return false;

   return tpvmod_capture_form_snapshot() !== tpvmod_unsaved_baseline;
}

function tpvmod_mark_submitted()
{
   tpvmod_unsaved_allow_leave = true;
}

function tpvmod_init_unsaved_guard()
{
   if(!tpvmod_unsaved_guard_enabled())
      return;

   $(window).off('beforeunload.tpvmod').on('beforeunload.tpvmod', function(e) {
      if(!tpvmod_has_unsaved_changes())
         return undefined;

      e.preventDefault();
      e.returnValue = '';
      return '';
   });
}

function tpvmod_update_tax_columns_visibility(total_recargo, total_irpf)
{
   if(tpvmod_cliente_tiene_recargo() || total_recargo != 0)
      $(".recargo").show();
   else
      $(".recargo").hide();

   if(total_irpf != 0)
      $(".irpf").show();
   else
      $(".irpf").hide();
}

function tpvmod_recargo_for_iva(iva)
{
   if(!tpvmod_cliente_tiene_recargo() || siniva || (cliente && cliente.regimeniva == 'Exento'))
      return 0;

   var ivaNum = parseFloat(iva);
   if(isNaN(ivaNum))
      return 0;

   for(var i=0; i<all_impuestos.length; i++)
   {
      if(!all_impuestos[i])
         continue;
      if(parseFloat(all_impuestos[i].iva) === ivaNum)
         return parseFloat(all_impuestos[i].recargo) || 0;
   }

   return 0;
}

function tpvmod_set_line_recargo(lineNum, recargo)
{
   var recargoVal = isNaN(recargo) ? 0 : recargo;
   var $recargoInput = $("#recargo_"+lineNum);
   if($recargoInput.length)
      $recargoInput.val(recargoVal);

   var $recargoDiv = $("#linea_"+lineNum).find("td.recargo div.form-control");
   if($recargoDiv.length)
      $recargoDiv.text(recargoVal);
}

function tpvmod_line_recargo(lineNum, iva)
{
   var recargo = tpvmod_recargo_for_iva(iva);
   tpvmod_set_line_recargo(lineNum, recargo);
   return recargo;
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
            for(var j=1; j<=numlineas; j++)
            {
               if($("#linea_"+j).length > 0)
               {
                  $("#iva_"+j).val(0);
                  tpvmod_set_line_recargo(j, 0);
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
         
         for(var j=1; j<=numlineas; j++)
         {
            if($("#linea_"+j).length > 0)
            {
               $("#irpf_"+j).html( show_numero(irpf) );
               
               if(siniva)
               {
                  $("#iva_"+j).val(0);
                  tpvmod_set_line_recargo(j, 0);
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

function tpvmod_line_actions_cell(lineNum, ref, lineUid)
{
   var opcionalBtn = '';
   if(ref && lineUid)
   {
      opcionalBtn = '<button class="btn btn-xs btn-info" type="button" title="Añadir opcional" onclick="return tpvmod_show_opcionales_modal(\''+lineUid+'\');">\n\
         <i class="fa fa-plus-circle"></i></button> ';
   }

   return "<td class=\"tpvmod-line-actions text-nowrap\">\n\
      "+opcionalBtn+"<span class=\"tpvmod-line-handle btn btn-xs btn-default\" title=\"Arrastrar para reordenar\">\n\
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

   var $row = $("#linea_"+lineNum);
   if(!tpvmod_is_opcional_row($row))
   {
      var parentUid = $row.attr('data-line-uid');
      if(parentUid)
      {
         tpvmod_line_rows()
            .filter('.tpvmod-line-opcional[data-parent-uid="'+parentUid+'"]')
            .remove();
      }
   }

   $row.remove();
   tpvmod_reorder_opcionales();
   tpvmod_renumber_lineas();
   recalcular();
   tpvmod_refresh_obligatorio_warnings();
   return false;
}

function tpvmod_submit_guardar(btn)
{
   if(!$('input[name="tipo"]:checked').length)
   {
      alert('Selecciona el tipo de documento a guardar.');
      return false;
   }

   if(!tpvmod_validate_obligatorios_before_save())
      return false;

   tpvmod_renumber_lineas();
   $('#tpv_total').prop('disabled', false);
   tpvmod_mark_submitted();
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
      items: 'tr[id^="linea_"]:not(.tpvmod-line-opcional)',
      handle: '.tpvmod-line-handle',
      axis: 'y',
      containment: 'parent',
      tolerance: 'pointer',
      stop: function() {
         tpvmod_reorder_opcionales();
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
         if(isNaN(l_iva))
            l_iva = 0;
         l_irpf = irpf;
         l_recargo = tpvmod_line_recargo(i, l_iva);
         
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
   
   tpvmod_update_tax_columns_visibility(total_recargo, total_irpf);
   
   $("#tpv_total").val( tpvmod_format_precio(neto + total_iva - total_irpf + total_recargo) );
   $("#tpv_total2").val(neto + total_iva - total_irpf + total_recargo);
   var tpv_efectivo = parseFloat( $("#tpv_efectivo").val() );
   $("#tpv_cambio").val( tpvmod_format_precio(tpv_efectivo - (neto + total_iva - total_irpf + total_recargo)) );
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

         l_recargo = tpvmod_line_recargo(i, l_iva);

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
   tpvmod_with_cliente_sync(tpvmod_add_linea_libre_core);
   return false;
}

function tpvmod_add_linea_libre_core()
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
               recargo = parseFloat(all_impuestos[i].recargo) || 0;
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
   tpvmod_add_articulo_line(ref, desc, pvp, dto, codimpuesto, cantidad, ivaArticulo);
}

function tpvmod_add_articulo_line(ref,desc,pvp,dto,codimpuesto,cantidad,ivaArticulo)
{
   numlineas += 1;
   $("#numlineas").val(numlineas);
   desc = Base64.decode(desc);
   var imp = resolve_iva_from_codimpuesto(codimpuesto, ivaArticulo);
   var iva = imp.iva;
   var recargo = imp.recargo;
   var lineUid = tpvmod_new_line_uid();
   
   var lineHtml = "<tr id=\"linea_"+numlineas+"\" data-line-uid=\""+lineUid+"\" data-product-ref=\""+ref+"\" data-codimpuesto=\""+codimpuesto+"\">\n\
         <td><input type=\"hidden\" name=\"referencia_"+numlineas+"\" value=\""+ref+"\"/>\n\
            <input type=\"hidden\" name=\"idlinea_"+numlineas+"\" value=\"-1\"/>\n\
            <input type=\"hidden\" id=\"iva_"+numlineas+"\" name=\"iva_"+numlineas+"\" value=\""+iva+"\"/>\n\
            <input type=\"hidden\" id=\"recargo_"+numlineas+"\" name=\"recargo_"+numlineas+"\" value=\""+recargo+"\"/>\n\
            <input type=\"hidden\" id=\"irpf_"+numlineas+"\" name=\"irpf_"+numlineas+"\" value=\""+irpf+"\"/>\n\
            <div class=\"form-control input-sm\"><a target=\"_blank\" href=\"index.php?page=ventas_articulo&ref="+ref+"\">"+ref+"</a></div></td>\n\
         <td><textarea class=\"form-control input-sm\" id=\"desc_"+numlineas+"\" name=\"desc_"+numlineas+"\" rows=\"1\" onclick=\"this.select()\">"+desc+"</textarea></td>\n\
         <td><input type=\"number\" step=\"any\" id=\"cantidad_"+numlineas+"\" class=\"form-control text-right input-sm\" name=\"cantidad_"+numlineas+
            "\" onchange=\"recalcular()\" onkeyup=\"recalcular()\" autocomplete=\"off\" value=\""+cantidad+"\"/></td>\n\
         "+tpvmod_line_actions_cell(numlineas, ref, lineUid)+"\n\
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
   tpvmod_prefetch_obligatorios_for_ref(ref, pvp);
   recalcular();
   tpvmod_init_lineas_sortable();
   $("#modal_articulos").modal('hide');
   
   $("#pvp_"+(numlineas)).focus();
   return numlineas;
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
   tpvmod_init_opcional_lines_from_dom();
   tpvmod_bind_opcional_quantity_sync();
   tpvmod_line_rows().filter(':not(.tpvmod-line-opcional)').each(function() {
      var ref = $.trim($(this).attr('data-product-ref') || $(this).find('input[name^="referencia_"]').val() || '');
      var pvp = $(this).find('input[name^="pvp_"]').val() || 0;
      if(ref !== '')
         tpvmod_prefetch_obligatorios_for_ref(ref, pvp);
   });
   $(document).on('change', 'select[name="divisa"]', recalcular);

   $("#f_tpv").on("submit", function(event) {
      if(!tpvmod_validate_obligatorios_before_save())
      {
         event.preventDefault();
         return false;
      }
      tpvmod_reorder_opcionales();
      tpvmod_renumber_lineas();
      tpvmod_mark_submitted();
   });

   tpvmod_init_unsaved_guard();
});