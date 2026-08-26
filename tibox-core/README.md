# TIBOX Core v0.2

Actualización compatible con TIBOX Core v0.1.

## Nuevo
- Importar catálogo mediante JSON.
- Exportar catálogo actual como JSON.
- Actualización por `slug` para evitar duplicados.
- Nuevos campos: promoción, plataformas/canales, propuesta principal y características.
- Mantiene los campos anteriores y los elementos existentes.

## Uso
1. Instalar/reemplazar el plugin TIBOX Core.
2. Ir a Catálogo → Importar JSON.
3. Subir un .json o pegar su contenido.
4. Importar.


## v0.3 — Slider Home

Nuevo tipo de contenido **Slider Home** con:
- Slide activo/inactivo.
- Eyebrow.
- Titular.
- Descripción.
- Imagen desktop.
- Imagen móvil.
- CTA principal y secundario.
- Alineación.
- Superficie visual.
- Orden.

El theme consume los slides mediante `tibox_core_get_home_slides()`.

## v0.3.1
- Slider guarda ID + URL de las imágenes.
- Miniatura visible en listado de slides.
- Corrección del orden del slide.
- Fallback robusto para medios.


## v0.3.2 — Reparación de medios

Nuevo menú: **Medios → Reparar miniaturas**.

Usa `wp_update_image_subsizes()` de WordPress para:
- reconstruir metadata faltante;
- crear subtamaños faltantes;
- conservar intacta la imagen original.

Incluye vista de diagnóstico de cada adjunto.


## v0.3.3 — Reconstrucción completa de miniaturas

La reparación anterior podía considerar un tamaño "existente" porque estaba
registrado en metadata aunque su archivo físico faltara.

Ahora Medios → Reparar miniaturas muestra:
- existencia/lectura del original;
- metadata disponible;
- existencia física del thumbnail.

Nueva acción **Forzar reconstrucción completa**:
- parte desde el archivo original;
- usa `wp_generate_attachment_metadata()`;
- vuelve a crear tamaños intermedios;
- reemplaza metadata por la recién generada.


## v0.4 — TIBOX Design Packages

Nuevo menú **TIBOX Design**:
- Paquetes
- Importar ZIP
- Asignaciones

ZIP integrado recomendado:
- manifest.json (opcional)
- index.html
- style.css
- script.js
- assets/

Características:
- versiones y rollback mediante asignaciones;
- preview de paquetes sin activarlos;
- CSS/JS se carga solo en su destino;
- assets relativos compatibles con `assets/...` y `{{PACKAGE_URL}}`;
- soporte de página específica;
- validación estricta y extracción segura sin `ZipArchive::extractTo()`.

Los ZIP aislados de landing siguen usando el importador de Landings existente.
