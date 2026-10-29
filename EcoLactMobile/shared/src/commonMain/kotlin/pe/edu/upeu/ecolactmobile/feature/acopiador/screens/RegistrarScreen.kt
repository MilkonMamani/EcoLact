
package pe.edu.upeu.ecolactmobile.feature.acopiador.screens

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pe.edu.upeu.ecolactmobile.core.ui.components.QrScannerView
import pe.edu.upeu.ecolactmobile.core.ui.icons.EcoLactIcons
import pe.edu.upeu.ecolactmobile.core.ui.theme.EcoLactColors
import pe.edu.upeu.ecolactmobile.core.util.formatHora
import pe.edu.upeu.ecolactmobile.core.util.formatLitros
import pe.edu.upeu.ecolactmobile.core.util.initials
import pe.edu.upeu.ecolactmobile.feature.acopiador.RegistrarEntregaResult
import pe.edu.upeu.ecolactmobile.feature.acopiador.EstadoRegistro
import pe.edu.upeu.ecolactmobile.feature.acopiador.components.EstadoRegistroIndicador
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonPrincipal
import pe.edu.upeu.ecolactmobile.feature.calidad.components.BotonSecundario
import pe.edu.upeu.ecolactmobile.feature.calidad.components.MensajeError
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.InitialsAvatar
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.anchoContenido
import pe.edu.upeu.ecolactmobile.feature.proveedor.components.contenedorBlanco
import pe.edu.upeu.ecolactmobile.feature.trabajador.NotaRequiereAsistencia

@Composable
fun AcopiadorRegistrarScreen(
    viewModel: RegistrarViewModel,
    onVerHistorial: () -> Unit,
    onVolverRecorrido: () -> Unit,
    onConfirmadaEnLinea: () -> Unit,
) {
    val state by viewModel.state.collectAsState()
    LaunchedEffect(state.resultado) {
        if (state.resultado is RegistrarEntregaResult.Confirmada) onConfirmadaEnLinea()
    }
    when (state.paso) {
        PasoRegistro.ESCANEAR -> Escanear(state, viewModel)
        PasoRegistro.FORMULARIO -> Formulario(state, viewModel)
        PasoRegistro.RESULTADO -> Resultado(state, viewModel, onVerHistorial, onVolverRecorrido)
    }
}

// ---- 1. Escanear QR (con alternativa DNI) ----

@Composable
private fun Escanear(state: RegistrarUiState, viewModel: RegistrarViewModel) {
    Box(Modifier.fillMaxSize().background(Color.Black)) {
        // Texto claro sobre fondo oscuro también en el aviso de permiso de cámara del escáner.
        CompositionLocalProvider(LocalContentColor provides EcoLactColors.Blanco) {
            key(state.intentoEscaneo) {
                QrScannerView(modifier = Modifier.fillMaxSize(), onScanned = viewModel::onQrEscaneado)
            }
        }
        Column(
            modifier = Modifier.fillMaxSize().padding(horizontal = 24.dp).navigationBarsPadding(),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.weight(1f))
            Box(
                modifier = Modifier
                    .widthIn(max = 260.dp)
                    .fillMaxWidth(0.72f)
                    .height(260.dp)
                    .border(3.dp, EcoLactColors.VerdePrincipal, RoundedCornerShape(20.dp)),
                contentAlignment = Alignment.Center,
            ) {
                if (state.identificando) CircularProgressIndicator(color = EcoLactColors.Blanco)
            }
            Spacer(Modifier.height(24.dp))
            Text(
                "Escanear código QR\ndel proveedor",
                style = MaterialTheme.typography.titleMedium,
                color = EcoLactColors.Blanco,
                textAlign = TextAlign.Center,
            )
            state.avisoEscaneo?.let { aviso ->
                Column(
                    modifier = Modifier.padding(top = 16.dp).anchoContenido().background(EcoLactColors.Blanco, RoundedCornerShape(12.dp)).padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    Text(aviso, style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoPrincipal)
                    TextButton(onClick = viewModel::onReintentarEscaneo, modifier = Modifier.align(Alignment.End)) {
                        Text("Escanear de nuevo", color = EcoLactColors.VerdeOscuro)
                    }
                }
            }
            Spacer(Modifier.weight(1f))
            OutlinedButton(
                onClick = viewModel::onAbrirDni,
                shape = RoundedCornerShape(12.dp),
                border = BorderStroke(1.dp, EcoLactColors.Blanco),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = EcoLactColors.Blanco),
                modifier = Modifier.anchoContenido().height(52.dp),
            ) {
                Icon(EcoLactIcons.Badge, contentDescription = null, modifier = Modifier.size(20.dp))
                Spacer(Modifier.size(8.dp))
                Text("Ingresar DNI", style = MaterialTheme.typography.titleSmall)
            }
            Spacer(Modifier.height(24.dp))
        }
    }
    if (state.mostrandoDni) DniSheet(state, viewModel)
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DniSheet(state: RegistrarUiState, viewModel: RegistrarViewModel) {
    ModalBottomSheet(
        onDismissRequest = viewModel::onCerrarDni,
        sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
        containerColor = EcoLactColors.Blanco,
    ) {
        Column(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp).navigationBarsPadding().imePadding().padding(bottom = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Ingresar DNI", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
            Text("Ingrese el número de DNI del proveedor", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
            OutlinedTextField(
                value = state.dni,
                onValueChange = viewModel::onDniChange,
                placeholder = { Text("Número de DNI") },
                singleLine = true,
                isError = state.dniError != null,
                shape = RoundedCornerShape(12.dp),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword, imeAction = ImeAction.Search),
                keyboardActions = KeyboardActions(onSearch = { viewModel.onBuscarDni() }),
                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = EcoLactColors.VerdePrincipal),
                modifier = Modifier.fillMaxWidth(),
            )
            state.dniError?.let { MensajeError(it) }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                BotonSecundario("Cancelar", viewModel::onCerrarDni, modifier = Modifier.weight(1f))
                BotonPrincipal("Buscar proveedor", viewModel::onBuscarDni, modifier = Modifier.weight(1f), cargando = state.identificando)
            }
        }
    }
}

