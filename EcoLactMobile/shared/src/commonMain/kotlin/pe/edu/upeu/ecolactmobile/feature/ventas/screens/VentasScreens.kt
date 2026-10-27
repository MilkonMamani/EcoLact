package pe.edu.upeu.ecolactmobile.feature.ventas.screens

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pe.edu.upeu.ecolactmobile.core.ui.components.DatosGuardadosNote
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.*
import pe.edu.upeu.ecolactmobile.feature.planta.components.AccionRapida
import pe.edu.upeu.ecolactmobile.feature.planta.components.EncabezadoSeccion
import pe.edu.upeu.ecolactmobile.feature.proveedor.Tono
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.*
import pe.edu.upeu.ecolactmobile.feature.trabajador.*
import pe.edu.upeu.ecolactmobile.feature.ventas.*

// Columnas de la "tabla" móvil del historial: cabecera y filas comparten los mismos pesos.
private const val PESO_CODIGO = 0.9f
private const val PESO_CLIENTE = 1.3f
private const val PESO_TOTAL = 1f
private val RADIO = 12.dp

private fun Venta.tono() = when { error != null -> Tono.ERROR; pendiente -> Tono.ATENCION; else -> Tono.EXITO }

private fun colorDe(tono: Tono) = when (tono) {
    Tono.EXITO -> EcoLactColors.VerdePrincipal
    Tono.ATENCION -> EcoLactColors.Naranja
    Tono.ERROR -> EcoLactColors.Rojo
    Tono.NEUTRAL -> EcoLactColors.TextoSecundario
}

private fun tipoPrecioTexto(tipo: String) = when (tipo) { "PROVEEDOR" -> "proveedor"; "MAYORISTA" -> "mayorista"; else -> "público" }

/** Hoy: solo la hora; otros días: día y mes. Siempre en hora de Perú. */
private fun momentoCorto(fecha: String) =
    if (parseFechaLocal(fecha, ZONA_OPERACION)?.date == hoy(ZONA_OPERACION)) formatHora(fecha, ZONA_OPERACION) else formatDiaMes(fecha, ZONA_OPERACION)

/** Elemento con separación inferior, para listas cuyo LazyColumn no deja espacio entre filas agrupadas. */
private fun LazyListScope.bloque(espacio: Int = 12, content: @Composable () -> Unit) =
    item { Box(Modifier.anchoContenido().padding(bottom = espacio.dp)) { content() } }

@Composable
private fun BotonPrincipal(text: String, enabled: Boolean = true, onClick: () -> Unit) {
    Button(
        onClick, Modifier.anchoContenido().heightIn(min = 52.dp), enabled = enabled, shape = RoundedCornerShape(RADIO),
        colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
    ) { Text(text, style = MaterialTheme.typography.labelLarge, textAlign = TextAlign.Center) }
}

@Composable
private fun BotonSecundario(text: String, enabled: Boolean = true, onClick: () -> Unit) {
    OutlinedButton(
        onClick, Modifier.anchoContenido().heightIn(min = 52.dp), enabled = enabled, shape = RoundedCornerShape(RADIO),
        border = BorderStroke(1.dp, EcoLactColors.Bordes),
    ) { Text(text, style = MaterialTheme.typography.labelLarge, color = EcoLactColors.VerdeOscuro, textAlign = TextAlign.Center) }
}

@Composable
private fun CampoBusqueda(valor: String, placeholder: String, onChange: (String) -> Unit) {
    OutlinedTextField(
        valor, onChange, Modifier.anchoContenido(), singleLine = true, shape = RoundedCornerShape(RADIO),
        placeholder = { Text(placeholder) },
        leadingIcon = { Icon(EcoLactIcons.Search, contentDescription = null, tint = EcoLactColors.TextoSecundario) },
        colors = OutlinedTextFieldDefaults.colors(
            focusedContainerColor = EcoLactColors.Blanco, unfocusedContainerColor = EcoLactColors.Blanco,
            unfocusedBorderColor = EcoLactColors.Bordes, focusedBorderColor = EcoLactColors.VerdePrincipal,
        ),
    )
}

