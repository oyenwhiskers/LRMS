# LRMS Refinement v1.0

## Purpose

This document separates the next LRMS improvements into practical phases before implementation starts.

The refinement is driven by two business goals:

- Track how many cases or files are registered each day.
- Track who borrows and returns physical files.

The main gap in the current system is that file registration and physical storage are treated as the same event. In reality, they are different:

1. A file can be registered in LRMS.
2. The physical file may still be waiting for the Person in Charge (PIC) to receive and place it in the file room.

LRMS should reflect that operational reality.

## Current State

Today the system behaves like this:

- A file is registered.
- A shelf is assigned immediately.
- The system treats the file as already stored and ready to borrow.
- A returned file becomes available again immediately after return is recorded.

This makes reporting inaccurate for physical custody because LRMS cannot distinguish between:

- registered but not yet stored
- returned but not yet re-shelved
- actually inside the file room and ready to borrow

## Target Operating Model

### Lifecycle Design

The recommended operational statuses are:

1. `Pending Initial Storage`
2. `Stored`
3. `Borrowed`
4. `Returned Pending Storage`
5. `Missing`
6. `Archived`

Important clarification:

- `Registered` should be treated as an event, not as a long-lived status.
- When a file is created in LRMS, the system records a `Registered` event and immediately places the file into `Pending Initial Storage`.

This removes ambiguity between "registered" and "pending initial storage" while still preserving the registration moment for reporting and audit.

### Status Meanings

- `Pending Initial Storage`: the file exists in LRMS but PIC has not yet confirmed physical intake into the file room.
- `Stored`: the file has been scanned and confirmed by PIC as physically in storage and is available for borrowing.
- `Borrowed`: the file has left the file room and is currently with a staff member.
- `Returned Pending Storage`: the file has been handed back from the borrower, but PIC has not yet re-shelved it.
- `Missing`: the file cannot currently be physically located.
- `Archived`: the file is retired from active circulation.

### Core Business Rules

- A file can only be borrowed when it is `Stored`.
- A returned file must not become immediately borrowable again.
- The PIC must scan the file back into storage before it returns to `Stored`.
- Every custody-changing step should create an audit event.
- Registration and physical storage must always be reported separately.

### Custody Ownership

To avoid ambiguity, LRMS should reflect operational ownership at each stage.

- `Pending Initial Storage`: operationally waiting for PIC intake.
- `Stored`: in storeroom custody.
- `Borrowed`: in borrower custody.
- `Returned Pending Storage`: physically returned from borrower, but operationally waiting for PIC re-shelving.
- `Missing`: custody unknown.

Recommended real-world rule:

- Once a return is recorded, the file should be treated as received back from the borrower and placed into a PIC-controlled re-shelving queue.
- If the organization wants stronger chain-of-custody later, LRMS can be extended to record the exact receiving staff member at return intake.

## Status Transition Matrix

The following transitions should be allowed in normal operations.

### Normal transitions

- `Registered event -> Pending Initial Storage`
- `Pending Initial Storage -> Stored`
- `Stored -> Borrowed`
- `Borrowed -> Returned Pending Storage`
- `Returned Pending Storage -> Stored`
- `Stored -> Missing`
- `Borrowed -> Missing`
- `Returned Pending Storage -> Missing`
- `Missing -> Stored`
- `Pending Initial Storage -> Archived`
- `Stored -> Archived`

### Disallowed transitions by default

- `Pending Initial Storage -> Borrowed`
- `Returned Pending Storage -> Borrowed`
- `Borrowed -> Stored` without a return event
- `Archived -> any active status`

### Correction policy

The system should avoid silent edits to custody history.

- If a wrong scan or wrong shelf assignment happens before confirmation, the user may correct it in the current workflow.
- If a completed operational event was recorded incorrectly, LRMS should prefer a compensating event or an administrator correction flow rather than deleting history.
- Movement history should remain understandable and auditable.

## Product Direction

### UI Concept

LRMS should move toward an enterprise operations interface with minimalist discipline.

That means:

- enterprise in structure
- minimalist in presentation
- queue-first workflows
- scan-first actions for PIC
- clear status visibility
- less decorative styling and less visual noise

