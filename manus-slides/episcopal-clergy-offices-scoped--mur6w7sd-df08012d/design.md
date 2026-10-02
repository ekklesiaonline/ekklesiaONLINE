## Design Concept
This deck operates as a technical strategy and migration architecture proposal, requiring the authoritative, high-density posture of a top-tier management consultancy (e.g., McKinsey). The visual anchor is Massimo Vignelli’s strict grid discipline combined with the rigorous typography of institutional research reports. The color story avoids the cliché of bright "church purple" or "heavenly gold" and generic "tech blue." Instead, it is conservative and steady: a mineral off-white background holds high-contrast, near-black text, anchored by a single primary hue—a deep Canonical Crimson—that signals institutional authority without becoming decorative. The typography relies entirely on a sharp serif for authoritative, complete-sentence Action Titles and a dense, legible sans-serif for heavy technical evidence. 

## Visual Language
- **Imagery**: Purely informational/systemic. No decorative church stock photos, stained glass, or abstract tech-glow backgrounds.
- **Containers**: Whitespace-first with structural wireframing. Data and technical content sit on pure white blocks over the mineral background, separated by 1px solid gray rules. No shadows, no rounded corners, no tinted cards.
- **Icons**: None. Technical capabilities and states are indicated via text labels or simple typographic glyphs.
- **Dataviz**: High-density matrix tables with dark headers and alternating light rows. Tree-structures and flow logic use thin-lined boxes. Chart series rely on the primary crimson and stepping grays.
- **Motif**: A 4px solid crimson (`#7A1C29`) tracking line spanning the exact width of the top margin (below the slide edge, above the Action Title) to anchor the page's authority.

## Layout Directives
- **Global**: 48px margins on all sides. Strict top-down alignment. Every page must feature a footer at the bottom margin containing a page number (right) and data source/reference link (left).
- **Cover**: Left-aligned heavy serif title, bottom-anchored subtitle/author, massive whitespace utilization above the motif line.
- **Table of Contents**: A structured metadata table detailing section, core insight, and target outcome, never a plain bullet list.
- **Section Dividers**: Minimalist. A massive serif numeral paired with a single summary sentence on a pure white background.
- **Closing**: Echoes the cover with a concise call-to-action or sign-off protocol.
- **Slide Map**:

| id | pattern | image | density |
| :--- | :--- | :--- | :--- |
| cover | title_hero | none | low |
| four_axes | 2x2_grid_comparison | none | medium |
| scope | structural_tree_left_text_right | none | high |
| offices | comparison_matrix_table | none | high |
| decision | step_flow_logic | none | medium |
| current | two_column_technical_audit | none | high |
| schema | entity_relationship_diagram | none | high |
| permission | dense_capabilities_matrix | none | high |
| rollout | phased_timeline_table | none | high |

## Design Tokens

### Colors
| token | hex | usage |
| :--- | :--- | :--- |
| primary | #7A1C29 | Motif rule, focal chart series, highlighted active states |
| background | #F8F9FA | Global slide background |
| surface | #FFFFFF | Tables, architecture boxes, diagram backgrounds |
| text-main | #1F2124 | All primary reading text, headings, table data |
| text-muted | #5A5F65 | Source notes, captions, secondary chart labels |
| border | #D1D5DB | Table borders, structural dividers, box outlines |
| chart-gray-1 | #868E96 | Secondary data series, inactive capabilities |
| chart-gray-2 | #ADB5BD | Tertiary data series, baseline infrastructure |

### Typography
| role | family | size (px) | applies_to |
| :--- | :--- | :--- | :--- |
| display | PT Serif | 48 | Cover title, major section divider numerals |
| heading | PT Serif | 28 | Action Titles (slide headers) |
| body | Inter | 18 | Main narrative text, bullet points |
| table-text | Inter | 16 | Matrix contents, diagram labels |
| mono | JetBrains Mono | 16 | Database schema names, API roles, exact system states |
| caption | Inter | 12 | Source citations, footers, page numbers |

## Guardrails
- **No decorative coloring**: Color is reserved strictly for the primary motif and data highlighting; do not use color to make text "look nice."
- **No naked bullet slides**: Every text block must carry an explicit conclusion or "so what" in bold.
- **No empty canvas**: Content pages must utilize 80%+ of the layout grid with tables, flows, and structural text.
- **Strict Size Floors**: Never reduce body text below 18px or mono/table text below 16px to force-fit content; restructure the layout instead.