/** Fila etiqueta / valor dentro de una tarjeta; el valor puede ocupar dos líneas sin aplastar la etiqueta. */
@Composable
private fun Fila(label: String, value: String, primera: Boolean = false, destacado: Boolean = false, color: Color? = null) {
    if (!primera) HorizontalDivider(color = EcoLactColors.Bordes)
    Row(Modifier.fillMaxWidth().heightIn(min = 44.dp).padding(vertical = 10.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(label, Modifier.weight(0.9f), style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
        Text(
            value, Modifier.weight(1.3f), textAlign = TextAlign.End, maxLines = 2, overflow = TextOverflow.Ellipsis,
            style = if (destacado) MaterialTheme.typography.titleLarge else MaterialTheme.typography.bodyLarge,
            fontWeight = if (destacado) FontWeight.Bold else FontWeight.Medium,
            color = color ?: EcoLactColors.TextoPrincipal,
        )
    }
}

@Composable
private fun Etiqueta(text: String, modifier: Modifier = Modifier, color: Color = EcoLactColors.TextoSecundario) {
    Text(text.uppercase(), modifier, style = MaterialTheme.typography.labelSmall.copy(letterSpacing = 0.6.sp), color = color)
}

@Composable
private fun Aviso(text: String, tono: Tono, icon: ImageVector = EcoLactIcons.Info) {
    val (fondo, color) = when (tono) {
        Tono.EXITO -> EcoLactColors.VerdeClaro to EcoLactColors.VerdeOscuro
        Tono.ATENCION -> EcoLactColors.NaranjaClaro to EcoLactColors.NaranjaOscuro
        Tono.ERROR -> EcoLactColors.RojoClaro to EcoLactColors.Rojo
        Tono.NEUTRAL -> EcoLactColors.Fondo to EcoLactColors.TextoSecundario
    }
    Row(Modifier.anchoContenido().background(fondo, RoundedCornerShape(RADIO)).padding(12.dp), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Icon(icon, contentDescription = null, tint = color, modifier = Modifier.size(20.dp))
        Text(text, style = MaterialTheme.typography.bodyMedium, color = color)
    }
}

@Composable
private fun EstadoVenta(v: Venta) {
    val color = colorDe(v.tono())
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
        Box(Modifier.size(6.dp).background(color, CircleShape))
        Text(v.estadoVisible, style = MaterialTheme.typography.labelSmall, color = color, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
private fun CabeceraTabla() {
    // end = padding de fila (8) + espacio (8) + chevron (24): alinea TOTAL con los importes.
    Row(Modifier.anchoContenido().padding(start = 16.dp, end = 40.dp, bottom = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Etiqueta("Venta", Modifier.weight(PESO_CODIGO))
        Etiqueta("Cliente", Modifier.weight(PESO_CLIENTE))
        Text("TOTAL", Modifier.weight(PESO_TOTAL), textAlign = TextAlign.End, style = MaterialTheme.typography.labelSmall.copy(letterSpacing = 0.6.sp), color = EcoLactColors.TextoSecundario)
    }
}

/** Fila de la tabla móvil: toda la fila es pulsable, sin desplazamiento horizontal. */
@Composable
private fun FilaVenta(v: Venta, primera: Boolean, ultima: Boolean, onClick: () -> Unit) {
    val shape = RoundedCornerShape(
        topStart = if (primera) RADIO else 0.dp, topEnd = if (primera) RADIO else 0.dp,
        bottomStart = if (ultima) RADIO else 0.dp, bottomEnd = if (ultima) RADIO else 0.dp,
    )
    Column(Modifier.anchoContenido().clip(shape).background(EcoLactColors.Blanco)) {
        if (!primera) HorizontalDivider(color = EcoLactColors.Bordes, modifier = Modifier.padding(start = 16.dp))
        Row(
            Modifier.fillMaxWidth().heightIn(min = 64.dp).clickable(role = Role.Button, onClick = onClick).padding(start = 16.dp, end = 8.dp, top = 12.dp, bottom = 12.dp),
            verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Column(Modifier.weight(PESO_CODIGO), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(v.codigo, style = MaterialTheme.typography.labelLarge, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(momentoCorto(v.fecha), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario, maxLines = 1)
            }
            Column(Modifier.weight(PESO_CLIENTE), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(v.cliente, style = MaterialTheme.typography.bodyMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
                EstadoVenta(v)
            }
            Text(formatSoles(v.importe_total), Modifier.weight(PESO_TOTAL), textAlign = TextAlign.End, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, maxLines = 1)
            Icon(EcoLactIcons.ChevronRight, contentDescription = null, tint = EcoLactColors.TextoSecundario, modifier = Modifier.size(24.dp))
        }
    }
}

@Composable
private fun ProductoCabecera(p: ProductoVenta) {
    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(p.nombreBase, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, maxLines = 2, overflow = TextOverflow.Ellipsis)
        p.presentacion?.let { Text(it, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) }
    }
}

@Composable
private fun StockTexto(p: ProductoVenta) {
    Text(
        if (p.stock <= 0) "Sin stock" else "Stock: ${formatCantidad(p.stock)} unidades",
        style = MaterialTheme.typography.bodyMedium,
        color = if (p.stock <= 0) EcoLactColors.Rojo else EcoLactColors.TextoPrincipal,
    )
}

@Composable
private fun EtiquetaMayorista(minimo: Double) {
    Text(
        "Mayorista desde ${formatCantidad(minimo)} unid.",
        style = MaterialTheme.typography.labelMedium, color = EcoLactColors.VerdeOscuro,
        modifier = Modifier.background(EcoLactColors.VerdeClaro, RoundedCornerShape(50)).padding(horizontal = 10.dp, vertical = 4.dp),
    )
}

// ───────────────────────────── Inicio ─────────────────────────────

@Composable
fun VentasInicioScreen(nombre: String, ventas: List<Venta>, autorizacion: Autorizacion, vender: () -> Unit, historial: () -> Unit, qr: () -> Unit, detalle: (Venta) -> Unit) {
    val resumen = resumenDelDia(ventas)
    val recientes = ventas.take(5)
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido()) {
                Text("Hola, ${primerNombre(nombre)}", style = MaterialTheme.typography.headlineSmall, color = EcoLactColors.TextoPrincipal)
                Text("Personal de ventas", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
            }
        }
        item { AsistenciaRequeridaBanner(autorizacion, qr) }
        item {
            // Tres KPIs propios: lo cobrado manda; nunca saldo ni movimientos de Caja.
            Column(Modifier.anchoContenido().contenedorBlanco()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text("Cobrado hoy", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                    Text(formatSoles(resumen.cobrado), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold, color = EcoLactColors.VerdeOscuro)
                }
                HorizontalDivider(color = EcoLactColors.Bordes)
                Row(Modifier.fillMaxWidth().height(IntrinsicSize.Min)) {
                    listOf("Ventas de hoy" to "${resumen.ventas}", "Productos vendidos" to formatCantidad(resumen.unidades)).forEachIndexed { i, (label, valor) ->
                        if (i > 0) VerticalDivider(color = EcoLactColors.Bordes)
                        Column(Modifier.weight(1f).padding(16.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                            Text(label, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario, maxLines = 1, overflow = TextOverflow.Ellipsis)
                            Text(valor, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            }
        }
        item {
            Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Acciones rápidas", style = MaterialTheme.typography.titleSmall, color = EcoLactColors.TextoPrincipal)
                Row(Modifier.height(IntrinsicSize.Min), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    AccionRapida("Nueva venta", EcoLactIcons.ShoppingCart, vender, Modifier.weight(1f).fillMaxHeight(), habilitada = autorizacion.puedeOperar)
                    AccionRapida("Ver historial", EcoLactIcons.History, historial, Modifier.weight(1f).fillMaxHeight())
                    AccionRapida("Mi QR", EcoLactIcons.QrCode, qr, Modifier.weight(1f).fillMaxHeight())
                }
            }
        }
        item {
            Column(Modifier.anchoContenido()) {
                EncabezadoSeccion("Ventas recientes", "Ver todas", historial)
                if (recientes.isEmpty()) EstadoVacio("Todavía no registras ventas.")
                recientes.forEachIndexed { i, v -> FilaVenta(v, i == 0, i == recientes.lastIndex) { detalle(v) } }
            }
        }
    }
}

// ───────────────────────────── Nueva venta / Productos ─────────────────────────────

@Composable
fun VentasCatalogoScreen(productos: List<ProductoVenta>, online: Boolean, cachedAt: String?, agregar: (ProductoVenta) -> Unit) {
    var buscar by remember { mutableStateOf("") }
    val puede = LocalAutorizacion.current.puedeOperar
    val filtrados = productos.filter { it.nombre.contains(buscar.trim(), true) }.sortedByDescending { it.vendible }
    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(12.dp)) {
        item { CampoBusqueda(buscar, "Buscar producto…") { buscar = it } }
        if (!online) item { DatosGuardadosNote(cachedAt, Modifier.anchoContenido()) }
        if (!puede) item { NotaRequiereAsistencia() }
        if (filtrados.isEmpty()) item { EstadoVacio(if (buscar.isBlank()) "No hay productos disponibles para vender." else "Ningún producto coincide con la búsqueda.") }
        itemsIndexed(filtrados, key = { _, p -> p.id }) { _, p ->
            Row(Modifier.anchoContenido().contenedorBlanco().padding(16.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    ProductoCabecera(p)
                    StockTexto(p)
                    p.minimoMayorista?.let { EtiquetaMayorista(it) }
                    if (p.rangos.isEmpty()) Text("Sin precio vigente", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
                }
                Button(
                    { agregar(p) }, enabled = puede && p.vendible, shape = RoundedCornerShape(RADIO),
                    colors = ButtonDefaults.buttonColors(containerColor = EcoLactColors.VerdePrincipal),
                    contentPadding = PaddingValues(horizontal = 14.dp),
                ) {
                    Icon(EcoLactIcons.Add, contentDescription = null, modifier = Modifier.size(18.dp))
                    Text("Agregar", Modifier.padding(start = 4.dp))
                }
            }
        }
    }
}

/** Solo lectura: precios y mínimos los configura el administrador. */
@Composable
fun VentasProductosScreen(productos: List<ProductoVenta>, online: Boolean, cachedAt: String?) {
    var buscar by remember { mutableStateOf("") }
    val filtrados = productos.filter { it.nombre.contains(buscar.trim(), true) }
    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(12.dp)) {
        item { CampoBusqueda(buscar, "Buscar producto…") { buscar = it } }
        if (!online) item { DatosGuardadosNote(cachedAt, Modifier.anchoContenido()) }
        if (filtrados.isEmpty()) item { EstadoVacio("No hay productos para mostrar.") }
        itemsIndexed(filtrados, key = { _, p -> p.id }) { _, p ->
            Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp)) {
                Column(Modifier.padding(vertical = 14.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) { ProductoCabecera(p); StockTexto(p) }
                Fila("Precio público", p.publico?.let(::formatSoles) ?: "—")
                Fila("Precio mayorista", p.mayorista?.let(::formatSoles) ?: "—")
                Fila("Mínimo mayorista", p.minimoMayorista?.let { "${formatCantidad(it)} unid." } ?: "—")
                Fila("Precio proveedor", p.proveedor?.let(::formatSoles) ?: "—")
            }
        }
    }
}

// ───────────────────────────── Seleccionar cliente ─────────────────────────────

@Composable
private fun OpcionCliente(icon: ImageVector, titulo: String, subtitulo: String, seleccionada: Boolean, onClick: () -> Unit) {
    Row(
        Modifier.anchoContenido().clip(RoundedCornerShape(RADIO))
            .background(if (seleccionada) EcoLactColors.VerdeClaro else EcoLactColors.Blanco)
            .border(if (seleccionada) 1.5.dp else 1.dp, if (seleccionada) EcoLactColors.VerdePrincipal else EcoLactColors.Bordes, RoundedCornerShape(RADIO))
            .clickable(role = Role.RadioButton, onClick = onClick).padding(14.dp),
        horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(40.dp).background(if (seleccionada) EcoLactColors.Blanco else EcoLactColors.VerdeClaro, CircleShape), contentAlignment = Alignment.Center) {
            Icon(icon, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(22.dp))
        }
        Column(Modifier.weight(1f)) {
            Text(titulo, style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold)
            Text(subtitulo, style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
        }
        Icon(EcoLactIcons.ChevronRight, contentDescription = null, tint = EcoLactColors.TextoSecundario)
    }
}

@Composable
fun VentasClienteScreen(producto: ProductoVenta?, compradores: List<ClienteVenta>, proveedores: List<ClienteVenta>, onNuevo: () -> Unit, seleccionar: (String, ClienteVenta?) -> Unit) {
    var tipo by remember { mutableStateOf<String?>(null) }
    var buscar by remember { mutableStateOf("") }
    val texto = buscar.trim()
    val lista = (if (tipo == "PROVEEDOR") proveedores else compradores)
        .filter { it.estado == "ACTIVO" && (it.nombre.contains(texto, true) || it.documento.orEmpty().contains(texto)) }
    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
        producto?.let { p -> bloque { Text(p.nombre, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) } }
        bloque { Text("¿A quién vendes?", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold) }
        bloque(8) { OpcionCliente(EcoLactIcons.Person, "Público general", "Sin registro · pago en efectivo", false) { seleccionar("PUBLICO", null) } }
        bloque(8) { OpcionCliente(EcoLactIcons.People, "Comprador registrado", "Buscar por nombre, DNI o RUC", tipo == "COMPRADOR") { tipo = "COMPRADOR"; buscar = "" } }
        bloque(20) { OpcionCliente(EcoLactIcons.LocalShipping, "Proveedor", "Precio proveedor · efectivo o descuento semanal", tipo == "PROVEEDOR") { tipo = "PROVEEDOR"; buscar = "" } }
        if (tipo != null) {
            bloque { CampoBusqueda(buscar, if (tipo == "PROVEEDOR") "Nombre o DNI" else "Nombre, DNI o RUC") { buscar = it } }
            if (tipo == "COMPRADOR") bloque {
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    BotonSecundario("+ Registrar nuevo comprador", LocalAutorizacion.current.puedeOperar, onNuevo)
                    NotaRequiereAsistencia()
                }
            }
            if (lista.isEmpty()) bloque { EstadoVacio(if (tipo == "PROVEEDOR") "No se encontraron proveedores." else "No se encontraron compradores.") }
            itemsIndexed(lista, key = { _, c -> "${tipo}-${c.id}" }) { i, c ->
                ProviderListItem(i == 0, i == lista.lastIndex, onClick = { seleccionar(tipo!!, c) }) {
                    ItemTitulo(c.nombre)
                    ItemSubtitulo(if (tipo == "PROVEEDOR") c.documento?.let { "DNI $it" } ?: "Sin DNI" else c.documentoVisible)
                }
            }
        }
    }
}

// ───────────────────────────── Resumen ─────────────────────────────

@Composable
private fun OpcionPago(titulo: String, seleccionada: Boolean, habilitada: Boolean, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp))
            .border(1.dp, if (seleccionada) EcoLactColors.VerdePrincipal else EcoLactColors.Bordes, RoundedCornerShape(10.dp))
            .background(if (seleccionada) EcoLactColors.VerdeClaro else EcoLactColors.Blanco)
            .clickable(enabled = habilitada, role = Role.RadioButton, onClick = onClick).padding(horizontal = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        RadioButton(seleccionada, onClick = null, enabled = habilitada, modifier = Modifier.padding(12.dp), colors = RadioButtonDefaults.colors(selectedColor = EcoLactColors.VerdePrincipal))
        Text(titulo, style = MaterialTheme.typography.bodyLarge, color = if (habilitada) EcoLactColors.TextoPrincipal else EcoLactColors.TextoSecundario)
    }
}

@Composable
fun VentasResumenScreen(producto: ProductoVenta, tipo: String, cliente: ClienteVenta?, cotizacion: CotizacionVenta?, busy: Boolean, online: Boolean, cantidad: String, pago: String, onCantidad: (String) -> Unit, onPago: (String) -> Unit, onCotizar: (Double, String) -> Unit, onRegistrar: () -> Unit) {
    val puede = LocalAutorizacion.current.puedeOperar
    val numero = cantidad.replace(',', '.').toDoubleOrNull()?.takeIf { it.isFinite() && it > 0 }
    val proveedor = tipo == "PROVEEDOR"
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido().contenedorBlanco().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) { Etiqueta("Producto"); ProductoCabecera(producto); StockTexto(producto) }
                HorizontalDivider(color = EcoLactColors.Bordes)
                Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Etiqueta("Cliente")
                    Text(cliente?.nombre ?: "Público general", style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold)
                    Text(
                        when (tipo) { "PROVEEDOR" -> "Proveedor · ${cliente?.documento?.let { "DNI $it" } ?: "Sin DNI"}"; "COMPRADOR" -> "Comprador · ${cliente?.documentoVisible}"; else -> "Sin registro" },
                        style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario,
                    )
                }
            }
        }
        item {
            Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                OutlinedTextField(
                    cantidad, onCantidad, Modifier.fillMaxWidth(), enabled = !busy, singleLine = true, shape = RoundedCornerShape(RADIO),
                    label = { Text("Cantidad") }, keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = EcoLactColors.Blanco, unfocusedContainerColor = EcoLactColors.Blanco, unfocusedBorderColor = EcoLactColors.Bordes, focusedBorderColor = EcoLactColors.VerdePrincipal),
                )
                if (!proveedor) producto.minimoMayorista?.let { EtiquetaMayorista(it) }
            }
        }
        item {
            Column(Modifier.anchoContenido().contenedorBlanco().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Etiqueta("Forma de pago")
                if (proveedor) {
                    OpcionPago("Efectivo", pago == "CONTADO", !busy) { onPago("CONTADO") }
                    OpcionPago("Descuento del pago semanal", pago == "DESCUENTO_PLANILLA", !busy && online) { onPago("DESCUENTO_PLANILLA") }
                    if (!online) Text("Necesitas conexión para validar el saldo disponible del proveedor.", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.NaranjaOscuro)
                } else Text("Efectivo", style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold)
            }
        }
        if (cotizacion == null) {
            item { BotonPrincipal("Calcular total", !busy && numero != null && (online || pago == "CONTADO")) { onCotizar(numero!!, pago) } }
        } else {
            item {
                Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp)) {
                    Fila("Cantidad", formatCantidad(numero ?: 0.0), primera = true)
                    Fila("Precio unitario", formatSoles(cotizacion.precio_unitario))
                    Fila("Subtotal", formatSoles(cotizacion.importe_total))
                    HorizontalDivider(color = EcoLactColors.Bordes)
                    Row(Modifier.fillMaxWidth().padding(vertical = 14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Text("TOTAL", Modifier.weight(1f), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                        Text(formatSoles(cotizacion.importe_total), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold, color = EcoLactColors.VerdeOscuro)
                    }
                }
            }
            item { Aviso("Precio ${tipoPrecioTexto(cotizacion.tipo_cliente)} aplicado · ${formatSoles(cotizacion.precio_unitario)} c/u", Tono.EXITO, EcoLactIcons.CheckCircle) }
            if (pago == "DESCUENTO_PLANILLA") item { Aviso("Este monto se descontará del próximo pago semanal del proveedor.", Tono.NEUTRAL) }
            if (!online) item { Aviso("Precio guardado en el dispositivo. Se validará al enviar la venta.", Tono.ATENCION, EcoLactIcons.CloudOff) }
            item {
                Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    BotonPrincipal(if (online) "Registrar venta" else "Guardar venta", !busy && puede && (online || pago == "CONTADO"), onRegistrar)
                    NotaRequiereAsistencia()
                }
            }
        }
    }
}