The product should feel:

- simple
- reliable
- operational

It should not feel:

- overly luxurious
- brand-heavy
- generic CRUD-only

### UX Principles

- Separate registration from physical custody.
- Show users what needs action now.
- Design around queues, not only modules.
- Keep scanning workflows short and high-confidence.
- Use file timelines and status labels to explain custody clearly.
- Split analytics from operations on the dashboard.

## Dashboard Direction

The dashboard should be divided into two areas.

### Registry KPI

Focus on file registration trends and filing workload.

Terminology rule:

- Use `file` consistently in LRMS metrics unless the client confirms that one case always equals one physical file.
- If the client prefers business-facing language, the UI may show `case file` instead of `file`, but the meaning must stay consistent.

Recommended metrics:

- Files registered today
- Files registered this week
- Pending initial storage
- Files stored today

Recommended chart:

- A line chart for registration trend
- Default view: last 30 days
- Optional groupings: daily, weekly, monthly

Date definition:

- Registration trend charts must use the file registration timestamp, not the storage-confirmed timestamp.
- Storage workload metrics must use the storage confirmation timestamp.

This answers:

- Are file registrations increasing or dropping?
- Are there spikes on certain days?
- Is filing work keeping up with registration?

### File Movement KPI

Focus on physical custody and operational activity.

Recommended metrics:

- Files borrowed today
- Files returned today
- Returned pending storage
- Files currently out
- Missing files

This answers:

- What is outside the file room?
- What has come back but is not re-shelved yet?
- What needs PIC attention right now?

## Recommended Screens

### 1. Dashboard

Purpose:

- Show high-level numbers
- Show registration trend
- Show operational queues

Recommended blocks:

- Registry KPI cards
- Registration trend chart
- Pending initial storage queue
- Returned pending storage queue
- Recent file activity

### 2. File Registration

Purpose:

- Create the file identity and business record

Recommended behavior:

- Save file record without assuming physical storage is complete
- Generate QR and label
- Move the file into a pending queue for PIC
- Record registration timestamp for analytics

### 3. PIC Storage Intake

Purpose:

- Confirm physical receipt into the file room

Recommended behavior:

- PIC scans file QR
- System shows file details
- PIC confirms or assigns shelf
- File status becomes `Stored`
- System records who performed the storage confirmation and when

This should be one of the cleanest screens in the system.

### 4. Borrow

Purpose:

- Record custody handoff from file room to staff

Recommended behavior:

- Only `Stored` files can be borrowed
- Show clear confirmation of borrower and scanned files
- Reject files that are pending storage, missing, or archived

### 5. Return

Purpose:

- Record that the physical file has come back from the borrower

Recommended behavior:

- File becomes `Returned Pending Storage`
- System tells PIC what still needs re-shelving
- System records the return timestamp separately from the re-storage timestamp

### 6. Re-shelving Queue

Purpose:

- Let PIC clear returned files back into storage

Recommended behavior:

- Scan or select returned files
- Confirm shelf placement
- File status becomes `Stored`
- Clear the file from the re-shelving queue

### 7. File Detail Timeline

Purpose:

- Make the custody story easy to understand

Recommended timeline events:

- Registered
- Stored
- Borrowed
- Returned
- Re-stored
- Missing
- Found

## Phase Plan

### Phase 1: Business Rules and Data Model

Goal:

- Align LRMS with the real-world file lifecycle

Scope:

- Define the final status model
- Treat registration as an event and not a long-lived status
- Separate registration from storage confirmation
- Decide whether shelf is planned at registration or confirmed at storage
- Define custody ownership at each stage
- Define which actions are allowed for each status
- Confirm reporting definitions
- Define migration rules for existing records

Outputs:

- agreed business rules
- agreed lifecycle
- agreed KPI definitions
- agreed transition matrix
- agreed migration approach

Notes:

- This phase should be approved before code changes begin.

### Phase 2: Backend Workflow Refinement

Goal:

- Support the new lifecycle safely

Scope:

