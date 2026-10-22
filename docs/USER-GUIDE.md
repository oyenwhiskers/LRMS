# LRMS Operator Guide

## Administrator

1. Create storage locations and job positions before staff accounts are approved.
   Open each rack in **Storage Locations** to print its rack sign and shelf QR labels. Attach each shelf label at its physical shelf. Reprint labels after changing rack or shelf names; the shelf QR stays the same.
2. Register every employee in Staff and print their Employee QR.
3. Assign action permissions to positions using least privilege.
4. Review pending account registrations. Verify the staff number and selected position, correct the position if needed, then approve or reject.
5. Deactivate staff or positions when access is no longer required. Historical movements are retained.

## Register and label a file

1. Open **Case Register** and select **Register case**.
2. Enter the purchaser and property. Add the vendor, dates, person in charge, and related files when applicable. File numbers are generated from the case category.
3. Save once. LRMS creates the file as **Registered** (active). Shelf is not assigned yet.
4. Select **Print label**, verify the preview, print and attach it to the physical folder.

## Complete a case and store the file

1. When the matter is finished, open the file and select **Request completion** (requires `completions.request`).
2. Bring the physical file to the boss for review.
3. The boss opens **Completion**, checks the file, then **Approve** or **Reject** (reject returns the file to Registered with a reason).
4. Approved files become **Completed** and are ready to scan in **Store File**.
5. The clerk/PIC opens **Store File**, scans the File QR, then scans the destination shelf QR. Status becomes **Stored**.

## Borrow files

1. Open **Borrow File**. Only **Stored** files can be borrowed.
2. Select **Scan employee** and scan the borrower’s Employee QR.
3. Select **Scan file** for each physical file. Multiple files may be scanned for one employee.
4. Check that all physical folders are present, then select **Record borrow**.
5. Hand the files to the employee only after LRMS confirms success.

## Return files

1. Open **Return File**.
2. Scan the receiver PIC QR, scan every returned File QR, then scan the returner QR.
3. Select **Record return**.
4. Scan the received files and their shelf in **Store File** before they become available to borrow again.

## Missing or found

1. Search for and open the file.
2. Select **Mark missing** and enter useful circumstances (stored or borrowed files only).
3. When recovered, select **Mark found**, choose the restored shelf, and add notes.
4. Never edit or remove movement history; use a new event to correct operational state.

## Excel migration

1. Configure racks and shelves first.
2. Download the current import template.
3. Use exact Case Category codes.
4. Upload and review the preview. Correct every error before confirming.
5. Reconcile the audit totals with the source workbook.

## Scanner troubleshooting

- Permit camera access for the HTTPS LRMS site.
- Clean the camera lens and keep the QR inside the frame.
- If camera access is unavailable, connect a USB/Bluetooth scanner and focus the relevant field.
- Do not type employee names or file details into QR fields.
- If the network fails, do not hand over a file until the transaction is confirmed by LRMS.
