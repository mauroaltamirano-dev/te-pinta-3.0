# 0014: Light-only theme with restaurant branding

Status: accepted
Date: 2026-10-16

## Context

The owner replaced the dark admin design with a light theme and a restaurant-configurable accent. Public storefront composition must remain unchanged. Browser preferences must not override the chosen product direction.

## Decision

Store a lowercase six-digit accent on `restaurants`, scoped through `RestaurantContext`. Keep palette derivation and contrast validation in `Core/Branding`, with no production dependency or module import. Settings owns the existing-authority HTTP surface.

Generate the palette on the server, inject CSS variables into the root view and refresh them through shared Inertia props. Components consume semantic Tailwind tokens; local color preview never changes the saved global palette. Require white action labels to contrast at least 4.5:1. Derive darker text/hover shades and tinted selections. Keep Barlow and existing navigation/layout composition.

## Consequences

- A saved accent takes effect without rebuilding assets, including SPA navigation.
- Pale accents are rejected instead of silently producing inaccessible white labels. The API remains authoritative; the database enforces hex shape.
- Direct-SQL values that fail contrast render using the default safe palette. No branding query runs during console boot.
- Dark mode is intentionally unsupported. The legacy appearance hook remains API-compatible but always resolves to light.
- Static install icons and the favicon retain default branding; the in-app logo uses a colorable mask.
- RBAC integration and sidebar information architecture remain separate work. ADR 0013 is reserved by the parallel RBAC workstream.

## Alternatives considered

- Retain both themes: rejected by the owner's light-only decision and unnecessary maintenance.
- Client-only palette generation: rejected because initial HTML and backend validation must agree before JavaScript runs.
- A color library: unnecessary for deterministic sRGB mixing and WCAG luminance calculations covered by focused tests.
