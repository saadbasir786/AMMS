---
trigger: always_on
---

# AMMS Frontend Architecture, UI/UX & Design System Standards

This document establishes the mandatory architecture, UI/UX conventions, and coding guidelines for the Afghanistan Meetings Management System (AMMS) frontend. All feature developments, refactorings, and UI components must strictly adhere to these standards.

---

## 1. Feature-Based Modular Architecture

All application domains must be organized under `src/features/<feature-name>/` following this structure:

```text
src/features/<feature-name>/
├── components/          # Feature-specific components, cards, forms, and widgets
├── pages/               # Top-level Page components
├── types.ts             # TypeScript domain interfaces, form models, and API types
└── index.ts             # Public barrel export for clean inter-module access
```

### Directives for `src/main.tsx`:
- `src/main.tsx` serves strictly as the **Application Router** and root shell provider.
- Never write inline form logic, heavy state handlers, or large JSX blocks directly in `main.tsx`. All view implementations must live inside their respective feature folder.

---

## 2. Calendar & Time System Standards

AMMS operates within the administrative calendar standards of Afghanistan:

1. **Date Inputs (`MultiCalendarDatePicker`)**:
   - **Mandatory**: Always use `MultiCalendarDatePicker` located at `@/components/ui/multi-calendar-date-picker`.
   - **Default Calendar**: Afghan Solar Hijri (*حمل، ثور، جوزا، سرطان، اسد، سنبله، میزان، عقرب، قوس، جدی، دلو، حوت*).
   - Must provide real-time triple-calendar conversion preview (`☀️ Solar · 🌙 Lunar · 🌍 Gregorian`).
2. **Time Inputs (`TimePicker`)**:
   - **Mandatory**: Always use `TimePicker` located at `@/components/ui/time-picker`.
   - Must offer 30-minute office hour quick chips (08:00 to 16:00) with precise manual minute entry fallback.
3. **Date & Time Display**:
   - Always format displayed dates using `formatSolarDate(date)` and `formatSolarDateTime(date)` from `@/lib/calendar-utils`.
4. **Backend Transmission Contract**:
   - Backend APIs receive dates in standard ISO `YYYY-MM-DD` and times in `HH:mm`.

---

## 3. Tone of Voice, Terminology & Persian Administrative UI Labels

All user-facing text must be clear, professional, respectful, and free of developer jargon:

1. **Strict Prohibition of Technical Workflow Codes**:
   - Never display raw technical codes such as `FLOW-01`, `FLOW-02`, `FLOW-06`, or database statuses in UI titles or headers.
2. **Standard Action Card Header**:
   - **Title**: `اقدامات قابل انجام` (Permissible Actions)
   - **Subtitle**: `عملیات مجاز متناسب با وضعیت فعلی [موضوع]` (Authorized actions matching current status)
3. **Standard Administrative Terminology**:
   - Use clear Persian terms: `تأیید ریاست`, `برگشت جهت اصلاح`, `رد درخواست`, `تنظیم برنامه واقعی جلسه`, `آجندا و دستور کار جلسه`, `صورت‌جلسه رسمی`.
4. **Strict Elimination of Generic Header Branding (`PageHeader` Eyebrows)**:
   - Never use static generic system prefixes like `سیستم مدیریت جلسات`, `سیستم جلسات`, or technical codes in `PageHeader` (`eyebrow` prop).
   - Omit the `eyebrow` prop entirely on primary listing/dashboard pages.
   - On detail/workspace pages, only provide the specific entity identifier if helpful (e.g. `REQ-2026-001` or `MEET-2026-004`), strictly without generic suffix/prefix branding.

---

## 4. UX & Smart Workflow Patterns

1. **Smart Pre-population**:
   - In progressive workflows (e.g., transition from meeting request to official scheduling), proposed initial values (`preferred_date`, `preferred_start_time`, `estimated_duration_minutes`, `proposed_location`, and nominated participants) must **automatically pre-populate** the subsequent official fields. The user can confirm with one click or modify any field as needed.
2. **Standard 2-Column Responsive Layout**:
   - **Main Column (2/3 width)**: Core forms, summary cards, and participant tables.
   - **Sidebar (1/3 width / Sticky)**: Reference comparison cards (initial proposals), action buttons, step guides, and immutable audit timeline.
3. **Destructive Action Confirmations (`ConfirmDialog`)**:
   - Irreversible actions (e.g. rejection, return for revision, cancellation) must always trigger a `ConfirmDialog`.
4. **Loading Skeletons & Network Error Handling**:
   - All pages must display an animated skeleton loader (`animate-pulse`) while fetching data.
   - Network errors must be presented with an `Alert variant="destructive"` containing a "تلاش مجدد" (Retry) button.

---

## 5. Visual Aesthetics & Design System Tokens

1. **Border Radius**:
   - Cards & Master Panels: `rounded-2xl`
   - Form Inputs, Buttons & Selectors: `rounded-xl`
   - Badges & Micro-chips: `rounded-lg`
2. **Shadows & Borders**:
   - Modern, ultra-subtle shadows: `shadow-xs` / `shadow-sm`.
   - Subtle borders: `border-border/70` and `border-border/50`.
3. **Semantic Status Badges (`StatusBadge`)**:
   - **Approved / Completed / Final**: Emerald Green (`emerald` / `bg-emerald-50 dark:bg-emerald-950/50`)
   - **Action Required / Returned**: Amber / Yellow (`amber` / `bg-amber-50 dark:bg-amber-950/50`)
   - **Rejected / Cancelled**: Red / Destructive (`destructive` / `rose`)
   - **In Progress / Scheduling**: Sky Blue / Indigo (`sky` / `indigo`)
   - **Draft**: Neutral Slate (`slate` / `muted`)

---

## 6. Autonomous Rule Evolution Directive (AI Mandate)

**Mandatory Directive for AI Pair-Programming Assistant:**
1. **Autonomous Implementation-Rule Updates**: As new reusable UI, UX, or non-domain architectural patterns are established, the AI assistant should proactively update this document or add specialized rule files under `.agents/rules/` without requiring manual reminders.
2. **Authorization & Domain Boundary**: Roles, permissions, organizational scope, delegated capabilities, workflow authority, approval paths, and lifecycle states are excluded from autonomous evolution. They are governed by `.agents/rules/roles-permissions-and-scope.md` and require an explicit user/domain decision before they may be changed.
3. **Pre-Implementation Check**: The assistant must review this rule document and any applicable specialized rule files before implementing code changes.
4. **Walkthrough Audit**: Any newly added or updated rules must be documented in the session walkthrough report.

---

## 7. Mandatory Per-Feature Git Commit Lifecycle (Manual Push by User)

1. **Commit Timing**: Whenever a feature, workspace, or domain capability is completed and approved by the user, a dedicated, descriptive Git commit MUST be recorded locally **before starting the next feature**.
2. **Local Commit Only**: The AI assistant must strictly perform only `git commit` and never run `git push`. Remote push operations are handled manually by the user.
3. **Commit Atomicity**: Each commit must strictly encapsulate changes belonging to that specific feature and its associated route/types/cleanup, keeping the Git history clean, logical, and bisectable.
4. **Zero Accumulation**: Never leave completed features uncommitted across multiple consecutive tasks.
