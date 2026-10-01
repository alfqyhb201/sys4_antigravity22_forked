---
name: TrueERP
description: Design agency workflow platform
colors:
  primary: "#441188"
  neutral-bg: "#020617"
  text-primary: "#f8fafc"
  accent: "#ff6600"
  muted: "#94a3b8"
  surface-dim: "#0f1120"
  surface: "#1a1d2e"
  surface-bright: "#252839"
  success: "#10b981"
  warning: "#f59e0b"
  error: "#ef4444"
  info: "#3b82f6"
typography:
  display:
    fontFamily: "Cairo, sans-serif"
    fontSize: "clamp(1.75rem, 4vw, 3rem)"
    fontWeight: 900
    lineHeight: 1.1
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Cairo, sans-serif"
    fontSize: "clamp(1.25rem, 3vw, 1.875rem)"
    fontWeight: 800
    lineHeight: 1.2
  title:
    fontFamily: "Cairo, sans-serif"
    fontSize: "clamp(1rem, 2vw, 1.25rem)"
    fontWeight: 700
    lineHeight: 1.3
  body:
    fontFamily: "Cairo, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "Inter, Cairo, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "0.05em"
    textTransform: "uppercase"
elevation:
  level0: "#020617"
  level1: "#0f1120"
  level2: "#1a1d2e"
  level3: "#252839"
  level4: "#2f3248"
rounded:
  sm: "6px"
  md: "10px"
  lg: "14px"
  xl: "16px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
  xxl: "48px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
    padding: "10px 18px"
    fontWeight: 700
  button-primary-hover:
    backgroundColor: "#350d6b"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.sm}"
    padding: "10px 18px"
    border: "1px solid rgba(255,255,255,0.12)"
    fontWeight: 600
  button-secondary-hover:
    backgroundColor: "rgba(255,255,255,0.06)"
    borderColor: "rgba(255,255,255,0.2)"
  card-default:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.lg}"
    padding: "{spacing.lg}"
  card-hover:
    backgroundColor: "{colors.surface-bright}"
    translateY: "-1px"
---

# Design System: TrueERP (v2)

## 1. Overview

**Creative North Star: "The Workbench"**

A professional-grade tool that earns its darkness. Inspired by Google AI Studio's focused environment and Material 3's systematic design language, TrueERP v2 is the design agency's command center — confident, precise, and unapologetically technical.

The interface is dark-native with a structured elevation system. Royal Purple is the brand's signature, used sparingly like a maker's mark on a tool. Ember Orange signals action and urgency. Every surface, shadow, and spacing choice follows a consistent system — nothing is decorative, everything communicates.

**Key Characteristics:**
- Dark-native with M3-inspired tonal elevation layers
- Royal Purple as the single signature color (≤15% per screen)
- Ember Orange reserved for action and urgency
- Flat surfaces at rest; depth as a response to interaction
- Systematic spacing and rounded-corner hierarchy
- Arabic-first (RTL default) with Cairo driving the visual rhythm

## 2. Colors

A restrained, high-contrast palette built around the Royal Purple brand signature. Dark surfaces dominate; color is reserved for interactive elements and status indicators.

