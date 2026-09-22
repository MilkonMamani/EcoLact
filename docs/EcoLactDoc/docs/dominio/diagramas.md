# Diagramas del dominio

```mermaid
erDiagram
    PROVEEDOR ||--o{ ENTREGA_LECHE : realiza
    ENTREGA_LECHE ||--o| ANALISIS_CALIDAD : tiene
    ENTREGA_LECHE }o--|| ZONA : pertenece
    ENTREGA_LECHE }o--o| LOTE_PRODUCCION : integra