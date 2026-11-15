# University Sports Portal

A PHP and MySQL portal for university sports teams. Players, coaches, and the Head of Sports (HOS) use role-specific dashboards and chat features.

## Features

- **Players:** View teammates and coaches for their assigned sport, use team and coach chats, and submit a sport request when unassigned.
- **Coaches:** View and manage their sport's roster, add or drop players, use team and player chats, participate in the coaches board, and message the HOS.
- **Head of Sports:** Review roster summaries, message coaches, and assign unassigned players through the transfer portal.
- **Chat:** Session-protected message APIs support team and one-to-one conversations, with messages refreshed by the chat interface.
- **Role boundaries:** Players and the HOS do not have a direct communication channel.

## Requirements

- XAMPP with Apache and MySQL
- PHP with PDO MySQL enabled
- PHP cURL enabled to run the login test

## Local setup with XAMPP

1. Place the project in the XAMPP web root, for example `C:\xampp\htdocs\university_sports`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin and import `database/schema.sql`.
   - **Warning:** This schema script starts with `DROP DATABASE IF EXISTS university_sports;` and recreates the database. Importing it deletes existing data in that database.
4. Review `config/db.php` and set the local MySQL host, database name, username, and password as needed.
5. Visit `http://localhost/university_sports/`.

The default database configuration expects MySQL at `127.0.0.1`, database `university_sports`, and the local XAMPP `root` account with a blank password. Change these settings to match your local environment.

## Project layout

```text
api/                 Chat message fetch and send endpoints
assets/css/          Shared styles
assets/js/           Chat interface JavaScript
auth/                Registration, login, and logout
coach/               Coach dashboard, roster actions, and chat pages
config/              PDO database connection
database/            SQL schema and local data utilities
hos/                 Head of Sports dashboard and assignment actions
includes/            Authentication checks and shared chat UI/helpers
player/              Player dashboard, sport requests, and chat pages
tests/               Local login-routing test
index.php            Public landing page
```

## Transfer portal

When a player's `sport_id` is `NULL`, the player dashboard shows the Transfer Portal instead of team details. Players can request a sport; the HOS dashboard lists unassigned players and their requested sports. Assigning a player sets `sport_id` and clears `requested_sport_id`.

The `users` table includes both `sport_id` and `requested_sport_id`. The schema defines `requested_sport_id` as a nullable reference to `sports`.

## Local seed and maintenance scripts

The scripts in `database/` are intended for local development only. Some reset users or change account passwords; the dummy-data seeder creates accounts with a shared test password. Review a script before running it, and remove temporary maintenance scripts before deploying or exposing the site beyond localhost. Never use the sample/test passwords in production.

## Login test

With Apache and MySQL running and the HOS test account configured, run:

```powershell
php tests\test_login.php
```

The test uses cURL to check the login session cookie and HOS dashboard redirect. It is also restricted to localhost when opened through the browser.