// ───────────────────────────── Confirmación ─────────────────────────────

@Composable
fun VentasConfirmacionScreen(venta: Venta, busy: Boolean, boleta: () -> Unit, nueva: () -> Unit, historial: () -> Unit) {
    val tono = venta.tono()
    val color = colorDe(tono)
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido().padding(top = 16.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Box(Modifier.size(72.dp).background(color.copy(alpha = 0.12f), CircleShape), contentAlignment = Alignment.Center) {
                    Icon(when (tono) { Tono.ERROR -> EcoLactIcons.Error; Tono.ATENCION -> EcoLactIcons.Schedule; else -> EcoLactIcons.CheckCircle }, contentDescription = null, tint = color, modifier = Modifier.size(40.dp))
                }
                Text(
                    when { venta.error != null -> "No se pudo registrar la venta"; busy -> "Enviando venta…"; venta.pendiente -> "Venta guardada en el dispositivo"; else -> "Venta registrada" },
                    style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, textAlign = TextAlign.Center,
                )
                if (venta.pendiente) Text("Pendiente de sincronización", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.NaranjaOscuro)
            }
        }
        item {
            Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp)) {
                Fila("Código", venta.codigo, primera = true)
                Fila("Total", formatSoles(venta.importe_total), destacado = true)
                if (venta.aceptada) Fila(if (venta.descuentoSemanal) "Descuento semanal" else "Cobrado", formatSoles(venta.importe_total), color = EcoLactColors.VerdeOscuro)
                Fila("Forma de pago", venta.pago)
            }
        }
        when {
            venta.error != null -> item { Aviso(venta.error, Tono.ERROR, EcoLactIcons.Error) }
            venta.pendiente -> item { Aviso("Se enviará automáticamente cuando vuelva la conexión. $MENSAJE_BOLETA_PENDIENTE", Tono.ATENCION, EcoLactIcons.CloudOff) }
            venta.descuentoSemanal -> item { Aviso("Este monto se descontará del próximo pago semanal del proveedor.", Tono.NEUTRAL) }
        }
        item {
            Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                if (venta.boletaDisponible) BotonPrincipal("Ver boleta", onClick = boleta)
                if (venta.aceptada) BotonSecundario("Registrar otra venta", !busy, nueva) else BotonPrincipal("Nueva venta", !busy, nueva)
                BotonSecundario("Ver historial", onClick = historial)
            }
        }
    }
}

