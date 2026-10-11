# Clergy, deanery and ecclesial authorization

This document was split into two focused guides:

- **[Ordinary and episcopal authorization](ORDINARY_AUTHORIZATION.md)** — shared schema/boundary table, episcopal office/permission matrix, ordinary bootstrap (`installOrdinary()`), office approval (`approveOffice()`), delegation, ending the office, canonical status/standing, the `canon_to_ordinary` strict "canon proposes, ordinary approves" workflow, and controlled rollout.
- **[Deanery authorization and scoping](DEANERY_AUTHORIZATION.md)** — deanery creation and `deanery_parish_membership`, the regional/deanery dean office and its current-member-parish scoping, route gating, deanery surfaces, and the `leadership_position.deanery_id` missing-FK note.
