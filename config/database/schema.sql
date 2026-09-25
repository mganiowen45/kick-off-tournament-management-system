-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: kick_off
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `achievements`
--

DROP TABLE IF EXISTS `achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) NOT NULL,
  `icon` varchar(20) NOT NULL DEFAULT 'star',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_achievements_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_announcements_tournament` (`tournament_id`,`created_at`),
  KEY `fk_announcements_author` (`author_id`),
  CONSTRAINT `fk_announcements_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcements_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_id`,`created_at`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`,`created_at`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contact_reports`
--

DROP TABLE IF EXISTS `contact_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `reason` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contact_reports_status` (`status`,`created_at`),
  KEY `idx_contact_reports_match` (`match_id`),
  KEY `fk_contact_reports_reporter` (`reporter_id`),
  KEY `fk_contact_reports_reported` (`reported_user_id`),
  CONSTRAINT `fk_contact_reports_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_contact_reports_reported` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_contact_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disputes`
--

DROP TABLE IF EXISTS `disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disputes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `raised_by` int(11) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `outcome` enum('player1_wins','player2_wins','draw','replay','walkover_player1','walkover_player2','pending') NOT NULL DEFAULT 'pending',
  `status` enum('open','under_review','resolved') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disputes_match` (`match_id`),
  KEY `idx_disputes_status` (`status`),
  KEY `idx_disputes_raised_by` (`raised_by`),
  KEY `idx_disputes_resolved_by` (`resolved_by`),
  CONSTRAINT `fk_disputes_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_disputes_raised_by` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_disputes_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `financial_ledger`
--

DROP TABLE IF EXISTS `financial_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `payout_id` bigint(20) unsigned DEFAULT NULL,
  `entry_type` enum('entry_fee','platform_fee','prize_pool','kickoff_contribution','refund','payout','adjustment') NOT NULL,
  `direction` enum('debit','credit') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'TZS',
  `reference` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_reference` (`reference`),
  KEY `idx_ledger_user` (`user_id`,`created_at`),
  KEY `idx_ledger_tournament` (`tournament_id`,`created_at`),
  KEY `fk_ledger_payment` (`payment_id`),
  CONSTRAINT `fk_ledger_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ledger_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ledger_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `game_profiles`
--

DROP TABLE IF EXISTS `game_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `platform_id` int(11) NOT NULL,
  `in_game_name` varchar(80) NOT NULL,
  `team_name` varchar(80) DEFAULT NULL,
  `external_game_id` varchar(120) DEFAULT NULL,
  `status` enum('active','pending_verification','rejected') NOT NULL DEFAULT 'active',
  `is_primary` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game_profile_identity` (`user_id`,`game_id`,`platform_id`),
  KEY `idx_game_profiles_game_platform` (`game_id`,`platform_id`),
  KEY `fk_game_profiles_platform` (`platform_id`),
  CONSTRAINT `fk_game_profiles_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  CONSTRAINT `fk_game_profiles_platform` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  CONSTRAINT `fk_game_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `games`
--

DROP TABLE IF EXISTS `games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_games_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `games` WRITE;
/*!40000 ALTER TABLE `games` DISABLE KEYS */;
INSERT INTO `games` (`id`, `name`, `slug`, `image_path`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1,'eFootball','efootball','assets/supported_games/eFootball.jpg',1,10,current_timestamp(),current_timestamp()),
(2,'EA SPORTS FC','ea-sports-fc','assets/supported_games/EA_sports.jpg',1,20,current_timestamp(),current_timestamp()),
(3,'Dream League Soccer','dream-league-soccer','assets/supported_games/DLS.jpg',1,30,current_timestamp(),current_timestamp())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `image_path` = VALUES(`image_path`), `is_active` = 1, `sort_order` = VALUES(`sort_order`);
/*!40000 ALTER TABLE `games` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_message_reads`
--

DROP TABLE IF EXISTS `group_message_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `group_message_reads` (
  `user_id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `last_message_id` int(11) NOT NULL DEFAULT 0,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`,`tournament_id`),
  KEY `fk_group_reads_tournament` (`tournament_id`),
  CONSTRAINT `fk_group_reads_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `attempt_key` char(64) NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `first_attempt_at` datetime NOT NULL,
  `last_attempt_at` datetime NOT NULL,
  PRIMARY KEY (`attempt_key`),
  KEY `idx_login_attempts_last` (`last_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `match_contact_events`
--

DROP TABLE IF EXISTS `match_contact_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `match_contact_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `opponent_id` int(11) NOT NULL,
  `channel` enum('whatsapp') NOT NULL DEFAULT 'whatsapp',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_match_contact_match` (`match_id`,`created_at`),
  KEY `idx_match_contact_requester` (`requester_id`,`created_at`),
  KEY `fk_match_contact_opponent` (`opponent_id`),
  CONSTRAINT `fk_match_contact_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_contact_opponent` FOREIGN KEY (`opponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_contact_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `match_no_show_reports`
--

DROP TABLE IF EXISTS `match_no_show_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `match_no_show_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `accused_id` int(11) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `status` enum('pending','accepted','rejected','duplicate','expired') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(1000) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_no_show_reporter_match` (`match_id`,`reporter_id`),
  KEY `idx_no_show_status` (`status`,`created_at`),
  KEY `idx_no_show_accused` (`accused_id`,`created_at`),
  KEY `fk_no_show_reporter` (`reporter_id`),
  KEY `fk_no_show_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_no_show_accused` FOREIGN KEY (`accused_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_no_show_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_no_show_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_no_show_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `match_results`
--

DROP TABLE IF EXISTS `match_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `match_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `claimed_result` enum('win','loss','draw') NOT NULL,
  `my_score` int(11) NOT NULL DEFAULT 0,
  `opponent_score` int(11) NOT NULL DEFAULT 0,
  `screenshot_url` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `verification_status` enum('pending','confirmed','conflicted') NOT NULL DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mr_match_player` (`match_id`,`submitted_by`),
  KEY `idx_mr_match` (`match_id`),
  KEY `idx_mr_submitted_by` (`submitted_by`),
  KEY `idx_mr_status` (`verification_status`),
  CONSTRAINT `fk_mr_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mr_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `match_schedules`
--

DROP TABLE IF EXISTS `match_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `match_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `status` enum('not_scheduled','proposed','confirmed','rejected','expired','completed') NOT NULL DEFAULT 'proposed',
  `proposed_by` int(11) NOT NULL,
  `proposed_start_utc` datetime NOT NULL,
  `display_timezone` varchar(64) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `proposed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmed_by` int(11) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `reschedule_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_match_schedules_match` (`match_id`),
  KEY `idx_match_schedules_status_time` (`status`,`proposed_start_utc`),
  KEY `fk_match_schedules_proposed_by` (`proposed_by`),
  KEY `fk_match_schedules_confirmed_by` (`confirmed_by`),
  KEY `fk_match_schedules_rejected_by` (`rejected_by`),
  CONSTRAINT `fk_match_schedules_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_match_schedules_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_schedules_proposed_by` FOREIGN KEY (`proposed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_schedules_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `matches`
--

DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `stage` varchar(40) NOT NULL DEFAULT 'knockout',
  `group_id` bigint(20) unsigned DEFAULT NULL,
  `player1_id` int(11) NOT NULL,
  `player2_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL DEFAULT 1,
  `match_number` int(11) NOT NULL DEFAULT 0,
  `bracket_position` int(11) NOT NULL DEFAULT 0,
  `next_match_id` int(11) DEFAULT NULL,
  `next_match_slot` tinyint(4) DEFAULT NULL,
  `series_id` bigint(20) unsigned DEFAULT NULL,
  `series_game_number` tinyint(3) unsigned DEFAULT NULL,
  `status` enum('scheduled','pending_result','confirmed','disputed','walkover','cancelled') NOT NULL DEFAULT 'scheduled',
  `player1_score` int(11) DEFAULT NULL,
  `player2_score` int(11) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `is_draw` tinyint(1) NOT NULL DEFAULT 0,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `play_deadline_at` datetime DEFAULT NULL,
  `grace_period_ends_at` datetime DEFAULT NULL,
  `no_show_status` enum('none','reported','double_no_show','awaiting_deadline_review','resolved') NOT NULL DEFAULT 'none',
  `played_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_matches_tournament` (`tournament_id`),
  KEY `idx_matches_player1` (`player1_id`),
  KEY `idx_matches_player2` (`player2_id`),
  KEY `idx_matches_winner` (`winner_id`),
  KEY `idx_matches_status` (`status`),
  KEY `idx_matches_round` (`tournament_id`,`round_number`),
  KEY `idx_matches_bracket_order` (`tournament_id`,`stage`,`round_number`,`bracket_position`),
  KEY `idx_matches_next` (`next_match_id`),
  KEY `idx_matches_series` (`series_id`,`series_game_number`),
  KEY `idx_matches_group` (`group_id`),
  CONSTRAINT `fk_matches_player1` FOREIGN KEY (`player1_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_player2` FOREIGN KEY (`player2_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_matches_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `type` enum('direct','group') NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_messages_sender` (`sender_id`),
  KEY `idx_messages_receiver` (`receiver_id`),
  KEY `idx_messages_tournament` (`tournament_id`),
  KEY `idx_messages_type` (`type`),
  KEY `idx_messages_conversation` (`sender_id`,`receiver_id`,`sent_at`),
  CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `body` text NOT NULL,
  `type` enum('result_confirmed','result_disputed','dispute_resolved','match_reminder','new_message','tournament_update','tournament_joined','round_advanced','account_warning','system','whatsapp_contact','match_time_proposed','match_time_confirmed','match_time_rejected','deadline_approaching','prize_credited','withdrawal_completed','refund_processed','payment_successful','payment_failed','payment_pending','cancellation_requested','cancellation_decision','group_assigned','knockout_qualified','payout_pending','no_show_reported','no_show_decision') NOT NULL DEFAULT 'system',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `link_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_is_read` (`user_id`,`is_read`),
  KEY `idx_notif_type` (`type`),
  KEY `idx_notif_created_at` (`created_at`),
  KEY `idx_notifications_actor` (`actor_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_webhook_events`
--

DROP TABLE IF EXISTS `payment_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(40) NOT NULL DEFAULT 'clickpesa',
  `event_id` varchar(160) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `order_reference` varchar(80) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_webhook_event` (`provider`,`event_id`),
  KEY `idx_webhook_order` (`order_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `provider` varchar(40) NOT NULL DEFAULT 'clickpesa',
  `order_reference` varchar(80) NOT NULL,
  `provider_reference` varchar(120) DEFAULT NULL,
  `checkout_url` varchar(500) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'TZS',
  `status` enum('pending','requires_action','paid','failed','expired','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_order_reference` (`order_reference`),
  KEY `idx_payments_user` (`user_id`,`created_at`),
  KEY `idx_payments_tournament` (`tournament_id`,`status`),
  CONSTRAINT `fk_payments_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payouts`
--

DROP TABLE IF EXISTS `payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'TZS',
  `status` enum('pending','approved','processing','submitted','paid','failed','reversed','cancelled') NOT NULL DEFAULT 'pending',
  `payout_reference` varchar(120) DEFAULT NULL,
  `provider_reference` varchar(120) DEFAULT NULL,
  `payout_url` varchar(500) DEFAULT NULL,
  `admin_note` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payout_user_tournament` (`tournament_id`,`user_id`),
  UNIQUE KEY `uq_payout_reference` (`payout_reference`),
  KEY `fk_payouts_user` (`user_id`),
  CONSTRAINT `fk_payouts_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payouts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `platforms`
--

DROP TABLE IF EXISTS `platforms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platforms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL,
  `slug` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platforms_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_inactivity_strikes`
--

DROP TABLE IF EXISTS `player_inactivity_strikes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_inactivity_strikes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inactivity_user` (`user_id`,`created_at`),
  KEY `idx_inactivity_tournament` (`tournament_id`,`created_at`),
  KEY `fk_inactivity_match` (`match_id`),
  CONSTRAINT `fk_inactivity_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inactivity_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inactivity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `refunds`
--

DROP TABLE IF EXISTS `refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'TZS',
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','submitted','processed','failed') NOT NULL DEFAULT 'pending',
  `provider_reference` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_refunds_status` (`status`,`created_at`),
  KEY `fk_refunds_payment` (`payment_id`),
  CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_games`
--

DROP TABLE IF EXISTS `series_games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `series_games` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `series_id` bigint(20) unsigned NOT NULL,
  `match_id` int(11) NOT NULL,
  `game_number` tinyint(3) unsigned NOT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_series_game_number` (`series_id`,`game_number`),
  UNIQUE KEY `uq_series_game_match` (`match_id`),
  CONSTRAINT `fk_series_games_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_series_games_series` FOREIGN KEY (`series_id`) REFERENCES `tournament_series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_avatars`
--

DROP TABLE IF EXISTS `system_avatars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_avatars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `category` enum('male_character','female_character','country_flag','club') NOT NULL DEFAULT 'male_character',
  `file_path` varchar(160) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_avatars_file` (`file_path`),
  KEY `idx_system_avatars_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` varchar(500) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_bracket_slots`
--

DROP TABLE IF EXISTS `tournament_bracket_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_bracket_slots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `stage` varchar(40) NOT NULL DEFAULT 'knockout',
  `round_number` int(11) NOT NULL,
  `bracket_position` int(11) NOT NULL,
  `slot_number` tinyint(3) unsigned NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `source_match_id` int(11) DEFAULT NULL,
  `is_bye` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bracket_slot` (`tournament_id`,`stage`,`round_number`,`bracket_position`,`slot_number`),
  KEY `idx_bracket_user` (`user_id`),
  KEY `fk_bracket_slot_source` (`source_match_id`),
  CONSTRAINT `fk_bracket_slot_source` FOREIGN KEY (`source_match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bracket_slot_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bracket_slot_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_cancellation_requests`
--

DROP TABLE IF EXISTS `tournament_cancellation_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_cancellation_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(1000) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cancel_requests_status` (`status`,`created_at`),
  KEY `fk_cancel_requests_tournament` (`tournament_id`),
  KEY `fk_cancel_requests_requester` (`requested_by`),
  KEY `fk_cancel_requests_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_cancel_requests_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cancel_requests_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cancel_requests_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_cover_images`
--

DROP TABLE IF EXISTS `tournament_cover_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_cover_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(60) NOT NULL DEFAULT 'Arena',
  `file_path` varchar(160) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_cover_file` (`file_path`),
  KEY `idx_tournament_covers_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_group_members`
--

DROP TABLE IF EXISTS `tournament_group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_group_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rank_position` int(11) DEFAULT NULL,
  `qualified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_member` (`group_id`,`user_id`),
  KEY `idx_group_members_tournament` (`tournament_id`,`user_id`),
  KEY `fk_group_members_user` (`user_id`),
  CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `tournament_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_members_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_groups`
--

DROP TABLE IF EXISTS `tournament_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('scheduled','active','completed') NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tournament_group_name` (`tournament_id`,`name`),
  CONSTRAINT `fk_tournament_groups_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_players`
--

DROP TABLE IF EXISTS `tournament_players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('registered','active','eliminated','withdrawn') NOT NULL DEFAULT 'registered',
  `payment_status` enum('not_required','pending','paid','failed','refunded') NOT NULL DEFAULT 'not_required',
  `reservation_expires_at` datetime DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `group_id` bigint(20) unsigned DEFAULT NULL,
  `league_points` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `goals_for` int(11) NOT NULL DEFAULT 0,
  `goals_against` int(11) NOT NULL DEFAULT 0,
  `current_round` int(11) NOT NULL DEFAULT 1,
  `is_eliminated` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tp_tournament_user` (`tournament_id`,`user_id`),
  KEY `idx_tp_tournament` (`tournament_id`),
  KEY `idx_tp_user` (`user_id`),
  KEY `idx_tp_status` (`status`),
  CONSTRAINT `fk_tp_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_series`
--

DROP TABLE IF EXISTS `tournament_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_series` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `player1_id` int(11) NOT NULL,
  `player2_id` int(11) NOT NULL,
  `best_of` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `player1_wins` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `player2_wins` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `winner_id` int(11) DEFAULT NULL,
  `status` enum('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_series_tournament` (`tournament_id`),
  KEY `fk_series_player1` (`player1_id`),
  KEY `fk_series_player2` (`player2_id`),
  KEY `fk_series_winner` (`winner_id`),
  CONSTRAINT `fk_series_player1` FOREIGN KEY (`player1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_series_player2` FOREIGN KEY (`player2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_series_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_series_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournaments`
--

DROP TABLE IF EXISTS `tournaments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `game` varchar(80) NOT NULL DEFAULT 'eFootball (PES Mobile)',
  `game_id` int(11) DEFAULT NULL,
  `platform_id` int(11) DEFAULT NULL,
  `cover_image_id` int(11) DEFAULT NULL,
  `format` enum('1v1','full_knockout','group_knockout') NOT NULL,
  `status` enum('draft','open','active','completed','cancelled') NOT NULL DEFAULT 'open',
  `visibility` enum('public','private') NOT NULL DEFAULT 'public',
  `share_token` char(32) NOT NULL,
  `share_token_hash` char(64) DEFAULT NULL,
  `invite_revoked_at` datetime DEFAULT NULL,
  `invite_regenerated_at` datetime DEFAULT NULL,
  `max_players` int(11) NOT NULL DEFAULT 16,
  `current_players` int(11) NOT NULL DEFAULT 0,
  `prize_pool` varchar(100) DEFAULT NULL,
  `funding_model` enum('free_casual','participant_funded','kickoff_sponsored') NOT NULL DEFAULT 'free_casual',
  `currency` char(3) NOT NULL DEFAULT 'TZS',
  `entry_fee_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `prize_pool_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `kickoff_contribution_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `prize_template` varchar(40) NOT NULL DEFAULT 'auto',
  `platform_fee_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `match_legs` enum('best_of_1','best_of_3','best_of_5') NOT NULL DEFAULT 'best_of_3',
  `current_round` int(11) NOT NULL DEFAULT 0,
  `winner_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `registration_deadline` date NOT NULL,
  `locked_at` datetime DEFAULT NULL,
  `registration_opens_at` datetime DEFAULT NULL,
  `check_in_opens_at` datetime DEFAULT NULL,
  `check_in_closes_at` datetime DEFAULT NULL,
  `auto_start_at` datetime DEFAULT NULL,
  `lifecycle_note` varchar(255) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tournaments_creator` (`creator_id`),
  KEY `idx_tournaments_status` (`status`),
  KEY `idx_tournaments_format` (`format`),
  KEY `idx_tournaments_start` (`start_date`),
  KEY `idx_tournaments_winner` (`winner_id`),
  KEY `idx_tournaments_game_platform` (`game_id`,`platform_id`),
  KEY `idx_tournaments_visibility_status` (`visibility`,`status`),
  KEY `idx_tournaments_funding` (`funding_model`,`entry_fee_amount`),
  KEY `idx_tournaments_cover` (`cover_image_id`),
  KEY `idx_tournaments_share_hash` (`share_token_hash`),
  KEY `fk_tournaments_platform` (`platform_id`),
  CONSTRAINT `fk_tournaments_cover` FOREIGN KEY (`cover_image_id`) REFERENCES `tournament_cover_images` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tournaments_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tournaments_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  CONSTRAINT `fk_tournaments_platform` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  CONSTRAINT `fk_tournaments_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_achievements`
--

DROP TABLE IF EXISTS `user_achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_achievements` (
  `user_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`achievement_id`),
  KEY `idx_user_achievements_achievement` (`achievement_id`),
  CONSTRAINT `fk_user_achievements_achievement` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_achievements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_blocks`
--

DROP TABLE IF EXISTS `user_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_blocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_blocks_pair` (`blocker_id`,`blocked_id`),
  KEY `idx_user_blocks_blocked` (`blocked_id`),
  CONSTRAINT `fk_user_blocks_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_blocks_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `country` varchar(60) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Africa/Dar_es_Salaam',
  `preferred_game` varchar(80) NOT NULL DEFAULT 'eFootball (PES Mobile)',
  `profile_setup_completed` tinyint(1) NOT NULL DEFAULT 0,
  `theme_preference` varchar(24) NOT NULL DEFAULT 'esport',
  `whatsapp_country_code` varchar(8) DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `whatsapp_verified_at` datetime DEFAULT NULL,
  `whatsapp_contact_opt_in` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_contact_updated_at` datetime DEFAULT NULL,
  `mfa_secret` varchar(255) DEFAULT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('player','admin') NOT NULL DEFAULT 'player',
  `status` enum('active','warned','banned') NOT NULL DEFAULT 'active',
  `points` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `total_matches` int(11) NOT NULL DEFAULT 0,
  `championships` int(11) NOT NULL DEFAULT 0,
  `avatar_url` varchar(100) NOT NULL DEFAULT 'gamer-neon.svg',
  `bio` text DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_points` (`points`),
  KEY `idx_users_whatsapp_opt_in` (`whatsapp_contact_opt_in`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `vw_active_tournaments`
--

DROP TABLE IF EXISTS `vw_active_tournaments`;
/*!50001 DROP VIEW IF EXISTS `vw_active_tournaments`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_active_tournaments` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `format`,
  1 AS `status`,
  1 AS `game`,
  1 AS `prize_pool`,
  1 AS `max_players`,
  1 AS `current_players`,
  1 AS `slots_pct`,
  1 AS `start_date`,
  1 AS `registration_deadline`,
  1 AS `creator_username`,
  1 AS `creator_country` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_leaderboard`
--

DROP TABLE IF EXISTS `vw_leaderboard`;
/*!50001 DROP VIEW IF EXISTS `vw_leaderboard`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_leaderboard` AS SELECT
 1 AS `id`,
  1 AS `username`,
  1 AS `country`,
  1 AS `preferred_game`,
  1 AS `points`,
  1 AS `wins`,
  1 AS `losses`,
  1 AS `draws`,
  1 AS `total_matches`,
  1 AS `win_rate_pct`,
  1 AS `global_rank` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_league_standings`
--

DROP TABLE IF EXISTS `vw_league_standings`;
/*!50001 DROP VIEW IF EXISTS `vw_league_standings`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_league_standings` AS SELECT
 1 AS `tournament_id`,
  1 AS `tournament_name`,
  1 AS `user_id`,
  1 AS `username`,
  1 AS `country`,
  1 AS `wins`,
  1 AS `draws`,
  1 AS `losses`,
  1 AS `goals_for`,
  1 AS `goals_against`,
  1 AS `goal_diff`,
  1 AS `league_points`,
  1 AS `position` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_open_disputes`
--

DROP TABLE IF EXISTS `vw_open_disputes`;
/*!50001 DROP VIEW IF EXISTS `vw_open_disputes`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_open_disputes` AS SELECT
 1 AS `dispute_id`,
  1 AS `status`,
  1 AS `outcome`,
  1 AS `reason`,
  1 AS `created_at`,
  1 AS `match_id`,
  1 AS `tournament_id`,
  1 AS `tournament_name`,
  1 AS `round_number`,
  1 AS `player1_username`,
  1 AS `player2_username`,
  1 AS `raised_by_username` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_unread_message_counts`
--

DROP TABLE IF EXISTS `vw_unread_message_counts`;
/*!50001 DROP VIEW IF EXISTS `vw_unread_message_counts`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_unread_message_counts` AS SELECT
 1 AS `user_id`,
  1 AS `unread_direct` */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `vw_active_tournaments`
--

/*!50001 DROP VIEW IF EXISTS `vw_active_tournaments`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_active_tournaments` AS select `t`.`id` AS `id`,`t`.`name` AS `name`,`t`.`format` AS `format`,`t`.`status` AS `status`,`t`.`game` AS `game`,`t`.`prize_pool` AS `prize_pool`,`t`.`max_players` AS `max_players`,`t`.`current_players` AS `current_players`,round(`t`.`current_players` / `t`.`max_players` * 100,0) AS `slots_pct`,`t`.`start_date` AS `start_date`,`t`.`registration_deadline` AS `registration_deadline`,`u`.`username` AS `creator_username`,`u`.`country` AS `creator_country` from (`tournaments` `t` join `users` `u` on(`u`.`id` = `t`.`creator_id`)) where `t`.`status` in ('open','active') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_leaderboard`
--

/*!50001 DROP VIEW IF EXISTS `vw_leaderboard`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_leaderboard` AS select `u`.`id` AS `id`,`u`.`username` AS `username`,`u`.`country` AS `country`,`u`.`preferred_game` AS `preferred_game`,`u`.`points` AS `points`,`u`.`wins` AS `wins`,`u`.`losses` AS `losses`,`u`.`draws` AS `draws`,`u`.`total_matches` AS `total_matches`,case when `u`.`total_matches` = 0 then 0 else round(`u`.`wins` / `u`.`total_matches` * 100,1) end AS `win_rate_pct`,rank() over ( order by `u`.`points` desc) AS `global_rank` from `users` `u` where `u`.`status` = 'active' and `u`.`role` = 'player' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_league_standings`
--

/*!50001 DROP VIEW IF EXISTS `vw_league_standings`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_league_standings` AS select `tp`.`tournament_id` AS `tournament_id`,`t`.`name` AS `tournament_name`,`tp`.`user_id` AS `user_id`,`u`.`username` AS `username`,`u`.`country` AS `country`,`tp`.`wins` AS `wins`,`tp`.`draws` AS `draws`,`tp`.`losses` AS `losses`,`tp`.`goals_for` AS `goals_for`,`tp`.`goals_against` AS `goals_against`,`tp`.`goals_for` - `tp`.`goals_against` AS `goal_diff`,`tp`.`league_points` AS `league_points`,rank() over ( partition by `tp`.`tournament_id` order by `tp`.`league_points` desc,`tp`.`goals_for` - `tp`.`goals_against` desc,`tp`.`goals_for` desc) AS `position` from ((`tournament_players` `tp` join `tournaments` `t` on(`t`.`id` = `tp`.`tournament_id`)) join `users` `u` on(`u`.`id` = `tp`.`user_id`)) where `tp`.`status` <> 'withdrawn' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_open_disputes`
--

/*!50001 DROP VIEW IF EXISTS `vw_open_disputes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_open_disputes` AS select `d`.`id` AS `dispute_id`,`d`.`status` AS `status`,`d`.`outcome` AS `outcome`,`d`.`reason` AS `reason`,`d`.`created_at` AS `created_at`,`m`.`id` AS `match_id`,`m`.`tournament_id` AS `tournament_id`,`t`.`name` AS `tournament_name`,`m`.`round_number` AS `round_number`,`p1`.`username` AS `player1_username`,`p2`.`username` AS `player2_username`,`raiser`.`username` AS `raised_by_username` from (((((`disputes` `d` join `matches` `m` on(`m`.`id` = `d`.`match_id`)) join `tournaments` `t` on(`t`.`id` = `m`.`tournament_id`)) join `users` `p1` on(`p1`.`id` = `m`.`player1_id`)) join `users` `p2` on(`p2`.`id` = `m`.`player2_id`)) left join `users` `raiser` on(`raiser`.`id` = `d`.`raised_by`)) where `d`.`status` in ('open','under_review') order by `d`.`created_at` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_unread_message_counts`
--

/*!50001 DROP VIEW IF EXISTS `vw_unread_message_counts`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_unread_message_counts` AS select `messages`.`receiver_id` AS `user_id`,count(0) AS `unread_direct` from `messages` where `messages`.`type` = 'direct' and `messages`.`is_read` = 0 group by `messages`.`receiver_id` */;
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

-- Dump completed on 2026-08-10  1:43:11