// ───────────────────────────── Historial ─────────────────────────────

private val PERIODOS = listOf("Todas", "Hoy", "Esta semana", "Este mes")

@Composable
private fun SelectorPeriodo(actual: String, onSeleccionar: (String) -> Unit) {
    // Segmentos de igual ancho: los cuatro filtros caben en 360dp sin scroll horizontal.
    Row(Modifier.anchoContenido().contenedorBlanco().padding(4.dp), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
        PERIODOS.forEach { p ->
            val sel = p == actual
            Box(
                Modifier.weight(1f).heightIn(min = 40.dp).clip(RoundedCornerShape(8.dp))
                    .background(if (sel) EcoLactColors.VerdeClaro else Color.Transparent)
                    .clickable(role = Role.Tab) { onSeleccionar(p) },
                contentAlignment = Alignment.Center,
            ) {
                Text(p, style = MaterialTheme.typography.labelMedium, fontWeight = if (sel) FontWeight.SemiBold else FontWeight.Normal,
                    color = if (sel) EcoLactColors.VerdeOscuro else EcoLactColors.TextoSecundario, maxLines = 1, textAlign = TextAlign.Center)
            }
        }
    }
}

@Composable
fun VentasHistorialScreen(ventas: List<Venta>, detalle: (Venta) -> Unit) {
    var buscar by remember { mutableStateOf("") }
    var periodo by remember { mutableStateOf("Todas") }
    val texto = buscar.trim()
    val filtradas = ventasEnPeriodo(ventas, periodo).filter { it.codigo.contains(texto, true) || it.cliente.contains(texto, true) || it.documento.orEmpty().contains(texto) }
    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
        bloque { Etiqueta("Historial de ventas", color = EcoLactColors.TextoPrincipal) }
        bloque { CampoBusqueda(buscar, "Buscar por código o cliente…") { buscar = it } }
        bloque(16) { SelectorPeriodo(periodo) { periodo = it } }
        if (filtradas.isEmpty()) bloque { EstadoVacio("No hay ventas en este período.") }
        else item { CabeceraTabla() }
        itemsIndexed(filtradas, key = { _, v -> v.localId ?: v.id.toString() }) { i, v -> FilaVenta(v, i == 0, i == filtradas.lastIndex) { detalle(v) } }
    }
}

