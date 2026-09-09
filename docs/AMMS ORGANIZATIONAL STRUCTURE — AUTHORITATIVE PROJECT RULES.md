AMMS ORGANIZATIONAL STRUCTURE — AUTHORITATIVE PROJECT RULES

Treat the following organizational structure as authoritative for AMMS.

1. CORE PRINCIPLE

AMMS must distinguish clearly between:

* Organizational Unit
* Position
* Employee
* System Role / Capability

These are separate concepts and must not be mixed.

Organizational hierarchy must be represented using parent-child relationships between organizational units.

Do NOT hardcode business logic based only on unit names or fixed hierarchy depth.

The `parent_id` relationship is the authoritative source of organizational hierarchy.

---

2. HIGH-LEVEL ORGANIZATIONAL STRUCTURE

The Administrative Office organization contains multiple organizational levels, including:

* General Presidency
* Deputy Presidency / Deputy Office
* General Directorate
* Directorate
* Attached Agency
* Internal Deputy Directorate
* Amirate
* General Management
* Management
* Section or other lower-level units where applicable

A Directorate is NOT always directly under the top organization.

Example:

General Presidency
→ Deputy Presidency
→ Directorate
→ Amirate
→ General Management
→ Management

But hierarchy can vary.

For example, a General Management can sometimes report directly to a Directorate instead of an Amirate.

Therefore:

DO NOT enforce rules such as:

`GENERAL_MANAGEMENT must always have AMIRIATE as parent`

The actual `parent_id` determines the hierarchy.

---

3. DIRECTORATE INTERNAL STRUCTURE

A typical Directorate may contain:

Directorate

* Director
* Internal Deputy Directorate

  * Deputy Director
* General Management

  * General Manager
  * Manager
  * Employee
* Amirate

  * Amir
  * General Management

    * General Manager
    * Manager
    * Employee

Example: Technology Directorate

Technology Directorate

* Technology Director

* Technology Deputy Directorate

  * Technology Deputy Director

* Executive General Management

  * Executive General Manager
  * Executive Manager
  * Executive Employee

* Network and Systems Amirate

  * Network and Systems Amir
  * Network General Management

    * Network General Manager
    * Network Manager
    * Network Employee

* Software Amirate

  * Software Amir
  * Software General Management

    * Software General Manager

This structure is confirmed by the project owner and must be treated as authoritative project domain knowledge.

---

4. ORGANIZATIONAL UNIT VS POSITION

Very important:

Examples of Organizational Units:

* Technology Directorate
* Technology Deputy Directorate
* Network and Systems Amirate
* Software Amirate
* Executive General Management
* Network General Management
* Network Management

Examples of Positions:

* Technology Director
* Technology Deputy Director
* Network and Systems Amir
* Software Amir
* Executive General Manager
* Network General Manager
* Network Manager
* Employee
* Expert

Do not represent a Position as an Organizational Unit.

A Position belongs to an Organizational Unit.

An Employee belongs to an Organizational Unit and may occupy a Position.

---

5. POSITION HIERARCHY

The organizational seniority of positions is approximately:

DIRECTOR
↓
DEPUTY_DIRECTOR
↓
AMIR
↓
GENERAL_MANAGER / EXPERT
↓
MANAGER
↓
EMPLOYEE

Important:

`EXPERT` is organizationally equivalent in level to `GENERAL_MANAGER`.

Recommended relative hierarchy:

DIRECTOR = 100
DEPUTY_DIRECTOR = 90
AMIR = 80
GENERAL_MANAGER = 70
EXPERT = 70
MANAGER = 60
EMPLOYEE = 50

These values represent organizational seniority only.

They MUST NOT automatically grant system permissions.

---

6. SYSTEM ROLE MUST REMAIN SEPARATE

Organizational position does not automatically equal an AMMS role.

Examples:

A General Manager may have no special AMMS role.

An ordinary Employee may be assigned:

`MEETING_COORDINATOR`

A Director may receive:

`DIRECTOR`

A Deputy Director should NOT automatically receive permanent Director permissions merely because they are the deputy.

System permissions must continue to use:

* scoped roles
* capabilities / permissions
* organizational scope
* delegation
* authority rules

Do not derive authorization only from position hierarchy.

---

7. DELEGATION

When a Director is absent, they may delegate operational authority to their Deputy Director.

This must be modeled as temporary, auditable delegation.

Correct concept:

Director
→ temporarily delegates authority
→ Deputy Director

Do NOT permanently assign the Deputy Director the Director role just to support absence scenarios.

Delegation should have, where applicable:

* delegator
* delegate
* organizational scope
* effective start
* effective end
* status
* created_by
* reason
* audit trail

Existing AMMS delegation architecture should be reused rather than duplicated.

---

