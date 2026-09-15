# Profile Picture Security and Delivery

## Goal

Codify the private-by-default media delivery model for ekklesiaRD with clear security and performance behavior.

## Final Model

- Storage location: `writable/uploads/profile/{userId}/`
- DB value format (`profile.profile_picture`): relative path such as `profile/123/abcd1234ef.jpg`
- Filename policy: random names (`UploadedFile::getRandomName()`), not predictable user+timestamp names
- Delivery policy: private guarded endpoint only

## Route and Controller Contract

- Route: `GET /media/profile-picture/{userId}`
- Route scope: session-protected route group (authenticated requests only)
- Controller: `MemberPortal\Controllers\PublicHome::profilePicture(int $userId)`

Additional private media routes:

- Route: `GET /media/parish-logo/{parishId}`
- Controller: `MemberPortal\Controllers\PublicHome::parishLogo(int $parishId)`
- Storage convention (`parishes.logo`): `parishes/{parishId}/logo/{randomName}`

- Route: `GET /media/event-attachment/{attachmentId}`
- Controller: `MemberPortal\Controllers\PublicHome::eventAttachment(int $attachmentId)`
- Storage convention (`event_attachments.stored_name`): `events/{eventId}/{randomName}`

Authorization rules:

- Allow self-view (`viewer.id === targetUserId`)
- Allow `systemadmin`
- Allow `provinceadmin` when `viewer.province_id === target.province_id`
- Allow `diocesanadmin` when `viewer.diocese_id === target.diocese_id`
- For other staff (`members.view` or `profile.edit`), require at least one shared active parish affiliation

Failure behavior:

- Unauthorized, invalid path, missing profile, or missing file returns 404 (no data leakage)

## Path Hardening

For any upload path consumption:

- Reject absolute paths
- Reject traversal (`..`, `../`, `..\\`)
- Allow only `[A-Za-z0-9/_\.-]`
- Resolve with `realpath()` and enforce prefix within `WRITEPATH/uploads`

Legacy upload endpoint hardening:

- `serveUpload()` blocks `profile/*`, `parishes/*`, and `events/*` requests
- Profile photos, parish logos, and event attachments must be served exclusively via their guarded `media/*` endpoints

## Performance Controls

Profile image responses include:

- `Cache-Control: private, max-age=300`
- `ETag`
- `Last-Modified`
- `304 Not Modified` when `If-None-Match` matches

Why this is fast enough and safe:

- Private cache improves repeat load speed for authenticated users
- ETag/304 avoids retransferring unchanged images
- Keeping files under `writable/` prevents direct web-root exposure

## URL Normalization Implemented

All profile image renders now use:

- `base_url('media/profile-picture/' . {userId})`

Normalized views:

- `Pastor/Views/People/index.php`
- `Pastor/Views/Members/edit.php`
- `MemberPortal/Views/Profile/edit.php`
- `MemberPortal/Views/Profile/view.php`

Additional normalized non-profile media views:

- `Diocesan/Views/Parishes/edit.php` uses `base_url('media/parish-logo/' . {parishId})`
- `Pastor/Views/Events/view.php` uses `base_url('media/event-attachment/' . {attachmentId})`

## Operational Notes

- Keep per-user folders; they reduce directory fan-out and improve file-system performance at scale.
- Keep per-entity folders for logos and attachments (`parishes/{id}/logo`, `events/{id}`) for the same reasons.
- Keep random names; they reduce guessability and URL enumeration risk.
- If a future requirement needs public avatars, migrate avatars to `public/uploads` and treat that as a separate explicit public model.