// ───────────────────────────── Detalle ─────────────────────────────

@Composable
fun VentasDetalleScreen(v: Venta, boleta: () -> Unit) {
    ProveedorPantalla {
        item {
            Row(Modifier.anchoContenido(), verticalAlignment = Alignment.CenterVertically) {
                Text(v.codigo, Modifier.weight(1f), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold, color = EcoLactColors.VerdeOscuro)
                StatusChip(v.estadoVisible, v.tono())
            }
        }
        item {
            Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp)) {
                Fila("Fecha", "${formatFecha(v.fecha, ZONA_OPERACION)} · ${formatHora(v.fecha, ZONA_OPERACION)}", primera = true)
                Fila("Cliente", v.cliente)
                Fila(v.etiquetaDocumento, v.documentoBoleta)
                if (v.proveedor_id != null) Fila("Tipo", "Proveedor")
            }
        }
        item {
            Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp)) {
                Fila("Producto", v.producto_nombre, primera = true)
                Fila("Cantidad", formatCantidad(v.cantidad))
                Fila("Tipo de precio", v.tipoPrecio)
                Fila("Precio unitario", formatSoles(v.precio_unitario))
                Fila("Subtotal", formatSoles(v.importe_total))
                Fila("TOTAL", formatSoles(v.importe_total), destacado = true, color = EcoLactColors.VerdeOscuro)
            }
        }
        item {
            Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp)) {
                Fila("Forma de pago", v.pago, primera = true)
                if (v.vendedor.isNotBlank()) Fila("Vendedor", v.vendedor)
                Fila("Estado", v.estadoVisible, color = colorDe(v.tono()))
            }
        }
        v.error?.let { item { Aviso(it, Tono.ERROR, EcoLactIcons.Error) } }
        if (v.pendiente) item { Aviso("Se enviará automáticamente cuando vuelva la conexión. $MENSAJE_BOLETA_PENDIENTE", Tono.ATENCION, EcoLactIcons.CloudOff) }
        if (v.boletaDisponible) item { BotonPrincipal("Ver boleta", onClick = boleta) }
    }
}

