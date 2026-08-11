# Design system

Te Pinta uses shadcn/ui as an accessible technical primitive layer, not as its visual identity. Public and admin experiences share tokens but have distinct composition and density.

## Visual directions

- **Public:** mobile-first food ordering, based on the supplied Food App reference: strong product imagery, clear category/product hierarchy, and direct ordering calls to action.
- **Admin/POS:** desktop/tablet-first operational interface, based on the supplied dark Food POS reference: dense but readable navigation, catalog on the left, current order on the right, and fast state recognition.

Template names, sample products, and fictitious content are never copied. The canonical Products implementation reference is `docs/design/references/admin-products.png`; its composition is adapted to Te Pinta rather than copied as a generic dashboard.

## Ownership

| Layer                     | Location                                      | Rule                                                                                    |
| ------------------------- | --------------------------------------------- | --------------------------------------------------------------------------------------- |
| Tokens and Tailwind theme | `resources/css/app.css`                       | Single source for color, type, spacing, radius, shadow, breakpoint, and state decisions |
| shadcn primitives         | `resources/js/components/ui/`                 | Accessible low-level primitives; avoid feature/business behavior                        |
| Te Pinta shared UI        | `resources/js/components/shared/`             | Branded reusable composition such as money or status display                            |
| Feature UI                | `resources/js/features/<feature>/components/` | Business-specific components owned by one feature                                       |
| Layouts                   | `resources/js/layouts/`                       | Public, admin/POS, and authentication shells                                            |

## Token rules

- Consume semantic variables (`background`, `surface`, `primary`, `destructive`, etc.) instead of raw colors in components.
- Add a token only when it represents a reusable design decision.
- Preserve visible focus, contrast, keyboard navigation, reduced-motion preferences, and touch target usability.
- Treat responsive behavior as recomposition, not proportional shrinking.
- Public pages start at mobile width; admin/POS starts with tablet/desktop workflows and remains usable at reasonable narrower widths.

## Component reuse

Before adding a component:

1. Check `components/ui` for an existing primitive.
2. Check `components/shared` for a Te Pinta composition.
3. Check the owning feature.
4. Add a wrapper only when it centralizes real visual or behavioral policy.

Do not duplicate buttons, dialogs, inputs, product cards, money displays, or status badges under different names.

## Figma implementation workflow

When the Figma integration and exact nodes are available:

1. Retrieve design context for the exact node.
2. Capture the exact frame screenshot.
3. Reuse exported/reference assets rather than approximating them.
4. Map visual decisions to semantic tokens and shared components.
5. Implement responsive composition.
6. Verify representative viewports and interactions with Playwright screenshots.
7. Record meaningful token or component decisions here.

## Products management composition

`resources/js/pages/admin/products/index.tsx` integrates with the global dark admin shell and adds a Settings-local navigation rail. The feature preserves the reference's hierarchy: category tabs and category management at the top, then a dense responsive product-card grid led by the Add product card.

Product cards use circular imagery, a consistent image placeholder, compact base-price rows, vegetarian/unavailable status badges, and one clear edit action. Product/category editing uses focused, accessible dialogs so validation errors stay beside their fields.

Changes save per product or category. Closing/canceling a dialog discards its unsaved local state; page-wide Save/Discard controls were intentionally not reproduced because the server persists each resource independently and a fake global buffer would misrepresent that behavior.
