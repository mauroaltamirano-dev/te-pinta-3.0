# Architecture decision records

Use this directory for durable decisions whose tradeoffs future maintainers should not have to reconstruct. Routine implementation details do not need an ADR.

## When to record a decision

Create an ADR when changing a major boundary, data model convention, security policy, runtime/deployment approach, or significant dependency.

## Naming

```text
NNNN-short-decision-title.md
```

Numbers are sequential and records are never renumbered. Superseded records remain in Git and link to their replacement.

## Template

```markdown
# NNNN: Decision title

Status: proposed | accepted | superseded
Date: YYYY-MM-DD

## Context

What problem or constraint requires a decision?

## Decision

What will the project do?

## Consequences

What becomes easier, harder, or intentionally deferred?

## Alternatives considered

Which credible alternative was rejected, and why?
```

The current foundational choices are summarized in `docs/ARCHITECTURE.md`. Create individual ADRs when those choices change or need deeper rationale.