- Add the new statuses
- Update validation and transitions
- Prevent borrow unless file is physically stored
- Change return flow to create a pending re-storage state
- Add storage intake and re-shelving actions
- Preserve audit history clearly
- Support event timestamps needed for reporting

Outputs:

- updated status logic
- updated movement rules
- new storage confirmation workflow

### Phase 3: Dashboard and Reporting

Goal:

- Make the two business purposes visible

Scope:

- Split dashboard into Registry and Movement sections
- Add registration KPI cards
- Add registration trend chart
- Define chart grouping and date basis clearly
- Add pending storage queues
- Update movement KPI cards

Outputs:

- clearer dashboard
- trend visibility
- operational visibility

### Phase 4: UI/UX Simplification

Goal:

- Make LRMS feel more enterprise-grade and easier to operate

Scope:

- Reduce visual inconsistency
- Simplify tables and action areas
- Improve status presentation
- Improve scanning screens
- Reduce unnecessary decorative styling
- Improve mobile and tablet usability for operational screens
- Define a consistent status color and label system
- Make queue screens first-class views for PIC users

Outputs:

- cleaner design system
- better action-first workflows
- more consistent UI

### Phase 5: Operational Hardening

Goal:

- Reduce human error and improve trust

Scope:

- Add clearer confirmations and inline validation
- Add queue empty states and exception states
- Improve timeline and audit clarity
- Review permissions for PIC-specific workflows
- Prepare rollout handling for existing live records
- Update guides and UAT

Outputs:

- safer operations
- clearer support documentation
- smoother rollout readiness

## Priority Recommendation

Recommended order:

1. Finalize business rules
2. Implement lifecycle and status changes
3. Add PIC storage workflows
4. Update dashboard and charting
5. Refine UI styling and workflow polish

Reason:

- If UI is redesigned before the lifecycle is corrected, the system will look better but still reflect the wrong operational model.

## Existing Data Migration

The refinement should explicitly plan for current live records created under the old model.

Recommended migration assumption:

- Existing active files that are currently treated as available should be migrated to `Stored`.
- Existing borrowed files should remain `Borrowed`.
- Existing missing files should remain `Missing`.

Recommended rollout safeguard:

- After migration, the team should run a physical reconciliation sample to confirm that the new system state matches real storeroom conditions.

If the client wants stricter accuracy:

- A one-time reconciliation queue can be introduced so selected existing files must be re-confirmed by PIC before being considered fully trusted under the new model.

## KPI Definitions

To avoid reporting drift, the KPIs should be defined clearly.

- `Files registered today`: count of files created in LRMS on the selected date.
- `Pending initial storage`: count of files currently in `Pending Initial Storage`.
- `Files stored today`: count of files whose storage intake was confirmed on the selected date.
- `Files borrowed today`: count of borrow events recorded on the selected date.
- `Files returned today`: count of return events recorded on the selected date.
- `Returned pending storage`: count of files currently in `Returned Pending Storage`.
- `Files currently out`: count of files currently in `Borrowed`.
- `Missing files`: count of files currently in `Missing`.

## UI Guardrails

To keep the redesign disciplined, the UI should follow these rules.

- The primary dashboard should prioritize status, queues, and actions over decorative branding.
- Status colors must be stable and reused consistently across cards, tables, queues, and timelines.
- PIC users should be able to reach storage intake and re-shelving in one click from navigation.
- Scan screens should minimize nonessential form fields and reading load.
- Tables should support quick scanning with fewer columns and clearer primary actions.
- Trend charts belong in the registry section, not mixed into borrow or return activity.

## Open Decisions

The following items should be confirmed before implementation:

1. Should registration require a shelf immediately, or should shelf be confirmed only when PIC stores the file?
2. Should returned files always go back through PIC re-storage before they are borrowable again?
3. Should PIC have a dedicated permission and queue?
4. Should the return workflow capture the receiving staff member separately from the borrower and processor?
5. What exact label should be used for the new statuses in the user interface?
6. Which registration trend views are required on day one: daily only, or daily plus weekly and monthly?

## Recommended Next Step

Use this document as the approval baseline for `v1.0 refinement`, then turn Phase 1 into a short business-rules specification before starting code changes.
