package pe.edu.upeu.ecolactmobile.feature.ventas.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.*
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar
import pe.edu.upeu.ecolactmobile.feature.trabajador.*
import pe.edu.upeu.ecolactmobile.feature.ventas.*

@Composable
private fun Titulo(text: String) { Text(text, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold) }

@Composable
private fun Dato(label: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(label, Modifier.weight(1f), color = EcoLactColors.TextoSecundario, style = MaterialTheme.typography.bodyMedium)
        Text(value, Modifier.weight(1.3f), textAlign = TextAlign.End, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Medium)
    }
}

@Composable
private fun Bloque(content: @Composable ColumnScope.() -> Unit) {
    Column(Modifier.fillMaxWidth().background(Color.White, RoundedCornerShape(16.dp)).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp), content = content)
}

@Composable
private fun Accion(text: String, enabled: Boolean = true, onClick: () -> Unit) {
    Button(onClick, Modifier.fillMaxWidth().heightIn(min = 48.dp), enabled = enabled, shape = RoundedCornerShape(12.dp)) { Text(text, textAlign = TextAlign.Center) }
}

@Composable
fun VentasInicioScreen(nombre: String, ventas: List<Venta>, autorizacion: Autorizacion, vender: () -> Unit, historial: () -> Unit, qr: () -> Unit, detalle: (Venta) -> Unit) {
    val hoy = ventasEnPeriodo(ventas, "Hoy").filter { it.id > 0 && it.estado == "REGISTRADA" }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { Column { Text("Hola, ${primerNombre(nombre)}", color = EcoLactColors.TextoSecundario); Titulo("Tus ventas de hoy") } }
        item { AsistenciaRequeridaBanner(autorizacion, qr) }
        item {
            Bloque {
                Dato("Ventas de hoy", hoy.size.toString())
                HorizontalDivider(color = EcoLactColors.Bordes)
                Text("Cobrado hoy", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                Text(formatSoles(hoy.filter { it.forma_pago == "CONTADO" }.sumOf { it.importe_total }), style = MaterialTheme.typography.headlineLarge, color = EcoLactColors.VerdePrincipal, fontWeight = FontWeight.Bold)
                HorizontalDivider(color = EcoLactColors.Bordes)
                Dato("Productos vendidos", formatDecimal(hoy.sumOf { it.cantidad }, 2))
            }
        }
        item { Accion("Nueva venta", autorizacion.puedeOperar, vender) }
        item { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedButton(historial, Modifier.weight(1f)) { Text("Ver historial") }
            OutlinedButton(qr, Modifier.weight(1f)) { Text("Mi QR") }
        } }
        item { Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("Ventas recientes", Modifier.weight(1f), style = MaterialTheme.typography.titleMedium)
            TextButton(historial) { Text("Ver todas ›") }
        } }
        if (ventas.isEmpty()) item { Text("Todavía no hay ventas para mostrar.", color = EcoLactColors.TextoSecundario) }
        items(ventas.take(5), key = { it.localId ?: it.id.toString() }) { VentaFila(it) { detalle(it) } }
    }
}

@Composable
fun VentasCatalogoScreen(productos: List<ProductoVenta>, vender: Boolean, online: Boolean, cachedAt: String?, agregar: (ProductoVenta) -> Unit) {
    var buscar by remember { mutableStateOf("") }
    val puede = LocalAutorizacion.current.puedeOperar
    val filtrados = productos.filter { it.nombre.contains(buscar, true) }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item { OutlinedTextField(buscar, { buscar = it }, Modifier.fillMaxWidth(), placeholder = { Text("Buscar producto…") }, singleLine = true) }
        if (!online) item { DatosGuardadosNote(cachedAt) }
        if (vender && !puede) item { NotaRequiereAsistencia() }
        if (filtrados.isEmpty()) item { Text("No hay productos disponibles para mostrar.") }
        items(filtrados, key = { it.id }) { p -> Bloque {
            Text(p.nombre, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
            Text("Stock: ${formatDecimal(p.stock)} unidades", color = if (p.stock <= 0) EcoLactColors.Rojo else EcoLactColors.TextoSecundario)
            p.minimoMayorista?.let { Text("Mayorista desde ${formatDecimal(it).removeSuffix(".00")} unid.", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.VerdePrincipal) }
            if (vender) {
                if (p.rangos.isEmpty()) Text("Sin precio vigente", color = EcoLactColors.TextoSecundario)
                Accion("Agregar", puede && p.stock > 0 && p.rangos.isNotEmpty()) { agregar(p) }
            }
            else {
                Dato("Precio público", p.publico?.let(::formatSoles) ?: "No disponible")
                Dato("Precio mayorista", p.mayorista?.let(::formatSoles) ?: "No disponible")
                Dato("Precio proveedor", p.proveedor?.let(::formatSoles) ?: "No disponible")
            }
        } }
    }
}

