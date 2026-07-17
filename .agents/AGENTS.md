# MeetFlow Custom Rules & Guidelines

## Native HTML Dialog Interactions
- **Avoid Thread-Blocking Dialogs Inside `<dialog>`**: Do NOT use browser-native blocking functions like `confirm()`, `alert()`, or `prompt()` inside HTML `<dialog>` elements or focus-dependent overlays.
  - **Reason**: Blocking calls interrupt the browser thread and cause focus loss, which triggers native `close` or `cancel` events on `<dialog>` elements, making them dismiss unexpectedly.
  - **Best Practice**: Implement inline double-confirmation states (e.g., toggling the action button's text to "Confirm?" and showing a cancel sibling button) or use nested custom `<dialog>` elements.

## Mobile QR Code Upload Architecture
- **Prefer Mobile-Friendly QR Uploads for Desktop File Inputs**: When building desktop forms where users need to upload documents or photos that are physically on their mobile devices (e.g., paper receipts, physical documents), implement a temporary QR Code workflow.
  - **Architecture Pattern**:
    1. **Token Generator**: Create a backend endpoint to generate a secure, short-lived random token (e.g., 10-minute expiry) and store it in a `temporary_tokens` table.
    2. **QR Code Rendering**: Render a QR Code pointing to a mobile upload page containing the token.
    3. **Mobile Client Upload**: Mobile device scans the QR Code, accesses the upload page, captures an image/file, and uploads it. The backend saves the file and updates the token record with the filename.
    4. **Client-Side Synchronization**: The desktop browser polls the token status (via AJAX/setInterval) and automatically updates the form state once the upload is completed on the mobile device, disabling QR Code display.

