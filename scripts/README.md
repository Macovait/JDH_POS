# Scripts Directory

This directory contains utility, maintenance, and diagnostic scripts for the JDH POS system.

## Folder Structure

| Folder      | Purpose                                                  |
|-------------|----------------------------------------------------------|
| `fixes/`    | One-off correction scripts (permissions, schema, etc.) |
| `debug/`    | Diagnostic scripts for troubleshooting issues            |
| `tests/`    | Standalone test scripts for features / roles / URLs      |
| `setup/`    | Setup / seeding scripts for roles, permissions, users   |
| `checks/`   | Schema validation, audit, and health-check scripts       |
| `migrations/`| One-off migration execution helpers                     |

## Usage

Most scripts in this directory are intended to be run:
- **Via CLI:** `php scripts/fixes/fix_something.php`
- **Via Browser:** Navigate to `http://localhost/JDH_POS/scripts/fixes/fix_something.php`

> **Warning:** Always back up your database before running fix or migration scripts.

## Root Directory Cleanup

Loose files that were previously in the project root have been moved here for clarity. If you create new fix/debug/test scripts, place them in the appropriate subfolder.
