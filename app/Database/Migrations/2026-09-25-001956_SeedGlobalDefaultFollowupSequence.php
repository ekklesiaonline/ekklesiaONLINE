<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedGlobalDefaultFollowupSequence extends Migration
{
    private const SEQUENCE_NAME = '6-Week First-Time Visitor Journey';

    public function up()
    {
        $db = $this->db;
        $now = date('Y-m-d H:i:s');

        // Avoid re-seeding if the global default already exists.
        $existing = $db->table('followup_sequence')
            ->where('parish_id IS NULL', null, false)
            ->where('name', self::SEQUENCE_NAME)
            ->get()
            ->getRowArray();

        if ($existing) {
            return;
        }

        $db->transStart();

        // 1. Global default sequence
        $db->table('followup_sequence')->insert([
            'parish_id'         => null,
            'name'              => self::SEQUENCE_NAME,
            'description'       => 'System default: a structured 6-week touchpoint cadence for first-time visitors, combining automated emails/texts with personal volunteer calls and a welcome gift.',
            'is_active'         => 1,
            'is_default'        => 0,
            'is_global_default' => 1,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
        $sequenceId = (int) $db->insertID();

        $commonVars = [
            'first_name',
            'last_name',
            'full_name',
            'parish_name',
            'parish_address',
            'clergy_name',
            'service_time',
            'visit_date',
            'phone',
            'next_event_name',
            'event_date',
        ];

        // 2. Reusable global templates
        $templates = [];

        $db->table('followup_template')->insert([
            'parish_id'  => null,
            'channel'    => 'email',
            'name'       => 'Thank you for visiting',
            'subject'    => 'Thanks for visiting {parish_name}',
            'body_text'  => "Hi {first_name},\n\nThank you for visiting {parish_name} on {visit_date}. We are grateful you worshipped with us and would love to see you again.\n\nBlessings,\n{clergy_name}",
            'body_html'  => "<p>Hi {first_name},</p><p>Thank you for visiting <strong>{parish_name}</strong> on {visit_date}. We are grateful you worshipped with us and would love to see you again.</p><p>Blessings,<br>{clergy_name}</p>",
            'variables'  => json_encode($commonVars),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templates['email_thank_you'] = (int) $db->insertID();

        $db->table('followup_template')->insert([
            'parish_id'  => null,
            'channel'    => 'text',
            'name'       => 'Hope to see you again',
            'subject'    => null,
            'body_text'  => "Hi {first_name}, we hope to see you again at {parish_name}. Services are at {service_time}. Reply STOP to opt out.",
            'body_html'  => null,
            'variables'  => json_encode($commonVars),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templates['text_follow_up'] = (int) $db->insertID();

        $db->table('followup_template')->insert([
            'parish_id'  => null,
            'channel'    => 'email',
            'name'       => 'Next steps and upcoming events',
            'subject'    => 'Upcoming events at {parish_name}',
            'body_text'  => "Hi {first_name},\n\nWe would love to help you take the next step at {parish_name}. Join us for {next_event_name} on {event_date} or stop by any service at {service_time}.\n\nBlessings,\n{clergy_name}",
            'body_html'  => "<p>Hi {first_name},</p><p>We would love to help you take the next step at <strong>{parish_name}</strong>. Join us for {next_event_name} on {event_date} or stop by any service at {service_time}.</p><p>Blessings,<br>{clergy_name}</p>",
            'variables'  => json_encode($commonVars),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templates['email_next_steps'] = (int) $db->insertID();

        $db->table('followup_template')->insert([
            'parish_id'  => null,
            'channel'    => 'mail',
            'name'       => 'Welcome letter',
            'subject'    => null,
            'body_text'  => "Dear {first_name},\n\nWelcome to {parish_name}! We are delighted you visited on {visit_date}. Enclosed is some information about our community, service times, and how to get involved.\n\nIn Christ,\n{clergy_name}\n{parish_address}",
            'body_html'  => "<p>Dear {first_name},</p><p>Welcome to <strong>{parish_name}</strong>! We are delighted you visited on {visit_date}. Enclosed is some information about our community, service times, and how to get involved.</p><p>In Christ,<br>{clergy_name}<br>{parish_address}</p>",
            'variables'  => json_encode($commonVars),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templates['mail_welcome_letter'] = (int) $db->insertID();

        $db->table('followup_template')->insert([
            'parish_id'  => null,
            'channel'    => 'email',
            'name'       => 'Get involved',
            'subject'    => 'You are welcome here',
            'body_text'  => "Hi {first_name},\n\nWe would love for you to become part of the {parish_name} family. Please reach out or visit us again soon.\n\nBlessings,\n{clergy_name}",
            'body_html'  => "<p>Hi {first_name},</p><p>We would love for you to become part of the <strong>{parish_name}</strong> family. Please reach out or visit us again soon.</p><p>Blessings,<br>{clergy_name}</p>",
            'variables'  => json_encode($commonVars),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templates['email_get_involved'] = (int) $db->insertID();

        // 3. Actions for the 6-week cadence
        $actions = [
            [
                'week_number'   => 1,
                'day_offset'    => 0,
                'channel'       => 'email',
                'template_id'   => $templates['email_thank_you'],
            ],
            [
                'week_number'   => 1,
                'day_offset'    => 2,
                'channel'       => 'text',
                'template_id'   => $templates['text_follow_up'],
            ],
            [
                'week_number'   => 2,
                'day_offset'    => 7,
                'channel'       => 'call',
                'template_id'   => null,
            ],
            [
                'week_number'   => 3,
                'day_offset'    => 14,
                'channel'       => 'email',
                'template_id'   => $templates['email_next_steps'],
            ],
            [
                'week_number'   => 5,
                'day_offset'    => 28,
                'channel'       => 'mail',
                'template_id'   => $templates['mail_welcome_letter'],
            ],
            [
                'week_number'   => 6,
                'day_offset'    => 35,
                'channel'       => 'email',
                'template_id'   => $templates['email_get_involved'],
            ],
            [
                'week_number'   => 6,
                'day_offset'    => 42,
                'channel'       => 'gift',
                'template_id'   => null,
            ],
        ];

        foreach ($actions as $action) {
            $db->table('followup_action')->insert([
                'sequence_id'   => $sequenceId,
                'week_number'   => $action['week_number'],
                'day_offset'    => $action['day_offset'],
                'channel'       => $action['channel'],
                'template_id'   => $action['template_id'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $db->transComplete();
    }

    public function down()
    {
        $db = $this->db;

        $sequence = $db->table('followup_sequence')
            ->where('parish_id IS NULL', null, false)
            ->where('name', self::SEQUENCE_NAME)
            ->get()
            ->getRowArray();

        if (!$sequence) {
            return;
        }

        $sequenceId = (int) $sequence['id'];

        // Cascade deletes actions; gather template IDs to clean up global templates.
        $templateIds = $db->table('followup_action')
            ->where('sequence_id', $sequenceId)
            ->where('template_id IS NOT NULL', null, false)
            ->select('template_id')
            ->get()
            ->getResultArray();

        $templateIds = array_values(array_unique(array_filter(array_column($templateIds, 'template_id'))));

        $db->table('followup_action')->where('sequence_id', $sequenceId)->delete();
        $db->table('followup_sequence')->delete($sequenceId);

        if ($templateIds !== []) {
            $db->table('followup_template')->whereIn('id', $templateIds)->delete();
        }
    }
}