// ───────────────────────────── Boleta (comprobante interno, sin datos tributarios) ─────────────────────────────

@Composable
private fun SeparadorBoleta() = HorizontalDivider(color = EcoLactColors.Bordes, thickness = 1.dp)

@Composable
fun VentasBoletaScreen(v: Venta, busy: Boolean = false, descargar: () -> Unit = {}, compartir: () -> Unit = {}) {
    ProveedorPantalla {
        item {
            Column(
                Modifier.anchoContenido().background(EcoLactColors.Blanco, RoundedCornerShape(RADIO)).border(1.dp, EcoLactColors.Bordes, RoundedCornerShape(RADIO)).padding(horizontal = 20.dp, vertical = 24.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text("EcoLact", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold, color = EcoLactColors.VerdePrincipal)
                    Text("BOLETA DE VENTA", style = MaterialTheme.typography.labelLarge.copy(letterSpacing = 1.5.sp), color = EcoLactColors.TextoPrincipal)
                    Text(v.codigo, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                }
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Column { Etiqueta("Fecha"); Text(formatFecha(v.fecha, ZONA_OPERACION), style = MaterialTheme.typography.bodyMedium) }
                    Column(horizontalAlignment = Alignment.End) { Etiqueta("Hora"); Text(formatHora(v.fecha, ZONA_OPERACION), style = MaterialTheme.typography.bodyMedium) }
                }
                SeparadorBoleta()
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Etiqueta("Datos del cliente", color = EcoLactColors.VerdeOscuro)
                    Column { Etiqueta("Cliente"); Text(v.cliente, style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold) }
                    Row(horizontalArrangement = Arrangement.spacedBy(32.dp)) {
                        Column { Etiqueta(v.etiquetaDocumento); Text(v.documentoBoleta, style = MaterialTheme.typography.bodyMedium) }
                        if (v.proveedor_id != null) Column { Etiqueta("Tipo"); Text("Proveedor", style = MaterialTheme.typography.bodyMedium) }
                    }
                }
                SeparadorBoleta()
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Row(Modifier.fillMaxWidth().background(EcoLactColors.Fondo, RoundedCornerShape(6.dp)).padding(horizontal = 6.dp, vertical = 6.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        Etiqueta("Cant.", Modifier.weight(0.55f))
                        Etiqueta("Descripción", Modifier.weight(1.5f))
                        Text("P. UNIT.", Modifier.weight(0.95f), textAlign = TextAlign.End, style = MaterialTheme.typography.labelSmall, color = EcoLactColors.TextoSecundario)
                        Text("TOTAL", Modifier.weight(0.95f), textAlign = TextAlign.End, style = MaterialTheme.typography.labelSmall, color = EcoLactColors.TextoSecundario)
                    }
                    Row(Modifier.fillMaxWidth().padding(horizontal = 6.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(formatCantidad(v.cantidad), Modifier.weight(0.55f), style = MaterialTheme.typography.bodySmall)
                        Text(v.producto_nombre, Modifier.weight(1.5f), style = MaterialTheme.typography.bodySmall)
                        Text(formatSoles(v.precio_unitario), Modifier.weight(0.95f), style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.End)
                        Text(formatSoles(v.importe_total), Modifier.weight(0.95f), style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.End, fontWeight = FontWeight.SemiBold)
                    }
                }
                SeparadorBoleta()
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Text("TOTAL", Modifier.weight(1f), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    Text(formatSoles(v.importe_total), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold, color = EcoLactColors.VerdeOscuro)
                }
                SeparadorBoleta()
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Etiqueta("Forma de pago")
                    Text(v.pago, style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.Medium)
                    if (v.descuentoSemanal) Text("Este monto se descontará del próximo pago semanal del proveedor.", style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
                }
            }
        }
        // El PDF lo genera Laravel desde la venta registrada: es el mismo documento que descarga la web.
        item {
            Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                BotonPrincipal("Descargar PDF", !busy, descargar)
                BotonSecundario("Compartir", !busy, compartir)
            }
        }
    }
}