@Composable
fun VentasClienteScreen(compradores: List<ClienteVenta>, proveedores: List<ClienteVenta>, onNuevo: () -> Unit, seleccionar: (String, ClienteVenta?) -> Unit) {
    var tipo by remember { mutableStateOf("PUBLICO") }
    var buscar by remember { mutableStateOf("") }
    val lista = (if (tipo == "PROVEEDOR") proveedores else compradores).filter { it.estado == "ACTIVO" && (it.nombre.contains(buscar, true) || it.documento.orEmpty().contains(buscar)) }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item { Titulo("¿A quién vendes?") }
        item { Bloque {
            listOf("PUBLICO" to "Público general", "COMPRADOR" to "Comprador registrado", "PROVEEDOR" to "Proveedor").forEach { (id, texto) ->
                Row(Modifier.fillMaxWidth().clickable { tipo = id; buscar = "" }, verticalAlignment = Alignment.CenterVertically) {
                    RadioButton(tipo == id, onClick = { tipo = id; buscar = "" }); Text(texto)
                }
            }
        } }
        if (tipo == "PUBLICO") item { Accion("Continuar con público general") { seleccionar(tipo, null) } }
        else {
            item { OutlinedTextField(buscar, { buscar = it }, Modifier.fillMaxWidth(), label = { Text(if (tipo == "PROVEEDOR") "Nombre o DNI" else "Nombre, DNI o RUC") }, singleLine = true) }
            if (tipo == "COMPRADOR") item { OutlinedButton(onNuevo, Modifier.fillMaxWidth(), enabled = LocalAutorizacion.current.puedeOperar) { Text("Registrar nuevo comprador") } }
            if (lista.isEmpty()) item { Text("No se encontraron clientes.") }
            items(lista, key = { it.id }) { c -> Box(Modifier.clickable { seleccionar(tipo, c) }) { Bloque { Text(c.nombre, fontWeight = FontWeight.SemiBold); Text(c.documento ?: "Sin documento", color = EcoLactColors.TextoSecundario) } } }
        }
    }
}

@Composable
fun VentasResumenScreen(producto: ProductoVenta, tipo: String, cliente: ClienteVenta?, cotizacion: CotizacionVenta?, busy: Boolean, online: Boolean, cantidad: String, pago: String, onCantidad: (String) -> Unit, onPago: (String) -> Unit, onCotizar: (Double, String) -> Unit, onRegistrar: () -> Unit) {
    val puede = LocalAutorizacion.current.puedeOperar
    val numero = cantidad.replace(',', '.').toDoubleOrNull()
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { Bloque { Text(producto.nombre, style = MaterialTheme.typography.titleLarge); Text(cliente?.nombre ?: "Público general", color = EcoLactColors.TextoSecundario) } }
        item { OutlinedTextField(cantidad, onCantidad, Modifier.fillMaxWidth(), enabled = !busy, label = { Text("Cantidad") }, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal), singleLine = true) }
        item { Bloque {
            Text("Forma de pago", style = MaterialTheme.typography.titleSmall)
            if (tipo == "PROVEEDOR") {
                listOf("CONTADO" to "Efectivo", "DESCUENTO_PLANILLA" to "Descuento del pago semanal").forEach { (id, texto) ->
                    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        RadioButton(pago == id, onClick = { onPago(id) }, enabled = !busy && (online || id == "CONTADO"))
                        Text(texto, Modifier.weight(1f))
                    }
                }
                if (!online) Text("Necesitas conexión para validar el saldo disponible del proveedor.", color = EcoLactColors.Naranja, style = MaterialTheme.typography.bodySmall)
            } else Text("Efectivo", fontWeight = FontWeight.SemiBold)
        } }
        if (cotizacion == null) item { Accion("Ver resumen", !busy && numero != null && numero.isFinite() && numero > 0 && (online || pago == "CONTADO")) { onCotizar(numero!!, pago) } }
        else {
            item { Bloque {
                Dato("Precio unitario", formatSoles(cotizacion.precio_unitario))
                Dato("Subtotal", formatSoles(cotizacion.importe_total))
                Text("✓ Precio ${when (cotizacion.tipo_cliente) { "PROVEEDOR" -> "proveedor"; "MAYORISTA" -> "mayorista"; else -> "público" }} aplicado", color = EcoLactColors.VerdePrincipal)
                HorizontalDivider(color = EcoLactColors.Bordes)
                Text("TOTAL", style = MaterialTheme.typography.labelLarge)
                Text(formatSoles(cotizacion.importe_total), style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold)
                if (!online) Text("Precio guardado. Se validará al enviar la venta.", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.Naranja)
            } }
            item { Accion(if (online) "Registrar venta" else "Guardar venta", !busy && puede && (online || pago == "CONTADO"), onRegistrar); NotaRequiereAsistencia() }
        }
    }
}

