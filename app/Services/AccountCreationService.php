<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/**
 * AccountCreationService
 *
 * Shared account-creation pipeline extracted from Clergy\Members::store():
 * Shield user → group assignment → profile + canonical person link →
 * PersonProfileSyncService, all inside one transaction.
 *
 * Two modes:
 *  - Standard (invited=false): caller supplies a password; the user is
 *    $user->activate()d into a fully usable account. Used by members/store.
 *  - Invited (invited=true): the admin never sees a password — a throwaway
 *    secret is generated, the email_password identity gets force_reset=1
 *    and invited_at stamped, profile.portal_access = 'invited', and
 *    users.active is set directly (never via $user->activate(), which would
 *    mark the account fully usable). The cleric completes activation by
 *    logging in through the magic-link invitation and setting their own
 *    password; MemberAccessService then flips portal_access to 'enabled'.
 *
 * @see \App\Controllers\Auth\SetPasswordController
 * @see MemberAccessService::activatePortalAccessAfterSuccessfulLogin()
 */
class AccountCreationService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Create a user + identity + profile (+ canonical person) in one transaction.
     *
     * @param array{
     *   email: string,
     *   role: string,
     *   password?: ?string,
     *   invited?: bool,
     *   parish_id?: ?int,
     *   diocese_id?: ?int,
     *   province_id?: ?int,
     *   person_id?: ?int,
     *   link_person?: bool,
     *   attach_parish_status?: ?string,
     *   portal_access?: ?string,
     *   profile?: array<string, mixed>
     * } $data
     *
     * @return array{user_id: int, profile_id: int, person_id: ?int, email: string}
     *
     * @throws \RuntimeException on any failure (transaction already rolled back)
     */
    public function createAccount(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $role = (string) ($data['role'] ?? '');
        $invited = (bool) ($data['invited'] ?? false);
        $parishId = (int) ($data['parish_id'] ?? 0);
        $dioceseId = (int) ($data['diocese_id'] ?? 0);
        $provinceId = (int) ($data['province_id'] ?? 0);
        $linkPersonId = (int) ($data['person_id'] ?? 0);
        $attachStatus = $data['attach_parish_status'] ?? null;
        $profileInput = (array) ($data['profile'] ?? []);

        if ($email === '' || $role === '') {
            throw new \RuntimeException('AccountCreationService requires an email and a role.');
        }

        $this->db->transStart();

        try {
            $users = auth()->getProvider();
            $password = $data['password'] ?? null;
            if ($password === null || $password === '') {
                $password = bin2hex(random_bytes(16)) . 'Aa1!';
            }

            $user = new User([
                'username' => $email,
                'email'    => $email,
                'password' => $password,
            ]);
            $users->save($user);
            $user = $users->findById($users->getInsertID());
            if (! $user) {
                throw new \RuntimeException('Failed to create the user account.');
            }

            if ($invited) {
                // Invitation flow: the account exists so a magic-link login can
                // reach the set-password page, but it is never $user->activate()d —
                // force_reset + portal_access='invited' keep it unusable until the
                // invitee sets their own password.
                $now = date('Y-m-d H:i:s');
                $this->db->table('users')->where('id', (int) $user->id)->update([
                    'active'     => 1,
                    'updated_at' => $now,
                ]);
                $this->db->table('auth_identities')
                    ->where('user_id', (int) $user->id)
                    ->where('type', 'email_password')
                    ->update([
                        'force_reset' => 1,
                        'invited_at'  => $now,
                        'updated_at'  => $now,
                    ]);
            } else {
                $user->activate();
            }

            $user->addGroup($role);

            // Resolve the canonical person row:
            //  - person_id provided → attach the existing person (canonical
            //    registry clergy being given a portal account)
            //  - otherwise create a person when a parish or clergy profile
            //    needs one
            $personId = $linkPersonId > 0 ? $linkPersonId : null;
            if ($personId !== null) {
                $this->attachUserPerson((int) $user->id, $personId);
            } elseif ($parishId > 0 || ! empty($data['create_person'])) {
                $personId = model('MemberParishAffiliationModel')
                    ->getOrCreatePersonIdForUser((int) $user->id);
            }

            $profileData = array_merge([
                'user_id'    => $user->id,
                'person_id'  => $personId,
                'parish_id'  => $parishId > 0 ? $parishId : null,
                'province_id' => $provinceId > 0 ? $provinceId : null,
                'diocese_id' => $dioceseId > 0 ? $dioceseId : null,
                'first_name' => null,
                'middle_name' => null,
                'last_name' => null,
                'nickname' => null,
                'phone' => null,
                'phone_country_code' => null,
                'address' => null,
                'address_line_2' => null,
                'city' => null,
                'state' => null,
                'zip' => null,
                'country' => null,
                'birth_date' => null,
                'baptism_date' => null,
                'is_confirmed' => 0,
                'confirmation_date' => null,
                'spiritual_gifts' => null,
            ], $profileInput);

            $portalAccess = $data['portal_access'] ?? ($invited ? 'invited' : null);
            if ($portalAccess !== null) {
                $profileData['portal_access'] = $portalAccess;
            }

            $profileId = (int) model('ProfileModel')->insert($profileData, true);

            // Sync the canonical person record with the full profile data (the
            // person may have been auto-created earlier with placeholder names).
            if ($profileId > 0) {
                (new PersonProfileSyncService())->syncFromProfile($profileId);
            }

            if ($parishId > 0 && $attachStatus !== null
                && ! model('MemberParishAffiliationModel')->attachToParish(
                    (int) $user->id,
                    $parishId,
                    true,
                    (string) $attachStatus,
                    $personId
                )) {
                throw new \RuntimeException('Failed to attach the account to its parish.');
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Account creation transaction failed.');
            }

            return [
                'user_id'    => (int) $user->id,
                'profile_id' => $profileId,
                'person_id'  => $personId,
                'email'      => $email,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[AccountCreationService] account creation failed: {message}', [
                'message' => $e->getMessage(),
            ]);
            throw ($e instanceof \RuntimeException) ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Attach an existing canonical person to a user via user_person
     * (PersonConversionService::linkToUser pattern) — no duplicate person row.
     */
    private function attachUserPerson(int $userId, int $personId): void
    {
        if (! $this->db->tableExists('user_person')) {
            return;
        }

        $existing = $this->db->table('user_person')
            ->where('user_id', $userId)
            ->where('person_id', $personId)
            ->countAllResults();

        if ($existing === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('user_person')->insert([
                'user_id'    => $userId,
                'person_id'  => $personId,
                'is_primary' => 1,
                'linked_at'  => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