// ───────────────────────────── Compradores ─────────────────────────────

@Composable
fun VentasCompradoresScreen(compradores: List<ClienteVenta>, nuevo: () -> Unit) {
    var buscar by remember { mutableStateOf("") }
    val texto = buscar.trim()
    val lista = compradores.filter { it.nombre.contains(texto, true) || it.documento.orEmpty().contains(texto) }
    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(0.dp)) {
        bloque { CampoBusqueda(buscar, "Nombre, DNI o RUC") { buscar = it } }
        bloque(16) {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                BotonPrincipal("+ Nuevo comprador", LocalAutorizacion.current.puedeOperar, nuevo)
                NotaRequiereAsistencia()
            }
        }
        if (lista.isEmpty()) bloque { EstadoVacio(if (compradores.isEmpty()) "No hay compradores guardados." else "Ningún comprador coincide con la búsqueda.") }
        itemsIndexed(lista, key = { _, c -> c.id }) { i, c ->
            ProviderListItem(i == 0, i == lista.lastIndex, onClick = null) {
                ItemTitulo(c.nombre)
                ItemSubtitulo(listOfNotNull(c.documentoVisible, c.telefono?.let { "Tel. $it" }).joinToString(" · "))
            }
        }
    }
}

@Composable
fun VentasNuevoCompradorScreen(busy: Boolean, online: Boolean, guardar: (NuevoComprador) -> Unit) {
    var nombre by remember { mutableStateOf("") }
    var documento by remember { mutableStateOf("") }
    var telefono by remember { mutableStateOf("") }
    val campo = OutlinedTextFieldDefaults.colors(focusedContainerColor = EcoLactColors.Blanco, unfocusedContainerColor = EcoLactColors.Blanco, unfocusedBorderColor = EcoLactColors.Bordes, focusedBorderColor = EcoLactColors.VerdePrincipal)
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                OutlinedTextField(nombre, { nombre = it.take(150) }, Modifier.fillMaxWidth(), label = { Text("Nombre / razón social") }, enabled = !busy, shape = RoundedCornerShape(RADIO), colors = campo)
                OutlinedTextField(
                    documento, { documento = it.filter(Char::isDigit).take(11) }, Modifier.fillMaxWidth(), label = { Text("Documento (DNI o RUC)") }, enabled = !busy, singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), shape = RoundedCornerShape(RADIO), colors = campo,
                    supportingText = { if (documento.length == 8 || documento.length == 11) Text(etiquetaDocumento(documento)) },
                )
                OutlinedTextField(telefono, { telefono = it.take(20) }, Modifier.fillMaxWidth(), label = { Text("Teléfono") }, enabled = !busy, singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone), shape = RoundedCornerShape(RADIO), colors = campo)
            }
        }
        if (!online) item { Aviso("Necesitas conexión para registrar un comprador.", Tono.ATENCION, EcoLactIcons.CloudOff) }
        item {
            Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                BotonPrincipal("Registrar comprador", !busy && online && nombre.isNotBlank() && LocalAutorizacion.current.puedeOperar) {
                    guardar(NuevoComprador(nombre.trim(), documento.trim().ifBlank { null }, telefono.trim().ifBlank { null }))
                }
                NotaRequiereAsistencia()
            }
        }
    }
}

