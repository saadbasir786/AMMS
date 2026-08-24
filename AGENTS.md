# AMMS Workspace Directives & Development Guidelines

Welcome to the Afghanistan Meetings Management System (AMMS) codebase. All development, refactoring, and feature additions must strictly adhere to the structured rules maintained under `.agents/rules/`:

## Active Workspace Charters:

1. **Frontend Architecture & Design Standards**:
   - Reference File: [.agents/rules/frontend-architecture-and-design.md](.agents/rules/frontend-architecture-and-design.md)
   - Core Directives:
     - Mandatory Feature-Based Architecture in `frontend/src/features/<feature>/`
     - Mandatory usage of `MultiCalendarDatePicker` (Afghan Solar Hijri) and `TimePicker`
     - Strict humanized Persian UI text using `اقدامات قابل انجام` and zero raw workflow codes (`FLOW-XX`)
     - Smart pre-population of proposed values into subsequent workflow stages
     - Consistent shadcn/tailwind design tokens with `rounded-2xl` and `rounded-xl`

2. **Autonomous Rule Evolution Directive (AI Self-Evolution)**:
   - As new patterns, UX decisions, or architectural standards are introduced, the AI assistant must autonomously update the rule documents under `.agents/rules/` without requiring manual reminders from the user.