@Composable
fun VentasConfirmacionScreen(venta: Venta, busy: Boolean, boleta: () -> Unit, nueva: () -> Unit, historial: () -> Unit) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
        item { Text(if (venta.error != null) "!" else "✓", style = MaterialTheme.typography.displayLarge, color = if (venta.error != null) EcoLactColors.Rojo else if (venta.pendiente) EcoLactColors.Naranja else EcoLactColors.VerdePrincipal) }
        item { Titulo(when { venta.error != null -> "No se pudo registrar la venta"; busy -> "Enviando venta…"; venta.pendiente -> "Venta guardada en el dispositivo"; else -> "Venta registrada" }) }
        item { Bloque { Dato("Código", venta.codigo); Dato("Total", formatSoles(venta.importe_total)); Text(venta.estadoVisible) } }
        if (venta.error != null) item { Text(venta.error, color = EcoLactColors.Rojo) }
        else if (venta.pendiente) item { Text("Pendiente de sincronización. Se enviará automáticamente cuando vuelva la conexión.", color = EcoLactColors.Naranja) }
        else item { Accion("Ver boleta", onClick = boleta) }
        item { Accion("Registrar otra venta", !busy, nueva) }
        item { OutlinedButton(historial, Modifier.fillMaxWidth()) { Text("Ver historial") } }
    }
}

@Composable
private fun VentaFila(v: Venta, onClick: () -> Unit) {
    Column(Modifier.fillMaxWidth().background(Color.White).clickable(onClick = onClick).padding(vertical = 14.dp, horizontal = 12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(v.codigo, Modifier.weight(0.9f), style = MaterialTheme.typography.labelMedium, fontWeight = FontWeight.Bold)
            Text(v.cliente, Modifier.weight(1.25f), style = MaterialTheme.typography.bodyMedium)
            Text(formatSoles(v.importe_total), Modifier.weight(1f), textAlign = TextAlign.End, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
            Text("›")
        }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(formatRelativo(v.fecha, ZONA_OPERACION), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
            Text(v.estadoVisible, style = MaterialTheme.typography.labelSmall, color = when { v.error != null -> EcoLactColors.Rojo; v.pendiente -> EcoLactColors.Naranja; else -> EcoLactColors.VerdePrincipal })
        }
    }
}

@Composable
fun VentasHistorialScreen(ventas: List<Venta>, detalle: (Venta) -> Unit) {
    var buscar by remember { mutableStateOf("") }
    var periodo by remember { mutableStateOf("Todas") }
    val filtradas = ventasEnPeriodo(ventas, periodo).filter { it.codigo.contains(buscar, true) || it.cliente.contains(buscar, true) || it.documento.orEmpty().contains(buscar) }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        item { Text("HISTORIAL DE VENTAS", style = MaterialTheme.typography.labelLarge) }
        item { OutlinedTextField(buscar, { buscar = it }, Modifier.fillMaxWidth(), placeholder = { Text("Buscar…") }, singleLine = true) }
        item { Column {
            listOf(listOf("Todas", "Hoy"), listOf("Esta semana", "Este mes")).forEach { opciones ->
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) { opciones.forEach { p -> FilterChip(periodo == p, { periodo = p }, { Text(p) }, modifier = Modifier.weight(1f), colors = FilterChipDefaults.filterChipColors(selectedContainerColor = EcoLactColors.VerdeClaro, selectedLabelColor = EcoLactColors.VerdeOscuro)) } }
            }
        } }
        item { Row(Modifier.fillMaxWidth().padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("VENTA", Modifier.weight(0.9f), style = MaterialTheme.typography.labelSmall)
            Text("CLIENTE", Modifier.weight(1.25f), style = MaterialTheme.typography.labelSmall)
            Text("TOTAL", Modifier.weight(1f), textAlign = TextAlign.End, style = MaterialTheme.typography.labelSmall)
            Spacer(Modifier.width(8.dp))
        } }
        if (filtradas.isEmpty()) item { Text("No hay ventas en este período.", modifier = Modifier.padding(16.dp)) }
        items(filtradas, key = { it.localId ?: it.id.toString() }) { VentaFila(it) { detalle(it) }; HorizontalDivider(color = EcoLactColors.Bordes) }
    }
}

