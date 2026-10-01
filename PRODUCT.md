# Product

## Register

product

## Users

Multi-role design agency team working daily in a professional environment (primarily desktop, working hours):

- **Agency managers & admins** — oversee operations, monitor performance, manage subscriptions, configure the system
- **Supervisors** — distribute design tasks, track workflow stages, ensure timely delivery
- **Designers** — receive tasks, upload designs, manage their daily workload
- **Reviewers** — review submitted designs, request revisions, approve final output
- **Accountants & finance** — manage subscriptions, transactions, financial records, client balances

Each role has a dedicated dashboard and a tailored subset of the admin interface. The system is used daily during working hours, primarily on desktop.

## Product Purpose

TrueERP is a centralized workflow platform for design agencies. It automates designer-to-client distribution, tracks the full lifecycle of design tasks (from order through review to delivery), and manages subscriptions and accounting. Success means a predictable workflow where every task reaches the right designer, every deadline is tracked, and financial operations are transparent.

## Brand Personality

Bold, Dark, Technical.

The interface is a professional-grade tool first — confident in its darkness, unapologetic about its complexity. It draws inspiration from Google AI Studio's focused work environment and Material 3's systematic design language. The dark theme is not a toggle; it's the native habitat where the team spends their day. The purple primary is the brand's signature mark, used with precision. The interface communicates capability through clarity, not decoration.

This is the design agency's own command center — built by makers, for makers. The interface should feel like sitting down at a well-organized workbench: everything in its place, nothing unnecessary, ready for the day's work.

## References

- **Google AI Studio** — focused, dark-native work surfaces; color used only where it communicates something
- **Material 3 Design** — systematic M3 elevation language, tonal surface overlays, intentional spacing rhythm

## Anti-references

- Generic Bootstrap / standard admin panel templates. No predictable sidebar-heavy layouts, gray-scale-only palettes, or cookie-cutter dashboard cards.
- Overly colorful or gamified interfaces. The product serves professionals doing serious work; restraint communicates confidence.
- SaaS clichés: hero-metric templates (big number + small label + gradient accent), identical card grids with icon + heading + text, numbered section markers as default scaffolding.
- "AI slop" patterns: gradient text, glassmorphism as default, side-stripe borders, over-rounded cards (>16px), hand-drawn SVG illustrations.
- Flat gray-scale admin panels. Gray on gray is not "clean" — it's unfinished.

## Design Principles

1. **Role-first architecture.** Every screen is tuned to the user's role and context. A designer sees tasks; a manager sees metrics. No one sifts through irrelevant data.
2. **Dark-native by default.** Dark is not a theme toggle — it's the primary environment. Light mode exists for accessibility, but the system is designed and tested in dark first.
3. **Precision over decoration.** Every color, shadow, and space carries intent: hierarchy, status, action, or data. If it doesn't communicate, it doesn't exist.
4. **M3-inspired systematic depth.** Elevation is a structured language (tonal surface overlays, not arbitrary shadows). Surfaces at rest are flat; depth signals interaction and hierarchy.
5. **The purple signature.** Royal Purple is the brand's single signature color, used sparingly (≤15% per screen). Orange speaks only about action and urgency. When both appear, orange leads the action and purple owns the identity.
6. **Friction-free workflows.** Primary actions are one click away. Secondary actions don't compete. Complex flows (distribution, accounting) provide clear guidance at each step.
7. **Arabic-first.** RTL is the default layout direction. Arabic typography (Cairo) drives the visual rhythm; English (Inter) supports numbers and UI labels.

## Accessibility & Inclusion

WCAG AA compliance target. Sufficient color contrast for body text and interactive elements. All animations respect `prefers-reduced-motion`. Touch targets meet minimum size requirements. The system is tested primarily in dark mode, but light mode variants maintain the same contrast standards.
