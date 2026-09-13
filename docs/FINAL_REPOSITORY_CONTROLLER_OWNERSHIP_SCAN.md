# Final Repository-Wide Controller Ownership Scan

Date: 2026-03-14

## Scope

- Reviewed routing ownership in app and all role modules.
- Searched for remaining legacy app-controller ownership patterns.

## Findings

### 1) Module ownership is active for role/domain routes

Active route ownership resolves to module controllers for:

- Province
- Diocesan
- Pastor
- Treasurer
- Volunteer
- MemberPortal

### 2) Remaining app-owned routes/controllers (intentional)

The following remain app-owned and are currently intentional:

- Public entry and upload serving via Home controller.
- No remaining app-owned feature module routes/controllers.

### 3) Legacy duplication status

No remaining duplicate route ownership was found for the previously migrated domains:

- Province
- Diocesan
- Pastor
- Treasurer
- Volunteer
- MemberPortal profile/dashboard/families

## What Is Intentionally Left In app

- app/Controllers/Home.php
- app/Controllers/BaseController.php (shared base used by module controllers)
- app/Config/Routes.php shell responsibilities: loads Shield auth routes.
- app/Config/Routes.php shell responsibilities: requires module route files.
- app/Config/Routes.php shell responsibilities: keeps public root/uploads and top-level session shell.

## Notes

- Some duplicate URL aliases remain inside module route files for backward compatibility (for example camelCase vs kebab-case variants). These are not app-vs-module ownership conflicts.
- The app route file continues to be the composition root, while feature ownership has moved to module route files and module controllers.