8. RECOMMENDED ORGANIZATIONAL UNIT TYPES

Review the current `organizational_units.unit_type` implementation.

The domain should be able to represent at least:

GENERAL_PRESIDENCY
DEPUTY_PRESIDENCY
GENERAL_DIRECTORATE
DIRECTORATE
ATTACHED_AGENCY
DEPUTY_DIRECTORATE
AMIRIATE
GENERAL_MANAGEMENT
MANAGEMENT
SECTION
OTHER

Do not make hierarchy dependent on these enum values.

They are classification metadata, not hierarchy rules.

Hierarchy is determined by `parent_id`.

---

9. RECOMMENDED POSITION TYPES

The system should be able to represent at least:

DIRECTOR
DEPUTY_DIRECTOR
AMIR
GENERAL_MANAGER
EXPERT
MANAGER
EMPLOYEE
OTHER

If current position modeling already supports this cleanly, reuse it.

Do not introduce unnecessary duplicate fields or tables.

---

10. IMPORTANT REVIEW OF EXISTING AMMS FLOWS

Audit the current codebase for assumptions that restrict organizational interaction to only "Directorates".

Historically, some AMMS flows were conceptually described as:

* Directorate-to-Directorate
* Multi-Directorate
* Director approval

The real domain is broader.

Meetings and requests may occur between arbitrary organizational units, for example:

Technology Directorate
↔ Finance Directorate

Deputy Presidency
↔ Deputy Presidency

Technology Directorate
↔ General Directorate of Municipal Affairs Coordination

Attached Agency
↔ Deputy Presidency

Therefore, where technically correct, prefer the domain concept:

`Organizational Unit`

instead of hardcoded:

`Directorate`

Do NOT rename established domain concepts blindly.

First inspect whether "Directorate" in each context means:

A. literal organizational unit type DIRECTORATE

or

B. historical naming for any participating organizational unit.

Only change behavior where the current implementation incorrectly restricts valid organizational structures.

---

11. AUTHORITY RESOLUTION

Authority must be determined from the combination of:

* organizational hierarchy
* scoped AMMS role
* position
* active delegation
* meeting/request authority context
* special capabilities

Do not determine authority from title text such as:

"رییس"
"معاون"
"آمر"

Titles are display data.

Use structured fields and relationships.

---

12. REQUIRED CODEBASE AUDIT

Before making changes:

1. Inspect current organizational tables/models.
2. Inspect `organizational_units`.
3. Inspect `positions`.
4. Inspect employee-position relationships.
5. Inspect scoped roles and role scopes.
6. Inspect delegation logic.
7. Inspect authority resolution.
8. Search for hardcoded `DIRECTORATE` assumptions.
9. Search for fixed-depth parent traversal.
10. Search for code that assumes every General Management belongs under an Amirate.
11. Search for code that treats Deputy Director as only a position or only a role.
12. Search for authorization derived directly from position title strings.

---

13. IMPLEMENTATION RULE

Do not perform a broad rewrite if the existing architecture already supports this hierarchy.

The current parent-based organizational tree is conceptually correct.

Prefer:

* minimal schema changes
* explicit domain enums/types
* backwards compatibility
* migrations that preserve existing data
* regression tests
* no destructive reset
* no unnecessary renaming of stable APIs

---

14. TEST CASES THAT MUST WORK

At minimum ensure the model supports:

CASE 1

Technology Directorate
→ Executive General Management

without an Amirate parent.

CASE 2

Technology Directorate
→ Network and Systems Amirate
→ Network General Management
→ Network Management

CASE 3

Technology Directorate
→ Software Amirate
→ Software General Management

CASE 4

Technology Directorate
→ Technology Deputy Directorate

CASE 5

Employee belongs to Network Management but holds a normal Employee position.

CASE 6

Expert belongs to a unit and has the same organizational seniority level as General Manager.

CASE 7

Deputy Director temporarily receives Director authority through delegation without receiving permanent Director authorization.

CASE 8

A meeting/request can involve organizational units of different types.

Example:

DIRECTORATE ↔ GENERAL_DIRECTORATE

or

DIRECTORATE ↔ DEPUTY_PRESIDENCY

CASE 9

Moving a unit to a different parent requires only organizational hierarchy update and must not require rewriting authorization logic.

---

15. FINAL DOMAIN RULE

The authoritative AMMS model is:

Organization
→ Organizational Unit Tree
→ Positions
→ Employees

plus independently:

Users
→ Scoped Roles
→ Capabilities
→ Delegations
→ Authority Context

Never collapse these into one hierarchy.

The organizational tree describes where a person belongs.

Positions describe what organizational post they occupy.

Roles and capabilities describe what they are allowed to do inside AMMS.

Delegation temporarily transfers specific authority.

These four concerns must remain separate.
