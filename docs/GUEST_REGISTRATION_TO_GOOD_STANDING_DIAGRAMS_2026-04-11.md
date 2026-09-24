> **SUPERSEDED** — This document describes the retired guest-table era. The canonical lifecycle is `parish_affiliation.membership_status` (`visitor → regular_attender → member`); see `docs/VISITOR_LIFECYCLE.md` and `docs/FOLLOWUP_WORKFLOW_SYSTEM.md`.
>
> ---
>
# Guest Registration to Good Standing - Diagram Pack (2026-04-11)

```mermaid
flowchart LR
    A[Guest Intake] --> B[Follow-up Tasks]
    B --> C{Conversion Path}
    C -->|A: Link to Existing User| D[Guest Conversion Link]
    C -->|B: Create New User| E[Member Account Creation]
    D --> F[Member Parish Affiliation Active]
    E --> F
    F --> G[Standing Refresh]
    G --> H[member_standing_summary]
    H --> I[Dashboard and Reports KPI]
```

```mermaid
sequenceDiagram
    autonumber
    actor Staff as Pastor/Parish Admin
    participant Guests as GuestsController
    participant FollowUp as FollowUpController
    participant Conv as GuestConversionsController
    participant Members as MembersController
    participant Stand as StandingController/Command
    participant DB as Database

    Staff->>Guests: POST /guests/store
    Guests->>DB: INSERT guests
    Guests->>DB: INSERT follow_up_tasks

    Staff->>FollowUp: POST /followup/update-status/{id}
    FollowUp->>DB: UPDATE follow_up_tasks.status

    alt Link to existing member
        Staff->>Conv: POST /guest-conversions/convert/{guestId}
        Conv->>DB: UPSERT member_parish_affiliations
        Conv->>DB: UPDATE guests.converted_to_member_at + converted_user_id + converted_by_user_id
    else Create new member
        Staff->>Guests: GET /guests/convertToMember/{guestId}
        Guests->>DB: UPDATE guests.followed_up = 1
        Staff->>Members: POST /members/store
        Members->>DB: INSERT users + auth_identities + auth_groups_users
        Members->>DB: INSERT profile
        Members->>DB: INSERT member_parish_affiliations
    end

    Staff->>Stand: POST /standing/refresh or CLI standing:refresh
    Stand->>DB: DELETE member_standing_summary for year
    Stand->>DB: INSERT recomputed member_standing_summary rows
```

```mermaid
flowchart TB
    subgraph Conversion Decision
        A[Guest Record Exists] --> B{Existing User Selected?}
        B -->|Yes| C[Link Guest to User]
        B -->|No| D[Create New User Account]
    end

    C --> E{Affiliation Exists and Active?}
    E -->|No| F[attachToParish]
    E -->|Yes| G[Keep Existing]

    D --> H[Create User and Identity]
    H --> I[Assign Group]
    I --> J[Create Profile]
    J --> F

    F --> K[Guest marked converted]
    G --> K
```

```mermaid
stateDiagram-v2
    [*] --> Registered
    Registered --> FollowupPending
    FollowupPending --> FollowupCompleted
    FollowupPending --> Converted
    FollowupCompleted --> Converted
    Converted --> LinkedToMember
    LinkedToMember --> InScopeAffiliated
    InScopeAffiliated --> SummaryRecomputed
    SummaryRecomputed --> GoodStandingTrue: rule satisfied
    SummaryRecomputed --> GoodStandingFalse: rule not satisfied
```

```mermaid
flowchart LR
    U[users.active] --> S[member_standing_summary]
    A[attendance + services] --> S
    P[annual_pledges] --> S
    D[member_discipline] --> S
    S --> J{Scope Join}
    M[member_parish_affiliations.active + membership_status] --> J
    J --> K[Parish Good Standing Count]
```

```mermaid
flowchart TB
    subgraph Tables by Phase
        T1[Guest Intake\nguests\nfollow_up_tasks]
        T2[Conversion A\nguests\nmember_parish_affiliations]
        T3[Conversion B\nusers\nauth_identities\nauth_groups_users\nprofiles\nmember_parish_affiliations]
        T4[Standing Compute\nmember_standing_summary\nusers\nattendance\nservices\nannual_pledges\nmember_discipline]
        T5[KPI Read\nmember_standing_summary + member_parish_affiliations]
    end

    T1 --> T2
    T1 --> T3
    T2 --> T4
    T3 --> T4
    T4 --> T5
```

```mermaid
flowchart LR
    R1[Rule 1: User Active] --> GS[Good Standing]
    R2[Rule 2: Attendance >= 3] --> GS
    R3[Rule 3: Communion >= 3] --> GS
    R4[Rule 4: Has Pledge in Year] --> GS
    R5[Rule 5: Not Under Discipline] --> GS
```

```mermaid
flowchart TB
    X1[Data Entry Controls\nPermissions + Parish Scope] --> X2[Conversion Integrity\nAffiliation enforced]
    X2 --> X3[Standing Refresh Controls\nScheduled + Manual]
    X3 --> X4[KPI Reliability\nSummary + Scope Joins]
    X4 --> X5[Leadership Visibility\nDashboard + Reports + API]
```
