## Design Concept
This deck serves as a technical architecture and security review, requiring a posture of absolute rigor, clarity, and objective truth. The design anchors heavily on the IBM Carbon Design System for its enterprise sobriety and the Swiss International Style for its uncompromising grid-based layouts and objective typography. We are rejecting the standard "cybersecurity" cliches (neon green/blue gradients on black, generic padlock icons) in favor of a "technical manual" aesthetic. The color story is conservative and mineral-toned: the background is a cool, papery off-white, grounding the deck in physical documentation rather than a virtual terminal. The primary color is a deep, near-black graphite, accented by a stark signal-amber used exclusively to denote boundary enforcement and security gaps. The typographic voice is engineered and precise, utilizing a neo-grotesque alongside a monospaced companion for exact rule definitions.

## Visual Language
- **Imagery**: Minimal to none. Visuals should consist entirely of architectural diagrams, boundary maps, and flowcharts constructed from native CSS shapes and lines.
- **Containers**: Whitespace-first with structural containment. Use rigid 1px solid borders (`#C6C6C6`) for cards and code/rule blocks. No drop shadows.
- **Icons**: Minimalist, sharp, 1px outline icons (Font Awesome). Use exclusively for system nodes (servers, databases, user roles) and never as decorative bullet replacements.
- **Dataviz**: Tables must be flat, with a 1px solid bottom border on the header row and alternating very subtle gray tints for rows. Charts (if any) use graphite for base systems, amber for active boundaries/alerts, and a mid-gray for passive components.
- **Motif**: A technical boundary marker—a subtle 1px `+` (crosshair) positioned in the absolute top-right and bottom-left corners (inset 24px) of every slide, visually reinforcing the concept of rigid "layered boundaries".

## Layout Directives
- **Global**: 48px margins on all sides. Page numbers in monospaced font at the bottom right. Left-aligned typography is strictly enforced; no center alignment for text blocks.
- **Cover**: High-contrast asymmetrical layout. Massive title text spanning the left two-thirds, with the motif scaled up as a structural graphic on the right.
- **Table of Contents**: Formatted as a technical index. Monospaced numerals aligned right against the section titles on the left.
- **Section Dividers**: Stark and minimal. A 1px horizontal line slicing the screen in half, with the section title resting precisely on the line.
- **Closing**: Echoes the cover but reverses the visual weight, emphasizing a solitary, confident target state statement.

- **Slide Map**:

| id | pattern | image | density |
| :--- | :--- | :--- | :--- |
| cover | Hero typography left, tech-crosshair graphic right | none | low |
| model | Horizontal flowchart mapping request journey | none | medium |
| hierarchy | Two-column: mono-spaced rules left, plain-text explanations right | none | high |
| scopes | Quadrant grid detailing geographic vs operational bounds | none | medium |
| audit_flow | Step-by-step vertical sequence diagram | none | medium |
| gaps | Comparative split-screen: gap vs addressed enforcement | none | high |
| recommendations | Checklist format inside rigid 1px bordered cards | none | medium |
| closing | Minimalist statement centered on the horizontal axis | none | low |

## Design Tokens

### Colors
| token | hex | usage |
| :--- | :--- | :--- |
| bg-page | `#F4F5F6` | Global slide background |
| bg-block | `#E0E3E5` | Background for code blocks, rules, and table headers |
| text-primary | `#161616` | Main headings, body text, primary data labels |
| text-secondary | `#525252` | Captions, metadata, secondary table text |
| line-boundary | `#C6C6C6` | 1px structural borders, diagrams, motif |
| accent-signal | `#D94E18` | Security boundaries, gaps, alerts, key node highlights |
| viz-neutral | `#8D8D8D` | Passive or unauthenticated path elements in diagrams |

### Text Roles
| role | family | size (px) | applies_to |
| :--- | :--- | :--- | :--- |
| display | IBM Plex Sans | 56 | Cover title, closing target state |
| heading | IBM Plex Sans | 36 | Slide titles, section dividers |
| body | IBM Plex Sans | 20 | Standard explanations, list items |
| technical | IBM Plex Mono | 18 | AdminHierarchyGuard rules, code snippets, node IDs |
| caption | IBM Plex Mono | 14 | Page numbers, source notes, chart axes |

## Guardrails
- **Forbidden**: Do not use "hacker/cyber" themes (dark backgrounds with neon text).
- **Forbidden**: Do not use generic security icons (padlocks, shields, keys).
- **Forbidden**: Do not use drop shadows, gradients, or rounded corners (border-radius must be 0).
- **Forbidden**: Do not center-align multi-line text blocks.
- **Size floors**: Body text must not fall below 18px; technical monospaced text minimum is 16px; captions minimum is 14px.
