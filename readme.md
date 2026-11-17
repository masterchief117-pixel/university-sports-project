# University Sports Portal

A PHP and MySQL portal for university sports teams. Players, coaches, photographers, and the Head of Sports (HOS) use role-specific dashboards and chat features.

## Features

- **Players:** View teammates and coaches for their assigned sport, use team and coach chats, and submit a sport request when unassigned.
- **Coaches:** View and manage their sport's roster, add or drop players, use team and player chats, participate in the coaches board, and message the HOS.
- **Head of Sports:** Review roster summaries, message coaches, and assign unassigned players through the transfer portal.
- **Photographers:** Create sport galleries, add local or external photos, manage gallery contents, and delete photos or entire galleries.
- **Chat:** Session-protected message APIs support team and one-to-one conversations, with messages refreshed by the chat interface.
- **Role boundaries:** Players and the HOS do not have a direct communication channel.
- **Account security:** Public registration creates player accounts only. Login supports a rotating, hashed 30-day remember token; the browser stores it in an HttpOnly, SameSite cookie.

## Requirements

- XAMPP with Apache and MySQL
- PHP 8.0 or newer with PDO MySQL, Fileinfo, and mbstring extensions enabled
- PHP cURL enabled to run the login test
- Node.js to run the JavaScript tests

## Local setup with XAMPP

1. Place the project in the XAMPP web root, for example `C:\xampp\htdocs\university_sports`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Configure the database connection using one of these options:
   - Copy `config/db.local.php.example` to `config/db.local.php` and set the local credentials. The local file is ignored by Git.
   - Or set `UNIVERSITY_SPORTS_DB_HOST`, `UNIVERSITY_SPORTS_DB_NAME`, `UNIVERSITY_SPORTS_DB_USER`, and `UNIVERSITY_SPORTS_DB_PASSWORD` in the PHP/Apache environment.
4. Open phpMyAdmin and import `database/schema.sql`. It creates the database and missing tables without dropping existing data, and inserts any missing sports.
   - `CREATE TABLE IF NOT EXISTS` does not alter existing tables. For a database that already contains application tables, apply the migrations below in order instead of relying on the schema import to update those tables.
5. Create the initial Head of Sports account from a terminal in the project root:

   ```powershell
   php database\create_hos.php
   ```

6. Visit `http://localhost/university_sports/`.

The database account needs permission to create the database when importing `schema.sql`. For deployed environments, use a dedicated least-privilege database account and keep credentials outside the web root.

### Updating an existing database

Back up the database before applying schema changes. Select `university_sports` in phpMyAdmin, then apply the migrations in numerical order:

1. `database/migrations/001_remember_tokens.sql`
2. `database/migrations/002_player_player_conversations.sql`
3. `database/migrations/003_photographer_galleries.sql`
4. `database/migrations/004_align_gallery_storage.sql`
5. `database/migrations/005_chat_message_cursor_index.sql`
6. `database/migrations/006_transfer_requests.sql`

The later alignment/index/transfer migrations check for existing columns, indexes, or constraints before adding or modifying them. Migration 006 converts `requested_sport_id` to an unsigned sports reference; resolve any invalid or negative requested sport IDs before applying it. These migrations do not drop application data.

For legacy chat tables, run `php config\setup_chat_db.php` from the project root to add the message recipient/read-state columns expected by chat integrations. The setup script is command-line-only.

## Project layout

```text
api/                 Chat message fetch and send endpoints
assets/css/          Shared styles
assets/js/           Chat interface JavaScript
auth/                Registration, login, and logout
coach/               Coach dashboard, roster actions, and chat pages
config/              PDO database connection
database/            SQL schema and local data utilities
database/migrations/ Ordered database updates for existing installations
hos/                 Head of Sports dashboard and assignment actions
includes/            Authentication checks and shared chat UI/helpers
player/              Player dashboard, sport requests, and chat pages
tests/               Local authentication and regression tests
index.php            Public landing page
```

## Transfer portal

When a player's `sport_id` is `NULL`, the player dashboard shows the Transfer Portal instead of team details. Players can request a sport; the HOS dashboard lists unassigned players and their requested sports. Assigning a player sets `sport_id` and clears `requested_sport_id`.

The `users` table includes both `sport_id` and `requested_sport_id`. The schema defines `requested_sport_id` as a nullable reference to `sports`.

## Local seed and maintenance scripts

The scripts in `database/` are intended for local development only and refuse web requests. `seed_users.php` clears user accounts and requires the explicit `--reset-test-users` flag; its test accounts use `password123`. The dummy-data seeder also uses a shared test password. Never use these utilities or passwords in production.

## Login test

With Apache and MySQL running and the HOS test account configured, run:

```powershell
php tests\test_login.php
```

The test uses cURL to check CSRF rejection, the login session cookie, the HOS dashboard redirect, and redirection from a wrong-role dashboard. It is also restricted to localhost when opened through the browser.
Set `UNIVERSITY_SPORTS_TEST_HOS_EMAIL` and `UNIVERSITY_SPORTS_TEST_HOS_PASSWORD` if the test account uses different credentials.

## Other tests

Run the chat client and dashboard-table tests with Node.js:

```powershell
node tests\test_chat.js
node tests\test_dashboard_tables.js
```

With Apache, MySQL, and the local gallery schema enabled, run the gallery viewer regression test:

```powershell
php tests\test_gallery_viewer.php
```

The test inserts temporary gallery rows and removes them when it finishes.

Run the photographer media deletion integration test with Apache and MySQL enabled:

```powershell
php tests\test_photographer_delete.php
```

It creates a temporary photographer, test galleries, and local media files, then removes those fixtures.

Run the chat authorization integration test with Apache and MySQL enabled:

```powershell
php tests\test_chat_authorization.php
```

It verifies that players can message their coach, player-HOS message and fetch requests are rejected, and send requests require a valid CSRF token. Temporary accounts and conversations are removed afterward.

## Player account data-flow test

The database-independent test verifies that player creation stores a hashed password and the requested sport, unassigned players can request and receive a sport, assignment clears `requested_sport_id`, and dropping a player clears `sport_id` without deleting the account:

```powershell
php tests\test_player_account_flow.php
```

This test requires PDO SQLite.

## Chat client test

The Node.js test verifies duplicate-send protection, cursor-based message updates, and loading older history:

```powershell
node tests\test_chat.js
```
