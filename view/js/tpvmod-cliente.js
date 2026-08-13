/*
 * tpvmod — client search / create / edit modals
 * Copyright (C) 2026 Javier Trujillo
 */

var tpvmod_cliente_url = '';

function tpvmodInitClienteModales(url)
{
   tpvmod_cliente_url = url;

   $(document).ready(function() {
      $('#f_buscar_clientes').on('keyup', function() {
         tpvmodBuscarClientes();
      });
      $('#f_buscar_clientes').on('submit', function(event) {
         event.preventDefault();
         tpvmodBuscarClientes();
      });

      $('.tpvmod-b-buscar-cliente').on('click', function(event) {
         event.preventDefault();
         tpvmodAbrirBuscarCliente();
      });

      $('.tpvmod-b-nuevo-cliente').on('click', function(event) {
         event.preventDefault();
         tpvmodAbrirFormularioCliente('');
      });

      $('.tpvmod-b-editar-cliente').on('click', function(event) {
         event.preventDefault();
         var cod = tpvmodGetCodclienteActivo();
         if (!cod) {
            alert('Selecciona un cliente primero.');
            return;
         }
         tpvmodAbrirFormularioCliente(cod);
      });
   });
}

function tpvmodGetCodclienteActivo()
{
   if (document.f_tpv && document.f_tpv.cliente) {
      return document.f_tpv.cliente.value;
   }
   if (document.f_custom_search && document.f_custom_search.codcliente) {
      return document.f_custom_search.codcliente.value;
   }
   return '';
}

function tpvmodFormatClienteLabel(nombre, telefono, telefono2)
{
   var label = nombre || '';
   if (telefono) {
      label += ' Tlf:' + telefono;
   }
   if (telefono2) {
      label += ' Tlf2:' + telefono2;
   }
   return label;
}

function tpvmodAbrirBuscarCliente()
{
   if (document.f_buscar_clientes) {
      document.f_buscar_clientes.query.value = '';
   }
   $('#cliente_search_results').html('');
   $('#modal_clientes').modal('show');
   if (document.f_buscar_clientes) {
      document.f_buscar_clientes.query.focus();
   }
}

function tpvmodBuscarClientes()
{
   if (!document.f_buscar_clientes) {
      return;
   }

   if (document.f_buscar_clientes.query.value === '') {
      $('#cliente_search_results').html('');
      return;
   }

   $.ajax({
      type: 'POST',
      url: tpvmod_cliente_url,
      dataType: 'html',
      data: $('form[name=f_buscar_clientes]').serialize(),
      success: function(datos) {
         var re = /<!--(.*?)-->/g;
         var m = re.exec(datos);
         if (m && m[1] === document.f_buscar_clientes.query.value) {
            $('#cliente_search_results').html(datos);
         }
      }
   });
}

function tpvmodSeleccionarCliente(codcliente, nombre, cif, telefono, labelCompleto)
{
   var label = labelCompleto || tpvmodFormatClienteLabel(nombre, telefono);

   if (document.f_tpv) {
      document.f_tpv.cliente.value = codcliente;
      if (document.f_tpv.ac_cliente) {
         document.f_tpv.ac_cliente.value = label;
      }
      if (typeof usar_cliente === 'function') {
         usar_cliente(codcliente);
      }
   } else if (document.f_custom_search) {
      document.f_custom_search.codcliente.value = codcliente;
      if (document.f_custom_search.ac_cliente) {
         document.f_custom_search.ac_cliente.value = label;
      }
      document.f_custom_search.submit();
   }

   $('#modal_clientes').modal('hide');
}

function tpvmodAbrirFormularioCliente(codcliente)
{
   var esNuevo = !codcliente;
   $('#modal_cliente_form_title').text(esNuevo ? 'Nuevo cliente' : 'Editar cliente');
   $('#tpv_cliente_form_body').html('<div class="text-center text-muted">Cargando...</div>');
   $('#modal_cliente_form').modal('show');

   $.ajax({
      type: 'POST',
      url: tpvmod_cliente_url,
      dataType: 'html',
      data: {
         cliente_form: '1',
         codcliente: codcliente || ''
      },
      success: function(html) {
         $('#tpv_cliente_form_body').html(html);
      },
      error: function() {
         $('#tpv_cliente_form_body').html('<div class="alert alert-danger">Error al cargar el formulario.</div>');
      }
   });
}

