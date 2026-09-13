# People and Members Data Model Report (2026-04-10)

## Scope and Counting Method

This report is based on the live `ekklesiaRD` schema (`information_schema`). It includes tables that store member identity, profile, affiliation, household, standing, participation, authentication, and onboarding workflow data.

## Total Tables Used for People or Members Information

Total identified tables: 24.

| table_name |
| --- |
| users |
| profile |
| member_church_affiliations |
| families |
| family_members |
| member_standing_summary |
| annual_pledges |
| attendance |
| donations |
| leadership_positions |
| team_members |
| volunteer_schedule |
| event_registrations |
| service_people |
| guests |
| follow_up_tasks |
| user_registration_requests |
| user_sessions |
| auth_identities |
| auth_groups_users |
| auth_permissions_users |
| auth_logins |
| auth_remember_tokens |
| auth_token_logins |

## Tables, Fields, and Relationships

| table_name | fields | relationships |
| --- | --- | --- |
| users | id, username, status, status_message, active, last_active, created_at, updated_at, deleted_at | central identity table; referenced by most user-linked tables |
| profile | id, user_id, profile_picture, church_id, province_id, diocese_id, first_name, middle_name, last_name, phone, phone_hash, address, city, state, zip, country, locale, social_media, birth_date, baptism_date, membership_status, spiritual_gifts, created_at, updated_at | user_id -> users.id; church_id -> churches.id; diocese_id -> dioceses.id; province_id -> provinces.id |
| member_church_affiliations | id, user_id, church_id, diocese_id, membership_status, is_home, active, started_at, ended_at, created_at, updated_at | user_id -> users.id; church_id -> churches.id; diocese_id -> dioceses.id |
| families | id, church_id, family_name, head_of_household_id, address, created_at, updated_at | church_id -> churches.id; head_of_household_id -> users.id |
| family_members | id, family_id, user_id, relationship, created_at | family_id -> families.id; user_id -> users.id |
| member_standing_summary | id, user_id, year, is_active_member, attendance_count, communion_count, has_pledged, is_under_discipline, is_good_standing, last_updated | user_id -> users.id |
| annual_pledges | id, user_id, church_id, pledge_year, pledged_amount, fulfilled_amount, pledged_at, fulfillment_status, notes, created_at, updated_at | user_id -> users.id; church_id -> churches.id |
| attendance | id, service_id, user_id, attended, communicant, created_at | service_id -> services.id; user_id -> users.id |
| donations | id, church_id, fund_id, user_id, amount, date, category, chart_account_id, payment_method, notes, created_at, updated_at | church_id -> churches.id; user_id -> users.id; fund_id -> funds.id; chart_account_id -> chart_of_accounts.id |
| leadership_positions | id, user_id, scope_type, church_id, diocese_id, province_id, title, role_code, active, started_at, ended_at, notes, appointed_by_user_id, created_at, updated_at | user_id -> users.id; appointed_by_user_id -> users.id; church_id -> churches.id; diocese_id -> dioceses.id; province_id -> provinces.id |
| team_members | id, team_id, user_id, role, created_at | team_id -> volunteer_teams.id; user_id -> users.id |
| volunteer_schedule | id, team_id, service_id, user_id, date, time_slot, status, created_at, updated_at | team_id -> volunteer_teams.id; service_id -> services.id; user_id -> users.id |
| event_registrations | id, event_id, church_id, user_id, guest_name, guest_email, guest_phone, status, notes, created_at, updated_at | event_id -> events.id; church_id -> churches.id; user_id -> users.id |
| service_people | id, service_id, person_name, role_name, sort_order, created_at, updated_at | service_id -> services.id |
| guests | id, church_id, first_name, last_name, email, email_hash, phone, phone_hash, visit_date, service_attended, followed_up, converted_to_member_at, converted_user_id, converted_by_user_id, notes, created_at, updated_at | church_id -> churches.id; converted_user_id -> users.id; converted_by_user_id -> users.id |
| follow_up_tasks | id, guest_id, assigned_to, due_date, status, notes, created_at, updated_at | guest_id -> guests.id; assigned_to -> users.id |
| user_registration_requests | id, user_id, province_id, diocese_id, requested_church_id, status, requested_role, reviewed_by, review_notes, profile_completed_at, reviewed_at, created_at, updated_at | user_id -> users.id; reviewed_by -> users.id; requested_church_id -> churches.id; diocese_id -> dioceses.id; province_id -> provinces.id |
| user_sessions | id, user_id, session_token, ip_address, user_agent, device_fingerprint, is_new_device, last_active, created_at | logical user link by user_id (no explicit FK constraint in metadata) |
| auth_identities | id, user_id, type, name, secret, secret2, expires, extra, force_reset, last_used_at, created_at, updated_at | user_id -> users.id |
| auth_groups_users | id, user_id, group, created_at | user_id -> users.id |
| auth_permissions_users | id, user_id, permission, created_at | user_id -> users.id |
| auth_logins | id, ip_address, user_agent, id_type, identifier, user_id, date, success | logical user link by user_id (no explicit FK constraint in metadata) |
| auth_remember_tokens | id, selector, hashedValidator, user_id, expires, created_at, updated_at | user_id -> users.id |
| auth_token_logins | id, ip_address, user_agent, id_type, identifier, user_id, date, success | logical user link by user_id (no explicit FK constraint in metadata) |

## Relationship Summary

`users` is the central person identity table. The main member profile and church membership state are extended through `profile` and `member_church_affiliations`. Most people/member operational tables link back to `users` through `user_id` or equivalent reviewer/assignee columns.

## Notes

This report reflects the live schema at generation time (2026-04-10).
