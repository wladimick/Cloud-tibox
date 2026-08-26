# TIBOX Theme v0.3

Theme WordPress ultraliviano, sin Elementor.

## Novedades v0.3

- Nueva página `Apariencia → Guía Claude Design`.
- Documentación visual de la arquitectura del sistema.
- Estado rápido de portada, menú principal y TIBOX Core.
- Mapa de plantillas y variables disponibles.
- Reglas DO / DON'T para Claude Design.
- Prompts específicos con botón de copiar para Header, Footer, Inicio, Página, Single Catálogo y Archivo Catálogo.
- Todas las plantillas de contenido se normalizan dentro del `<main id="main-content">` generado por el theme.

## Editor del theme

`Apariencia → TIBOX Theme`

Permite administrar:

- Header global.
- Footer global.
- Plantilla Inicio.
- Plantilla Página.
- Single Catálogo.
- Archivo Catálogo.
- Single general.
- Archivo general.
- 404.
- CSS global.
- JavaScript global.

## Guía IA

`Apariencia → Guía Claude Design`

Es la referencia que debe usar el equipo antes de pedir un diseño a Claude.

## Seguridad

Los editores son solo para administradores. No se permite PHP dentro de los campos administrables. Nunca pegar API keys, tokens o credenciales.


## v0.4 — CSS por responsabilidad

TIBOX Theme ahora separa el CSS administrable en:

- Base / global
- Header
- Footer
- Inicio
- Página
- Single catálogo
- Archivo catálogo
- Single general
- Archivo general
- 404

El CSS existente de v0.1–v0.3 se mantiene automáticamente en **Base / global**.
No se borra ni se intenta dividir automáticamente, para evitar romper estilos.

Los bloques condicionales solo se imprimen en la vista correspondiente, reduciendo
CSS innecesario por página.


## v0.5 — Slider Home dinámico

- Nueva variable de Inicio: `{{HOME_HERO_SLIDER}}`.
- El contenido se administra desde **WordPress → Slider Home** (TIBOX Core v0.3).
- 1 slide activo = hero estático.
- 2+ slides = flechas + puntos, sin autoplay.
- CSS estructural se carga solo en Inicio cuando existen slides.
- Personalización visual adicional en **TIBOX Theme → CSS / JavaScript → Slider Home**.

## v0.5.1
- Slider renderiza medios por attachment ID y, si falla, usa URL de respaldo.
- Compatible con slides creados previamente.


## v0.5.2 — Variables avanzadas del Catálogo

Nuevas variables para Single Catálogo:
- `{{CATALOG_PROMO}}`
- `{{CATALOG_VALUE_PROPOSITION}}`
- `{{CATALOG_PLATFORM_CHIPS}}`
- `{{CATALOG_FEATURE_LIST}}`
- `{{CATALOG_CATEGORIES}}`

Los chips y la lista de beneficios ya vienen renderizados como HTML seguro,
por lo que Claude Design no necesita crear loops PHP.


## v0.6 — TIBOX Design Packages

Prioridad:
1. ZIP activo / preview
2. Plantilla HTML manual
3. PHP por defecto

Un ZIP integrado conserva Header/Footer y reemplaza el contenido de Inicio, Página,
Single catálogo, Archivo catálogo, Single general, Archivo general o 404.

El CSS manual específico de la vista se omite cuando hay ZIP activo para evitar
colisiones. Base, Header, Footer y Slider Home continúan funcionando.

- Corrección del stacking context del Slider Home en modo foto (`z-index:auto`).