// ───────────────────────────── Perfil / Comunicados ─────────────────────────────

@Composable
fun VentasPerfilScreen(nombre: String, iniciales: String, usuario: String, dni: String?, cerrarSesion: () -> Unit) {
    ProveedorPantalla {
        item {
            Column(Modifier.anchoContenido(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                InitialsAvatar(iniciales, large = true)
                Text(nombre, style = MaterialTheme.typography.titleLarge, modifier = Modifier.padding(top = 8.dp), textAlign = TextAlign.Center)
                Text("Personal de ventas", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
            }
        }
        item {
            Column(Modifier.anchoContenido().contenedorBlanco().padding(horizontal = 16.dp)) {
                Fila("Usuario", usuario, primera = true)
                Fila("DNI", dni ?: "—")
                Fila("Rol", "Personal de ventas")
            }
        }
        item { BotonSecundario("Cerrar sesión", onClick = cerrarSesion) }
    }
}

@Composable
fun VentasComunicadosScreen(comunicados: List<ComunicadoVentas>) {
    ProveedorPantalla(verticalSpacing = Arrangement.spacedBy(12.dp)) {
        if (comunicados.isEmpty()) item { EstadoVacio("No hay comunicados guardados.") }
        itemsIndexed(comunicados, key = { _, c -> c.id }) { _, c ->
            Column(Modifier.anchoContenido().contenedorBlanco().padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(c.titulo, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                Text(c.contenido, style = MaterialTheme.typography.bodyMedium)
                if (c.fecha_publicacion.isNotBlank()) Text(formatFecha(c.fecha_publicacion, ZONA_OPERACION), style = MaterialTheme.typography.bodySmall, color = EcoLactColors.TextoSecundario)
            }
        }
    }
}
