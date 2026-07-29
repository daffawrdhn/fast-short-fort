# Development Guidelines for FORT

These rules are established to prevent recurring errors during the development of this native PHP application.

## Rules & Constraints

### 1. HTML Form Decoupling (No Nested Forms)
* **Rule**: Never nest `<form>` tags. It is invalid HTML, and browsers will ignore the inner forms, causing button actions to submit incorrectly to the outer form.
* **Implementation Pattern**:
  For list views that combine bulk actions and individual row actions:
  - Close the bulk actions form immediately:
    ```html
    <form id="bulk-form" action="/links/bulk" method="POST">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
    </form>
    ```
  - Associate checkboxes and bulk buttons with the bulk form using the `form` attribute:
    ```html
    <button type="submit" form="bulk-form" name="action" value="delete">Delete</button>
    <input type="checkbox" name="ids[]" value="123" form="bulk-form">
    ```
  - Render individual row actions inside their own independent `<form>` elements safely:
    ```html
    <form action="/links/123/delete" method="POST">
        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
        <button type="submit">Delete</button>
    </form>
    ```

### 2. Layout View Variables (Session Isolation)
* **Rule**: Layout views (like `layouts/app.php`) are rendered globally. They must not rely on variables passed from specific controllers (e.g. `$user`) to render header info.
* **Implementation Pattern**:
  - Always read global user data from the PHP session array:
    ```php
    $userName = $_SESSION['user_name'] ?? 'User';
    ```

### 3. Data Mutation Methods & CSRF Checks
* **Rule**: Any action that alters database state (e.g., delete, toggle, forceDelete, switch workspace) must be executed via `POST` requests and contain a valid CSRF token.
* **Implementation Pattern**:
  - Never use GET anchor tags (`<a>`) for mutating buttons.
  - In controllers, verify CSRF validation is present:
    ```php
    if (!$request->validateCsrf()) {
        $this->session->flash('error', 'Invalid CSRF token.');
        $response->redirect('/links');
        return;
    }
    ```
  - When performing soft deletes or toggles, verify controller methods call the correct model methods (e.g., `forceDelete()` vs `delete()`).
  - Redirect the user to list/index endpoints (e.g., `/links`) instead of using `$response->back()` when completing deletions from detail pages to ensure correct navigation flow.
