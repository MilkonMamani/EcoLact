---
title: "Pruebas y validación de EcoLact"
---

<div class="ec-pruebas" markdown="1">
<div class="ec-test-banner" markdown="1">

:material-test-tube: 1. Estrategia de pruebas

</div>
<div class="ec-test-content" markdown="1">

<span class="ec-test-kicker">EcoLact / 6. Pruebas / Ejecución verificable</span>

# Pruebas y validación de EcoLact

Las pruebas verifican reglas de negocio, comportamiento observable e interacción entre componentes. Este informe corresponde al árbol local de EcoLact del **29 de octubre de 2026**, con cambios sin commit. Los valores se calcularon desde los casos JUnit, no desde el número de archivos ni de aserciones.

<div class="ec-test-summary"><div class="ec-test-card ec-test-"><strong>2021</strong>Ejecutadas</div><div class="ec-test-card ec-test-passed"><strong>1999</strong>Aprobadas</div><div class="ec-test-card ec-test-failed"><strong>22</strong>Fallidas / errores</div><div class="ec-test-card ec-test-skipped"><strong>1</strong>Omitidas</div><div class="ec-test-card ec-test-"><strong>241.028 s</strong>Tiempo de casos</div></div>


**Lectura del resultado:** 1999 aprobadas · 6 fallidas · 16 errores · 1 omitidas. “Fallidas / errores” reúne aserciones incumplidas y excepciones; ambas se desglosan en las tablas. Las omitidas fueron descubiertas por el runner pero su cuerpo no se ejecutó. iOS y el flujo extremo a extremo permanecen fuera de este total.

## Niveles de la estrategia

<div class="ec-test-pyramid" role="img" aria-label="Tres niveles: integración, funcionales y API, unitarias">
<div class="ec-test-level"><b>Integración</b><br>Consistencia entre componentes</div>
<div class="ec-test-level"><b>Funcionales / API</b><br>Casos de uso, respuestas y autorización</div>
<div class="ec-test-level"><b>Unitarias</b><br>Entidades, reglas y transformaciones aisladas</div>
</div>

La pirámide expresa niveles de responsabilidad; su tamaño no representa una proporción medida de cobertura.

<div class="ec-test-table" markdown="1">

|Área|Evidencia existente|Alcance y pendiente|
|---|---|---|
|Unitarias|Unit de PHP y commonTest de Kotlin|Entidades, parsers y modelos. Sin porcentaje de cobertura de líneas medido.|
|Funcionales y casos de uso|Feature/Application y Feature/Web|Reglas, persistencia SQLite, vistas y transacciones. No equivale a un navegador operando la web.|
|API y autorización|Feature/Http/Api y Feature/Auth|Peticiones internas Laravel, roles, fechas e idempotencia; no usa un servidor remoto.|
|Móvil y offline|androidHostTest y commonTest en JVM|SQLite en memoria y respuestas controladas. Faltan dispositivos físicos.|
|Integración|Selección de Feature y pruebas de repositorio móvil|Componentes reales dentro de cada proceso; App → API desplegada → MySQL pendiente.|

</div>


## Herramientas verificadas

Pest **4.7.8**, PHPUnit **12.5.33** y Laravel Testing sobre Laravel **13.31.0** están instalados. Kotlin Test, Gradle **9.1.0**, SQLDelight y Ktor MockEngine aparecen en las dependencias móviles. SQLite JDBC se emplea en las pruebas host; la configuración PHP utiliza `pdo_sqlite` y `:memory:`. Las versiones exactas del entorno están en [Evidencias](evidencias.md).

## Entorno seguro y reproducibilidad

!!! warning "Alcance temporal de los resultados"
    La auditoría final detectó 15 archivos PHP de aplicación/pruebas diferentes del inventario inicial, con fechas de modificación posteriores al cierre de Feature. Los resultados corresponden a la ejecución registrada y **no validan esas modificaciones posteriores**. El detalle está en [Evidencias](evidencias.md#integridad-privacidad-y-reproduccion).

Se verificó `phpunit.xml`: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vacío. No había `bootstrap/cache/config.php`. Esas variables se fijaron antes de iniciar Artisan; correo y caché son de tipo `array`, la cola es `sync`, broadcast es `null` y los archivos de prueba se dirigieron a almacenamiento temporal.

`EcoLactMySqlSchemaTest.php` contiene conexiones MySQL explícitas. Su condición inicial omite el test salvo que `ECOLACT_MYSQL_SCHEMA_TEST=1`; se impuso **0**. No se ejecutaron migraciones manuales, borrados ni solicitudes modificadoras contra servicios reales. `RefreshDatabase` actúa sobre SQLite de prueba.

El script `scripts/ejecutar_evidencias.py` conserva salida completa y metadatos. Rechaza reutilizar un nombre de log histórico. Para una nueva ejecución se debe elegir otro identificador y volver a auditar cambios en los tests; el script comprueba SQLite y ausencia de caché, pero no sustituye esa inspección.

## Resultados destacados

<div class="ec-test-summary" markdown="1">
<div class="ec-test-card" markdown="1">

### [Backend](backend.md)

Reglas, casos de uso, vistas y autorización.

</div>
<div class="ec-test-card" markdown="1">

### [Aplicación móvil](mobile.md)

Persistencia local y sincronización simulada.

</div>
<div class="ec-test-card" markdown="1">

### [Integración](integracion.md)

Flujos y límites entre componentes.

</div>
<div class="ec-test-card" markdown="1">

### [Evidencias](evidencias.md)

Logs, JUnit, capturas y resultados por caso.

</div>
</div>

## Cómo se contabiliza

El resumen suma **Unit + Feature + Android Host** una sola vez por pareja clase/caso. La prueba inicial y la selección de integración son repeticiones diagnósticas y no se vuelven a sumar. commonTest ya forma parte de Android Host. “Tiempo de casos” suma los tiempos de JUnit; no es la duración de compilación ni el tiempo transcurrido entre ejecuciones paralelas.


<div class="ec-test-conclusion" markdown="1">

Consulta la [estrategia](index.md), el [backend](backend.md), la [app móvil](mobile.md), la [integración](integracion.md) y el [archivo de evidencias](evidencias.md).

</div>
</div>
</div>
