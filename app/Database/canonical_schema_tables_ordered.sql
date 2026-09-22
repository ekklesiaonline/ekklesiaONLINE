CREATE TABLE `province` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_number` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` tinyint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `province_number` (`province_number`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `diocese` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `alias_norm` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `province_id_name` (`province_id`,`name`),
  KEY `province_id` (`province_id`),
  KEY `name` (`name`),
  KEY `idx_dioceses_province_id` (`province_id`),
  KEY `idx_dioceses_alias_norm` (`alias_norm`),
  CONSTRAINT `dioceses_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `parish_ueid` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `logo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `banner` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `street` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_street` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_state` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `zip` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ZIP+4 format',
  `mailing_zip` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `county` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_county` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'United States',
  `mailing_country` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tax_id` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Tax identification number (EIN)',
  `address` text COLLATE utf8mb4_general_ci,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `public_token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_parish_parish_ueid` (`parish_ueid`),
  KEY `idx_parish_diocese_id` (`diocese_id`),
  KEY `idx_parish_is_active` (`is_active`),
  CONSTRAINT `parish_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8337 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `family` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `family_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `head_of_household_id` int unsigned DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `families_head_of_household_id_foreign` (`head_of_household_id`),
  KEY `parish_id` (`parish_id`),
  CONSTRAINT `families_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `families_head_of_household_id_foreign` FOREIGN KEY (`head_of_household_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('M','F','other') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `interests` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `last_name` (`last_name`)
) ENGINE=InnoDB AUTO_INCREMENT=225 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_message` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `last_active` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `fund` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `fund_type` enum('unrestricted','restricted','permanent') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unrestricted',
  `restriction_purpose` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `chart_account_id` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parish_fund_name` (`parish_id`,`name`),
  KEY `funds_created_by_foreign` (`created_by`),
  KEY `parish_id` (`parish_id`),
  KEY `parish_id_is_active` (`parish_id`,`is_active`),
  KEY `chart_account_id` (`chart_account_id`),
  CONSTRAINT `funds_chart_account_id_foreign` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `funds_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `funds_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=172 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `chart_of_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `account_type` enum('income','expense','asset','liability','equity') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'expense',
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `allow_budget` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `account_type_is_active` (`account_type`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `service` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `service_date` date DEFAULT NULL,
  `service_time` time DEFAULT NULL,
  `liturgical_day` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service` json DEFAULT NULL,
  `place` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `preacher` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `celebrant` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`),
  KEY `idx_parish_date_composite` (`parish_id`),
  KEY `idx_parish_date` (`parish_id`),
  KEY `idx_date_range` (`parish_id`),
  CONSTRAINT `services_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `calendar_id` int unsigned DEFAULT NULL,
  `scope_level` enum('parish','diocese') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `scope_diocese_id` int unsigned DEFAULT NULL,
  `visibility` enum('public','members','staff','volunteers','invited','registered_only','household') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'members',
  `audience_team_id` int unsigned DEFAULT NULL,
  `audience_family_id` int unsigned DEFAULT NULL,
  `schedule_mode` enum('one_day','all_day','multi_day') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'one_day',
  `is_all_day` tinyint(1) NOT NULL DEFAULT '0',
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `cover_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
  `location` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime DEFAULT NULL,
  `capacity` int unsigned DEFAULT NULL,
  `requires_registration` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('draft','published','cancelled','completed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'published',
  `publish_at` datetime DEFAULT NULL,
  `recurrence_pattern` enum('none','daily','weekly','biweekly','monthly') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `recurrence_days_json` text COLLATE utf8mb4_general_ci,
  `recurrence_until` date DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `events_created_by_foreign` (`created_by`),
  KEY `parish_id_start_at` (`parish_id`,`start_at`),
  KEY `status` (`status`),
  KEY `idx_events_calendar_start` (`calendar_id`,`start_at`),
  KEY `idx_events_scope_dates` (`scope_level`,`scope_diocese_id`,`parish_id`,`start_at`),
  KEY `fk_events_scope_diocese_id` (`scope_diocese_id`),
  KEY `idx_event_audience_team_id` (`audience_team_id`),
  KEY `idx_event_audience_family_id` (`audience_family_id`),
  CONSTRAINT `events_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_event_audience_family` FOREIGN KEY (`audience_family_id`) REFERENCES `family` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_event_audience_team` FOREIGN KEY (`audience_team_id`) REFERENCES `team` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_events_calendar_id` FOREIGN KEY (`calendar_id`) REFERENCES `event_calendar` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_events_scope_diocese_id` FOREIGN KEY (`scope_diocese_id`) REFERENCES `diocese` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_calendar` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `scope_level` enum('parish','diocese') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `color_hex` varchar(7) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '#0d6efd',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_calendars_diocese_id_foreign` (`diocese_id`),
  KEY `event_calendars_created_by_foreign` (`created_by`),
  KEY `idx_event_calendars_scope` (`scope_level`,`parish_id`,`diocese_id`,`is_active`),
  KEY `event_calendars_parish_id_foreign` (`parish_id`),
  CONSTRAINT `event_calendars_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `event_calendars_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `event_calendars_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6864 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `accounting_period` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `fiscal_year` smallint unsigned NOT NULL,
  `period_number` tinyint unsigned NOT NULL,
  `period_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','soft_closed','closed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parish_id_fiscal_year_period_number` (`parish_id`,`fiscal_year`,`period_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `annual_pledge` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `parish_id` int unsigned NOT NULL,
  `pledge_year` int unsigned NOT NULL,
  `pledged_amount` decimal(10,2) DEFAULT NULL,
  `fulfilled_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `pledged_at` date DEFAULT NULL,
  `fulfillment_status` enum('pledged','partial','fulfilled','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pledged',
  `status_id` int unsigned DEFAULT '1',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_annual_pledges_user_parish_year` (`user_id`,`parish_id`,`pledge_year`),
  KEY `idx_annual_pledges_parish_year` (`parish_id`,`pledge_year`),
  KEY `idx_annual_pledges_user_year` (`user_id`,`pledge_year`),
  CONSTRAINT `annual_pledges_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `annual_pledges_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `attendance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `attended` tinyint(1) NOT NULL DEFAULT '0',
  `communicant` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_service_user` (`service_id`,`user_id`),
  KEY `service_id_user_id` (`service_id`,`user_id`),
  KEY `idx_attended` (`attended`),
  KEY `idx_service_user` (`service_id`,`user_id`),
  KEY `idx_user_attended` (`user_id`,`attended`),
  CONSTRAINT `attendance_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `attendance_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=161 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `action` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `entity_type` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `old_values` longtext COLLATE utf8mb4_general_ci,
  `new_values` longtext COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_action_created` (`action`,`created_at`),
  KEY `idx_audit_user_church` (`user_id`,`parish_id`),
  KEY `entity_type` (`entity_type`),
  KEY `entity_id` (`entity_id`),
  KEY `audit_logs_parish_id_foreign` (`parish_id`),
  CONSTRAINT `audit_logs_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `auth_groups_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `group` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_groups_users_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_groups_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `auth_identities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `secret` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `secret2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `extra` text COLLATE utf8mb4_general_ci,
  `force_reset` tinyint(1) NOT NULL DEFAULT '0',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_secret` (`type`,`secret`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `auth_identities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=220 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `auth_logins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_type` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `date` datetime NOT NULL,
  `success` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_type_identifier` (`id_type`,`identifier`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=980 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `auth_permissions_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `permission` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_permissions_users_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_permissions_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3567 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `auth_remember_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `selector` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `hashedValidator` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `expires` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `auth_remember_tokens_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_remember_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `auth_token_logins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_type` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `identifier` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `date` datetime NOT NULL,
  `success` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_type_identifier` (`id_type`,`identifier`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `bank_account` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `chart_account_id` int unsigned DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `institution_name` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `masked_account_number` varchar(34) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `routing_last4` varchar(4) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `currency_code` char(3) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'USD',
  `opening_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `opened_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `bank_reconciliation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_account_id` int unsigned NOT NULL,
  `statement_date` date NOT NULL,
  `statement_balance` decimal(14,2) NOT NULL,
  `status` enum('open','closed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `closed_by` int unsigned DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_account_id` (`bank_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `bank_reconciliation_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_reconciliation_id` int unsigned NOT NULL,
  `bank_transaction_id` int unsigned DEFAULT NULL,
  `is_cleared` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_reconciliation_id` (`bank_reconciliation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `bank_transaction` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_account_id` int unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unmatched',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_account_id` (`bank_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `bank_transaction_journal_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_transaction_id` int unsigned NOT NULL,
  `journal_entry_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_transaction_id_journal_entry_id` (`bank_transaction_id`,`journal_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `call_task` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `log_id` int unsigned DEFAULT NULL,
  `assigned_to` int unsigned DEFAULT NULL,
  `phone` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `status` enum('pending','completed','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `scheduled_date` date NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_status` (`parish_id`,`status`),
  KEY `person_id` (`person_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `log_id` (`log_id`),
  CONSTRAINT `call_tasks_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `call_tasks_log_id_foreign` FOREIGN KEY (`log_id`) REFERENCES `followup_log` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `campaign_delivery_event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int unsigned NOT NULL,
  `recipient_id` int unsigned DEFAULT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `metadata` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_role_catalog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('parish','diocese','province','system') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `role_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scope_role_key` (`scope`,`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `clergy_appointment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `appointment_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `licensed_on` date DEFAULT NULL,
  `started_on` date DEFAULT NULL,
  `ended_on` date DEFAULT NULL,
  `license_reference` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_person_id` (`parish_id`,`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `clergy_license_document` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `clergy_appointment_id` int unsigned NOT NULL,
  `document_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `issued_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clergy_appointment_id` (`clergy_appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `communication_campaign` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `template_id` int unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `channel` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'email',
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `communication_recipient` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_id_person_id` (`campaign_id`,`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `communication_suppression` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `channel` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `reason` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `suppressed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_address` (`channel`,`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `communication_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `channel` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'email',
  `subject` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `body_text` text COLLATE utf8mb4_general_ci,
  `body_html` text COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `dashboard_personalization` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `layout_json` longtext COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `data_backfill_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `run_id` int unsigned NOT NULL,
  `entity_type` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `error` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `run_id` (`run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `data_backfill_run` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `error_log` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `data_quality_snapshot` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `scope_type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `metric_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `value` int NOT NULL,
  `captured_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_metric_key` (`parish_id`,`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `diocesan_attendance_monthly` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `period_month` date NOT NULL COMMENT 'Normalized month start date (YYYY-MM-01).',
  `sunday_services_count` int unsigned NOT NULL DEFAULT '0',
  `sunday_attended_total` int unsigned NOT NULL DEFAULT '0',
  `sunday_attendance_average` decimal(10,2) NOT NULL DEFAULT '0.00',
  `weekday_services_count` int unsigned NOT NULL DEFAULT '0',
  `weekday_attended_total` int unsigned NOT NULL DEFAULT '0',
  `weekday_attendance_average` decimal(10,2) NOT NULL DEFAULT '0.00',
  `source_last_service_date` date DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_da_diocese_parish_month` (`diocese_id`,`parish_id`,`period_month`),
  KEY `idx_da_month_diocese` (`diocese_id`,`period_month`),
  KEY `idx_da_month_church` (`parish_id`,`period_month`),
  CONSTRAINT `diocesan_attendance_monthly_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `diocesan_attendance_monthly_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `diocesan_sunday_monthly_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `period_month` date NOT NULL COMMENT 'First day of month for the monthly bucket.',
  `sunday_services_count` int unsigned NOT NULL DEFAULT '0',
  `sunday_attended_total` int unsigned NOT NULL DEFAULT '0',
  `sunday_communion_total` int unsigned NOT NULL DEFAULT '0',
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dsmm_diocese_period` (`diocese_id`,`period_month`),
  KEY `idx_dsmm_period_month` (`period_month`),
  CONSTRAINT `diocesan_sunday_monthly_metrics_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `diocesan_sunday_service_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `service_id` int unsigned NOT NULL COMMENT 'Reference to services.id',
  `service_date` date NOT NULL,
  `present_count` int unsigned NOT NULL DEFAULT '0' COMMENT 'Attendance rows marked attended=1 for the service.',
  `communion_count` int unsigned NOT NULL DEFAULT '0' COMMENT 'Attendance rows marked communicant=1 for the service.',
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dssm_service` (`service_id`),
  KEY `idx_dssm_diocese_date` (`diocese_id`,`service_date`),
  KEY `idx_dssm_parish_date` (`parish_id`,`service_date`),
  CONSTRAINT `diocesan_sunday_service_metrics_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `diocesan_sunday_service_metrics_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `diocesan_sunday_service_metrics_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `diocesan_sunday_weekly_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `period_start` date NOT NULL COMMENT 'Monday date for the weekly bucket.',
  `sunday_services_count` int unsigned NOT NULL DEFAULT '0',
  `sunday_attended_total` int unsigned NOT NULL DEFAULT '0',
  `sunday_communion_total` int unsigned NOT NULL DEFAULT '0',
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dswm_diocese_period` (`diocese_id`,`period_start`),
  KEY `idx_dswm_period_start` (`period_start`),
  CONSTRAINT `diocesan_sunday_weekly_metrics_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `diocese_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diocese_setting_diocese_id_key` (`diocese_id`,`key`),
  CONSTRAINT `diocese_setting_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `donation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `fund_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL COMMENT 'null for anonymous/non-member',
  `person_id` int unsigned DEFAULT NULL,
  `amount` text COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'offering',
  `chart_account_id` int unsigned DEFAULT NULL,
  `payment_method` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `is_restricted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'TEC Parochial: restricted vs. unrestricted gift',
  `restriction_purpose` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Describes the restriction if is_restricted=1',
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  KEY `idx_parish_date` (`parish_id`,`date`),
  KEY `idx_donations_chart_account` (`chart_account_id`),
  KEY `idx_donations_fund_id` (`fund_id`),
  KEY `idx_date_range` (`date`,`parish_id`),
  CONSTRAINT `donations_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `donations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_donations_chart_account` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_donations_fund_id` FOREIGN KEY (`fund_id`) REFERENCES `fund` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `donation_idempotency` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `donation_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `electoral_roll_entry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_affiliation_id` int unsigned NOT NULL,
  `report_year` smallint unsigned NOT NULL,
  `is_eligible` tinyint(1) NOT NULL DEFAULT '1',
  `qualified_on` date DEFAULT NULL,
  `removed_on` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_affiliation_id_report_year` (`parish_affiliation_id`,`report_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `stored_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `mime_type` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `attachment_type` enum('image','document') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'document',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `event_attachments_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_invitee` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id_user_id` (`event_id`,`user_id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `event_invitee_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `event_invitee_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_recurrence_day` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `occur_date` date NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id_occur_date` (`event_id`,`occur_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_registration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `guest_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `guest_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `guest_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('registered','checked_in','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'registered',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_registrations_user_id_foreign` (`user_id`),
  KEY `event_id_status` (`event_id`,`status`),
  KEY `parish_id` (`parish_id`),
  CONSTRAINT `event_registrations_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `event_registrations_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `event_registrations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `event_registration_response` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` int unsigned NOT NULL,
  `question` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `answer` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `registration_id` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `expense` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `fund_id` int unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., utilities, salaries, maintenance',
  `chart_account_id` int unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `is_capital` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'TEC Parochial: capital vs. operating expense',
  PRIMARY KEY (`id`),
  KEY `expenses_approved_by_foreign` (`approved_by`),
  KEY `parish_id` (`parish_id`),
  KEY `date` (`date`),
  KEY `idx_expenses_chart_account` (`chart_account_id`),
  KEY `idx_expenses_fund_id` (`fund_id`),
  KEY `idx_parish_date` (`parish_id`,`date`),
  KEY `idx_date_range` (`date`,`parish_id`),
  CONSTRAINT `expenses_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `expenses_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_chart_account` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_fund_id` FOREIGN KEY (`fund_id`) REFERENCES `fund` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `facility` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `location_notes` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `capacity` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `facility_reservation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `facility_id` int unsigned NOT NULL,
  `event_id` int unsigned DEFAULT NULL,
  `reserved_by_person_id` int unsigned DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `facility_id_start_at` (`facility_id`,`start_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `facility_reservation_slot` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `facility_id` int unsigned NOT NULL,
  `slot_start` datetime NOT NULL,
  `slot_end` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reservation_id` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `facility_resource` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` int unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `resource_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'equipment',
  `quantity_available` int unsigned NOT NULL DEFAULT '1',
  `is_bookable` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `facility_id` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `family_member` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `relationship` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'e.g., Head, Spouse, Child, etc.',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_family_members_family_user` (`family_id`,`user_id`),
  KEY `family_members_user_id_foreign` (`user_id`),
  KEY `family_id_user_id` (`family_id`,`user_id`),
  KEY `idx_family_members_relationship` (`relationship`),
  CONSTRAINT `family_members_family_id_foreign` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `family_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `file_attachment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `domain` enum('users','parishes','documents') COLLATE utf8mb4_general_ci NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `storage_key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `domain_entity_id` (`domain`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `followup_action` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sequence_id` int unsigned NOT NULL,
  `week_number` tinyint unsigned NOT NULL COMMENT 'Week within the sequence, starting at 1',
  `day_offset` smallint unsigned NOT NULL COMMENT 'Days after first visit date to schedule this action',
  `channel` enum('text','email','call','mail','gift') COLLATE utf8mb4_general_ci NOT NULL,
  `template_id` int unsigned DEFAULT NULL COMMENT 'Nullable: call/gift actions may have no message template',
  `is_automated` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = dispatched by system',

CREATE TABLE `followup_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `action_id` int unsigned DEFAULT NULL COMMENT 'Nullable: manual/ad-hoc log entries may not map to a sequence action',
  `assigned_to` int unsigned DEFAULT NULL COMMENT 'User responsible for execution; null for automated actions',
  `scheduled_date` date DEFAULT NULL COMMENT 'Target date for this action (visit_date + day_offset)',
  `executed_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `response_metadata` text COLLATE utf8mb4_general_ci,
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `followup_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`),
  KEY `action_id` (`action_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `scheduled_date` (`scheduled_date`),
  KEY `status` (`status`),
  KEY `status_scheduled_date` (`status`,`scheduled_date`),
  KEY `person_id_scheduled_date` (`person_id`,`scheduled_date`),
  CONSTRAINT `guest_followup_log_action_id_foreign` FOREIGN KEY (`action_id`) REFERENCES `followup_action` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `guest_followup_log_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_followup_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `followup_sequence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g. 6-Week Framework, New Believer Track',
  `description` text COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `followup_sequences_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1406 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `followup_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `channel` enum('text','email','call','mail','gift') COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Delivery channel this template is written for',
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Internal label for the template',
  `subject` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Email subject line',

CREATE TABLE `governance_term` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `role` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `body` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `started_on` date DEFAULT NULL,
  `ended_on` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_person_id` (`parish_id`,`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `journal_entry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `entry_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `period_id` int unsigned DEFAULT NULL,
  `entry_date` date NOT NULL,
  `source_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'manual',
  `source_id` int unsigned DEFAULT NULL,
  `memo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('draft','posted','void') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `posted_by` int unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parish_id_entry_number` (`parish_id`,`entry_number`),
  KEY `parish_id_entry_date` (`parish_id`,`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `journal_entry_balance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int unsigned NOT NULL,
  `chart_account_id` int unsigned NOT NULL,
  `balance_after` decimal(14,2) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `journal_entry_line` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int unsigned NOT NULL,
  `chart_account_id` int unsigned NOT NULL,
  `debit_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `credit_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `memo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `kpi_target` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `scope_type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `metric_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `target_value` decimal(14,2) NOT NULL,
  `period_year` smallint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_metric_key` (`parish_id`,`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `leadership_position` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `scope_type` enum('parish','diocese','province') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `parish_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `province_id` int unsigned DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `role_code` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `started_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `appointed_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `employment_type` enum('full_time','part_time','non_stipendiary','unpaid') COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Stipend/employment classification',
  `role_category` enum('clergy','lay_staff') COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Broad category for Parochial Report staffing lines',
  `clergy_order` enum('bishop','priest','deacon') COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Canonical order;

CREATE TABLE `liturgical_calendar_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `owner_province_id` int unsigned DEFAULT NULL,
  `owner_diocese_id` int unsigned DEFAULT NULL,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `source_tier` enum('system','province','diocese','parish') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'system',
  `is_system_template` tinyint(1) NOT NULL DEFAULT '1',
  `code` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `season` enum('Advent','Christmas','Epiphany','Lent','Holy Week','Easter','Pentecost','Season after Pentecost') COLLATE utf8mb4_general_ci NOT NULL,
  `observance_type` enum('sunday','feast','day','other') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'sunday',
  `sort_order` smallint unsigned NOT NULL,
  `liturgical_color` enum('blue','purple','white','green','red','rose','black','gold') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `season_icon` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observance_icon` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `parish_id` (`parish_id`),
  KEY `season` (`season`),
  KEY `observance_type` (`observance_type`),
  KEY `sort_order` (`sort_order`),
  KEY `idx_liturgical_owner_province` (`owner_province_id`),
  KEY `idx_liturgical_owner_diocese` (`owner_diocese_id`),
  KEY `idx_liturgical_created_by` (`created_by_user_id`),
  KEY `idx_liturgical_source_tier` (`source_tier`),
  KEY `idx_liturgical_is_system_template` (`is_system_template`),
  CONSTRAINT `fk_liturgical_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_liturgical_owner_diocese` FOREIGN KEY (`owner_diocese_id`) REFERENCES `diocese` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_liturgical_owner_province` FOREIGN KEY (`owner_province_id`) REFERENCES `province` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `liturgical_calendar_entries_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `member_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_discipline_user_period` (`user_id`,`start_date`,`end_date`),
  KEY `idx_member_discipline_active_start` (`is_active`,`start_date`),
  CONSTRAINT `member_discipline_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `member_standing_summary` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `year` int unsigned NOT NULL,
  `is_active_member` tinyint(1) NOT NULL DEFAULT '0',
  `attendance_count` int unsigned NOT NULL DEFAULT '0',
  `communion_count` int unsigned NOT NULL DEFAULT '0',
  `has_pledged` tinyint(1) NOT NULL DEFAULT '0',
  `is_under_discipline` tinyint(1) NOT NULL DEFAULT '0',
  `is_good_standing` tinyint(1) NOT NULL DEFAULT '0',
  `last_updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_member_standing_summary_user_year` (`user_id`,`year`),
  KEY `idx_member_standing_summary_year_status` (`year`,`is_good_standing`),
  KEY `idx_member_standing_summary_user_year` (`user_id`,`year`),
  CONSTRAINT `member_standing_summary_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `membership_stage_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_affiliation_id` int unsigned NOT NULL,
  `pathway_stage` enum('newcomer','inquirer','attendee','member','confirmed','transferred_in','transferred_out') COLLATE utf8mb4_general_ci NOT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `source` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_affiliation_id` (`parish_affiliation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `migrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `class` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `group` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `namespace` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `time` int NOT NULL,
  `batch` int unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ministry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ministry_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ministry_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `role` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `started_on` date DEFAULT NULL,
  `ended_on` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ministry_id_person_id` (`ministry_id`,`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `body` text COLLATE utf8mb4_general_ci,
  `action_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notification_recipient` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_notification_id` (`user_id`,`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `organisation_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_organisation_setting_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_affiliation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `linked_user_id` int unsigned DEFAULT NULL,
  `affiliation_type` enum('guest','attendee','member') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'guest',
  `membership_status` enum('visitor','regular_attender','member','inactive','transferred','deceased') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'visitor',
  `pathway_stage` tinyint unsigned DEFAULT NULL,
  `is_member` tinyint(1) GENERATED ALWAYS AS ((`membership_status` in (_utf8mb4'member',_utf8mb4'inactive'))) STORED,
  `source` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'system',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `followed_up` tinyint(1) NOT NULL DEFAULT '0',
  `converted_to_member_at` datetime DEFAULT NULL,
  `converted_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `parish_id_person_id` (`parish_id`,`person_id`),
  KEY `linked_user_id` (`linked_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_budget` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `chart_account_id` int unsigned NOT NULL,
  `fiscal_year` smallint unsigned NOT NULL,
  `budget_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parish_budgets` (`parish_id`,`chart_account_id`,`fiscal_year`),
  KEY `parish_budgets_chart_account_id_foreign` (`chart_account_id`),
  KEY `parish_budgets_created_by_foreign` (`created_by`),
  KEY `parish_budgets_updated_by_foreign` (`updated_by`),
  KEY `parish_id_fiscal_year` (`parish_id`,`fiscal_year`),
  CONSTRAINT `parish_budgets_chart_account_id_foreign` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `parish_budgets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `parish_budgets_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `parish_budgets_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_integration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `provider` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `integration_type` enum('email','sms','payment','forms','calendar','other') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'other',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `webhook_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `webhook_secret` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `sync_status` enum('idle','syncing','error','paused') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'idle',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parish_provider` (`parish_id`,`provider`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `parish_integration_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_provider_credential` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `integration_id` int unsigned NOT NULL,
  `credential_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `credential_value` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '1',
  `last_rotated` datetime DEFAULT NULL,
  `rotation_interval_days` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_integration_credential` (`integration_id`,`credential_key`),
  KEY `idx_cred_active` (`is_active`),
  CONSTRAINT `parish_provider_credentials_integration_id_foreign` FOREIGN KEY (`integration_id`) REFERENCES `parish_integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_report` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `report_year` smallint unsigned NOT NULL,
  `report_status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `finalized_at` datetime DEFAULT NULL,
  `finalized_by` int unsigned DEFAULT NULL,
  `frozen_snapshot_json` longtext COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parish_id_report_year` (`parish_id`,`report_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_report_storage` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `report_year` smallint unsigned NOT NULL COMMENT 'Four-digit fiscal/calendar year',
  `report_status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `mailing_address_1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_address_2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_city` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_state` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_zip` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_county` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_country` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `report_prepared_by_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `report_prepared_by_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `report_prepared_by_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `financial_prepared_by_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `financial_prepared_by_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `financial_prepared_by_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clerk_certified_by_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clerk_certified_by_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clerk_certified_by_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clerk_certified_date` date DEFAULT NULL,
  `treasurer_certified_by_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `treasurer_certified_by_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `treasurer_certified_by_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `treasurer_certified_date` date DEFAULT NULL,
  `rector_certified_by_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rector_certified_by_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rector_certified_by_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rector_certified_date` date DEFAULT NULL,
  `warden_approved_by_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `warden_approved_by_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `warden_approved_by_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `warden_approved_date` date DEFAULT NULL,
  `completion_participants_count` int DEFAULT NULL,
  `completion_participant_roles` text COLLATE utf8mb4_general_ci,
  `outreach_beneficiaries_weekly` int DEFAULT NULL,
  `nonmember_volunteers_weekly` int DEFAULT NULL,
  `nonlive_views_estimated` tinyint(1) DEFAULT '0',
  `membership_previous_total` int DEFAULT NULL,
  `membership_increase_total` int DEFAULT NULL,
  `membership_decrease_total` int DEFAULT NULL,
  `active_participants_total` int DEFAULT NULL,
  `primary_worship_leader` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clergy_situation_json` text COLLATE utf8mb4_general_ci,
  `programs_json` text COLLATE utf8mb4_general_ci,
  `programs_other` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subsidiary_organizations_json` text COLLATE utf8mb4_general_ci,
  `subsidiary_organizations_other` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `languages_other` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `simultaneous_multilingual_service` tinyint(1) DEFAULT '0',
  `percent_white` decimal(5,2) DEFAULT NULL,
  `average_age_override` decimal(5,2) DEFAULT NULL,
  `reporting_currency` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `confirmed_pledges_count` int DEFAULT NULL,
  `confirmed_pledges_amount` decimal(14,2) DEFAULT NULL,
  `other_revenue_amount` decimal(14,2) DEFAULT NULL,
  `cash_balance` decimal(14,2) DEFAULT NULL,
  `endowment_restricted` decimal(14,2) DEFAULT NULL,
  `endowment_unrestricted` decimal(14,2) DEFAULT NULL,
  `investment_restricted` decimal(14,2) DEFAULT NULL,
  `investment_unrestricted` decimal(14,2) DEFAULT NULL,
  `pct_draw` decimal(5,2) DEFAULT NULL COMMENT 'Percentage draw from endowment (e.g. 5.00 = 5%)',
  `amount_drawn_operating` decimal(14,2) DEFAULT NULL,
  `amount_drawn_capital` decimal(14,2) DEFAULT NULL,
  `amount_drawn` decimal(14,2) DEFAULT NULL,
  `investment_net_gain_loss` decimal(14,2) DEFAULT NULL,
  `has_debt` tinyint(1) NOT NULL DEFAULT '0',
  `debt_amount` decimal(14,2) DEFAULT NULL,
  `debt_description` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `owns_property` tinyint(1) NOT NULL DEFAULT '0',
  `rents_to_outside` tinyint(1) NOT NULL DEFAULT '0',
  `rental_income` decimal(14,2) DEFAULT NULL,
  `rental_expense` decimal(14,2) DEFAULT NULL,
  `carbon_footprint_notes` text COLLATE utf8mb4_general_ci,
  `exemplary_program_text` text COLLATE utf8mb4_general_ci,
  `future_hope_text` text COLLATE utf8mb4_general_ci,
  `frozen_snapshot_json` longtext COLLATE utf8mb4_general_ci,
  `finalized_at` datetime DEFAULT NULL,
  `finalized_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parish_id_report_year` (`parish_id`,`report_year`),
  CONSTRAINT `parish_financial_summary_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_role_audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `actor_user_id` int unsigned NOT NULL,
  `target_user_id` int unsigned NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`),
  KEY `actor_user_id` (`actor_user_id`),
  KEY `target_user_id` (`target_user_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `parish_role_audit_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `parish_role_audit_logs_target_user_id_foreign` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `parish_role_audit_log_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parish_setting` (`parish_id`,`key`),
  CONSTRAINT `parish_setting_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parish_user_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_user_id_role_key` (`parish_id`,`user_id`,`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `period_close_batch` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `period_id` int unsigned NOT NULL,
  `initiated_by` int unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `error_log` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `period_id` (`period_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person_address` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `address_type` enum('mailing','physical','seasonal','other') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'mailing',
  `line1` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `line2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'United States',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person_background_check` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `check_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `checked_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `provider` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person_communication_consent_event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `channel` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person_communication_preference` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `channel` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `opted_in` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_id_channel` (`person_id`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person_contact` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `contact_type` enum('email','mobile','home_phone','work_phone','emergency_phone','other') COLLATE utf8mb4_general_ci NOT NULL,
  `contact_value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person_safeguarding_training` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `training_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `completed_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `certificate_ref` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_id_key` (`person_id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `person_transfer_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `from_parish_id` int unsigned DEFAULT NULL,
  `to_parish_id` int unsigned DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pledge_idempotency` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `pledge_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pledge_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `portal_access_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_status` (`parish_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pre_registration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `profile` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `province_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `middle_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nickname` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` text COLLATE utf8mb4_general_ci,
  `phone_country_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone_hash` char(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `address_line_2` text COLLATE utf8mb4_general_ci,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `zip` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `locale` varchar(10) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'en',
  `social_media` json DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `baptism_date` date DEFAULT NULL,
  `confirmation_date` date DEFAULT NULL,
  `is_confirmed` tinyint unsigned NOT NULL DEFAULT '0',
  `spiritual_gifts` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `portal_access` enum('none','invited','enabled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `portal_enabled_at` datetime DEFAULT NULL,
  `is_dependent` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Age < 18 or otherwise a dependent of a household member',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `person_id` (`person_id`),
  KEY `parish_id` (`parish_id`),
  KEY `idx_profiles_diocese_id` (`diocese_id`),
  KEY `idx_profiles_province_id` (`province_id`),
  KEY `idx_profiles_phone_hash` (`phone_hash`),
  KEY `idx_profiles_portal_access` (`portal_access`),
  CONSTRAINT `profiles_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `profiles_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `profiles_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=225 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `province_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int unsigned NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_province_setting_province_id_key` (`province_id`,`key`),
  CONSTRAINT `province_setting_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_clergy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `position` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `ordination_date` date DEFAULT NULL,
  `assignment_date` date DEFAULT NULL,
  `status` enum('active','retired','sabbatical','deceased') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `report_clergy_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_financial` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int unsigned NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'DOP',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `category` (`category`),
  CONSTRAINT `report_financial_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_membership` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int unsigned NOT NULL,
  `category` enum('adult_members','youth_members','children','inactive_members','transfers_in','transfers_out','confirmations','weddings','funerals') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'adult_members',
  `count` int unsigned NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `category` (`category`),
  CONSTRAINT `report_membership_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_narratives` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int unsigned NOT NULL,
  `section_title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `section_type` enum('narrative','program','ministry','initiative') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'narrative',
  `content` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `section_type` (`section_type`),
  CONSTRAINT `report_narratives_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_scope_hierarchy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_report_id` int unsigned NOT NULL,
  `child_report_id` int unsigned NOT NULL,
  `scope_level` enum('parish','diocese','province') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `aggregated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_report_id` (`parent_report_id`),
  KEY `child_report_id` (`child_report_id`),
  CONSTRAINT `report_scope_hierarchy_child_report_id_foreign` FOREIGN KEY (`child_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `report_scope_hierarchy_parent_report_id_foreign` FOREIGN KEY (`parent_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_signatory` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `signatory_role` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `signed_at` datetime DEFAULT NULL,
  `digital_signature` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `report_signatory_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sacramental_participant` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sacramental_record_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `participant_role` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sacramental_record_id` (`sacramental_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sacramental_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `sacrament_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `sacrament_date` date DEFAULT NULL,
  `register_ref` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `certificate_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `officiant_person_id` int unsigned DEFAULT NULL,
  `location_notes` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_person_id` (`parish_id`,`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `service_metric` (
  `service_id` int unsigned NOT NULL,
  `format_of_service` enum('In-Person','Hybrid','Online') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `attendance_classification` enum('Regular','Special') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_family` enum('holy_communion','daily_office','other') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_schedule` enum('sunday','weekday','private') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reported_present_count` int unsigned DEFAULT NULL,
  `reported_communion_count` int unsigned DEFAULT NULL,
  `online_live_views` int unsigned DEFAULT NULL,
  `online_recorded_views_7d` int unsigned DEFAULT NULL,
  `online_peak_viewers` int unsigned DEFAULT NULL,
  `languages_json` json DEFAULT NULL,
  `baptism_adult` smallint unsigned DEFAULT NULL,
  `baptism_child` smallint unsigned DEFAULT NULL,
  `confirmation_adult` smallint unsigned DEFAULT NULL,
  `confirmation_teen` smallint unsigned DEFAULT NULL,
  `reception_count` smallint unsigned DEFAULT NULL,
  `marriage_count` smallint unsigned DEFAULT NULL,
  `burial_count` smallint unsigned DEFAULT NULL,
  `eucharist_count` smallint unsigned DEFAULT NULL,
  `daily_office_count` smallint unsigned DEFAULT NULL,
  `online_live_viewers` int unsigned DEFAULT NULL,
  `online_recorded_views` int unsigned DEFAULT NULL,
  `place_labels_json` json DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`service_id`),
  KEY `format_of_service` (`format_of_service`),
  KEY `attendance_classification` (`attendance_classification`),
  CONSTRAINT `service_metrics_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `service_people` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `person_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `service_id_sort_order` (`service_id`,`sort_order`),
  CONSTRAINT `service_people_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `service_worship_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `parish_id` int unsigned NOT NULL,
  `song_id` int unsigned DEFAULT NULL,
  `item_type` enum('song','reading','prayer','sermon','announcement','other') DEFAULT 'song',
  `title` varchar(255) NOT NULL,
  `leader_name` varchar(255) DEFAULT NULL,
  `notes` text,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_sort` (`service_id`,`sort_order`),
  KEY `idx_parish` (`parish_id`),
  KEY `idx_song` (`song_id`),
  CONSTRAINT `fk_swi_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_swi_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_swi_song` FOREIGN KEY (`song_id`) REFERENCES `worship_song` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` text COLLATE utf8mb4_general_ci,
  `type` varchar(31) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'string',
  `context` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `settings_audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` enum('organisation','province','diocese') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'organisation',
  `scope_id` int unsigned DEFAULT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `old_value` longtext COLLATE utf8mb4_general_ci,
  `new_value` longtext COLLATE utf8mb4_general_ci,
  `changed_by` int unsigned NOT NULL,
  `change_reason` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `scope_type_scope_id` (`scope_type`,`scope_id`),
  KEY `setting_key` (`setting_key`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sms_queue` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `to_number` varchar(30) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Destination phone in E.164 format',
  `body` text COLLATE utf8mb4_general_ci NOT NULL,
  `from_number` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Override sender number',

CREATE TABLE `social_media_post` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `platform` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `system_admin_primary` (
  `id` tinyint unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_admin_primary_user_id` (`user_id`),
  CONSTRAINT `fk_system_admin_primary_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `team` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `leader_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `volunteer_teams_leader_id_foreign` (`leader_id`),
  KEY `parish_id` (`parish_id`),
  CONSTRAINT `volunteer_teams_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `volunteer_teams_leader_id_foreign` FOREIGN KEY (`leader_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `team_member` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `team_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `role` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'e.g., coordinator, member',
  `joined_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `team_members_user_id_foreign` (`user_id`),
  KEY `team_id_user_id` (`team_id`,`user_id`),
  CONSTRAINT `team_members_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `team_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_person` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '1',
  `linked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_person_id` (`user_id`,`person_id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_registration_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `province_id` int unsigned NOT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `requested_parish_id` int unsigned DEFAULT NULL,
  `status` enum('pending_profile','pending_review','approved','dismissed','banned') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending_profile',
  `requested_role` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  `review_notes` text COLLATE utf8mb4_general_ci,
  `profile_completed_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `user_registration_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `province_id` (`province_id`),
  KEY `diocese_id` (`diocese_id`),
  KEY `status` (`status`),
  KEY `urr_requested_parish_idx` (`requested_parish_id`),
  CONSTRAINT `user_registration_requests_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `user_registration_requests_province_id_foreign` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_registration_requests_requested_parish_id_foreign` FOREIGN KEY (`requested_parish_id`) REFERENCES `parish` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `user_registration_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `user_registration_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_scope_assignment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `scope_type` enum('system','province','diocese','parish') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `scope_id` int unsigned DEFAULT NULL,
  `role_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `granted_by` int unsigned DEFAULT NULL,
  `granted_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_scope_type_scope_id` (`user_id`,`scope_type`,`scope_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_session` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `session_token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `device_fingerprint` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `is_new_device` tinyint(1) NOT NULL DEFAULT '0',
  `last_active` datetime NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_id_last_active` (`user_id`,`last_active`)
) ENGINE=InnoDB AUTO_INCREMENT=537 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_trusted_device` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `selector` varchar(24) COLLATE utf8mb4_general_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_general_ci NOT NULL,
  `display_name` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `first_seen_at` datetime DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_key` (`user_id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `vendor` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_address` text COLLATE utf8mb4_general_ci,
  `tax_reference` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `vendor_bill` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `vendor_id` int unsigned NOT NULL,
  `bill_number` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `bill_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('draft','approved','partially_paid','paid','void') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `subtotal_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `outstanding_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_by` int unsigned DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_vendor_id` (`parish_id`,`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `vendor_bill_line` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vendor_bill_id` int unsigned NOT NULL,
  `chart_account_id` int unsigned NOT NULL,
  `fund_id` int unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_bill_id` (`vendor_bill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `vendor_payment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `vendor_bill_id` int unsigned DEFAULT NULL,
  `bank_account_id` int unsigned DEFAULT NULL,
  `payment_number` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('scheduled','processed','void') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'processed',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `vendor_payment_allocation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vendor_payment_id` int unsigned NOT NULL,
  `vendor_bill_id` int unsigned NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_payment_id_vendor_bill_id` (`vendor_payment_id`,`vendor_bill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `volunteer_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `team_id` int unsigned NOT NULL,
  `service_id` int unsigned DEFAULT NULL COMMENT 'optional link to a specific service',
  `user_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `time_slot` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'e.g., morning, afternoon',
  `status` enum('scheduled','confirmed','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'scheduled',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `service_date` date DEFAULT NULL,
  `service_time_slot` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `volunteer_schedule_service_id_foreign` (`service_id`),
  KEY `team_id` (`team_id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  CONSTRAINT `volunteer_schedule_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `volunteer_schedule_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `volunteer_schedule_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `webhook_delivery_attempt` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `target_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_general_ci,
  `response_code` int DEFAULT NULL,
  `response_body` text COLLATE utf8mb4_general_ci,
  `attempt_number` tinyint NOT NULL DEFAULT '1',
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `worship_song` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `artist` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `key_signature` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tempo` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ccli` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `lyrics` longtext COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parish_id_title` (`parish_id`,`title`),
  CONSTRAINT `worship_songs_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `worship_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `liturgy` text COLLATE utf8mb4_general_ci,
  `readings` text COLLATE utf8mb4_general_ci,
  `hymns` text COLLATE utf8mb4_general_ci,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