@Composable
fun VentasDetalleScreen(v: Venta, boleta: () -> Unit) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { Bloque {
            Text(v.codigo, style = MaterialTheme.typography.headlineMedium, color = EcoLactColors.VerdePrincipal)
            Dato("Fecha", "${formatFecha(v.fecha, ZONA_OPERACION)} · ${formatHora(v.fecha, ZONA_OPERACION)}")
            Dato("Cliente", v.cliente); Dato(v.etiquetaDocumento, v.documentoBoleta)
            HorizontalDivider(); Dato("Producto", v.producto_nombre); Dato("Cantidad", formatDecimal(v.cantidad))
            Dato("Tipo de precio", v.tipoPrecio); Dato("Precio unitario", formatSoles(v.precio_unitario)); Dato("Subtotal", formatSoles(v.importe_total))
            HorizontalDivider(); Text("TOTAL", style = MaterialTheme.typography.labelLarge)
            Text(formatSoles(v.importe_total), style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold)
            Dato("Forma de pago", v.pago); Dato("Vendedor", v.vendedor); Dato("Estado", v.estadoVisible)
            v.error?.let { Text(it, color = EcoLactColors.Rojo) }
        } }
        if (v.id > 0) item { Accion("Ver boleta", onClick = boleta) }
    }
}

@Composable
fun VentasBoletaScreen(v: Venta) {
    LazyColumn(Modifier.fillMaxSize().background(Color.White), contentPadding = PaddingValues(horizontal = 20.dp, vertical = 28.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
        item { Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("EcoLact", style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold, color = EcoLactColors.VerdePrincipal)
            Text("BOLETA DE VENTA", style = MaterialTheme.typography.labelLarge)
            Text(v.codigo, style = MaterialTheme.typography.titleMedium)
        } }
        item { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text("Fecha ${formatFecha(v.fecha, ZONA_OPERACION)}", style = MaterialTheme.typography.bodySmall)
            Text("Hora ${formatHora(v.fecha, ZONA_OPERACION)}", style = MaterialTheme.typography.bodySmall)
        }; HorizontalDivider(Modifier.padding(top = 16.dp), color = EcoLactColors.Bordes) }
        item { Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text("DATOS DEL CLIENTE", style = MaterialTheme.typography.labelSmall, color = EcoLactColors.TextoSecundario)
            Text(v.cliente, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
            Text("${v.etiquetaDocumento}: ${v.documentoBoleta}", style = MaterialTheme.typography.bodyMedium)
            if (v.proveedor_id != null) Text("Tipo: Proveedor", style = MaterialTheme.typography.bodySmall)
        } }
        item { HorizontalDivider(color = EcoLactColors.Bordes) }
        item { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            Text("Cant.", Modifier.weight(0.55f), style = MaterialTheme.typography.labelSmall)
            Text("Descripción", Modifier.weight(1.5f), style = MaterialTheme.typography.labelSmall)
            Text("P. Unit.", Modifier.weight(0.9f), style = MaterialTheme.typography.labelSmall, textAlign = TextAlign.End)
            Text("Total", Modifier.weight(0.95f), style = MaterialTheme.typography.labelSmall, textAlign = TextAlign.End)
        } }
        item { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(formatDecimal(v.cantidad).removeSuffix(".00"), Modifier.weight(0.55f), style = MaterialTheme.typography.bodySmall)
            Text(v.producto_nombre, Modifier.weight(1.5f), style = MaterialTheme.typography.bodySmall)
            Text(formatSoles(v.precio_unitario), Modifier.weight(0.9f), style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.End)
            Text(formatSoles(v.importe_total), Modifier.weight(0.95f), style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.End, fontWeight = FontWeight.SemiBold)
        } }
        item { HorizontalDivider(color = EcoLactColors.Bordes) }
        item { Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("TOTAL", Modifier.weight(1f), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text(formatSoles(v.importe_total), Modifier.weight(2f), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold, textAlign = TextAlign.End)
        } }
        item { HorizontalDivider(color = EcoLactColors.Bordes) }
        item { Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Forma de pago", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.TextoSecundario)
            Text(v.pago, style = MaterialTheme.typography.bodyLarge)
            if (v.forma_pago == "DESCUENTO_PLANILLA") Text("Este monto se descontará del próximo pago semanal del proveedor.", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
        } }
    }
}