function tpvmodGuardarCliente()
{
   if (!document.f_cliente_tpv) {
      return;
   }

   $.ajax({
      type: 'POST',
      url: tpvmod_cliente_url,
      dataType: 'json',
      data: $('form[name=f_cliente_tpv]').serialize(),
      success: function(json) {
         if (!json || !json.ok) {
            var msg = (json && json.errors) ? json.errors.join('\n') : 'Error al guardar.';
            alert(msg);
            return;
         }

         tpvmodSeleccionarCliente(json.codcliente, '', '', '', json.label);
         $('#modal_cliente_form').modal('hide');
      },
      error: function() {
         alert('Error al guardar el cliente.');
      }
   });
}

function tpvmodGuardarDireccion()
{
   if (!document.getElementById('f_direccion_tpv')) {
      return;
   }

   $.ajax({
      type: 'POST',
      url: tpvmod_cliente_url,
      dataType: 'json',
      data: $('#f_direccion_tpv').find(':input').serialize() + '&_csrf_token=' + encodeURIComponent(tpvmodClienteCsrfToken()),
      success: function(json) {
         if (!json || !json.ok) {
            alert((json && json.errors) ? json.errors.join('\n') : 'Error al guardar la dirección.');
            return;
         }
         tpvmodAbrirFormularioCliente(json.codcliente);
      },
      error: function() {
         alert('Error al guardar la dirección.');
      }
   });
}

function tpvmodEditarDireccion(dirId)
{
   if (!window.tpvDireccionesData) {
      return;
   }

   var dir = null;
   for (var i = 0; i < window.tpvDireccionesData.length; i++) {
      if (parseInt(window.tpvDireccionesData[i].id, 10) === parseInt(dirId, 10)) {
         dir = window.tpvDireccionesData[i];
         break;
      }
   }
   if (!dir || !document.getElementById('f_direccion_tpv')) {
      return;
   }

   var form = document.getElementById('f_direccion_tpv');
   form.querySelector('[name="dir_id"]').value = dir.id;
   form.querySelector('[name="descripcion"]').value = dir.descripcion || '';
   form.querySelector('[name="direccion"]').value = dir.direccion || '';
   form.querySelector('[name="ciudad"]').value = dir.ciudad || '';
   form.querySelector('[name="provincia"]').value = dir.provincia || '';
   form.querySelector('[name="codpostal"]').value = dir.codpostal || '';
   form.querySelector('[name="codpais"]').value = dir.codpais || '';
   form.querySelector('[name="domenvio"]').checked = !!dir.domenvio;
   form.querySelector('[name="domfacturacion"]').checked = !!dir.domfacturacion;
}

function tpvmodEliminarDireccion(dirId)
{
   if (!document.getElementById('f_direccion_tpv') || !confirm('¿Eliminar esta dirección?')) {
      return;
   }

   var codcliente = document.querySelector('#f_direccion_tpv input[name="codcliente"]').value;
   var token = tpvmodClienteCsrfToken();

   $.ajax({
      type: 'POST',
      url: tpvmod_cliente_url,
      dataType: 'json',
      data: {
         delete_direccion_tpv: '1',
         codcliente: codcliente,
         dir_id: dirId,
         _csrf_token: token
      },
      success: function(json) {
         if (!json || !json.ok) {
            alert((json && json.errors) ? json.errors.join('\n') : 'Error al eliminar.');
            return;
         }
         tpvmodAbrirFormularioCliente(json.codcliente);
      },
      error: function() {
         alert('Error al eliminar la dirección.');
      }
   });
}

function tpvmodClienteCsrfToken()
{
   var input = document.querySelector('form[name=f_cliente_tpv] input[name="_csrf_token"]');
   if (input && input.value) {
      return input.value;
   }
   input = document.querySelector('form[name=f_direccion_tpv] input[name="_csrf_token"]');
   if (input && input.value) {
      return input.value;
   }
   input = document.querySelector('form[name=f_buscar_clientes] input[name="_csrf_token"]');
   if (input && input.value) {
      return input.value;
   }
   var meta = document.querySelector('meta[name="csrf-token"]');
   return meta ? meta.getAttribute('content') : '';
}
