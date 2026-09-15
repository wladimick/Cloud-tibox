# TIBOX Design Tools v0.1.0

Complemento para TIBOX Core v0.4 que corrige y amplía la gestión de Design Packages sin modificar el theme.

## Qué resuelve

- Distingue entre **Página específica** y **Plantilla general de páginas**.
- Soporta `target_slug` en `manifest.json` para asignar paquetes por slug en vez de depender del ID de WordPress.
- Permite corregir el destino de paquetes ya importados sin tocar su código.
- Añade **Editor de código** para HTML, CSS y JavaScript.
- Al guardar código crea una **nueva versión**; la anterior se conserva para rollback.
- Si se activa una nueva versión creada desde un paquete activo, reemplaza la asignación de la versión anterior.
- Advierte cuando existe una plantilla general de páginas activa, para evitar que una página comercial termine afectando Contacto u otras páginas.

## Requisitos

- WordPress 6.4+
- PHP 8.0+
- TIBOX Core v0.4 activo

## Uso para los paquetes actuales

1. Instalar y activar `TIBOX Design Tools`.
2. Ir a `TIBOX Design → Editor de código`.
3. Abrir el paquete `Sitios Web`.
4. En `Corregir solo el destino`, elegir `Página específica` → `Sitios Web`.
5. Guardar destino. Si el paquete estaba activo globalmente, la asignación `page` se elimina y se reemplaza por `page:{ID}`.
6. Repetir para Presencia Digital, Soluciones a Medida y Contacto.

## Manifest recomendado

```json
{
  "name": "TIBOX Cloud · Sitios Web",
  "version": "1.0.1",
  "type": "template",
  "target": "page",
  "target_slug": "sitios-web",
  "entry": "index.html",
  "css": "style.css",
  "js": "script.js"
}
```

`target_slug` es preferible a `target_object_id`, porque el ID cambia entre instalaciones.
