<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CheckUserProfile extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'user:check';
    protected $description = 'Check profile and groups for a user by ID.';
    protected $usage       = 'user:check <user_id>';
    protected $arguments   = [
        'user_id' => 'The user ID to inspect.',
    ];

    public function run(array $params)
    {
        $userId = (int) ($params[0] ?? 0);
        if ($userId <= 0) {
            CLI::error('Provide a valid user_id.');
            return EXIT_ERROR;
        }

        $db = db_connect();

        $user = $db->table('users')->where('id', $userId)->get()->getRowArray();
        $profile = $db->table('profile')->where('user_id', $userId)->get()->getRowArray();
        $request = $db->tableExists('user_registration_request')
            ? $db->table('user_registration_request')->where('user_id', $userId)->get()->getRowArray()
            : null;
        $groups = $db->table('auth_groups_users')->where('user_id', $userId)->get()->getResultArray();

        CLI::write('User: ' . json_encode($user ?? []));
        CLI::write('Profile: ' . json_encode($profile ?? []));
        CLI::write('Registration request: ' . json_encode($request ?? []));
        CLI::write('Groups: ' . json_encode(array_column($groups, 'group')));

        return EXIT_SUCCESS;
    }
}
