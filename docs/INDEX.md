# Documentation index

Start with the smallest document that owns the change.

| Need                                                            | Read                                       |
| --------------------------------------------------------------- | ------------------------------------------ |
| Understand boundaries and dependency direction                  | [ARCHITECTURE.md](ARCHITECTURE.md)         |
| Find the exact owner of a route, rule, page, or setting         | [PROJECT_MAP.md](PROJECT_MAP.md)           |
| Add or change a backend capability                              | [MODULES.md](MODULES.md)                   |
| Change order grouping or totals                                 | [ORDER_PRICING.md](ORDER_PRICING.md)       |
| Change tables, constraints, identifiers, or lifecycle           | [DATABASE.md](DATABASE.md)                 |
| Change authorization, public checkout, or private data handling | [SECURITY.md](SECURITY.md)                 |
| Change tokens, layouts, or reusable UI                          | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)       |
| Set up, migrate, or run the application                         | [DEVELOPMENT.md](DEVELOPMENT.md)           |
| Add an event or diagnose a failure                              | [LOGGING.md](LOGGING.md)                   |
| Add or run automated checks                                     | [TESTING.md](TESTING.md)                   |
| Understand recorded tradeoffs                                   | [decisions/README.md](decisions/README.md) |

## Change checklist

1. Identify the owning module and read its focused guide.
2. Keep authorization, validation, prices, and totals on the backend.
3. Preserve order snapshots; never rebuild history from current catalog/settings.
4. Run targeted checks, then the full applicable quality gate.
5. Update this documentation when a durable boundary or rule changes.
