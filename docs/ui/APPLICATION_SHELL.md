# AMMS Application Shell

## Scope

The application shell owns only global presentation and navigation concerns:

- desktop sidebar;
- mobile navigation sheet;
- top header and breadcrumb context;
- current-user identity and logout affordance;
- notification entry point;
- responsive content container.

## Invariants

The shell must not define or change business authority. Route visibility continues to use the existing AMMS role metadata supplied by the application, while Laravel remains authoritative for permissions and workflow actions.

The shell must not change API contracts, workflow states, reporting semantics, or backend behavior.

## RTL and accessibility

Persian/Dari RTL is the default direction. Interactive navigation uses semantic buttons, active routes expose `aria-current="page"`, mobile navigation uses the shadcn/Radix Sheet primitive, and the user menu uses the shadcn/Radix DropdownMenu primitive.

## Migration rule

Legacy screen styles remain active while individual pages are migrated to Tailwind and shadcn/ui. New shell classes avoid the old `.sidebar`, `.topbar`, `.content`, and related selectors so both systems can coexist safely during the transition.