### Primary
- **Royal Purple** (#441188 / oklch(40% 0.18 290)): The brand signature. Primary buttons, interactive hover states, active navigation items. Used sparingly — its rarity communicates its importance.

### Secondary
- **Ember Orange** (#ff6600 / oklch(65% 0.22 45)): Action and energy. Secondary buttons (border variant), warning indicators, follow-up states. Reads as urgency without alarm.

### Neutral (Dark Mode)
M3-inspired tonal elevation scale. Surfaces get lighter (not darker) as they elevate toward the user.

| Level | Token | Value | Use |
|-------|-------|-------|-----|
| 0 | `surface-abyss` | #020617 | Body background |
| 1 | `surface-dim` | #0f1120 | Sidebar, secondary panels |
| 2 | `surface` | #1a1d2e | Cards, dropdowns, elevated panels |
| 3 | `surface-bright` | #252839 | Modals, dialogs, pickers |
| 4 | `surface-high` | #2f3248 | Tooltips, menus, toasts |

### Neutral (Light Mode)
- **White** (#ffffff): Primary surface background
- **Smoke** (#f1f5f9): Card backgrounds, secondary surfaces
- **Gray-100** (#e2e8f0): Borders, dividers
- **Slate-800** (#1e293b): Primary text
- **Slate-500** (#64748b): Muted text, placeholders

### Semantic Status
- **Emerald** (#10b981): Completed, delivered, active success states
- **Amber** (#f59e0b): Sending, pending attention, in-progress warning states
- **Red** (#ef4444): Error, revision needed, blocked states
- **Blue** (#3b82f6): In review, information, processing states

### Light mode token mappings
- `--brand-surface`: #ffffff
- `--brand-text`: #0f172a
- `--brand-muted`: #64748b
- `--brand-border`: rgba(0, 0, 0, 0.08)

### Named Rules
**The One Voice Rule.** Royal Purple is the brand's single signature color. It appears on ≤15% of any given screen. When a second accent is needed, Ember Orange speaks only about action and urgency — never decoration. If both appear, orange leads the action and purple owns the identity.

## 3. Typography

**Display & Body:** Cairo (sans-serif, 700–900 weights)
**Labels & Data:** Inter (Latin numbers, short UI labels)

Cairo's wide glyph set handles Arabic RTL text at every size. Inter steps in where monospaced number alignment matters. One typeface family (Cairo) at three weights (700, 800, 900) creates the entire hierarchy above body text — no font switching, no variable-axis tricks.

### Hierarchy
- **Display** (900 Black, clamp(1.75rem, 4vw, 3rem), 1.1, -0.02em): Dashboard headings, welcome hero. `text-wrap: balance`.
- **Headline** (800 ExtraBold, clamp(1.25rem, 3vw, 1.875rem), 1.2): Section titles, page headers. `text-wrap: balance`.
- **Title** (700 Bold, clamp(1rem, 2vw, 1.25rem), 1.3): Card titles, sidebar groups, modal headers.
- **Body** (400 Regular, 0.875rem, 1.6): Content, descriptions, table cells. Max 75ch. `text-wrap: pretty`.
- **Label** (700 Bold, 0.75rem, 1.3, 0.05em tracking, uppercase): Badges, table headers, metadata. Max 4 words in uppercase.
- **Caption** (400 Regular, 0.75rem, 1.4): Helper text, timestamps, secondary metadata.

### Named Rules
**The Weight-Only Scale Rule.** Three weights (700 Bold, 800 ExtraBold, 900 Black) cover the entire hierarchy. No italics, no condensed, no variable-axis tricks. Size and weight contrast do all the work.

## 4. Elevation & Depth

M3-inspired tonal elevation system. Surfaces at rest sit flat on their layer; depth signals hierarchy and interaction.

### The Elevation Scale

Each level is a tonal surface overlay — lighter (in dark mode) or darker (in light mode) than the level below. No box-shadows at rest.

- **Level 0 — Body** (`surface-abyss`): The background plane. Content sits on this.
- **Level 1 — Sidebar** (`surface-dim`): Navigation, secondary panels. One step above body.
- **Level 2 — Card** (`surface`): Cards, dropdowns, elevated panels. The default interactive surface.
- **Level 3 — Modal** (`surface-bright`): Modals, dialogs, pickers. Content that demands focus.
- **Level 4 — Tooltip** (`surface-high`): Tooltips, toasts, floating menus. The highest surface.

### Interaction States

- **Rest:** No shadow, no border decoration. Surface color alone distinguishes the element.
- **Hover:** The surface shifts one elevation level brighter. On cards, optional `translateY(-1px)`.
- **Active / Focus:** Purple ring (2px, rgba(68, 17, 136, 0.4)). No glow.
- **Disabled:** Reduced opacity (50%). No interaction states.

### The Flat-By-Default Rule

A surface at rest has no shadow. Elevation communicates state: hover, active, selected, or modal. If two elements are on the same level, neither casts a shadow.

## 5. Rounded Corners

A rational corner scale — tighter than the previous version, inspired by M3's measured approach.

| Radius | Token | Use |
|--------|-------|-----|
| 6px | `rounded-sm` | Buttons, inputs, chips, badges |
| 10px | `rounded-md` | Dropdowns, menus, small containers |
| 14px | `rounded-lg` | Cards, panels, large containers |
| 16px | `rounded-xl` | Modals, dialogs (max corner radius) |
| 9999px | `rounded-full` | Avatars, status dots, tags |

### Named Rules
**The Ceiling Rule.** 16px is the maximum corner radius for any container. Full-pill (9999px) is for avatars, tags, and status indicators only — never for cards, sections, or inputs.

## 6. Components

### Buttons
- **Shape:** 6px radius (rounded-sm). Clean and purposeful.
- **Primary:** Royal Purple bg, white text, 10px 18px padding, 700 Bold. Hover: darker purple (#350d6b). No shadow at rest.
- **Secondary:** Transparent bg, white text, 1px rgba(255,255,255,0.12) border. Hover: subtle white bg tint (rgba(255,255,255,0.06)), border strengthens to 0.2.
- **Ghost:** Transparent, muted text (--brand-muted). Hover: subtle surface tint. No border.
- **Disabled:** Opacity 0.5, no interaction. No shadow.
- **Focus:** Purple ring (2px, rgba(68, 17, 136, 0.4)). Transition: 200ms ease.

### Badges / Chips
- **Shape:** 9999px (rounded-full), 2px 8px padding.
- **Text:** 0.6875rem (11px), 700 Bold, uppercase, ring-1 ring-inset.
- **Colors:** Status-mapped via semantic palette (Emerald, Amber, Red, Blue, Royal Purple).
- **Behavior:** Static labels. No hover state.

### Cards / Containers
- **Corner:** 14px (rounded-lg).
- **Background:** Level 2 surface (--surface / #1a1d2e in dark mode).
- **Shadow:** None at rest. On hover: surface shifts to Level 3 (--surface-bright) + optional translateY(-1px). Transition: 250ms ease.
- **Internal padding:** 24px (spacing-lg).
- **No border at rest.** Cards are distinguished by elevation alone.

### Inputs / Fields
- **Shape:** 6px radius, 1px solid border (rgba(255,255,255,0.1) in dark mode).
- **Background:** Level 2 surface (--surface).
- **Text:** Cairo Regular, 0.875rem.
- **Focus:** Purple ring (2px). No glow. Transition: 200ms.
- **Placeholder:** --brand-muted (#94a3b8) at 4.5:1 contrast.
- **Error:** Red border (#ef4444) + subtle red bg tint. Error message below in 0.75rem red text.
- **Disabled:** Reduced opacity, no focus ring.

### Navigation (Filament Sidebar)
- **Style:** Collapsible on desktop. Level 1 surface (--surface-dim).
- **Active Item:** Royal Purple accent indicator (right border in RTL). Text in Royal Purple.
- **Hover:** Level 2 surface tint. Text shifts toward Royal Purple.
- **Groups:** Separated by muted label-style headers (0.6875rem, uppercase, --brand-muted).

## 7. Spacing

A 6-step scale with a 1.5× ratio between steps.

| Token | Value | Use |
|-------|-------|-----|
| xs | 4px | Inset padding, icon gaps |
| sm | 8px | Stack between compact items |
| md | 16px | Default stack, card interior padding |
| lg | 24px | Section spacing, card groups |
| xl | 32px | Major section breaks |
| xxl | 48px | Page-level spacing, hero areas |

## 8. Motion

- **Duration:** 200–250ms for micro-interactions (hover, focus, transitions). 300ms for surface changes (modal open/close, panel reveal).
- **Easing:** `cubic-bezier(0.2, 0, 0, 1)` — a sharp ease-out inspired by M3's standard curve. No bounce, no elastic.
- **Reduced motion:** Every animation gated behind `@media (prefers-reduced-motion: reduce)`. Falls back to instant (0ms) or crossfade (100ms opacity-only).
- **Surface transitions:** Opacity + translateY for modal/dialog entrances. Never animate layout properties (width, height, top, left).

## 9. Light Mode Adaptations

Light mode inverts the elevation scale: surfaces get darker as they elevate.

- **Level 0:** #f8fafc (body background)
- **Level 1:** #f1f5f9 (sidebar)
- **Level 2:** #ffffff (cards, panels)
- **Level 3:** #ffffff + subtle shadow (modals)
- **Borders:** rgba(0, 0, 0, 0.08)
- **Primary text:** #0f172a
- **Muted text:** #64748b

## 10. Do's and Don'ts

### Do:
- **Do** use Royal Purple sparingly — it's the brand signature, not a default accent. ≤15% per screen.
- **Do** use Ember Orange for actionable elements: secondary buttons, warnings, follow-up indicators.
- **Do** let elevation do the talking. Cards are distinguished by surface level, not borders or shadows.
- **Do** use the full weight range of Cairo (700–900) to create hierarchy without font switching.
- **Do** respect the semantic status colors: Emerald for done, Amber for pending, Red for blocking, Blue for reviewing.
- **Do** keep body text ≥4.5:1 contrast ratio against its background.
- **Do** cap headings at `clamp(1.75rem, 4vw, 3rem)` max. No oversized hero text.

### Don't:
- **Don't** look like a generic admin panel. No flat gray-scale palettes, no predictable sidebar-heavy layouts.
- **Don't** use both a `border` and a `box-shadow` on the same element at rest. Elevation is carried by surface tone, not shadows.
- **Don't** apply side-stripe colored borders (border-left/right > 1px) as accent on cards.
- **Don't** use gradient text (background-clip: text). Emphasis through weight and size.
- **Don't** over-round containers. Cards: 14px max. Buttons: 6px. Full-pill only for chips/badges.
- **Don't** apply glassmorphism. No blur backdrops, no translucent panels. Solid surfaces only.
- **Don't** use all-caps body copy. Reserve uppercase for short labels (≤4 words) and badges.
- **Don't** create numbered section markers (01 / 02 / 03) as default scaffolding.
- **Don't** use box-shadows at rest. The elevation tone IS the shadow.
