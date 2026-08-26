# TIBOX Theme v0.3 · Contrato Claude Design / IA

La fuente de verdad operativa está también disponible dentro de WordPress en:

`Apariencia → Guía Claude Design`

## Arquitectura

- **WordPress:** contenido, páginas, medios y menús.
- **TIBOX Core:** catálogo y lógica comercial.
- **TIBOX Theme:** presentación, templates y variables dinámicas.
- **Claude Design / IA:** genera la capa visual HTML/CSS/JS respetando este contrato.

## Regla estructural v0.3

Header y Footer son fragmentos globales y pueden contener `<header>` / `<footer>`.

Todas las plantillas de contenido se insertan dentro de un `<main id="main-content">` generado por TIBOX Theme. Por lo tanto Claude **no debe generar `<main>`** para Home, Página, Single Catálogo, Archivo Catálogo, Single general, Archivo general ni 404.

Nunca generar `<!DOCTYPE>`, `<html>`, `<head>` o `<body>`.

## Variables globales

- `{{SITE_URL}}`
- `{{HOME_URL}}`
- `{{THEME_URL}}`
- `{{SITE_NAME}}`
- `{{CURRENT_YEAR}}`
- `{{CUSTOM_LOGO}}`
- `{{MENU_PRIMARY}}`
- `{{MENU_FOOTER}}`

## Variables de contenido

- `{{PAGE_ID}}`
- `{{PAGE_TITLE}}`
- `{{PAGE_URL}}`
- `{{PAGE_EXCERPT}}`
- `{{PAGE_CONTENT}}`
- `{{FEATURED_IMAGE}}`

## Variables del catálogo

- `{{CATALOG_TYPE}}`
- `{{CATALOG_SUMMARY}}`
- `{{CATALOG_PRICE}}`
- `{{CATALOG_BADGE}}`
- `{{CTA_LABEL}}`
- `{{CTA_URL}}`

## Variables de archivos

- `{{ARCHIVE_TITLE}}`
- `{{ARCHIVE_DESCRIPTION}}`
- `{{ARCHIVE_ITEMS}}`
- `{{PAGINATION}}`

## Reglas

- HTML semántico.
- CSS nativo.
- JavaScript nativo solo cuando haga falta.
- Sin PHP, API keys, credenciales ni secretos.
- Sin React/JSX/Tailwind/Bootstrap/bundlers salvo decisión expresa.
- Conservar literalmente las variables `{{...}}`.
- Responsive, teclado, focus visible y `prefers-reduced-motion`.
- No hardcodear URL/año/logo/menús cuando existe una variable.
- El catálogo usa una sola plantilla para todos los ítems.


## Slider Home

En la plantilla de Inicio utiliza exactamente:

`{{HOME_HERO_SLIDER}}`

El contenido (texto, imágenes y CTAs) NO se hardcodea en la plantilla. Se administra en WordPress → Slider Home.

Para rediseñar el componente, entrega CSS para **CSS / JavaScript → Slider Home** y utiliza las clases `.tbx-home-slider*`.
