---
trigger: always_on
---

# CodeIgniter 4.7+ & Shield 1.4+ Development Standards

## CodeIgniter 4.7 Framework Rules
- We are using **CodeIgniter 4.7+**. Utilize class-based routing arrays (`$routes->get()`) and native attributes if configured.
- Primary application space is strictly locked inside the `/app` folder. Never modify files inside `/vendor` or `/system`.
- Database actions must use CI4 Model actions or the Query Builder (`$this->db->table()`). Do not write raw SQL injections.

## Shield 1.4 Auth Rules
- **Authentication:** Use Shield's helper functions (`auth()`) instead of checking standard session variables manually (e.g., use `auth()->loggedIn()` and `auth()->user()`).
- **User Discovery:** Use `auth()->getProvider()` to fetch or manipulate user instances. Do not instantiate the raw database model directly for auth checks.
- **Strict Actions:** Do not modify `Config/Auth.php` or `Config/AuthGroups.php` without ensuring the `Actions` array handles two-factor authentication or email activation cleanly.