// ---- 2. Proveedor identificado + litros ----

@Composable
private fun Formulario(state: RegistrarUiState, viewModel: RegistrarViewModel) {
    val proveedor = state.proveedor ?: return
    Column(
        modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).imePadding().padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Row(
            modifier = Modifier.anchoContenido().contenedorBlanco().padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            InitialsAvatar(initials(proveedor.nombres, proveedor.apellidos, proveedor.nombre_completo))
            Column(Modifier.weight(1f)) {
                Text(proveedor.nombre_completo, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
                Text("DNI: ${proveedor.dni}", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                proveedor.zona_nombre?.let { Text("Zona: $it", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario) }
            }
            TextButton(onClick = viewModel::onCambiarProveedor) { Text("Cambiar", color = EcoLactColors.VerdeOscuro) }
        }

        Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Cantidad de leche", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
            OutlinedTextField(
                value = state.litros,
                onValueChange = viewModel::onLitrosChange,
                placeholder = { Text("0", style = MaterialTheme.typography.headlineMedium) },
                suffix = { Text("L", style = MaterialTheme.typography.headlineSmall, color = EcoLactColors.TextoSecundario) },
                textStyle = MaterialTheme.typography.headlineMedium.copy(fontWeight = FontWeight.SemiBold),
                singleLine = true,
                isError = state.litrosError != null,
                supportingText = state.litrosError?.let { { Text(it) } },
                shape = RoundedCornerShape(12.dp),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Next),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedContainerColor = EcoLactColors.Blanco,
                    unfocusedContainerColor = EcoLactColors.Blanco,
                    focusedBorderColor = EcoLactColors.VerdePrincipal,
                    unfocusedBorderColor = EcoLactColors.Bordes,
                ),
                modifier = Modifier.fillMaxWidth(),
            )
        }

        Column(Modifier.anchoContenido(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Observación (opcional)", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
            OutlinedTextField(
                value = state.observacion,
                onValueChange = viewModel::onObservacionChange,
                placeholder = { Text("Agregar una observación…") },
                minLines = 3,
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedContainerColor = EcoLactColors.Blanco,
                    unfocusedContainerColor = EcoLactColors.Blanco,
                    focusedBorderColor = EcoLactColors.VerdePrincipal,
                    unfocusedBorderColor = EcoLactColors.Bordes,
                ),
                modifier = Modifier.fillMaxWidth(),
            )
        }

        state.errorGuardar?.let { MensajeError(it) }
        BotonPrincipal("Guardar entrega", viewModel::onGuardar, cargando = state.guardando, operacion = true)
        NotaRequiereAsistencia()
    }
}

// ---- 3. Resultado ----

@Composable
private fun Resultado(state: RegistrarUiState, viewModel: RegistrarViewModel, onVerHistorial: () -> Unit, onVolverRecorrido: () -> Unit) {
    val enServidor = state.resultado is RegistrarEntregaResult.Confirmada
    Column(
        modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Spacer(Modifier.height(24.dp))
        Box(Modifier.size(72.dp).background(EcoLactColors.VerdeClaro, CircleShape), contentAlignment = Alignment.Center) {
            Icon(EcoLactIcons.CheckCircle, contentDescription = null, tint = EcoLactColors.VerdePrincipal, modifier = Modifier.size(40.dp))
        }
        Text("Entrega guardada", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal)
        Text(
            if (enServidor) "Se registró correctamente en el servidor." else "Se registró correctamente\nen el dispositivo.",
            style = MaterialTheme.typography.bodyMedium,
            color = EcoLactColors.TextoSecundario,
            textAlign = TextAlign.Center,
        )
        Column(Modifier.anchoContenido().contenedorBlanco().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            state.proveedor?.let { Text(it.nombre_completo, style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold, color = EcoLactColors.TextoPrincipal) }
            Text(
                "${formatLitros(state.litrosGuardados)} · ${state.horaGuardada?.let { formatHora(it) }.orEmpty()}",
                style = MaterialTheme.typography.bodyMedium,
                color = EcoLactColors.TextoSecundario,
            )
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Estado:", style = MaterialTheme.typography.bodyMedium, color = EcoLactColors.TextoSecundario)
                if (enServidor) {
                    EstadoRegistroIndicador(EstadoRegistro.SINCRONIZADA)
                } else {
                    EstadoRegistroIndicador(EstadoRegistro.PENDIENTE)
                    Text("de sincronización", style = MaterialTheme.typography.labelMedium, color = EcoLactColors.NaranjaOscuro)
                }
            }
        }
        if (!enServidor) {
            Text(
                "Se enviará sola cuando vuelva la conexión.",
                style = MaterialTheme.typography.bodySmall,
                color = EcoLactColors.TextoSecundario,
                textAlign = TextAlign.Center,
            )
        }
        Spacer(Modifier.height(8.dp))
        if (viewModel.desdeRecorrido) {
            BotonPrincipal("Volver al recorrido", onVolverRecorrido)
        } else {
            BotonPrincipal("Registrar otra entrega", viewModel::onRegistrarOtra, icon = EcoLactIcons.Add, operacion = true)
        }
        BotonSecundario("Ver historial", onVerHistorial)
    }
}
