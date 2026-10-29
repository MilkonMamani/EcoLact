---
title: Reglas de negocio
hide:
  - toc
---

<div class="ec-sistema ec-reglas">
  <div class="ec-rn-banner"><span class="ec-rn-heading-icon">▤</span><span>5. Reglas de negocio</span></div>
  <div class="ec-rn-content">
    <h1>Reglas de negocio de EcoLact</h1>
    <p class="ec-rn-lead">Condiciones que ordenan el acopio, el control de calidad, el pago a proveedores, la producción, las compras y las ventas. Se documentan conforme al comportamiento observado en los casos de uso del repositorio.</p>
    <div class="ec-rn-intro">
      <div><strong>Fuente principal</strong><span>Casos de uso y reglas del código actual en GitHub.</span></div>
      <div><strong>Requirimientos</strong><span>RF y RNF de la matriz de trazabilidad proporcionada.</span></div>
      <div><strong>Identificadores</strong><span>Los códigos RN-AC, RN-CAL, etc. son identificadores documentales propuestos.</span></div>
    </div>
    

<section class="ec-rn-section" id="rn-acopio"><h2><span class="ec-rn-section-n">01</span>Acopio y recorridos</h2>
<div class="ec-rn-table-wrap"><table class="ec-rn-table"><thead><tr><th>ID</th><th>Regla</th><th>Condición y efecto real</th></tr></thead><tbody>
<tr><td>RN-AC-01</td><td>Entrega válida</td><td>La cantidad debe ser mayor que cero; el proveedor debe estar activo y el acopiador habilitado para registrar.</td></tr>
<tr><td>RN-AC-02</td><td>Recorrido autorizado</td><td>La entrega desde una parada solo puede registrarla el acopiador responsable de un recorrido abierto. En el registro directo se comprueba la zona.</td></tr>
<tr><td>RN-AC-03</td><td>Efectos del registro</td><td>Al guardar la entrega se incorpora la leche al inventario y se genera o actualiza el pago pendiente. No se espera al análisis de calidad.</td></tr>
<tr><td>RN-AC-04</td><td>Parada sin entrega y duplicados</td><td>Si el proveedor no entrega, se marca «No entregó». Los reintentos de una entrega con la misma clave de idempotencia no deben duplicarla.</td></tr>
</tbody></table></div>
<details class="ec-rn-evidence"><summary>Ver evidencia en GitHub</summary><div class="ec-rn-sources"><a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Acopio/RegistrarEntrega.php" target="_blank" rel="noopener noreferrer">RegistrarEntrega.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Recorrido/RegistrarAcopioDeRecorrido.php" target="_blank" rel="noopener noreferrer">RegistrarAcopioDeRecorrido.php</a></div></details></section>
<section class="ec-rn-section" id="rn-calidad"><h2><span class="ec-rn-section-n">02</span>Muestreo y control de calidad</h2>
<div class="ec-rn-table-wrap"><table class="ec-rn-table"><thead><tr><th>ID</th><th>Regla</th><th>Condición y efecto real</th></tr></thead><tbody>
<tr><td>RN-CAL-01</td><td>Muestreo selectivo</td><td>No se analiza obligatoriamente cada entrega. Calidad selecciona una muestra manualmente o mediante sorteo del servidor; una muestra no debe duplicarse.</td></tr>
<tr><td>RN-CAL-02</td><td>Registro preliminar</td><td>El OCR del ticket solo propone valores. El analista los revisa y guarda el análisis en BORRADOR; el resultado se determina al confirmar.</td></tr>
<tr><td>RN-CAL-03</td><td>Evaluación configurada</td><td>Cada valor registrado requiere una regla vigente. Si falta alguna, la confirmación se detiene. La prioridad de resultados es RECHAZADA, luego OBSERVADA y finalmente ACEPTADA.</td></tr>
<tr><td>RN-CAL-04</td><td>Resultado definitivo</td><td>El análisis CONFIRMADO no puede modificarse; se registra el resultado, se notifica al proveedor y se recalcula el pago pendiente sin alterar los ya pagados.</td></tr>
</tbody></table></div>
<figure class="ec-rn-diagram"><img src="../../assets/images/diagrama-reglas-calidad.svg" alt="Flujo de selección de muestra, análisis, verificación de reglas y priorización de resultados" loading="lazy"><figcaption>La falta de valores o reglas vigentes impide confirmar; una vez confirmado, el resultado queda inmutable.</figcaption></figure>
<details class="ec-rn-evidence"><summary>Ver evidencia en GitHub</summary><div class="ec-rn-sources"><a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Calidad/SeleccionarMuestraCalidad.php" target="_blank" rel="noopener noreferrer">SeleccionarMuestraCalidad.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Calidad/CalcularResultadoCalidad.php" target="_blank" rel="noopener noreferrer">CalcularResultadoCalidad.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Calidad/FinalizarAnalisisCalidad.php" target="_blank" rel="noopener noreferrer">FinalizarAnalisisCalidad.php</a></div></details></section>
<section class="ec-rn-section" id="rn-pagos"><h2><span class="ec-rn-section-n">03</span>Valorización y pagos a proveedores</h2>
<div class="ec-rn-table-wrap"><table class="ec-rn-table"><thead><tr><th>ID</th><th>Regla</th><th>Condición y efecto real</th></tr></thead><tbody>
<tr><td>RN-PAG-01</td><td>Periodo semanal</td><td>Las entregas se agrupan para el pago semanal por proveedor, de jueves a miércoles.</td></tr>
<tr><td>RN-PAG-02</td><td>Resultado semanal</td><td>Se toma el peor resultado CONFIRMADO del periodo. Sin incidencias se usa el precio base; OBSERVADA puede descontar a toda la semana según configuración; RECHAZADA vuelve la semana NO_PAGABLE.</td></tr>
<tr><td>RN-PAG-03</td><td>Sin análisis de muestra</td><td>Una entrega sin análisis confirmado puede integrar el pago pendiente con el precio base, según el resto de resultados de la semana.</td></tr>
<tr><td>RN-PAG-04</td><td>Descuentos de planilla</td><td>La venta con DESCUENTO_PLANILLA solo corresponde a proveedores y no debe exceder su saldo estimado. En una semana no pagable, ese descuento se traslada a la siguiente semana pagable.</td></tr>
<tr><td>RN-PAG-05</td><td>Pago único y exacto</td><td>Solo se paga una obligación PENDIENTE, una vez, por su importe calculado completo. Un pago ya PAGADO no se recalcula ni permite abonos parciales.</td></tr>
</tbody></table></div>
<figure class="ec-rn-diagram"><img src="../../assets/images/diagrama-reglas-pagos.svg" alt="Lógica semanal de pago según resultado de calidad" loading="lazy"><figcaption>La decisión usa el peor resultado confirmado del periodo y se aplica al pendiente, siempre que aún no esté pagado.</figcaption></figure>
<details class="ec-rn-evidence"><summary>Ver evidencia en GitHub</summary><div class="ec-rn-sources"><a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Pago/SincronizarPagosProveedor.php" target="_blank" rel="noopener noreferrer">SincronizarPagosProveedor.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Pago/RegistrarPagoObligacion.php" target="_blank" rel="noopener noreferrer">RegistrarPagoObligacion.php</a></div></details></section>
<section class="ec-rn-section" id="rn-produccion"><h2><span class="ec-rn-section-n">04</span>Producción e inventario de leche</h2>
<div class="ec-rn-table-wrap"><table class="ec-rn-table"><thead><tr><th>ID</th><th>Regla</th><th>Condición y efecto real</th></tr></thead><tbody>
<tr><td>RN-PRO-01</td><td>Inicio condicionado</td><td>Para iniciar un lote deben existir recetas válidas e insumos suficientes. El consumo se descuenta al INICIAR la producción.</td></tr>
<tr><td>RN-PRO-02</td><td>Cierre por producción real</td><td>Al finalizar el lote se registra la cantidad efectivamente obtenida y la merma; recién entonces ingresan al stock los productos terminados.</td></tr>
<tr><td>RN-PRO-03</td><td>Anulación y reversión</td><td>Una entrega anulada revierte su ingreso de leche sin borrar el movimiento original. No se admite la reversión si la leche disponible ya fue consumida y el stock quedaría negativo.</td></tr>
</tbody></table></div>
<details class="ec-rn-evidence"><summary>Ver evidencia en GitHub</summary><div class="ec-rn-sources"><a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Produccion/IniciarProduccion.php" target="_blank" rel="noopener noreferrer">IniciarProduccion.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Produccion/FinalizarProduccion.php" target="_blank" rel="noopener noreferrer">FinalizarProduccion.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Produccion/RevertirIngresoLeche.php" target="_blank" rel="noopener noreferrer">RevertirIngresoLeche.php</a></div></details></section>
<section class="ec-rn-section" id="rn-compras"><h2><span class="ec-rn-section-n">05</span>Compras de insumos</h2>
<div class="ec-rn-table-wrap"><table class="ec-rn-table"><thead><tr><th>ID</th><th>Regla</th><th>Condición y efecto real</th></tr></thead><tbody>
<tr><td>RN-INS-01</td><td>Presentaciones y equivalencias</td><td>La cantidad comprada se convierte a su unidad base usando la presentación configurada (por ejemplo, dos sacos de 25 kg son 50 000 g).</td></tr>
<tr><td>RN-INS-02</td><td>Compra confirmada</td><td>La lectura de la boleta no aumenta el stock. Solo la confirmación registra la compra y genera los movimientos de ingreso de insumos.</td></tr>
<tr><td>RN-INS-03</td><td>Relación con caja</td><td>Una compra confirmada genera salida de caja cuando tiene un total positivo conocido. Sin importe no se registra una salida de dinero inventada.</td></tr>
</tbody></table></div>
<details class="ec-rn-evidence"><summary>Ver evidencia en GitHub</summary><div class="ec-rn-sources"><a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Produccion/CalcularCompraInsumos.php" target="_blank" rel="noopener noreferrer">CalcularCompraInsumos.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Produccion/RegistrarCompraInsumos.php" target="_blank" rel="noopener noreferrer">RegistrarCompraInsumos.php</a></div></details></section>
<section class="ec-rn-section" id="rn-ventas"><h2><span class="ec-rn-section-n">06</span>Ventas y movimientos de caja</h2>
<div class="ec-rn-table-wrap"><table class="ec-rn-table"><thead><tr><th>ID</th><th>Regla</th><th>Condición y efecto real</th></tr></thead><tbody>
<tr><td>RN-VEN-01</td><td>Precio y stock</td><td>Una venta requiere producto activo, precio vigente y stock suficiente. El sistema determina el precio según proveedor, mayorista o público.</td></tr>
<tr><td>RN-VEN-02</td><td>Venta al contado</td><td>Una venta CONTADO genera un ingreso de caja; una venta DESCUENTO_PLANILLA no ingresa dinero efectivo en ese momento.</td></tr>
<tr><td>RN-VEN-03</td><td>Salidas trazables</td><td>Los pagos efectivamente registrados a proveedores y trabajadores, así como las compras de insumos con importe, producen salidas vinculadas a sus operaciones.</td></tr>
</tbody></table></div>
<details class="ec-rn-evidence"><summary>Ver evidencia en GitHub</summary><div class="ec-rn-sources"><a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Ventas/RegistrarVentaProducto.php" target="_blank" rel="noopener noreferrer">RegistrarVentaProducto.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Caja/RegistrarMovimientoCaja.php" target="_blank" rel="noopener noreferrer">RegistrarMovimientoCaja.php</a></div></details></section>
<section class="ec-rn-section" id="rn-personal"><h2><span class="ec-rn-section-n">07</span>Asistencia y personal</h2>
<div class="ec-rn-table-wrap"><table class="ec-rn-table"><thead><tr><th>ID</th><th>Regla</th><th>Condición y efecto real</th></tr></thead><tbody>
<tr><td>RN-PER-01</td><td>Registro de asistencia</td><td>El trabajador activo que deba controlar asistencia marca mediante QR dentro de la ventana diaria definida; ciertas operaciones requieren asistencia válida.</td></tr>
<tr><td>RN-PER-02</td><td>No duplicar ni ocultar faltas</td><td>Un segundo escaneo del día no crea otra asistencia. Si ya existe una FALTA, la corrección requiere intervención administrativa explícita.</td></tr>
</tbody></table></div>
<details class="ec-rn-evidence"><summary>Ver evidencia en GitHub</summary><div class="ec-rn-sources"><a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/app/Application/Pago/RegistrarAsistenciaQr.php" target="_blank" rel="noopener noreferrer">RegistrarAsistenciaQr.php</a> · <a href="https://github.com/MilkonMamani/EcoLact/blob/main/EcoLactWeb/routes/web.php" target="_blank" rel="noopener noreferrer">web.php</a></div></details></section>
<section class="ec-rn-section" id="rn-trazabilidad">
<h2><span class="ec-rn-section-n">08</span>Relación con la matriz de requerimientos</h2>
<p>La hoja «Matriz Requerimientos» sí contiene algunos RF de EcoLact. La relación siguiente es preliminar: permite localizar reglas respaldadas por esos RF, <strong>sin afirmar que el Excel ya esté trazado correctamente</strong>.</p>
<div class="ec-rn-table-wrap"><table class="ec-rn-table"><thead><tr><th>Requisitos del Excel</th><th>Reglas de esta página relacionadas</th><th>Observación</th></tr></thead><tbody>
<tr><td>RF-01 · Autenticar usuario</td><td>RN-AC-01; reglas de autorización por rol</td><td>Coherente con el acceso protegido del backend.</td></tr>
<tr><td>RF-02 · Registrar proveedores y acopiadores</td><td>RN-AC-01 y RN-AC-02</td><td>Debe actualizarse el origen INT-01, actualmente referido a estudiantes.</td></tr>
<tr><td>RF-03 · Registrar sectores</td><td>RN-AC-02</td><td>La denominación del backend es zonas/rutas; «sectores» debe revisarse.</td></tr>
<tr><td>RF-04 y RF-05 · Entregas y recolección</td><td>RN-AC-01 a RN-AC-04</td><td>El código contiene recorridos, acopio e identificación de proveedores.</td></tr>
<tr><td>RF-07 y RF-08 · Análisis y notificación</td><td>RN-CAL-01 a RN-CAL-04</td><td>Se confirma por muestreo; el proveedor recibe una notificación del resultado.</td></tr>
<tr><td>RF-09 · Consultar historial</td><td>Relacionada con la consulta de entregas y resultados</td><td>Se documentará en «Flujos», no como regla aislada.</td></tr>
<tr><td>RF-10 y RF-11 · Reuniones y su asistencia</td><td>Sin correspondencia directa</td><td>No equivalen a comunicados ni al control de asistencia QR de trabajadores.</td></tr>
<tr><td>Producción, compras, ventas, caja y pagos</td><td>RN-PAG, RN-PRO, RN-INS, RN-VEN, RN-PER</td><td>La matriz todavía necesita nuevos RF para cubrir estas funciones existentes.</td></tr>
</tbody></table></div></section>

<div class="ec-rn-conclusion"><strong>Conclusión</strong><p>EcoLact no funciona mediante reglas aisladas. Una entrega activa inventario y pago pendiente; la calidad modifica el cálculo semanal cuando se confirma; la producción consume insumos y registra resultados reales; y los movimientos de caja se originan en operaciones confirmadas. Por eso se documentan primero las condiciones del negocio y se deja el procedimiento paso a paso para la sección <a href="../../flujos/">Flujos</a>.</p></div>
<div class="ec-rn-footer">Fuente revisada: código del repositorio EcoLact y archivo Excel «Matriz de trazabilidad de requerimientos» proporcionado por el usuario. Los límites específicos de calidad y precios vigentes dependen de la configuración; no se inventan valores.</div>
</div></div>
