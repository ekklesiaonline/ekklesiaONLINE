# Board Deck: Guest to Good Standing (5 Slides)

## Slide 1 - Executive Lifecycle

```mermaid
flowchart LR
    A[Guest Intake] --> B[Follow-up]
    B --> C[Conversion]
    C --> D[Member Affiliation Active]
    D --> E[Standing Recompute]
    E --> F[Good Standing KPI]
```

## Slide 2 - Conversion Decision Paths

```mermaid
flowchart TB
    A[Guest Candidate] --> B{Existing Member Account?}
    B -->|Yes| C[Link Guest to Existing User]
    B -->|No| D[Create New User and Profile]
    C --> E[Ensure Active Church Affiliation]
    D --> E
    E --> F[Mark Guest as Converted]
```

## Slide 3 - Data Tables by Phase

```mermaid
flowchart LR
    T1[Guest Intake\nguests\nfollow_up_tasks] --> T2[Conversion\nguests\nmember_church_affiliations]
    T2 --> T3[Member Creation\nusers\nauth_identities\nauth_groups_users\nprofiles]
    T3 --> T4[Standing Compute\nmember_standing_summary\nattendance\nservices\nannual_pledges\nmember_discipline]
    T4 --> T5[Reporting and Dashboard\nmember_standing_summary + affiliation joins]
```

## Slide 4 - Good Standing Rule

```mermaid
flowchart LR
    R1[User Active] --> G[Good Standing]
    R2[Attendance >= 3] --> G
    R3[Communion >= 3] --> G
    R4[Pledged in Year] --> G
    R5[Not Under Discipline] --> G
```

## Slide 5 - Governance and KPI Assurance

```mermaid
flowchart TB
    A[Permission and Church Scope Controls] --> B[Conversion Integrity Checks]
    B --> C[Scheduled and On-Demand standing:refresh]
    C --> D[Summary-Based KPI Reads]
    D --> E[Board Reporting: Dashboard, Reports, API]
```
