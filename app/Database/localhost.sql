
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `accounting_period`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_period` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `fiscal_year` smallint unsigned NOT NULL,
  `period_number` tinyint unsigned NOT NULL,
  `period_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','soft_closed','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ap_parish_year_period` (`parish_id`,`fiscal_year`,`period_number`),
  CONSTRAINT `fk_ap_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `annual_pledge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `annual_pledge` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned NOT NULL,
  `parish_id` int unsigned NOT NULL,
  `pledge_year` int unsigned NOT NULL,
  `pledged_amount` decimal(12,2) DEFAULT NULL,
  `fulfilled_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pledged_at` date DEFAULT NULL,
  `status_id` int unsigned NOT NULL DEFAULT '1',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `fulfillment_status` enum('pledged','partial','fulfilled','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pledged',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pledge_parish_person_year` (`parish_id`,`person_id`,`pledge_year`),
  KEY `idx_pledge_parish_year` (`parish_id`,`pledge_year`),
  KEY `idx_pledge_user` (`user_id`),
  KEY `idx_pledge_status` (`status_id`),
  KEY `idx_pledge_person` (`person_id`),
  CONSTRAINT `fk_pledge_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pledge_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pledge_status` FOREIGN KEY (`status_id`) REFERENCES `pledge_status` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_pledge_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `parish_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned NOT NULL,
  `attended` tinyint(1) NOT NULL DEFAULT '0',
  `communicant` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance_service_person` (`service_id`,`person_id`),
  KEY `idx_attendance_user` (`user_id`),
  KEY `idx_attendance_parish_date` (`parish_id`,`created_at`),
  KEY `idx_attendance_person` (`person_id`),
  CONSTRAINT `fk_attendance_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `action` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `entity_id` int unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_parish` (`parish_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=532 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_groups_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_groups_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `permission` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group` (`group`),
  KEY `permission` (`permission`)
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_groups_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_groups_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_groups_users_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_groups_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_identities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `secret2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `extra` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `force_reset` tinyint(1) NOT NULL DEFAULT '0',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `invited_at` datetime DEFAULT NULL,
  `invitation_sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_secret` (`type`,`secret`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `auth_identities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_logins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `date` datetime NOT NULL,
  `success` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_type_identifier` (`id_type`,`identifier`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=627 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_permissions_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_permissions_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `permission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `auth_permissions_users_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_permissions_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=258 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_remember_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `selector` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hashedValidator` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `expires` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `auth_remember_tokens_user_id_foreign` (`user_id`),
  CONSTRAINT `auth_remember_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_token_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_token_logins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `date` datetime NOT NULL,
  `success` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_type_identifier` (`id_type`,`identifier`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_users_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_users_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `permission` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `type` enum('grant','revoke') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'grant' COMMENT 'Grant adds permission, revoke removes it from group permissions',
  `created_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL COMMENT 'User ID who granted/revoked this permission',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_permission` (`user_id`,`permission`),
  KEY `user_id` (`user_id`),
  KEY `permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_account`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_account` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `chart_account_id` int unsigned DEFAULT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `institution_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `masked_account_number` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `routing_last4` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `currency_code` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'USD',
  `opening_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `opened_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ba_chart_account` (`chart_account_id`),
  KEY `idx_ba_parish` (`parish_id`),
  CONSTRAINT `fk_ba_chart_account` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_ba_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reconciliation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `bank_account_id` int unsigned NOT NULL,
  `period_end_date` date NOT NULL,
  `statement_ending_balance` decimal(14,2) NOT NULL,
  `cleared_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `difference_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','balanced','posted') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `prepared_by` int unsigned DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_br_account_period_end` (`bank_account_id`,`period_end_date`),
  KEY `fk_br_parish` (`parish_id`),
  KEY `fk_br_prepared_by` (`prepared_by`),
  KEY `fk_br_approved_by` (`approved_by`),
  CONSTRAINT `fk_br_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_br_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_br_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_br_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_reconciliation_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reconciliation_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation_id` int unsigned NOT NULL,
  `bank_transaction_id` bigint unsigned NOT NULL,
  `cleared_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bri_recon_transaction` (`reconciliation_id`,`bank_transaction_id`),
  KEY `fk_bri_bank_transaction` (`bank_transaction_id`),
  CONSTRAINT `fk_bri_bank_transaction` FOREIGN KEY (`bank_transaction_id`) REFERENCES `bank_transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bri_reconciliation` FOREIGN KEY (`reconciliation_id`) REFERENCES `bank_reconciliation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_transaction` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `bank_account_id` int unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `posted_date` date DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `transaction_type` enum('deposit','withdrawal','fee','adjustment') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'withdrawal',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_id` int unsigned DEFAULT NULL,
  `reconciliation_status` enum('unmatched','cleared','reconciled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unmatched',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bt_parish` (`parish_id`),
  KEY `idx_bt_account_date` (`bank_account_id`,`transaction_date`),
  CONSTRAINT `fk_bt_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bt_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transaction_journal_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_transaction_journal_link` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bank_transaction_id` bigint unsigned NOT NULL,
  `journal_entry_id` int unsigned NOT NULL,
  `link_type` enum('auto','manual','adjustment') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'manual',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_btjl_unique` (`bank_transaction_id`,`journal_entry_id`),
  KEY `fk_btjl_created_by` (`created_by`),
  KEY `idx_btjl_journal_entry` (`journal_entry_id`),
  CONSTRAINT `fk_btjl_bank_transaction` FOREIGN KEY (`bank_transaction_id`) REFERENCES `bank_transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_btjl_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_btjl_journal_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entry` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `call_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `call_task` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `guest_id` int unsigned NOT NULL,
  `log_id` int unsigned DEFAULT NULL,
  `assigned_to` int unsigned DEFAULT NULL,
  `phone` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `status` enum('pending','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `scheduled_date` date NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_call_parish` (`parish_id`),
  KEY `idx_call_guest` (`guest_id`),
  CONSTRAINT `fk_call_guest` FOREIGN KEY (`guest_id`) REFERENCES `guest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_call_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_delivery_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_delivery_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recipient_id` int unsigned NOT NULL,
  `event_type` enum('queued','sent','delivered','opened','failed','suppressed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'queued',
  `provider_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `provider_event_id` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `event_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `occurred_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cde_recipient_type` (`recipient_id`,`event_type`),
  CONSTRAINT `fk_cde_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `communication_recipient` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chart_of_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `account_type` enum('income','expense','asset','liability','equity') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'expense',
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `allow_budget` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coa_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `church`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `church` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `church_role_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `church_role_catalog` (
  `role_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `scope` enum('parish','diocese','province','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clergy_appointment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clergy_appointment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `appointment_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `licensed_on` date DEFAULT NULL,
  `started_on` date DEFAULT NULL,
  `ended_on` date DEFAULT NULL,
  `license_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ca_parish` (`parish_id`),
  KEY `idx_ca_person` (`person_id`),
  KEY `idx_ca_type_start` (`parish_id`,`appointment_type`,`started_on`),
  CONSTRAINT `fk_ca_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ca_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clergy_license_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clergy_license_document` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `instrument_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `license_reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issued_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `file_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','expired','revoked') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cld_parish` (`parish_id`),
  KEY `fk_cld_diocese` (`diocese_id`),
  KEY `idx_cld_person` (`person_id`),
  CONSTRAINT `fk_cld_diocese` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_cld_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_cld_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `communication_campaign`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_campaign` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `template_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `channel` enum('email','sms','push','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'email',
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subject_line` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `body_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `audience_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'custom',
  `status` enum('draft','scheduled','sending','sent','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cc_template` (`template_id`),
  KEY `fk_cc_created_by` (`created_by`),
  KEY `idx_cc_parish_status` (`parish_id`,`status`),
  CONSTRAINT `fk_cc_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_cc_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cc_template` FOREIGN KEY (`template_id`) REFERENCES `communication_template` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `communication_recipient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_recipient` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `person_contact_id` int unsigned DEFAULT NULL,
  `delivery_status` enum('queued','sent','delivered','opened','failed','suppressed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'queued',
  `queued_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cr_person` (`person_id`),
  KEY `fk_cr_person_contact` (`person_contact_id`),
  KEY `idx_cr_campaign_status` (`campaign_id`,`delivery_status`),
  CONSTRAINT `fk_cr_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `communication_campaign` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cr_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_cr_person_contact` FOREIGN KEY (`person_contact_id`) REFERENCES `person_contact` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `communication_suppression`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_suppression` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned DEFAULT NULL,
  `person_contact_id` int unsigned DEFAULT NULL,
  `channel` enum('email','sms','push','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'email',
  `reason` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','lifted') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `suppressed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `lifted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cs_person` (`person_id`),
  KEY `fk_cs_person_contact` (`person_contact_id`),
  KEY `idx_cs_channel_status` (`channel`,`status`),
  CONSTRAINT `fk_cs_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_cs_person_contact` FOREIGN KEY (`person_contact_id`) REFERENCES `person_contact` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `communication_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `channel` enum('email','sms','push','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'email',
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subject_template` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `body_template` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ct_parish` (`parish_id`),
  CONSTRAINT `fk_ct_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dashboard_personalization`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dashboard_personalization` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `dashboard_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `layout_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'v1',
  `widget_order_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `widget_state_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pinned_action_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `last_viewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dashboard_personalization_user_dashboard` (`user_id`,`dashboard_key`),
  KEY `idx_dashboard_personalization_last_viewed` (`last_viewed_at`),
  CONSTRAINT `fk_dash_personalization_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_backfill_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_backfill_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint unsigned NOT NULL,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `source_pk` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `target_pk` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','processed','skipped','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_backfill_item` (`run_id`,`table_name`,`source_pk`),
  KEY `idx_backfill_item_run_status` (`run_id`,`status`),
  CONSTRAINT `fk_backfill_item_run` FOREIGN KEY (`run_id`) REFERENCES `data_backfill_run` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_backfill_run`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_backfill_run` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('running','completed','failed','paused') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'running',
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `last_checkpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_backfill_run_job_status` (`job_name`,`status`),
  KEY `idx_backfill_run_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_quality_snapshot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_quality_snapshot` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` enum('system','province','diocese','parish') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scope_id` int unsigned NOT NULL DEFAULT '0',
  `period_year_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quality_score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `issue_count` int unsigned NOT NULL DEFAULT '0',
  `blocker_count` int unsigned NOT NULL DEFAULT '0',
  `warning_count` int unsigned NOT NULL DEFAULT '0',
  `detail_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `generated_at` datetime DEFAULT NULL,
  `generated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_data_quality_scope_period` (`scope_type`,`scope_id`,`period_year_month`),
  KEY `idx_data_quality_generated_at` (`generated_at`),
  KEY `fk_data_quality_snapshot_generated_by` (`generated_by`),
  CONSTRAINT `fk_data_quality_snapshot_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocesan_attendance_monthly`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocesan_attendance_monthly` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `parish_id` int unsigned NOT NULL,
  `period_year_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
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
  UNIQUE KEY `uk_diocesan_att_month` (`diocese_id`,`parish_id`,`period_year_month`),
  KEY `idx_dam_parish` (`parish_id`),
  CONSTRAINT `fk_dam_diocese` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dam_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocesan_sunday_monthly_metric`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocesan_sunday_monthly_metric` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `period_year_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sunday_services_count` int unsigned NOT NULL DEFAULT '0',
  `sunday_attended_total` int unsigned NOT NULL DEFAULT '0',
  `sunday_communion_total` int unsigned NOT NULL DEFAULT '0',
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diocesan_month` (`diocese_id`,`period_year_month`),
  CONSTRAINT `fk_dsmm_diocese` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocesan_sunday_monthly_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocesan_sunday_service_metric`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocesan_sunday_service_metric` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `parish_id` int unsigned NOT NULL,
  `service_id` int unsigned NOT NULL,
  `service_date` date NOT NULL,
  `present_count` int unsigned NOT NULL DEFAULT '0',
  `communion_count` int unsigned NOT NULL DEFAULT '0',
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diocesan_service` (`service_id`),
  KEY `fk_dssm_parish` (`parish_id`),
  KEY `idx_dssm_diocese_date` (`diocese_id`,`service_date`),
  CONSTRAINT `fk_dssm_diocese` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dssm_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dssm_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocesan_sunday_service_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  KEY `idx_dssm_church_date` (`parish_id`,`service_date`),
  CONSTRAINT `diocesan_sunday_service_metrics_church_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `diocesan_sunday_service_metrics_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `diocesan_sunday_service_metrics_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocesan_sunday_weekly_metric`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocesan_sunday_weekly_metric` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `period_start` date NOT NULL,
  `sunday_services_count` int unsigned NOT NULL DEFAULT '0',
  `sunday_attended_total` int unsigned NOT NULL DEFAULT '0',
  `sunday_communion_total` int unsigned NOT NULL DEFAULT '0',
  `generated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diocesan_week` (`diocese_id`,`period_start`),
  CONSTRAINT `fk_dswm_diocese` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocesan_sunday_weekly_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocese`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocese` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `alias_norm` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `province_id_name` (`province_id`,`name`),
  KEY `province_id` (`province_id`),
  KEY `name` (`name`),
  KEY `idx_dioceses_province_id` (`province_id`),
  KEY `idx_dioceses_alias_norm` (`alias_norm`),
  CONSTRAINT `fk_diocese_province` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocese_admin_primary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocese_admin_primary` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `diocese_id` (`diocese_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocese_alias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocese_alias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `alias` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `alias_norm` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_diocese_alias` (`diocese_id`),
  CONSTRAINT `fk_diocese_alias` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocese_province_reassignment_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocese_province_reassignment_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `from_province_id` int unsigned DEFAULT NULL,
  `to_province_id` int unsigned NOT NULL,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `performed_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dprl_diocese` (`diocese_id`),
  KEY `idx_dprl_from_province` (`from_province_id`),
  KEY `idx_dprl_to_province` (`to_province_id`),
  KEY `idx_dprl_performed_by` (`performed_by`),
  KEY `idx_dprl_created_at` (`created_at`),
  CONSTRAINT `fk_dprl_diocese` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dprl_from_province` FOREIGN KEY (`from_province_id`) REFERENCES `province` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_dprl_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_dprl_to_province` FOREIGN KEY (`to_province_id`) REFERENCES `province` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diocese_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diocese_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `diocese_id` int unsigned NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_diocese_setting` (`diocese_id`,`key`),
  CONSTRAINT `fk_diocese_setting_diocese` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=764 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `donation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `fund_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL COMMENT 'null for anonymous/non-member',
  `person_id` int unsigned DEFAULT NULL,
  `amount` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'offering',
  `chart_account_id` int unsigned DEFAULT NULL,
  `payment_method` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `is_restricted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'TEC Parochial: restricted vs. unrestricted gift',
  `restriction_purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Describes the restriction if is_restricted=1',
  PRIMARY KEY (`id`),
  KEY `church_id` (`parish_id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  KEY `idx_church_date` (`parish_id`,`date`),
  KEY `idx_donations_chart_account` (`chart_account_id`),
  KEY `idx_donations_fund_id` (`fund_id`),
  KEY `idx_date_range` (`date`,`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `donation_idempotency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donation_idempotency` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `donation_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `donation_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donation_receipts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `donation_id` int unsigned NOT NULL,
  `receipt_number` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `issued_at` datetime NOT NULL,
  `issued_by` int unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_donation_receipts_parish_number` (`parish_id`,`receipt_number`),
  KEY `parish_id` (`parish_id`),
  KEY `donation_id` (`donation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `electoral_roll_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `electoral_roll_entry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_affiliation_id` int unsigned NOT NULL,
  `report_year` smallint unsigned NOT NULL,
  `is_eligible` tinyint(1) NOT NULL DEFAULT '1',
  `qualified_on` date DEFAULT NULL,
  `removed_on` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ere_affiliation_year` (`parish_affiliation_id`,`report_year`),
  KEY `idx_ere_year` (`report_year`),
  CONSTRAINT `fk_ere_affiliation` FOREIGN KEY (`parish_affiliation_id`) REFERENCES `parish_affiliation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `calendar_id` int unsigned DEFAULT NULL,
  `scope_level` enum('parish','diocese','province') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `scope_diocese_id` int unsigned DEFAULT NULL,
  `scope_province_id` int unsigned DEFAULT NULL,
  `organizer_type` enum('national','province','diocese','parish') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `organizer_church_id` int unsigned DEFAULT NULL,
  `organizer_province_id` int unsigned DEFAULT NULL,
  `organizer_diocese_id` int unsigned DEFAULT NULL,
  `organizer_parish_id` int unsigned DEFAULT NULL,
  `host_parish_id` int unsigned DEFAULT NULL,
  `visibility` enum('people','members','public') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'people',
  `schedule_mode` enum('one_day','all_day','multi_day') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'one_day',
  `is_all_day` tinyint(1) NOT NULL DEFAULT '0',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `event_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime DEFAULT NULL,
  `capacity` int unsigned DEFAULT NULL,
  `requires_registration` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('draft','published','cancelled','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'published',
  `recurrence_pattern` enum('none','daily','weekly','biweekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `recurrence_days_json` text COLLATE utf8mb4_general_ci,
  `recurrence_until` date DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_parish` (`parish_id`),
  KEY `idx_event_calendar` (`calendar_id`),
  KEY `idx_events_scope_province` (`scope_province_id`),
  KEY `idx_event_organizer_church` (`organizer_type`,`organizer_church_id`),
  KEY `idx_event_organizer_province` (`organizer_type`,`organizer_province_id`),
  KEY `idx_event_organizer_diocese` (`organizer_type`,`organizer_diocese_id`),
  KEY `idx_event_organizer_parish` (`organizer_type`,`organizer_parish_id`),
  KEY `idx_event_host_parish` (`host_parish_id`),
  CONSTRAINT `fk_event_calendar` FOREIGN KEY (`calendar_id`) REFERENCES `event_calendar` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_event_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_events_scope_province_id` FOREIGN KEY (`scope_province_id`) REFERENCES `province` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_attachment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stored_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mime_type` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `attachment_type` enum('image','document') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'document',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attachment_event` (`event_id`),
  CONSTRAINT `fk_attachment_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stored_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mime_type` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `attachment_type` enum('image','document') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'document',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `event_attachments_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_calendar` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `church_id` int unsigned DEFAULT NULL,
  `province_id` int unsigned DEFAULT NULL,
  `scope_level` enum('national','province','diocese','parish') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `color_hex` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '#0d6efd',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_calendar_parish` (`parish_id`),
  KEY `idx_event_calendar_church` (`church_id`),
  KEY `idx_event_calendar_province` (`province_id`),
  CONSTRAINT `fk_calendar_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_event_calendar_church` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_event_calendar_province` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_invitee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_recurrence_day`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_recurrence_day` (
  `event_id` int unsigned NOT NULL,
  `day_of_week` tinyint unsigned NOT NULL,
  PRIMARY KEY (`event_id`,`day_of_week`),
  CONSTRAINT `fk_recurrence_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_registration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_registration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `guest_id` int unsigned DEFAULT NULL,
  `registration_status` enum('pending','confirmed','waitlisted','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `attendance_status` enum('registered','checked_in','no_show') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'registered',
  `registered_at` datetime DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `source` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'portal',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_er_event_person` (`event_id`,`person_id`),
  UNIQUE KEY `uk_er_event_guest` (`event_id`,`guest_id`),
  KEY `fk_er_parish` (`parish_id`),
  KEY `fk_er_person` (`person_id`),
  KEY `fk_er_guest` (`guest_id`),
  KEY `idx_er_event_status` (`event_id`,`registration_status`),
  CONSTRAINT `fk_er_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_er_guest` FOREIGN KEY (`guest_id`) REFERENCES `guest` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_er_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_er_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_registration_response`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_registration_response` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_registration_id` int unsigned NOT NULL,
  `question_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `response_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_err_registration` (`event_registration_id`),
  CONSTRAINT `fk_err_registration` FOREIGN KEY (`event_registration_id`) REFERENCES `event_registration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `fund_id` int unsigned DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g., utilities, salaries, maintenance',
  `chart_account_id` int unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `is_capital` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'TEC Parochial: capital vs. operating expense',
  PRIMARY KEY (`id`),
  KEY `expenses_approved_by_foreign` (`approved_by`),
  KEY `church_id` (`parish_id`),
  KEY `date` (`date`),
  KEY `idx_expenses_chart_account` (`chart_account_id`),
  KEY `idx_expenses_fund_id` (`fund_id`),
  KEY `idx_church_date` (`parish_id`,`date`),
  KEY `idx_date_range` (`date`,`parish_id`),
  CONSTRAINT `expenses_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `expenses_church_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_chart_account` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_fund_id` FOREIGN KEY (`fund_id`) REFERENCES `funds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `location_notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `capacity` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_facility_parish` (`parish_id`),
  CONSTRAINT `fk_facility_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_reservation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_reservation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `facility_id` int unsigned NOT NULL,
  `event_id` int unsigned DEFAULT NULL,
  `reserved_by_person_id` int unsigned DEFAULT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fres_parish` (`parish_id`),
  KEY `fk_fres_event` (`event_id`),
  KEY `fk_fres_person` (`reserved_by_person_id`),
  KEY `idx_fres_facility_start` (`facility_id`,`start_at`),
  CONSTRAINT `fk_fres_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_fres_facility` FOREIGN KEY (`facility_id`) REFERENCES `facility` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fres_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fres_person` FOREIGN KEY (`reserved_by_person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_reservation_slot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_reservation_slot` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `facility_id` int unsigned NOT NULL,
  `slot_start` datetime NOT NULL,
  `slot_end` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_frs_facility_slot` (`facility_id`,`slot_start`),
  KEY `idx_frs_reservation` (`reservation_id`),
  CONSTRAINT `fk_frs_facility` FOREIGN KEY (`facility_id`) REFERENCES `facility` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_frs_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `facility_reservation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facility_resource`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facility_resource` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `facility_id` int unsigned NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `resource_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'equipment',
  `quantity_available` int unsigned NOT NULL DEFAULT '1',
  `is_bookable` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fr_facility` (`facility_id`),
  CONSTRAINT `fk_fr_facility` FOREIGN KEY (`facility_id`) REFERENCES `facility` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `family`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `family` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `family_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `head_of_household_id` int unsigned DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_family_head` (`head_of_household_id`),
  CONSTRAINT `fk_family_head` FOREIGN KEY (`head_of_household_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `family_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_member` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `relationship` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_family_person` (`family_id`),
  CONSTRAINT `fk_fm_family` FOREIGN KEY (`family_id`) REFERENCES `family` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `file_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `file_attachment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `owner_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner_id` int unsigned NOT NULL,
  `domain` enum('users','parishes','documents') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `storage_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_size` int unsigned NOT NULL,
  `sha256` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visibility` enum('public','private') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'private',
  `backend_driver` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'local',
  `scan_status` enum('pending','clean','flagged') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_file_attachment_storage_key` (`storage_key`),
  KEY `idx_file_attachment_owner` (`owner_type`,`owner_id`),
  KEY `idx_file_attachment_scope` (`province_id`,`diocese_id`,`parish_id`),
  KEY `idx_file_attachment_parish` (`parish_id`),
  KEY `idx_file_attachment_uploaded_by` (`uploaded_by`),
  KEY `idx_file_attachment_deleted_at` (`deleted_at`),
  KEY `fk_file_attachment_diocese` (`diocese_id`),
  CONSTRAINT `fk_file_attachment_diocese` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_file_attachment_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_file_attachment_province` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_file_attachment_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `followup_action`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `followup_action` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sequence_id` int unsigned NOT NULL,
  `week_number` tinyint unsigned NOT NULL,
  `day_offset` smallint unsigned NOT NULL,
  `channel` varchar(20) NOT NULL,
  `is_automated` tinyint(1) NOT NULL DEFAULT '0',
  `template_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2604 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `followup_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `followup_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `guest_id` int unsigned DEFAULT NULL,
  `action_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `assigned_to` int unsigned DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `response_metadata` text COLLATE utf8mb4_general_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `followup_date` date NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_followup_parish` (`parish_id`),
  KEY `idx_followup_guest` (`guest_id`),
  KEY `idx_followup_person` (`person_id`),
  CONSTRAINT `fk_followup_guest` FOREIGN KEY (`guest_id`) REFERENCES `guest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_followup_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_followup_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1933 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `followup_sequence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `followup_sequence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1402 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `followup_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `followup_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `channel` enum('text','email','call','mail','gift') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Delivery channel this template is written for',
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Internal label for the template',
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Email subject line; null for non-email channels',
  `body_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Plain-text body; used for SMS/text and email text part',
  `body_html` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'HTML body; used for email only',
  `variables` json DEFAULT NULL COMMENT 'Merge-tag schema, e.g. {"guest_name":"string"}',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `church_id` (`parish_id`),
  KEY `channel` (`channel`),
  CONSTRAINT `followup_templates_church_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fund`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fund` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fund_type` enum('unrestricted','restricted','permanent') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unrestricted',
  `restriction_purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `chart_account_id` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_church_fund_name` (`parish_id`,`name`),
  KEY `funds_created_by_foreign` (`created_by`),
  KEY `church_id` (`parish_id`),
  KEY `church_id_is_active` (`parish_id`,`is_active`),
  KEY `chart_account_id` (`chart_account_id`)
) ENGINE=InnoDB AUTO_INCREMENT=172 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `funds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `funds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fund_parish00` (`parish_id`),
  CONSTRAINT `fk_fund_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `governance_term`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `governance_term` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `office_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `appointment_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `term_start` date DEFAULT NULL,
  `term_end` date DEFAULT NULL,
  `appointment_method` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gt_parish` (`parish_id`),
  KEY `idx_gt_person` (`person_id`),
  KEY `idx_gt_office_term` (`parish_id`,`office_type`,`term_start`),
  CONSTRAINT `fk_gt_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_gt_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `guest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guest` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visit_date` date NOT NULL,
  `service_attended` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `followed_up` tinyint(1) NOT NULL DEFAULT '0',
  `converted_to_member_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_guest_parish` (`parish_id`),
  KEY `idx_guest_person_link` (`person_id`),
  CONSTRAINT `fk_guest_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_guest_person_link` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1393 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `guest_visit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guest_visit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `guest_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visit_type` varchar(20) NOT NULL,
  `service_id` int unsigned DEFAULT NULL,
  `attended_event` varchar(255) DEFAULT NULL,
  `gift_given` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1402 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `entry_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `period_id` int unsigned DEFAULT NULL,
  `entry_date` date NOT NULL,
  `source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'manual',
  `source_id` int unsigned DEFAULT NULL,
  `memo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('draft','posted','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `posted_by` int unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_je_parish_number` (`parish_id`,`entry_number`),
  KEY `fk_je_period` (`period_id`),
  KEY `fk_je_posted_by` (`posted_by`),
  KEY `fk_je_created_by` (`created_by`),
  KEY `idx_je_parish_date` (`parish_id`,`entry_date`),
  CONSTRAINT `fk_je_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_je_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_je_period` FOREIGN KEY (`period_id`) REFERENCES `accounting_period` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_je_posted_by` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entry_balance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entry_balance` (
  `journal_entry_id` int unsigned NOT NULL,
  `total_debits` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_credits` decimal(14,2) NOT NULL DEFAULT '0.00',
  `is_balanced` tinyint(1) NOT NULL DEFAULT '0',
  `computed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`journal_entry_id`),
  CONSTRAINT `fk_jeb_journal_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entry` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entry_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entry_line` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int unsigned NOT NULL,
  `chart_account_id` int unsigned NOT NULL,
  `fund_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `line_memo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `debit_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `credit_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_jel_fund` (`fund_id`),
  KEY `fk_jel_person` (`person_id`),
  KEY `idx_jel_entry` (`journal_entry_id`),
  KEY `idx_jel_chart_account` (`chart_account_id`),
  CONSTRAINT `fk_jel_chart_account` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jel_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entry` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jel_fund` FOREIGN KEY (`fund_id`) REFERENCES `funds` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_jel_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kpi_target`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kpi_target` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` enum('system','province','diocese','parish') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scope_id` int unsigned NOT NULL DEFAULT '0',
  `metric_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `period_year_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `target_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `warning_threshold` decimal(14,2) DEFAULT NULL,
  `critical_threshold` decimal(14,2) DEFAULT NULL,
  `owner_user_id` int unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kpi_target_scope_metric_period` (`scope_type`,`scope_id`,`metric_key`,`period_year_month`),
  KEY `idx_kpi_target_period` (`period_year_month`),
  KEY `fk_kpi_target_owner_user` (`owner_user_id`),
  CONSTRAINT `fk_kpi_target_owner_user` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leadership_position`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leadership_position` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `scope_type` enum('parish','diocese','province') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `parish_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `province_id` int unsigned DEFAULT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `started_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `appointed_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `employment_type` enum('full_time','part_time','non_stipendiary','unpaid') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Stipend/employment classification',
  `role_category` enum('clergy','lay_staff') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Broad category for Parochial Report staffing lines',
  `clergy_order` enum('bishop','priest','deacon') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Canonical order; NULL for lay staff',
  PRIMARY KEY (`id`),
  KEY `leadership_positions_appointed_by_user_id_foreign` (`appointed_by_user_id`),
  KEY `idx_leadership_scope_active` (`scope_type`,`active`),
  KEY `idx_leadership_church_active` (`parish_id`,`active`),
  KEY `idx_leadership_diocese_active` (`diocese_id`,`active`),
  KEY `idx_leadership_province_active` (`province_id`,`active`),
  KEY `idx_leadership_user_active` (`user_id`,`active`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `liturgical_calendar_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `liturgical_calendar_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `owner_province_id` int unsigned DEFAULT NULL,
  `owner_diocese_id` int unsigned DEFAULT NULL,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `source_tier` enum('system','province','diocese','parish') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'system',
  `is_system_template` tinyint(1) NOT NULL DEFAULT '1',
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `season` enum('Advent','Christmas','Epiphany','Lent','Holy Week','Easter','Pentecost','Season after Pentecost') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `observance_type` enum('sunday','feast','day','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'sunday',
  `sort_order` smallint unsigned NOT NULL,
  `liturgical_color` enum('blue','purple','white','green','red','rose','black','gold') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `season_icon` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observance_icon` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `church_id` (`parish_id`),
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
  CONSTRAINT `liturgical_calendar_entries_church_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `liturgical_calendar_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `liturgical_calendar_entry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `owner_province_id` int unsigned DEFAULT NULL,
  `owner_diocese_id` int unsigned DEFAULT NULL,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `source_tier` enum('system','province','diocese','parish') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'system',
  `is_system_template` tinyint(1) NOT NULL DEFAULT '1',
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `season` enum('Advent','Christmas','Epiphany','Lent','Holy Week','Easter','Pentecost','Season after Pentecost') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `observance_type` enum('sunday','feast','day','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'sunday',
  `sort_order` smallint unsigned NOT NULL,
  `liturgical_color` enum('blue','purple','white','green','red','rose','black','gold') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `season_icon` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observance_icon` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `church_id` (`parish_id`),
  KEY `season` (`season`),
  KEY `observance_type` (`observance_type`),
  KEY `sort_order` (`sort_order`),
  KEY `idx_liturgical_owner_province` (`owner_province_id`),
  KEY `idx_liturgical_owner_diocese` (`owner_diocese_id`),
  KEY `idx_liturgical_created_by` (`created_by_user_id`),
  KEY `idx_liturgical_source_tier` (`source_tier`),
  KEY `idx_liturgical_is_system_template` (`is_system_template`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_discipline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_discipline` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member_discipline_user_period` (`user_id`,`start_date`,`end_date`),
  KEY `idx_member_discipline_active_start` (`is_active`,`start_date`),
  CONSTRAINT `member_discipline_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_standing_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `membership_stage_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `membership_stage_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_affiliation_id` int unsigned NOT NULL,
  `pathway_stage` enum('newcomer','inquirer','attendee','member','confirmed','transferred_in','transferred_out') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_msh_affiliation` (`parish_affiliation_id`),
  KEY `idx_msh_timeline` (`parish_affiliation_id`,`effective_from`),
  CONSTRAINT `fk_msh_affiliation` FOREIGN KEY (`parish_affiliation_id`) REFERENCES `parish_affiliation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `namespace` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `time` int NOT NULL,
  `batch` int unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ministry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ministry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ministry_parish` (`parish_id`),
  CONSTRAINT `fk_ministry_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ministry_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ministry_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ministry_id` int unsigned NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `permissions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_role_ministry` (`ministry_id`),
  CONSTRAINT `fk_role_ministry` FOREIGN KEY (`ministry_id`) REFERENCES `ministry` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` enum('system','province','diocese','parish','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'system',
  `scope_id` int unsigned DEFAULT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
  `severity` enum('info','success','warning','danger') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'info',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `action_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('draft','queued','published','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'published',
  `publish_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notification_scope` (`scope_type`,`scope_id`),
  KEY `idx_notification_status_publish` (`status`,`publish_at`),
  KEY `idx_notification_expires` (`expires_at`),
  KEY `fk_notification_created_by` (`created_by`),
  CONSTRAINT `fk_notification_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_recipient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_recipient` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `delivery_status` enum('queued','delivered','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'queued',
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `dismissed_at` datetime DEFAULT NULL,
  `failure_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_recipient_unique` (`notification_id`,`user_id`),
  KEY `idx_notification_recipient_user_status` (`user_id`,`delivery_status`),
  KEY `idx_notification_recipient_read` (`user_id`,`read_at`),
  CONSTRAINT `fk_notification_recipient_notification` FOREIGN KEY (`notification_id`) REFERENCES `notification` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notification_recipient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organisation_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organisation_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_org_setting_key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `parish_ueid` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `zip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ZIP+4 format',
  `mailing_zip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `county` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_county` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'United States',
  `mailing_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tax_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Tax identification number (EIN)',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `public_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_parish_ueid` (`parish_ueid`),
  KEY `idx_parish_diocese_id` (`diocese_id`),
  KEY `idx_parish_is_active` (`is_active`),
  CONSTRAINT `parish_diocese_id_foreign` FOREIGN KEY (`diocese_id`) REFERENCES `diocese` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6922 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_admin_primary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_admin_primary` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parish_id` (`parish_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_affiliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_affiliation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `linked_user_id` int unsigned DEFAULT NULL,
  `affiliation_type` enum('guest','attendee','member') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'guest',
  `membership_status` enum('visitor','regular_attender','member','inactive','transferred','deceased') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'visitor',
  `pathway_stage` tinyint unsigned DEFAULT NULL,
  `is_member` tinyint(1) GENERATED ALWAYS AS ((`membership_status` in (_utf8mb4'member',_utf8mb4'inactive'))) STORED,
  `source` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'system',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `is_primary` tinyint unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parish_affiliation_person_parish` (`parish_id`,`person_id`),
  UNIQUE KEY `idx_parish_affiliation_user_parish` (`linked_user_id`,`parish_id`),
  KEY `idx_pa_parish` (`parish_id`),
  KEY `idx_pa_person` (`person_id`),
  KEY `idx_pa_linked_user` (`linked_user_id`),
  KEY `idx_pa_active_lookup` (`parish_id`,`membership_status`,`end_date`),
  KEY `idx_pa_parish_person` (`parish_id`,`person_id`),
  KEY `idx_parish_affiliation_status` (`parish_id`,`is_member`,`membership_status`),
  CONSTRAINT `fk_pa_linked_user` FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_pa_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pa_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `parish_affiliation_parish_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_budget`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_budget` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `chart_account_id` int unsigned NOT NULL,
  `fiscal_year` smallint unsigned NOT NULL,
  `budget_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_budget_parish_account_year` (`parish_id`,`chart_account_id`,`fiscal_year`),
  KEY `fk_budget_chart_account` (`chart_account_id`),
  KEY `idx_budget_parish_year` (`parish_id`,`fiscal_year`),
  CONSTRAINT `fk_budget_chart_account` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_budget_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_integration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_integration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `provider` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `integration_type` enum('email','sms','payment','forms','calendar','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'other',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `webhook_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `webhook_secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `sync_status` enum('idle','syncing','error','paused') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'idle',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_integration_parish` (`parish_id`),
  CONSTRAINT `fk_integration_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_people`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_people` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `profile_id` int unsigned DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `profile_picture` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `portal_access` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `membership_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `is_member` tinyint(1) NOT NULL DEFAULT '0',
  `is_good_standing` tinyint(1) NOT NULL DEFAULT '0',
  `is_leader` tinyint unsigned NOT NULL DEFAULT '0',
  `leader_titles` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user_profile',
  `synced_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `is_baptized` tinyint unsigned NOT NULL DEFAULT '0',
  `is_confirmed` tinyint unsigned NOT NULL DEFAULT '0',
  `is_communicant` tinyint unsigned NOT NULL DEFAULT '0',
  `is_dependent` tinyint unsigned NOT NULL DEFAULT '0',
  `is_under_16` tinyint unsigned NOT NULL DEFAULT '0',
  `has_pledge` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parish_people_parish_user` (`parish_id`,`user_id`),
  KEY `idx_parish_people_member_status` (`parish_id`,`is_member`,`membership_status`),
  KEY `idx_parish_people_portal` (`parish_id`,`portal_access`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_provider_credential`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_provider_credential` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `integration_id` int unsigned NOT NULL,
  `credential_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `credential_value` varbinary(1024) NOT NULL,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '1',
  `last_rotated` datetime DEFAULT NULL,
  `rotation_interval_days` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_credential_integration` (`integration_id`),
  CONSTRAINT `fk_credential_integration` FOREIGN KEY (`integration_id`) REFERENCES `parish_integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_receipt_counter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_receipt_counter` (
  `parish_id` int unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_report` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `report_year` smallint unsigned NOT NULL,
  `report_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `finalized_at` datetime DEFAULT NULL,
  `finalized_by` int unsigned DEFAULT NULL,
  `frozen_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_report_parish_year` (`parish_id`,`report_year`),
  KEY `idx_report_status` (`report_status`),
  CONSTRAINT `fk_report_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_report_storage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_report_storage` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `report_year` smallint unsigned NOT NULL COMMENT 'Four-digit fiscal/calendar year',
  `report_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `mailing_address_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_address_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_city` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_state` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_zip` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_county` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_country` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `report_prepared_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `report_prepared_by_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `report_prepared_by_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `financial_prepared_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `financial_prepared_by_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `financial_prepared_by_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clerk_certified_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clerk_certified_by_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clerk_certified_by_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clerk_certified_date` date DEFAULT NULL,
  `treasurer_certified_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `treasurer_certified_by_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `treasurer_certified_by_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `treasurer_certified_date` date DEFAULT NULL,
  `rector_certified_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rector_certified_by_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rector_certified_by_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rector_certified_date` date DEFAULT NULL,
  `warden_approved_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `warden_approved_by_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `warden_approved_by_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `warden_approved_date` date DEFAULT NULL,
  `completion_participants_count` int DEFAULT NULL,
  `completion_participant_roles` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `outreach_beneficiaries_weekly` int DEFAULT NULL,
  `nonmember_volunteers_weekly` int DEFAULT NULL,
  `nonlive_views_estimated` tinyint(1) DEFAULT '0',
  `membership_previous_total` int DEFAULT NULL,
  `membership_increase_total` int DEFAULT NULL,
  `membership_decrease_total` int DEFAULT NULL,
  `active_participants_total` int DEFAULT NULL,
  `primary_worship_leader` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clergy_situation_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `programs_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `programs_other` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subsidiary_organizations_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `subsidiary_organizations_other` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `languages_other` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `simultaneous_multilingual_service` tinyint(1) DEFAULT '0',
  `percent_white` decimal(5,2) DEFAULT NULL,
  `average_age_override` decimal(5,2) DEFAULT NULL,
  `reporting_currency` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
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
  `debt_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `owns_property` tinyint(1) NOT NULL DEFAULT '0',
  `rents_to_outside` tinyint(1) NOT NULL DEFAULT '0',
  `rental_income` decimal(14,2) DEFAULT NULL,
  `rental_expense` decimal(14,2) DEFAULT NULL,
  `carbon_footprint_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `exemplary_program_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `future_hope_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `frozen_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `finalized_at` datetime DEFAULT NULL,
  `finalized_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `church_id_report_year` (`parish_id`,`report_year`),
  CONSTRAINT `church_financial_summary_church_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_role_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_role_audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `actor_user_id` int unsigned NOT NULL,
  `target_user_id` int unsigned NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cral_parish` (`parish_id`),
  KEY `idx_cral_actor` (`actor_user_id`),
  KEY `idx_cral_target` (`target_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_parish_setting` (`parish_id`,`key`),
  CONSTRAINT `fk_parish_setting` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47258 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parish_user_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parish_user_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pur_parish_user_role` (`parish_id`,`user_id`,`role_key`),
  KEY `idx_pur_user` (`user_id`),
  KEY `idx_pur_role` (`role_key`),
  KEY `idx_pur_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_pur_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pur_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pur_role_catalog` FOREIGN KEY (`role_key`) REFERENCES `church_role_catalog` (`role_key`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempt_count` int unsigned NOT NULL DEFAULT '0',
  `last_attempt_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_password_reset_email` (`email`),
  KEY `idx_password_reset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `period_close_batch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `period_close_batch` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `period_id` int unsigned NOT NULL,
  `status` enum('draft','running','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `started_by` int unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `summary_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pcb_period` (`period_id`),
  KEY `fk_pcb_started_by` (`started_by`),
  CONSTRAINT `fk_pcb_period` FOREIGN KEY (`period_id`) REFERENCES `accounting_period` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pcb_started_by` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('M','F','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ft_person_name_email` (`first_name`,`last_name`,`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_address` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `address_type` enum('mailing','physical','seasonal','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'mailing',
  `line1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `line2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `postal_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'United States',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pad_person` (`person_id`),
  KEY `idx_pad_lookup` (`person_id`,`address_type`,`is_primary`),
  CONSTRAINT `fk_pad_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_background_check`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_background_check` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `check_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `completed_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `result` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('valid','expiring','expired','revoked') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'valid',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pbc_person` (`person_id`),
  CONSTRAINT `fk_pbc_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_communication_consent_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_communication_consent_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `channel` enum('email','sms','push','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'email',
  `action` enum('opt_in','opt_out','essential_only') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'opt_in',
  `source` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `captured_by` int unsigned DEFAULT NULL,
  `evidence_ref` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `occurred_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pcce_captured_by` (`captured_by`),
  KEY `idx_pcce_person_channel_time` (`person_id`,`channel`,`occurred_at`),
  CONSTRAINT `fk_pcce_captured_by` FOREIGN KEY (`captured_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_pcce_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_communication_preference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_communication_preference` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `channel` enum('email','sms','push','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'email',
  `preference_status` enum('opt_in','opt_out','essential_only') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'opt_in',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pcp_person_channel` (`person_id`,`channel`),
  KEY `fk_pcp_updated_by` (`updated_by`),
  CONSTRAINT `fk_pcp_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pcp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_contact` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `contact_type` enum('email','mobile','home_phone','work_phone','emergency_phone','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `contact_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pc_person` (`person_id`),
  KEY `idx_pc_lookup` (`person_id`,`contact_type`,`is_primary`),
  CONSTRAINT `fk_pc_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_membership_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_membership_details` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `is_baptized` tinyint unsigned NOT NULL DEFAULT '0',
  `is_confirmed` tinyint unsigned NOT NULL DEFAULT '0',
  `is_communicant` tinyint unsigned NOT NULL DEFAULT '0',
  `is_dependent` tinyint unsigned NOT NULL DEFAULT '0',
  `has_pledge` tinyint unsigned NOT NULL DEFAULT '0',
  `pledge_status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `good_standing_override` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_onboarding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_onboarding` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `onboarding_status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`),
  KEY `parish_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_safeguarding_training`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_safeguarding_training` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `training_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `provider` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `completed_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `status` enum('valid','expiring','expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'valid',
  `certificate_reference` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pst_person` (`person_id`),
  CONSTRAINT `fk_pst_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_person_setting` (`person_id`,`key`),
  CONSTRAINT `fk_person_setting_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_status_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `status_type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `from_status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `to_status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `changed_by` int unsigned DEFAULT NULL,
  `reason` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`),
  KEY `parish_id` (`parish_id`),
  KEY `status_type` (`status_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_transfer_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_transfer_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `from_parish_id` int unsigned DEFAULT NULL,
  `to_parish_id` int unsigned DEFAULT NULL,
  `from_diocese_id` int unsigned DEFAULT NULL,
  `to_diocese_id` int unsigned DEFAULT NULL,
  `transfer_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `requested_on` date DEFAULT NULL,
  `received_on` date DEFAULT NULL,
  `effective_on` date DEFAULT NULL,
  `status` enum('draft','pending','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `reference_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ptr_from_parish` (`from_parish_id`),
  KEY `fk_ptr_to_parish` (`to_parish_id`),
  KEY `fk_ptr_from_diocese` (`from_diocese_id`),
  KEY `fk_ptr_to_diocese` (`to_diocese_id`),
  KEY `idx_ptr_person` (`person_id`),
  CONSTRAINT `fk_ptr_from_diocese` FOREIGN KEY (`from_diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_ptr_from_parish` FOREIGN KEY (`from_parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_ptr_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ptr_to_diocese` FOREIGN KEY (`to_diocese_id`) REFERENCES `diocese` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_ptr_to_parish` FOREIGN KEY (`to_parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pledge_idempotency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pledge_idempotency` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pledge_id` int unsigned DEFAULT NULL,
  `processed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_idempotency_key` (`idempotency_key`),
  KEY `idx_idem_pledge` (`pledge_id`),
  CONSTRAINT `fk_idem_pledge` FOREIGN KEY (`pledge_id`) REFERENCES `annual_pledge` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pledge_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pledge_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_access_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_access_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `parish_id` int unsigned NOT NULL,
  `requested_access_type` enum('guest','member') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'guest',
  `status` enum('pending','approved','rejected','claimed','expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `verification_method` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `verification_token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `matched_confidence` smallint unsigned DEFAULT NULL,
  `review_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_par_user` (`user_id`),
  KEY `idx_par_person` (`person_id`),
  KEY `idx_par_parish` (`parish_id`),
  KEY `idx_par_reviewed_by` (`reviewed_by`),
  KEY `idx_par_parish_status` (`parish_id`,`status`),
  CONSTRAINT `fk_par_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_par_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_par_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_par_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pre_registration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pre_registration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `attempt_count` int unsigned NOT NULL DEFAULT '0',
  `last_attempt_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prereg_email` (`email`),
  KEY `idx_prereg_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `profile_picture` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `province_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `middle_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `phone_country_code` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `address_line_2` text COLLATE utf8mb4_general_ci,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `zip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `locale` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'en',
  `social_media` json DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `baptism_date` date DEFAULT NULL,
  `confirmation_date` date DEFAULT NULL,
  `is_confirmed` tinyint unsigned NOT NULL DEFAULT '0',
  `spiritual_gifts` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `portal_access` enum('none','invited','enabled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `portal_enabled_at` datetime DEFAULT NULL,
  `is_dependent` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Age < 18 or otherwise a dependent of a household member',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `church_id` (`parish_id`),
  KEY `idx_profiles_diocese_id` (`diocese_id`),
  KEY `idx_profiles_province_id` (`province_id`),
  KEY `idx_profiles_phone_hash` (`phone_hash`),
  KEY `idx_profiles_portal_access` (`portal_access`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `province`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `province` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_number` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `province_id` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` tinyint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `province_id` (`province_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `province_admin_assignment_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `province_admin_assignment_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `action` enum('assigned','updated','removed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `performed_by` int unsigned DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_paah_province` (`province_id`),
  KEY `idx_paah_user` (`user_id`),
  KEY `idx_paah_performed_by` (`performed_by`),
  KEY `idx_paah_created_at` (`created_at`),
  CONSTRAINT `fk_paah_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_paah_province` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_paah_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `province_admin_primary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `province_admin_primary` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `province_id` (`province_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `province_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `province_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `province_id` int unsigned NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_province_setting` (`province_id`,`key`),
  CONSTRAINT `fk_province_setting_province` FOREIGN KEY (`province_id`) REFERENCES `province` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `query_sql` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `parameters` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_parish` (`parish_id`),
  CONSTRAINT `fk_custom_report_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_attachment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_report_id` int unsigned NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stored_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `mime_type` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` int unsigned NOT NULL DEFAULT '0',
  `attachment_type` enum('financial_statement','meeting_minutes','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'other',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_attachment` (`parish_report_id`),
  CONSTRAINT `fk_report_attachment` FOREIGN KEY (`parish_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_clergy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_clergy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_report_id` int unsigned NOT NULL,
  `primary_worship_leader` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clergy_situation_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clergy_report` (`parish_report_id`),
  CONSTRAINT `fk_clergy_report` FOREIGN KEY (`parish_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_financial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_financial` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_report_id` int unsigned NOT NULL,
  `cash_balance` decimal(14,2) DEFAULT NULL,
  `endowment_restricted` decimal(14,2) DEFAULT NULL,
  `endowment_unrestricted` decimal(14,2) DEFAULT NULL,
  `investment_restricted` decimal(14,2) DEFAULT NULL,
  `investment_unrestricted` decimal(14,2) DEFAULT NULL,
  `pct_draw` decimal(5,2) DEFAULT NULL,
  `amount_drawn_operating` decimal(14,2) DEFAULT NULL,
  `amount_drawn_capital` decimal(14,2) DEFAULT NULL,
  `amount_drawn` decimal(14,2) DEFAULT NULL,
  `investment_net_gain_loss` decimal(14,2) DEFAULT NULL,
  `has_debt` tinyint(1) NOT NULL DEFAULT '0',
  `debt_amount` decimal(14,2) DEFAULT NULL,
  `debt_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `owns_property` tinyint(1) NOT NULL DEFAULT '0',
  `rents_to_outside` tinyint(1) NOT NULL DEFAULT '0',
  `rental_income` decimal(14,2) DEFAULT NULL,
  `rental_expense` decimal(14,2) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_financial_report` (`parish_report_id`),
  CONSTRAINT `fk_financial_report` FOREIGN KEY (`parish_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_membership`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_membership` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_report_id` int unsigned NOT NULL,
  `confirmed_pledges_count` int DEFAULT NULL,
  `confirmed_pledges_amount` decimal(14,2) DEFAULT NULL,
  `other_revenue_amount` decimal(14,2) DEFAULT NULL,
  `membership_previous_total` int DEFAULT NULL,
  `membership_increase_total` int DEFAULT NULL,
  `membership_decrease_total` int DEFAULT NULL,
  `active_participants_total` int DEFAULT NULL,
  `outreach_beneficiaries_weekly` int DEFAULT NULL,
  `nonmember_volunteers_weekly` int DEFAULT NULL,
  `nonlive_views_estimated` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_membership_report` (`parish_report_id`),
  CONSTRAINT `fk_membership_report` FOREIGN KEY (`parish_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_narratives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_narratives` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_report_id` int unsigned NOT NULL,
  `narratives_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `challenges_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `future_plans_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_narratives_report` (`parish_report_id`),
  CONSTRAINT `fk_narratives_report` FOREIGN KEY (`parish_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_programs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_report_id` int unsigned NOT NULL,
  `programs_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `subsidiary_organizations_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `programs_other` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subsidiary_organizations_other` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `languages_other` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `simultaneous_multilingual_service` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_programs_report` (`parish_report_id`),
  CONSTRAINT `fk_programs_report` FOREIGN KEY (`parish_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_scope_hierarchy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_scope_hierarchy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_report_id` int unsigned NOT NULL,
  `child_report_id` int unsigned NOT NULL,
  `scope_level` enum('parish','diocese','province') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'parish',
  `aggregated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_report_id` (`parent_report_id`),
  KEY `child_report_id` (`child_report_id`),
  CONSTRAINT `report_scope_hierarchy_child_report_id_foreign` FOREIGN KEY (`child_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `report_scope_hierarchy_parent_report_id_foreign` FOREIGN KEY (`parent_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_scope_monthly_metric`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_scope_monthly_metric` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` enum('system','province','diocese','parish') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scope_id` int unsigned NOT NULL DEFAULT '0',
  `period_year_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `active_affiliation_count` int unsigned NOT NULL DEFAULT '0',
  `pending_access_request_count` int unsigned NOT NULL DEFAULT '0',
  `attendance_total` int unsigned NOT NULL DEFAULT '0',
  `donation_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `open_call_task_count` int unsigned NOT NULL DEFAULT '0',
  `pending_report_count` int unsigned NOT NULL DEFAULT '0',
  `generated_at` datetime DEFAULT NULL,
  `generated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_report_scope_monthly_metric` (`scope_type`,`scope_id`,`period_year_month`),
  KEY `idx_report_scope_monthly_period` (`period_year_month`),
  KEY `fk_report_scope_monthly_metric_generated_by` (`generated_by`),
  CONSTRAINT `fk_report_scope_monthly_metric_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_scope_queue_snapshot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_scope_queue_snapshot` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` enum('system','province','diocese','parish') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scope_id` int unsigned NOT NULL DEFAULT '0',
  `snapshot_date` date NOT NULL,
  `queue_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `open_count` int unsigned NOT NULL DEFAULT '0',
  `oldest_item_at` datetime DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `generated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_report_scope_queue_snapshot` (`scope_type`,`scope_id`,`snapshot_date`,`queue_key`),
  KEY `idx_report_scope_queue_date` (`snapshot_date`),
  KEY `fk_report_scope_queue_snapshot_generated_by` (`generated_by`),
  CONSTRAINT `fk_report_scope_queue_snapshot_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_signatory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_signatory` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_report_id` int unsigned NOT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `governance_term_id` int unsigned DEFAULT NULL,
  `clergy_appointment_id` int unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role_id` int unsigned NOT NULL,
  `certification_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_signatory` (`parish_report_id`),
  KEY `idx_signatory_role` (`role_id`),
  KEY `idx_rs_person` (`person_id`),
  KEY `idx_rs_governance_term` (`governance_term_id`),
  KEY `idx_rs_clergy_appointment` (`clergy_appointment_id`),
  CONSTRAINT `fk_report_signatory` FOREIGN KEY (`parish_report_id`) REFERENCES `parish_report` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rs_clergy_appointment` FOREIGN KEY (`clergy_appointment_id`) REFERENCES `clergy_appointment` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rs_governance_term` FOREIGN KEY (`governance_term_id`) REFERENCES `governance_term` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_rs_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_signatory_role` FOREIGN KEY (`role_id`) REFERENCES `signatory_role` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sacramental_participant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sacramental_participant` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sacramental_record_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `participant_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sp_record_person_role` (`sacramental_record_id`,`person_id`,`participant_role`),
  KEY `idx_sp_person` (`person_id`),
  CONSTRAINT `fk_sp_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sp_record` FOREIGN KEY (`sacramental_record_id`) REFERENCES `sacramental_record` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sacramental_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sacramental_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `sacrament_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sacrament_date` date DEFAULT NULL,
  `register_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `certificate_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `officiant_person_id` int unsigned DEFAULT NULL,
  `location_notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sr_officiant_person` (`officiant_person_id`),
  KEY `idx_sr_parish` (`parish_id`),
  KEY `idx_sr_person` (`person_id`),
  KEY `idx_sr_lookup` (`parish_id`,`sacrament_type`,`sacrament_date`),
  CONSTRAINT `fk_sr_officiant_person` FOREIGN KEY (`officiant_person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_sr_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `service_date` date NOT NULL,
  `service_time` time DEFAULT NULL,
  `liturgical_day` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service` json DEFAULT NULL,
  `service_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Sunday',
  `liturgy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `readings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `hymns` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service_parish_date` (`parish_id`,`service_date`),
  CONSTRAINT `fk_service_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_metric`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_metric` (
  `service_id` int unsigned NOT NULL,
  `format_of_service` enum('In-Person','Hybrid','Online') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `attendance_classification` enum('Regular','Special') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_family` enum('holy_communion','daily_office','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `service_schedule` enum('sunday','weekday','private') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
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
  KEY `attendance_classification` (`attendance_classification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_people`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_people` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `person_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `service_id_sort_order` (`service_id`,`sort_order`),
  CONSTRAINT `service_people_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_worship_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `type` varchar(31) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'string',
  `context` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings_audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` enum('organisation','province','diocese') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'organisation',
  `scope_id` int unsigned DEFAULT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `changed_by` int unsigned NOT NULL,
  `change_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `scope_type_scope_id` (`scope_type`,`scope_id`),
  KEY `setting_key` (`setting_key`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `signatory_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `signatory_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sms_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_queue` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `to_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Destination phone in E.164 format',
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `from_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Override sender number; NULL = use env default',
  `parish_id` int unsigned DEFAULT NULL,
  `status` enum('pending','sent','failed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `max_attempts` tinyint unsigned NOT NULL DEFAULT '3',
  `message_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Provider message SID on success',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `scheduled_at` datetime NOT NULL COMMENT 'Do not send before this datetime',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status_scheduled_at` (`status`,`scheduled_at`),
  KEY `church_id` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `social_media_post`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_media_post` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `platform` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_social_parish` (`parish_id`),
  CONSTRAINT `fk_social_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_admin_primary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_admin_primary` (
  `id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci,
  `data_type` varchar(31) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'string',
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `is_locked` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `setting_key_2` (`setting_key`),
  KEY `category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_team_parish` (`parish_id`),
  CONSTRAINT `fk_team_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_member` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `team_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned NOT NULL,
  `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `joined_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_team_person` (`team_id`,`person_id`),
  KEY `idx_tm_user` (`user_id`),
  KEY `idx_tm_person_link` (`person_id`),
  CONSTRAINT `fk_tm_person_link` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tm_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `translatable_names`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `translatable_names` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `lang_code` varchar(5) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_entity_lang` (`entity_type`,`entity_id`,`lang_code`),
  KEY `idx_entity_type_lang` (`entity_type`,`lang_code`),
  KEY `idx_entity_id` (`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_person` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '1',
  `linked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_person_user` (`user_id`),
  UNIQUE KEY `uk_user_person_person` (`person_id`),
  CONSTRAINT `fk_user_person_person` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_person_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_registration_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_registration_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `province_id` int unsigned NOT NULL,
  `diocese_id` int unsigned DEFAULT NULL,
  `parish_id` int unsigned DEFAULT NULL,
  `status` enum('pending_profile','pending_review','approved','dismissed','banned') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending_profile',
  `requested_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  `review_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
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
  KEY `urr_requested_church_idx` (`parish_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_session` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `device_fingerprint` varchar(64) NOT NULL,
  `last_active` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `is_new_device` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_session_user` (`user_id`),
  KEY `idx_session_token` (`session_token`)
) ENGINE=InnoDB AUTO_INCREMENT=465 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_setting` (`user_id`,`key`),
  CONSTRAINT `fk_user_setting_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `last_active` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mailing_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tax_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_parish` (`parish_id`),
  CONSTRAINT `fk_vendor_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_bill` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `vendor_id` int unsigned NOT NULL,
  `bill_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `bill_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('draft','approved','partially_paid','paid','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `subtotal_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `outstanding_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_by` int unsigned DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vb_parish_vendor_bill` (`parish_id`,`vendor_id`,`bill_number`),
  KEY `fk_vb_vendor` (`vendor_id`),
  KEY `fk_vb_created_by` (`created_by`),
  KEY `fk_vb_approved_by` (`approved_by`),
  KEY `idx_vb_parish_status` (`parish_id`,`status`),
  CONSTRAINT `fk_vb_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_vb_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_vb_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vb_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill_line`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_bill_line` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vendor_bill_id` int unsigned NOT NULL,
  `chart_account_id` int unsigned NOT NULL,
  `fund_id` int unsigned DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_vbl_chart_account` (`chart_account_id`),
  KEY `fk_vbl_fund` (`fund_id`),
  KEY `idx_vbl_bill` (`vendor_bill_id`),
  CONSTRAINT `fk_vbl_bill` FOREIGN KEY (`vendor_bill_id`) REFERENCES `vendor_bill` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vbl_chart_account` FOREIGN KEY (`chart_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vbl_fund` FOREIGN KEY (`fund_id`) REFERENCES `funds` (`id`) ON DELETE CASCADE ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_payment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned NOT NULL,
  `vendor_bill_id` int unsigned DEFAULT NULL,
  `bank_account_id` int unsigned DEFAULT NULL,
  `payment_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('scheduled','processed','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'processed',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vp_parish_number` (`parish_id`,`payment_number`),
  KEY `fk_vp_bank_account` (`bank_account_id`),
  KEY `fk_vp_created_by` (`created_by`),
  KEY `idx_vp_bill` (`vendor_bill_id`),
  CONSTRAINT `fk_vp_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_account` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_vp_bill` FOREIGN KEY (`vendor_bill_id`) REFERENCES `vendor_bill` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_vp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `fk_vp_parish` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_payment_allocation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_payment_allocation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vendor_payment_id` int unsigned NOT NULL,
  `vendor_bill_id` int unsigned NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vpa_payment_bill` (`vendor_payment_id`,`vendor_bill_id`),
  KEY `idx_vpa_bill` (`vendor_bill_id`),
  CONSTRAINT `fk_vpa_bill` FOREIGN KEY (`vendor_bill_id`) REFERENCES `vendor_bill` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vpa_payment` FOREIGN KEY (`vendor_payment_id`) REFERENCES `vendor_payment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `volunteer_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `volunteer_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `team_id` int unsigned NOT NULL,
  `service_id` int unsigned DEFAULT NULL COMMENT 'optional link to a specific service',
  `user_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `time_slot` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'e.g., morning, afternoon',
  `status` enum('scheduled','confirmed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'scheduled',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `service_date` date DEFAULT NULL,
  `service_time_slot` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `volunteer_schedule_service_id_foreign` (`service_id`),
  KEY `team_id` (`team_id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  CONSTRAINT `volunteer_schedule_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE SET NULL,
  CONSTRAINT `volunteer_schedule_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `volunteer_schedule_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vw_current_clergy_appointment`;
/*!50001 DROP VIEW IF EXISTS `vw_current_clergy_appointment`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_current_clergy_appointment` AS SELECT 
 1 AS `id`,
 1 AS `parish_id`,
 1 AS `person_id`,
 1 AS `appointment_type`,
 1 AS `status`,
 1 AS `licensed_on`,
 1 AS `started_on`,
 1 AS `ended_on`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `vw_open_vendor_bills`;
/*!50001 DROP VIEW IF EXISTS `vw_open_vendor_bills`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_open_vendor_bills` AS SELECT 
 1 AS `id`,
 1 AS `parish_id`,
 1 AS `vendor_id`,
 1 AS `bill_number`,
 1 AS `bill_date`,
 1 AS `due_date`,
 1 AS `status`,
 1 AS `total_amount`,
 1 AS `outstanding_amount`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `webhook_delivery_attempt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_delivery_attempt` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `integration_id` int unsigned NOT NULL,
  `webhook_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `response_status_code` int DEFAULT NULL,
  `response_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `attempt_count` int unsigned NOT NULL DEFAULT '0',
  `next_attempt_at` datetime DEFAULT NULL,
  `succeeded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_integration` (`integration_id`),
  KEY `idx_webhook_next_attempt` (`next_attempt_at`),
  CONSTRAINT `fk_webhook_integration` FOREIGN KEY (`integration_id`) REFERENCES `parish_integration` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `worship_song`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `worship_song` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `artist` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `key_signature` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tempo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ccli` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `lyrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `church_id_title` (`parish_id`,`title`),
  CONSTRAINT `worship_songs_church_id_foreign` FOREIGN KEY (`parish_id`) REFERENCES `parish` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `worship_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `worship_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `liturgy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `readings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `hymns` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `vw_current_clergy_appointment`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_current_clergy_appointment` AS select `ca`.`id` AS `id`,`ca`.`parish_id` AS `parish_id`,`ca`.`person_id` AS `person_id`,`ca`.`appointment_type` AS `appointment_type`,`ca`.`status` AS `status`,`ca`.`licensed_on` AS `licensed_on`,`ca`.`started_on` AS `started_on`,`ca`.`ended_on` AS `ended_on` from `ekklesiaonline`.`clergy_appointment` `ca` where ((`ca`.`status` = 'active') and ((`ca`.`ended_on` is null) or (`ca`.`ended_on` >= curdate()))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `vw_open_vendor_bills`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_open_vendor_bills` AS select `vb`.`id` AS `id`,`vb`.`parish_id` AS `parish_id`,`vb`.`vendor_id` AS `vendor_id`,`vb`.`bill_number` AS `bill_number`,`vb`.`bill_date` AS `bill_date`,`vb`.`due_date` AS `due_date`,`vb`.`status` AS `status`,`vb`.`total_amount` AS `total_amount`,`vb`.`outstanding_amount` AS `outstanding_amount` from `ekklesiaonline`.`vendor_bill` `vb` where ((`vb`.`status` in ('approved','partially_paid')) and (`vb`.`outstanding_amount` > 0)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

