# Documentation index

Use this page to find the smallest document needed for a change.

| Need                                                                         | Read                                       |
| ---------------------------------------------------------------------------- | ------------------------------------------ |
| Understand system boundaries and dependency direction                        | [ARCHITECTURE.md](ARCHITECTURE.md)         |
| Find a route, page, module, migration, config, or log                        | [PROJECT_MAP.md](PROJECT_MAP.md)           |
| Add or change a backend module                                               | [MODULES.md](MODULES.md)                   |
| Change tables, identifiers, indexes, or data lifecycle                       | [DATABASE.md](DATABASE.md)                 |
| Change authentication, authorization, validation, or sensitive data handling | [SECURITY.md](SECURITY.md)                 |
| Change tokens, layouts, or reusable UI                                       | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)       |
| Set up or run the application                                                | [DEVELOPMENT.md](DEVELOPMENT.md)           |
| Add application events or diagnose failures                                  | [LOGGING.md](LOGGING.md)                   |
| Add or run automated checks                                                  | [TESTING.md](TESTING.md)                   |
| Understand recorded tradeoffs                                                | [decisions/README.md](decisions/README.md) |

## Change checklist

1. Identify the owning module and relevant document.
2. Keep server-side authorization and validation with the use case.
3. Reuse an existing component or capability before creating another.
4. Run targeted checks, then the full applicable quality gate.
5. Update the map and decision record if a boundary or durable tradeoff changed.
