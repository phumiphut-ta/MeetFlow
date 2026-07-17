# MeetFlow Custom Rules & Guidelines

## Native HTML Dialog Interactions
- **Avoid Thread-Blocking Dialogs Inside `<dialog>`**: Do NOT use browser-native blocking functions like `confirm()`, `alert()`, or `prompt()` inside HTML `<dialog>` elements or focus-dependent overlays.
  - **Reason**: Blocking calls interrupt the browser thread and cause focus loss, which triggers native `close` or `cancel` events on `<dialog>` elements, making them dismiss unexpectedly.
  - **Best Practice**: Implement inline double-confirmation states (e.g., toggling the action button's text to "Confirm?" and showing a cancel sibling button) or use nested custom `<dialog>` elements.
