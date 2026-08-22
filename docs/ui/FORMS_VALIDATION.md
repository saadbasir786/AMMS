# AMMS Forms & Validation UX

## Scope

This UI refactor standardizes form presentation and feedback only. Laravel remains authoritative for business validation, authorization, workflow transitions, and persisted state.

## Invariants

- No API routes, payload semantics, role checks, or FLOW states are changed by the form system.
- Backend validation errors remain authoritative and are surfaced prominently in the UI.
- Required markers, labels, hints, disabled/loading states, focus states, and accessible error presentation are standardized.
- Submit buttons prevent duplicate clicks while a request is pending.
- Draft and submit actions remain distinct and keep their existing backend sequence.
- Existing business defaults are preserved.
- The first representative migration is the FLOW-02 inter-directorate meeting request form.

## Shared primitives

- `FormField`
- `FormSection`
- shadcn `Input`
- shadcn `Textarea`
- shadcn `Select`
- shadcn `Checkbox`
- shadcn `Alert`
- shadcn `Button`

Further forms should migrate incrementally without changing their API contracts or workflow semantics.