@Composable
fun VentasCompradoresScreen(compradores: List<ClienteVenta>, nuevo: () -> Unit) {
    var buscar by remember { mutableStateOf("") }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item { OutlinedTextField(buscar, { buscar = it }, Modifier.fillMaxWidth(), label = { Text("Nombre, DNI o RUC") }, singleLine = true) }
        item { Accion("Nuevo comprador", LocalAutorizacion.current.puedeOperar, nuevo); NotaRequiereAsistencia() }
        items(compradores.filter { it.nombre.contains(buscar, true) || it.documento.orEmpty().contains(buscar) }, key = { it.id }) { c -> Bloque { Text(c.nombre, fontWeight = FontWeight.SemiBold); Text(c.documento ?: "Sin documento"); c.telefono?.let { Text(it, color = EcoLactColors.TextoSecundario) } } }
        if (compradores.isEmpty()) item { Text("No hay compradores guardados.") }
    }
}

@Composable
fun VentasNuevoCompradorScreen(busy: Boolean, online: Boolean, guardar: (NuevoComprador) -> Unit) {
    var nombre by remember { mutableStateOf("") }
    var documento by remember { mutableStateOf("") }
    var telefono by remember { mutableStateOf("") }
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { OutlinedTextField(nombre, { nombre = it.take(150) }, Modifier.fillMaxWidth(), label = { Text("Nombre / razón social") }, enabled = !busy) }
        item { OutlinedTextField(documento, { documento = it.take(20) }, Modifier.fillMaxWidth(), label = { Text("Documento (DNI / RUC)") }, enabled = !busy, singleLine = true) }
        item { OutlinedTextField(telefono, { telefono = it.take(20) }, Modifier.fillMaxWidth(), label = { Text("Teléfono") }, enabled = !busy, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone), singleLine = true) }
        if (!online) item { Text("Necesitas conexión para registrar un comprador.", color = EcoLactColors.Naranja) }
        item { Accion("Registrar comprador", !busy && online && nombre.isNotBlank() && LocalAutorizacion.current.puedeOperar) { guardar(NuevoComprador(nombre.trim(), documento.trim().ifBlank { null }, telefono.trim().ifBlank { null })) }; NotaRequiereAsistencia() }
    }
}

@Composable
fun VentasPerfilScreen(nombre: String, iniciales: String, usuario: String, dni: String?) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { InitialsAvatar(iniciales, large = true) }
        item { Titulo(nombre); Text("Personal de ventas", color = EcoLactColors.TextoSecundario) }
        item { Bloque { Dato("Usuario", usuario); Dato("DNI", dni ?: "—") } }
    }
}

@Composable
fun VentasComunicadosScreen(comunicados: List<ComunicadoVentas>) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        if (comunicados.isEmpty()) item { Text("No hay comunicados guardados.") }
        items(comunicados, key = { it.id }) { c -> Bloque { Text(c.titulo, style = MaterialTheme.typography.titleMedium); Text(c.contenido); Text(formatFecha(c.fecha_publicacion, ZONA_OPERACION), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario) } }
    }
}
