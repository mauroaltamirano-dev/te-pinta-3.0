# Agent entry point

1. Read `docs/INDEX.md`, then the documents relevant to the area you will change.
2. Preserve the modular boundaries in `app/Modules`; keep cross-cutting infrastructure in `app/Core` or narrowly named `app/Shared` code.
3. Do not introduce abstractions or production dependencies without demonstrated need and a documented reason. Prefer composition and framework-native capabilities.
4. Never write secrets or log sensitive data. Authorization, validation, prices, roles, and state transitions are enforced by the backend.
5. Update documentation when architecture, database structure, security, or relevant behavior changes.
6. Run the affected tests, lint, formatting, type checks, and build before delivery. Report anything you could not run.

Use English for code and project artifacts unless the existing context explicitly requires otherwise.
