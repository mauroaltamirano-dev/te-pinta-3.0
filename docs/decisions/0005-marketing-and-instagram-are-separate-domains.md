# 0005: Marketing e Instagram son dominios separados

Estado: aceptado
Fecha: 2026-08-23

## Contexto

Te Pinta necesita decidir qué Product destacar comercialmente y también opera un sistema de publicación de Instagram Story. Estas capacidades podrían colaborar en el futuro, pero tienen responsabilidades, ciclos de vida de datos, modos de falla y límites de seguridad diferentes.

Tratar Instagram como un submódulo de Marketing vincularía la intención comercial con un canal de publicación específico. Tratar Marketing como parte de Instagram haría que la presentación de la tienda dependiera de n8n, las credenciales de Meta, las colas y los resultados de la entrega externa.

## Decisión

`Marketing` e `Instagram` son módulos pares.

- Marketing define **qué** destaca u ofrece el negocio. Marketing v1 es responsable del historial, la programación, la vista previa, la visibilidad en la tienda pública y el resumen compacto del Dashboard de Featured Product.
- Instagram define **cómo y cuándo** se publica el contenido en Instagram. Continúa siendo responsable de los assets privados de Story, las programaciones, el estado de la cola, la coordinación con n8n, la entrega mediante la API de Meta y el historial de publicaciones.

Marketing v1 no depende de Instagram. No escribe modelos de Instagram, no encola Stories, no llama a n8n ni almacena fields específicos del canal. Instagram no lee registros de Featured Product.

Cualquier conexión futura, como «crear una Story a partir de esta promoción», debe atravesar un contrato explícito entre los módulos. El dominio iniciador puede proporcionar una referencia de contenido segura para uso público, mientras que Instagram continúa siendo responsable de la validación del canal, los assets, la programación, la publicación y las fallas externas.

La URL existente `/admin/marketing/instagram` se conserva como ruta heredada en este slice para evitar modificar la implementación concurrente de Instagram. Su path no establece la responsabilidad del dominio; la navegación global presenta Marketing e Instagram como destinos independientes.

## Consecuencias

- El Marketing de la tienda puede funcionar cuando Instagram o n8n están deshabilitados o no disponibles.
- La automatización de Instagram puede continuar independientemente de la programación de Featured Product.
- Los registros de Marketing se mantienen libres de fields de Meta, Story, cola y automatización.
- La integración futura exige un límite versionado de forma deliberada, en lugar de acoplamiento directo entre modelos o Controllers.
- La ruta heredada de Instagram puede migrarse por separado más adelante, con redirects y revisión operativa, sin modificar esta decisión de responsabilidad.

## Alternativas consideradas

- **Instagram dentro de Marketing:** rechazado porque un canal de publicación no debe ser responsable de las ofertas comerciales ni de la presentación de la tienda.
- **Marketing dentro de Instagram:** rechazado porque Featured Product no depende de Meta, n8n, Stories ni del éxito de la publicación.
- **Implementar ahora escrituras directas de Marketing a Instagram:** rechazado porque este slice vertical debe demostrar la programación y el renderizado público de Featured Product antes de diseñar una automatización entre módulos.
- **Renombrar o mover el módulo concurrente de Instagram en este slice:** rechazado porque agrega un riesgo de migración y routing ajeno al objetivo mientras ese módulo operativo ya está en desarrollo.
