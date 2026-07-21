# LRMS User Acceptance Test

Record the tester, date, browser/device, scanner, printer, result and evidence for every case. A launch blocker is any failure that can lose, misidentify, expose or incorrectly transfer a physical file.

## Administrator

1. Create positions for Records Clerk, Lawyer and Management.
2. Assign only the minimum action permissions required by each position.
3. Create an active staff record and print its employee QR.
4. Submit a self-registration using that staff number.
5. Confirm a pending account cannot enter LRMS.
6. Approve the registration, correcting its requested position.
7. Confirm the approved account receives exactly that position’s permissions.
8. Deactivate the position and confirm access is removed without deleting history.

## Storage and file identity

1. Create a room, cabinet and shelf.
2. Confirm a parent cannot be deactivated while active children exist.
3. Register a file with reference, purchaser, property, person in charge and location.
4. Confirm a unique `FILE000001`-style identity and opaque QR are generated.
5. Print the label on the target printer and attach it to a sample folder.
6. Confirm text is legible at normal handling distance and the QR scans in office lighting.
7. Reprint and download the PDF label.

## Timed borrow

1. Open Borrow on the clerk’s mobile device.
2. Scan an active employee QR.
3. Scan one file, then repeat with several files.
4. Submit and verify holder, status, operator and time.
5. Target: the single-file transaction completes in under five seconds after the page is ready.
6. Rescan an already borrowed file and confirm the full batch is rejected without partial changes.
7. Try an inactive employee and an invalid QR; both must be rejected.

## Return and missing files

1. Return a file using the QR of the person physically returning it.
2. Confirm LRMS preserves the previous holder and records the actual returner.
3. Confirm status becomes Available, holder becomes empty, and the exact location is displayed.
4. Mark a borrowed file missing; confirm the last holder remains in history.
5. Mark it found and confirm a reason, operator, time and Available status are recorded.

## Search, import and export

1. Search independently by reference, purchaser, vendor, property, holder and status.
2. Confirm results and movement history respect permissions.
3. Download the Excel template and preview a valid workbook.
4. Preview duplicate references, missing values and invalid locations; no records may be imported.
5. Confirm a valid import and reconcile source, imported and failed row counts.
6. Export files and movements; confirm no passwords or QR payloads are present.

## Sign-off

- Records Clerk:
- Administrator:
- Lawyer representative:
- Project owner:
- Open blockers:
- Approved launch date:
