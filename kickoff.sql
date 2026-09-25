-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 09:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kick_off`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) NOT NULL,
  `icon` varchar(20) NOT NULL DEFAULT 'star'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_records`
--

CREATE TABLE `backup_records` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `status` enum('RUNNING','SUCCESS','FAILED') NOT NULL DEFAULT 'RUNNING',
  `backup_size` int(11) DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `error_summary` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_reports`
--

CREATE TABLE `contact_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `reason` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `raised_by` int(11) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `outcome` enum('player1_wins','player2_wins','draw','replay','walkover_player1','walkover_player2','pending') NOT NULL DEFAULT 'pending',
  `status` enum('open','under_review','resolved') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_ledger`
--

CREATE TABLE `financial_ledger` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `entry_type` enum('entry_fee','platform_fee','prize_pool','kickoff_contribution','refund','payout','adjustment') NOT NULL,
  `direction` enum('debit','credit') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'TZS',
  `reference` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`id`, `name`, `slug`, `image_path`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'eFootball', 'efootball', 'assets/supported_games/efootball.jpg', 1, 1, '2026-09-21 15:09:23', '2026-09-21 15:09:23'),
(2, 'Dream League Soccer', 'dream-league-soccer', 'assets/supported_games/dream-league-soccer.jpg', 1, 2, '2026-09-21 15:09:23', '2026-09-21 15:09:23'),
(3, 'FIFA', 'fifa', 'assets/supported_games/fifa.jpg', 1, 3, '2026-09-21 15:09:23', '2026-09-21 15:09:23');

-- --------------------------------------------------------

--
-- Table structure for table `game_profiles`
--

CREATE TABLE `game_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `platform_id` int(11) NOT NULL,
  `in_game_name` varchar(80) NOT NULL,
  `team_name` varchar(80) DEFAULT NULL,
  `external_game_id` varchar(120) DEFAULT NULL,
  `status` enum('active','pending_verification','rejected') NOT NULL DEFAULT 'active',
  `is_primary` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `game_profiles`
--

INSERT INTO `game_profiles` (`id`, `user_id`, `game_id`, `platform_id`, `in_game_name`, `team_name`, `external_game_id`, `status`, `is_primary`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'admin', 'Barcelona', NULL, 'active', 1, '2026-09-21 15:14:18', '2026-09-21 15:14:18'),
(2, 2, 1, 1, 'Pablo', 'PSG', NULL, 'active', 1, '2026-09-21 16:00:33', '2026-09-21 16:00:33');

-- --------------------------------------------------------

--
-- Table structure for table `group_message_reads`
--

CREATE TABLE `group_message_reads` (
  `user_id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `last_message_id` int(11) NOT NULL DEFAULT 0,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_locks`
--

CREATE TABLE `job_locks` (
  `job_name` varchar(100) NOT NULL,
  `locked_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `locked_by` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_runs`
--

CREATE TABLE `job_runs` (
  `id` int(11) NOT NULL,
  `job_name` varchar(100) NOT NULL,
  `run_id` varchar(64) NOT NULL,
  `status` enum('PENDING','RUNNING','SUCCESS','FAILED','RETRYING') NOT NULL DEFAULT 'PENDING',
  `attempt` int(11) NOT NULL DEFAULT 1,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `records_processed` int(11) DEFAULT 0,
  `records_succeeded` int(11) DEFAULT 0,
  `records_failed` int(11) DEFAULT 0,
  `error_summary` text DEFAULT NULL,
  `last_heartbeat` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `stage` varchar(40) NOT NULL DEFAULT 'knockout',
  `group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `player1_id` int(11) NOT NULL,
  `player2_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL DEFAULT 1,
  `match_number` int(11) NOT NULL DEFAULT 0,
  `bracket_position` int(11) NOT NULL DEFAULT 0,
  `next_match_id` int(11) DEFAULT NULL,
  `next_match_slot` tinyint(4) DEFAULT NULL,
  `series_id` bigint(20) UNSIGNED DEFAULT NULL,
  `series_game_number` tinyint(3) UNSIGNED DEFAULT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `match_contact_events`
--

CREATE TABLE `match_contact_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `match_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `opponent_id` int(11) NOT NULL,
  `channel` enum('whatsapp') NOT NULL DEFAULT 'whatsapp',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `match_no_show_reports`
--

CREATE TABLE `match_no_show_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `match_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `accused_id` int(11) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `status` enum('pending','accepted','rejected','duplicate','expired') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(1000) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `match_results`
--

CREATE TABLE `match_results` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `claimed_result` enum('win','loss','draw') NOT NULL,
  `my_score` int(11) NOT NULL DEFAULT 0,
  `opponent_score` int(11) NOT NULL DEFAULT 0,
  `screenshot_url` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `verification_status` enum('pending','confirmed','conflicted') NOT NULL DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `match_schedules`
--

CREATE TABLE `match_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `reschedule_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `type` enum('direct','group') NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `body` text NOT NULL,
  `type` enum('result_confirmed','result_disputed','dispute_resolved','match_reminder','new_message','tournament_update','tournament_joined','round_advanced','account_warning','system','whatsapp_contact','match_time_proposed','match_time_confirmed','match_time_rejected','deadline_approaching','prize_credited','withdrawal_completed','refund_processed','payment_successful','payment_failed','payment_pending','cancellation_requested','cancellation_decision','group_assigned','knockout_qualified','payout_pending','no_show_reported','no_show_decision') NOT NULL DEFAULT 'system',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `link_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operational_alerts`
--

CREATE TABLE `operational_alerts` (
  `id` int(11) NOT NULL,
  `component` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `tournament_id`, `provider`, `order_reference`, `provider_reference`, `checkout_url`, `amount`, `currency`, `status`, `metadata`, `created_at`, `updated_at`, `confirmed_at`, `expires_at`) VALUES
(1, 2, 2, 'clickpesa', 'KO-2-2-3E11E8F8', NULL, NULL, 2000.00, 'TZS', 'pending', NULL, '2026-09-22 02:04:32', '2026-09-22 02:04:32', NULL, '2026-09-22 02:34:32'),
(2, 2, 3, 'clickpesa', 'KO-3-2-26EE013B', NULL, NULL, 2000.00, 'TZS', 'pending', NULL, '2026-09-22 02:17:03', '2026-09-22 02:17:03', NULL, '2026-09-22 02:47:03'),
(3, 2, 4, 'clickpesa', 'KO-4-2-23A4F837', NULL, NULL, 2000.00, 'TZS', 'pending', NULL, '2026-09-22 02:36:05', '2026-09-22 02:36:05', NULL, '2026-09-22 03:06:05'),
(4, 2, 5, 'clickpesa', 'KO-5-2-A72B9612', NULL, NULL, 3000.00, 'TZS', 'pending', NULL, '2026-09-22 02:36:46', '2026-09-22 02:36:46', NULL, '2026-09-22 03:06:46'),
(5, 2, 6, 'clickpesa', 'KO-6-2-2FE8AEEA', NULL, NULL, 4000.00, 'TZS', 'pending', NULL, '2026-09-22 12:50:12', '2026-09-22 12:50:12', NULL, '2026-09-22 13:20:12'),
(6, 2, 7, 'clickpesa', 'KO7253811B63F3', NULL, NULL, 10000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 13:46:21', '2026-09-22 13:46:21', NULL, '2026-09-22 14:16:21'),
(7, 2, 8, 'clickpesa', 'KO82417F3F300D', NULL, NULL, 10000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 13:47:46', '2026-09-22 13:47:48', NULL, '2026-09-22 14:17:46'),
(8, 2, 9, 'clickpesa', 'KO9218A79FBC0E', NULL, NULL, 10000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 13:52:40', '2026-09-22 13:52:41', NULL, '2026-09-22 14:22:40'),
(9, 2, 10, 'clickpesa', 'KO1027904CEAC6B', NULL, NULL, 10000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 14:44:10', '2026-09-22 14:44:12', NULL, '2026-09-22 15:14:10'),
(10, 2, 11, 'clickpesa', 'KO1120428CB5664', NULL, NULL, 5000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 19:51:28', '2026-09-22 19:51:31', NULL, '2026-09-22 20:21:28'),
(11, 2, 12, 'clickpesa', 'KO122231711ABBE', NULL, NULL, 5000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 19:52:17', '2026-09-22 19:52:22', NULL, '2026-09-22 20:22:17'),
(12, 2, 13, 'clickpesa', 'KO1321A37E1629C', NULL, NULL, 5000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 20:55:07', '2026-09-22 20:55:10', NULL, '2026-09-22 21:25:07'),
(13, 2, 14, 'clickpesa', 'KO1421F061B9E8F', NULL, NULL, 5000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 21:16:06', '2026-09-22 21:16:09', NULL, '2026-09-22 21:46:06'),
(14, 2, 15, 'clickpesa', 'KO15220F7A3024A', NULL, NULL, 5000.00, 'TZS', 'failed', '{\"payment_flow\":\"tournament_creator_ussd_push\",\"phone\":\"255614585412\"}', '2026-09-22 21:28:49', '2026-09-22 21:28:51', NULL, '2026-09-22 21:58:49');

-- --------------------------------------------------------

--
-- Table structure for table `payment_webhook_events`
--

CREATE TABLE `payment_webhook_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(40) NOT NULL DEFAULT 'clickpesa',
  `event_id` varchar(160) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `order_reference` varchar(80) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payouts`
--

CREATE TABLE `payouts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'TZS',
  `status` enum('pending','approved','processing','submitted','paid','failed','reversed','cancelled') DEFAULT 'pending',
  `provider_reference` varchar(120) DEFAULT NULL,
  `admin_note` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payout_method_id` int(11) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `provider_fee` decimal(10,2) DEFAULT NULL,
  `previewed_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `payout_reference` varchar(100) DEFAULT NULL,
  `provider_status` varchar(50) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `last_provider_check_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platforms`
--

CREATE TABLE `platforms` (
  `id` int(11) NOT NULL,
  `name` varchar(40) NOT NULL,
  `slug` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platforms`
--

INSERT INTO `platforms` (`id`, `name`, `slug`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Mobile', 'mobile', 1, 1, '2026-09-21 15:11:23', '2026-09-21 15:11:23'),
(2, 'PC', 'pc', 1, 2, '2026-09-21 15:11:23', '2026-09-21 15:11:23'),
(3, 'Console', 'console', 1, 3, '2026-09-21 15:11:23', '2026-09-21 15:11:23');

-- --------------------------------------------------------

--
-- Table structure for table `player_inactivity_strikes`
--

CREATE TABLE `player_inactivity_strikes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `attempt_key` char(64) NOT NULL,
  `attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `first_attempt_at` datetime NOT NULL,
  `last_attempt_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`attempt_key`, `attempts`, `first_attempt_at`, `last_attempt_at`) VALUES
('acb191ccf32e4f9490559ef45a95f455619c29ca89734526ad8bec76c3902144', 2, '2026-09-22 02:03:04', '2026-09-22 02:03:19');

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'TZS',
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','submitted','processed','failed') NOT NULL DEFAULT 'pending',
  `provider_reference` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `series_games`
--

CREATE TABLE `series_games` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `series_id` bigint(20) UNSIGNED NOT NULL,
  `match_id` int(11) NOT NULL,
  `game_number` tinyint(3) UNSIGNED NOT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_avatars`
--

CREATE TABLE `system_avatars` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `category` enum('male_character','female_character','country_flag','club') NOT NULL DEFAULT 'male_character',
  `file_path` varchar(160) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` varchar(500) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournaments`
--

CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournaments`
--

INSERT INTO `tournaments` (`id`, `creator_id`, `name`, `description`, `game`, `game_id`, `platform_id`, `cover_image_id`, `format`, `status`, `visibility`, `share_token`, `share_token_hash`, `invite_revoked_at`, `invite_regenerated_at`, `max_players`, `current_players`, `prize_pool`, `funding_model`, `currency`, `entry_fee_amount`, `prize_pool_amount`, `kickoff_contribution_amount`, `prize_template`, `platform_fee_percent`, `match_legs`, `current_round`, `winner_id`, `start_date`, `registration_deadline`, `locked_at`, `registration_opens_at`, `check_in_opens_at`, `check_in_closes_at`, `auto_start_at`, `lifecycle_note`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'Legends clash', 'Play fair', 'eFootball', 1, 1, 51, '1v1', 'open', 'public', '897c77a7507943c4922630efe8be970f', '2b4f03eef58d0ebe5d7469a861501c4789bc8268a3eb9b14b6b398a3d05c3410', NULL, NULL, 2, 1, 'TZS 1,800', 'participant_funded', 'TZS', 1000.00, 1800.00, 0.00, 'auto', 10.00, 'best_of_3', 0, NULL, '2026-09-26', '2026-09-25', NULL, '2026-09-21 16:02:08', NULL, NULL, NULL, NULL, NULL, '2026-09-21 13:02:08', '2026-09-21 13:02:08'),
(2, 2, 'rangers', '', 'eFootball', 1, 1, 55, '1v1', 'open', 'public', '2e0a8c5f79d7cfcc79a231dc4a60e07d', '68ffa97262780c5df73ff22295a9ddbdd2a174b6cef99244a46881bec4660de5', NULL, NULL, 2, 1, 'TZS 3,600', 'participant_funded', 'TZS', 2000.00, 3600.00, 0.00, 'auto', 10.00, 'best_of_3', 0, NULL, '2026-09-25', '2026-09-24', NULL, '2026-09-22 02:04:32', NULL, NULL, NULL, NULL, NULL, '2026-09-21 23:04:32', '2026-09-21 23:04:32'),
(3, 2, 'rangers', '', 'eFootball', 1, 1, 55, '1v1', 'open', 'public', '70600871e595bd4a5453c5da92607107', '74142ec85e30291a7274f5f7f65a8de8d0dc8165080e4dd59ddb9957adb89305', NULL, NULL, 2, 1, 'TZS 3,600', 'participant_funded', 'TZS', 2000.00, 3600.00, 0.00, 'auto', 10.00, 'best_of_3', 0, NULL, '2026-09-25', '2026-09-24', NULL, '2026-09-22 02:17:03', NULL, NULL, NULL, NULL, NULL, '2026-09-21 23:17:03', '2026-09-21 23:17:03'),
(4, 2, 'rangers', '', 'eFootball', 1, 1, 55, '1v1', 'open', 'public', '76fef0d375e0855ac44adca0fc8baa99', '4ffd4e858f5a73cf0f52211e776feb377b6b437cd9c335fd90e9e2a65a4337ec', NULL, NULL, 2, 1, 'TZS 3,600', 'participant_funded', 'TZS', 2000.00, 3600.00, 0.00, 'auto', 10.00, 'best_of_3', 0, NULL, '2026-09-25', '2026-09-24', NULL, '2026-09-22 02:36:05', NULL, NULL, NULL, NULL, NULL, '2026-09-21 23:36:05', '2026-09-21 23:36:05'),
(5, 2, 'rangers', '', 'eFootball', 1, 1, 54, '1v1', 'open', 'public', '3c02475ac691d5d0395d130966b86e62', '9c8fc15f98a05781506f6dbd5b71d9d8ef42c9a20df900e4b917ccc42034c0f0', NULL, NULL, 2, 1, 'TZS 5,400', 'participant_funded', 'TZS', 3000.00, 5400.00, 0.00, 'auto', 10.00, 'best_of_3', 0, NULL, '2026-09-25', '2026-09-24', NULL, '2026-09-22 02:36:46', NULL, NULL, NULL, NULL, NULL, '2026-09-21 23:36:46', '2026-09-21 23:36:46'),
(6, 2, 'LEGENDS KNOCKOUT', '', 'eFootball', 1, 1, 52, 'full_knockout', 'open', 'public', 'c022ccf2bd59c2f5fa9cbb30401f0b18', '28aea613efcea768c8805a6c49aa1b3611a59187bac31e716affddaffcd72e45', NULL, NULL, 8, 1, 'TZS 28,800', 'participant_funded', 'TZS', 4000.00, 28800.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-09-30', '2026-09-25', NULL, '2026-09-22 12:50:12', NULL, NULL, NULL, NULL, NULL, '2026-09-22 09:50:12', '2026-09-22 09:50:12'),
(7, 2, 'LEGENDS KNOCKOUT', '', 'eFootball', 1, 1, 55, 'full_knockout', 'open', 'public', '90a3265c698f1ff9e6af9d736f524db4', '9e8b1a766b30b4acbaeede4d60302de450e23dcdb553334b1655ad4f9e662f42', NULL, NULL, 8, 1, 'TZS 72,000', 'participant_funded', 'TZS', 10000.00, 72000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-01', '2026-09-27', NULL, '2026-09-22 13:46:21', NULL, NULL, NULL, NULL, NULL, '2026-09-22 10:46:21', '2026-09-22 10:46:21'),
(8, 2, 'LEGENDS KNOCKOUT', '', 'eFootball', 1, 1, 55, 'full_knockout', 'open', 'public', '776b36faf2972a2d6ccebf4223b18050', '4a8ec31040a89d4e2ebfb09e4827a8d85ee6cf91733ec550d6334e5857963e05', NULL, NULL, 8, 1, 'TZS 72,000', 'participant_funded', 'TZS', 10000.00, 72000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-01', '2026-09-27', NULL, '2026-09-22 13:47:46', NULL, NULL, NULL, NULL, NULL, '2026-09-22 10:47:46', '2026-09-22 10:47:46'),
(9, 2, 'LEGENDS KNOCKOUT', '', 'eFootball', 1, 1, 55, 'full_knockout', 'open', 'public', '978ffb7737e5b102211bd5ebf05a9918', '3f56c1854033c40b3eac7b60c70a174c790e63d3b6e1054b29036119a6473cb2', NULL, NULL, 8, 1, 'TZS 72,000', 'participant_funded', 'TZS', 10000.00, 72000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-01', '2026-09-27', NULL, '2026-09-22 13:52:40', NULL, NULL, NULL, NULL, NULL, '2026-09-22 10:52:40', '2026-09-22 10:52:40'),
(10, 2, 'LEGENDS KNOCKOUT', '', 'eFootball', 1, 1, 55, 'full_knockout', 'open', 'public', '7d0e15b7a8f5e77b5b72e739f82dcbda', '8b1b0fa9df99a3e361b113cab4ac1c8751f3b7fde5f125894574d31ba589214e', NULL, NULL, 8, 1, 'TZS 72,000', 'participant_funded', 'TZS', 10000.00, 72000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-01', '2026-09-27', NULL, '2026-09-22 14:44:10', NULL, NULL, NULL, NULL, NULL, '2026-09-22 11:44:10', '2026-09-22 11:44:10'),
(11, 2, 'legends clash', '', 'eFootball', 1, 1, 52, 'full_knockout', 'open', 'public', '614b68c7e8d2aee3acfad26ed68c1c10', 'd96b9e6ead2f4c1ed8be26e19de582b9736e2530afae5d85d08758198b0b93cb', NULL, NULL, 8, 1, 'TZS 36,000', 'participant_funded', 'TZS', 5000.00, 36000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-01', '2026-09-25', NULL, '2026-09-22 19:51:28', NULL, NULL, NULL, NULL, NULL, '2026-09-22 16:51:28', '2026-09-22 16:51:28'),
(12, 2, 'legends clash', '', 'eFootball', 1, 1, 52, 'full_knockout', 'open', 'public', 'bdb727ecd7473829da755779cb752557', '69e0808cb2a9bc17e29bb32932450c8c38eb2b136ff4e52c6306f4bcfb4e40a3', NULL, NULL, 8, 1, 'TZS 36,000', 'participant_funded', 'TZS', 5000.00, 36000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-01', '2026-09-25', NULL, '2026-09-22 19:52:17', NULL, NULL, NULL, NULL, NULL, '2026-09-22 16:52:17', '2026-09-22 16:52:17'),
(13, 2, 'legends clash', '', 'eFootball', 1, 1, 52, 'full_knockout', 'open', 'public', 'bf577c8b6f0b845607b752ac0d57fd04', 'bd122716f8a4bb3520145e3e3bd64d4339a14bfc56a4e7e67a7411768c4cdd69', NULL, NULL, 8, 1, 'TZS 36,000', 'participant_funded', 'TZS', 5000.00, 36000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-01', '2026-09-25', NULL, '2026-09-22 20:55:07', NULL, NULL, NULL, NULL, NULL, '2026-09-22 17:55:07', '2026-09-22 17:55:07'),
(14, 2, 'champions', '', 'eFootball', 1, 1, 54, 'full_knockout', 'open', 'public', 'efdea780d1027a1f82bb4885eebca339', 'cbb0eea19ec663cc8718bba7efba35a4e3aa0d9e5470cb9ac18ba807ab03d905', NULL, NULL, 16, 1, 'TZS 72,000', 'participant_funded', 'TZS', 5000.00, 72000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-03', '2026-09-25', NULL, '2026-09-22 21:16:06', NULL, NULL, NULL, NULL, NULL, '2026-09-22 18:16:06', '2026-09-22 18:16:06'),
(15, 2, 'champions', '', 'eFootball', 1, 1, 54, 'full_knockout', 'open', 'public', '7a68bc5e46aff65ced0afdef85305562', 'cf94d84e32fcbff4a40a1fb6e9176f2b6423da7fc0b60e149db015d7e2591935', NULL, NULL, 16, 1, 'TZS 72,000', 'participant_funded', 'TZS', 5000.00, 72000.00, 0.00, 'auto', 10.00, 'best_of_1', 0, NULL, '2026-10-03', '2026-09-25', NULL, '2026-09-22 21:28:49', NULL, NULL, NULL, NULL, NULL, '2026-09-22 18:28:49', '2026-09-22 18:28:49');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_bracket_slots`
--

CREATE TABLE `tournament_bracket_slots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `stage` varchar(40) NOT NULL DEFAULT 'knockout',
  `round_number` int(11) NOT NULL,
  `bracket_position` int(11) NOT NULL,
  `slot_number` tinyint(3) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `source_match_id` int(11) DEFAULT NULL,
  `is_bye` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournament_cancellation_requests`
--

CREATE TABLE `tournament_cancellation_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(1000) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournament_cover_images`
--

CREATE TABLE `tournament_cover_images` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(60) NOT NULL DEFAULT 'Arena',
  `file_path` varchar(160) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournament_cover_images`
--

INSERT INTO `tournament_cover_images` (`id`, `name`, `category`, `file_path`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(50, '1266706137252769 940d9c97', 'General', 'assets/tournament-covers/1266706137252769-940d9c97.jpg', 1, 10, '2026-09-21 15:57:23', '2026-09-21 15:57:23'),
(51, '22588435624810597 5e5a3389', 'General', 'assets/tournament-covers/22588435624810597-5e5a3389.jpg', 1, 20, '2026-09-21 15:57:23', '2026-09-21 15:57:23'),
(52, '40391727904517571 36779ae6', 'General', 'assets/tournament-covers/40391727904517571-36779ae6.jpg', 1, 30, '2026-09-21 15:57:23', '2026-09-21 15:57:23'),
(53, '411797959700051150 638793f8', 'General', 'assets/tournament-covers/411797959700051150-638793f8.jpg', 1, 40, '2026-09-21 15:57:23', '2026-09-21 15:57:23'),
(54, 'Actualit Ligue Des Champions Revue De Presse Articles Et Synth Se A811e66d', 'General', 'assets/tournament-covers/actualit-ligue-des-champions-revue-de-presse-articles-et-synth-se-a811e66d.jpg', 1, 50, '2026-09-21 15:57:23', '2026-09-21 15:57:23'),
(55, 'Crafted In Collaboration With Fifa The Fifa Club Ea29d6e8', 'General', 'assets/tournament-covers/crafted-in-collaboration-with-fifa-the-fifa-club-ea29d6e8.jpg', 1, 60, '2026-09-21 15:57:23', '2026-09-21 15:57:23'),
(56, 'Duel Arena', 'General', 'assets/tournament-covers/duel-arena.svg', 1, 70, '2026-09-21 15:57:23', '2026-09-21 15:57:23');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_groups`
--

CREATE TABLE `tournament_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('scheduled','active','completed') NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournament_group_members`
--

CREATE TABLE `tournament_group_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `group_id` bigint(20) UNSIGNED NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rank_position` int(11) DEFAULT NULL,
  `qualified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournament_players`
--

CREATE TABLE `tournament_players` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('registered','active','eliminated','withdrawn') NOT NULL DEFAULT 'registered',
  `payment_status` enum('not_required','pending','paid','failed','refunded') NOT NULL DEFAULT 'not_required',
  `reservation_expires_at` datetime DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `league_points` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `goals_for` int(11) NOT NULL DEFAULT 0,
  `goals_against` int(11) NOT NULL DEFAULT 0,
  `current_round` int(11) NOT NULL DEFAULT 1,
  `is_eliminated` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournament_players`
--

INSERT INTO `tournament_players` (`id`, `tournament_id`, `user_id`, `status`, `payment_status`, `reservation_expires_at`, `checked_in_at`, `group_id`, `league_points`, `wins`, `losses`, `draws`, `goals_for`, `goals_against`, `current_round`, `is_eliminated`, `joined_at`) VALUES
(1, 1, 2, 'registered', 'pending', '2026-09-21 16:32:08', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-21 13:02:08'),
(2, 2, 2, 'registered', 'pending', '2026-09-22 02:34:32', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-21 23:04:32'),
(3, 3, 2, 'registered', 'pending', '2026-09-22 02:47:03', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-21 23:17:03'),
(4, 4, 2, 'registered', 'pending', '2026-09-22 03:06:05', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-21 23:36:05'),
(5, 5, 2, 'registered', 'pending', '2026-09-22 03:06:46', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-21 23:36:46'),
(6, 6, 2, 'registered', 'pending', '2026-09-22 13:20:12', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 09:50:12'),
(7, 7, 2, 'registered', 'pending', '2026-09-22 14:16:21', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 10:46:21'),
(8, 8, 2, 'registered', 'pending', '2026-09-22 14:17:46', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 10:47:46'),
(9, 9, 2, 'registered', 'pending', '2026-09-22 14:22:40', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 10:52:40'),
(10, 10, 2, 'registered', 'pending', '2026-09-22 15:14:10', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 11:44:10'),
(11, 11, 2, 'registered', 'pending', '2026-09-22 20:21:28', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 16:51:28'),
(12, 12, 2, 'registered', 'pending', '2026-09-22 20:22:17', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 16:52:17'),
(13, 13, 2, 'registered', 'pending', '2026-09-22 21:25:07', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 17:55:07'),
(14, 14, 2, 'registered', 'pending', '2026-09-22 21:46:06', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 18:16:06'),
(15, 15, 2, 'registered', 'pending', '2026-09-22 21:58:49', NULL, NULL, 0, 0, 0, 0, 0, 0, 1, 0, '2026-09-22 18:28:49');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_prizes`
--

CREATE TABLE `tournament_prizes` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `placement` int(11) NOT NULL,
  `placement_label` varchar(255) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'TZS',
  `status` enum('pending','allocated','processing','paid','failed') DEFAULT 'pending',
  `payout_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournament_series`
--

CREATE TABLE `tournament_series` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `player1_id` int(11) NOT NULL,
  `player2_id` int(11) NOT NULL,
  `best_of` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `player1_wins` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `player2_wins` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `winner_id` int(11) DEFAULT NULL,
  `status` enum('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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
  `mfa_secret` varchar(255) DEFAULT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `country`, `timezone`, `preferred_game`, `profile_setup_completed`, `theme_preference`, `whatsapp_country_code`, `whatsapp_number`, `whatsapp_verified_at`, `whatsapp_contact_opt_in`, `whatsapp_contact_updated_at`, `role`, `status`, `points`, `wins`, `losses`, `draws`, `total_matches`, `championships`, `avatar_url`, `bio`, `remember_token`, `remember_expires`, `created_at`, `last_login`, `updated_at`, `mfa_secret`, `mfa_enabled`) VALUES
(1, 'admin', 'mganiowen45@gmail.com', '$2y$10$.r70U.zxxDVCtU4Awnj5p.ShJMgmfrHm6G.SoGqpluEtt7KIgBkRq', 'Owen', 'Mgani', 'Tanzania', 'Africa/Dar_es_Salaam', 'eFootball', 1, 'esport', '+255', '+255614585412', NULL, 0, '2026-09-21 15:14:18', 'admin', 'active', 0, 0, 0, 0, 0, 0, 'gamer-neon.svg', NULL, NULL, NULL, '2026-09-19 13:02:06', '2026-09-21 12:20:13', '2026-09-21 12:20:13', NULL, 0),
(2, 'Pablo', 'mganiowen@gmail.com', '$2y$10$P01v0i9KX9B0lKMZWx.VIOjHtxFsoYYnrrAYhK8VhZnlP8dBm/Hga', 'Joshua', 'Mgina', 'Tanzania', 'Africa/Dar_es_Salaam', 'eFootball', 1, 'midnight', '+255', '+255744345120', NULL, 0, '2026-09-22 17:47:24', 'player', 'active', 0, 0, 0, 0, 0, 0, 'gamer-cyber.svg', NULL, NULL, NULL, '2026-09-21 12:59:25', '2026-09-22 16:49:13', '2026-09-22 16:49:13', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_achievements`
--

CREATE TABLE `user_achievements` (
  `user_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_blocks`
--

CREATE TABLE `user_blocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_payout_methods`
--

CREATE TABLE `user_payout_methods` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `provider` varchar(50) DEFAULT 'ClickPesa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_active_tournaments`
-- (See below for the actual view)
--
CREATE TABLE `vw_active_tournaments` (
`id` int(11)
,`name` varchar(100)
,`format` enum('1v1','full_knockout','group_knockout')
,`status` enum('draft','open','active','completed','cancelled')
,`game` varchar(80)
,`prize_pool` varchar(100)
,`max_players` int(11)
,`current_players` int(11)
,`slots_pct` decimal(14,0)
,`start_date` date
,`registration_deadline` date
,`creator_username` varchar(30)
,`creator_country` varchar(60)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_leaderboard`
-- (See below for the actual view)
--
CREATE TABLE `vw_leaderboard` (
`id` int(11)
,`username` varchar(30)
,`country` varchar(60)
,`preferred_game` varchar(80)
,`points` int(11)
,`wins` int(11)
,`losses` int(11)
,`draws` int(11)
,`total_matches` int(11)
,`win_rate_pct` decimal(15,1)
,`global_rank` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_league_standings`
-- (See below for the actual view)
--
CREATE TABLE `vw_league_standings` (
`tournament_id` int(11)
,`tournament_name` varchar(100)
,`user_id` int(11)
,`username` varchar(30)
,`country` varchar(60)
,`wins` int(11)
,`draws` int(11)
,`losses` int(11)
,`goals_for` int(11)
,`goals_against` int(11)
,`goal_diff` bigint(12)
,`league_points` int(11)
,`position` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_open_disputes`
-- (See below for the actual view)
--
CREATE TABLE `vw_open_disputes` (
`dispute_id` int(11)
,`status` enum('open','under_review','resolved')
,`outcome` enum('player1_wins','player2_wins','draw','replay','walkover_player1','walkover_player2','pending')
,`reason` varchar(255)
,`created_at` timestamp
,`match_id` int(11)
,`tournament_id` int(11)
,`tournament_name` varchar(100)
,`round_number` int(11)
,`player1_username` varchar(30)
,`player2_username` varchar(30)
,`raised_by_username` varchar(30)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_unread_message_counts`
-- (See below for the actual view)
--
CREATE TABLE `vw_unread_message_counts` (
`user_id` int(11)
,`unread_direct` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_active_tournaments`
--
DROP TABLE IF EXISTS `vw_active_tournaments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_active_tournaments`  AS SELECT `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`format` AS `format`, `t`.`status` AS `status`, `t`.`game` AS `game`, `t`.`prize_pool` AS `prize_pool`, `t`.`max_players` AS `max_players`, `t`.`current_players` AS `current_players`, round(`t`.`current_players` / `t`.`max_players` * 100,0) AS `slots_pct`, `t`.`start_date` AS `start_date`, `t`.`registration_deadline` AS `registration_deadline`, `u`.`username` AS `creator_username`, `u`.`country` AS `creator_country` FROM (`tournaments` `t` join `users` `u` on(`u`.`id` = `t`.`creator_id`)) WHERE `t`.`status` in ('open','active') ;

-- --------------------------------------------------------

--
-- Structure for view `vw_leaderboard`
--
DROP TABLE IF EXISTS `vw_leaderboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_leaderboard`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`country` AS `country`, `u`.`preferred_game` AS `preferred_game`, `u`.`points` AS `points`, `u`.`wins` AS `wins`, `u`.`losses` AS `losses`, `u`.`draws` AS `draws`, `u`.`total_matches` AS `total_matches`, CASE WHEN `u`.`total_matches` = 0 THEN 0 ELSE round(`u`.`wins` / `u`.`total_matches` * 100,1) END AS `win_rate_pct`, rank() over ( order by `u`.`points` desc) AS `global_rank` FROM `users` AS `u` WHERE `u`.`status` = 'active' AND `u`.`role` = 'player' ;

-- --------------------------------------------------------

--
-- Structure for view `vw_league_standings`
--
DROP TABLE IF EXISTS `vw_league_standings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_league_standings`  AS SELECT `tp`.`tournament_id` AS `tournament_id`, `t`.`name` AS `tournament_name`, `tp`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`country` AS `country`, `tp`.`wins` AS `wins`, `tp`.`draws` AS `draws`, `tp`.`losses` AS `losses`, `tp`.`goals_for` AS `goals_for`, `tp`.`goals_against` AS `goals_against`, `tp`.`goals_for`- `tp`.`goals_against` AS `goal_diff`, `tp`.`league_points` AS `league_points`, rank() over ( partition by `tp`.`tournament_id` order by `tp`.`league_points` desc,`tp`.`goals_for` - `tp`.`goals_against` desc,`tp`.`goals_for` desc) AS `position` FROM ((`tournament_players` `tp` join `tournaments` `t` on(`t`.`id` = `tp`.`tournament_id`)) join `users` `u` on(`u`.`id` = `tp`.`user_id`)) WHERE `tp`.`status` <> 'withdrawn' ;

-- --------------------------------------------------------

--
-- Structure for view `vw_open_disputes`
--
DROP TABLE IF EXISTS `vw_open_disputes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_open_disputes`  AS SELECT `d`.`id` AS `dispute_id`, `d`.`status` AS `status`, `d`.`outcome` AS `outcome`, `d`.`reason` AS `reason`, `d`.`created_at` AS `created_at`, `m`.`id` AS `match_id`, `m`.`tournament_id` AS `tournament_id`, `t`.`name` AS `tournament_name`, `m`.`round_number` AS `round_number`, `p1`.`username` AS `player1_username`, `p2`.`username` AS `player2_username`, `raiser`.`username` AS `raised_by_username` FROM (((((`disputes` `d` join `matches` `m` on(`m`.`id` = `d`.`match_id`)) join `tournaments` `t` on(`t`.`id` = `m`.`tournament_id`)) join `users` `p1` on(`p1`.`id` = `m`.`player1_id`)) join `users` `p2` on(`p2`.`id` = `m`.`player2_id`)) left join `users` `raiser` on(`raiser`.`id` = `d`.`raised_by`)) WHERE `d`.`status` in ('open','under_review') ORDER BY `d`.`created_at` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_unread_message_counts`
--
DROP TABLE IF EXISTS `vw_unread_message_counts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_unread_message_counts`  AS SELECT `messages`.`receiver_id` AS `user_id`, count(0) AS `unread_direct` FROM `messages` WHERE `messages`.`type` = 'direct' AND `messages`.`is_read` = 0 GROUP BY `messages`.`receiver_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_achievements_code` (`code`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_tournament` (`tournament_id`,`created_at`),
  ADD KEY `fk_announcements_author` (`author_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_actor` (`actor_id`,`created_at`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`,`created_at`);

--
-- Indexes for table `backup_records`
--
ALTER TABLE `backup_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_reports`
--
ALTER TABLE `contact_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact_reports_status` (`status`,`created_at`),
  ADD KEY `idx_contact_reports_match` (`match_id`),
  ADD KEY `fk_contact_reports_reporter` (`reporter_id`),
  ADD KEY `fk_contact_reports_reported` (`reported_user_id`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_disputes_match` (`match_id`),
  ADD KEY `idx_disputes_status` (`status`),
  ADD KEY `idx_disputes_raised_by` (`raised_by`),
  ADD KEY `idx_disputes_resolved_by` (`resolved_by`);

--
-- Indexes for table `financial_ledger`
--
ALTER TABLE `financial_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ledger_user` (`user_id`,`created_at`),
  ADD KEY `idx_ledger_tournament` (`tournament_id`,`created_at`),
  ADD KEY `fk_ledger_payment` (`payment_id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_games_slug` (`slug`);

--
-- Indexes for table `game_profiles`
--
ALTER TABLE `game_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_game_profile_identity` (`user_id`,`game_id`,`platform_id`),
  ADD KEY `idx_game_profiles_game_platform` (`game_id`,`platform_id`),
  ADD KEY `fk_game_profiles_platform` (`platform_id`);

--
-- Indexes for table `group_message_reads`
--
ALTER TABLE `group_message_reads`
  ADD PRIMARY KEY (`user_id`,`tournament_id`),
  ADD KEY `fk_group_reads_tournament` (`tournament_id`);

--
-- Indexes for table `job_locks`
--
ALTER TABLE `job_locks`
  ADD PRIMARY KEY (`job_name`);

--
-- Indexes for table `job_runs`
--
ALTER TABLE `job_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `run_id` (`run_id`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_matches_tournament` (`tournament_id`),
  ADD KEY `idx_matches_player1` (`player1_id`),
  ADD KEY `idx_matches_player2` (`player2_id`),
  ADD KEY `idx_matches_winner` (`winner_id`),
  ADD KEY `idx_matches_status` (`status`),
  ADD KEY `idx_matches_round` (`tournament_id`,`round_number`),
  ADD KEY `idx_matches_bracket_order` (`tournament_id`,`stage`,`round_number`,`bracket_position`),
  ADD KEY `idx_matches_next` (`next_match_id`),
  ADD KEY `idx_matches_series` (`series_id`,`series_game_number`),
  ADD KEY `idx_matches_group` (`group_id`);

--
-- Indexes for table `match_contact_events`
--
ALTER TABLE `match_contact_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_match_contact_match` (`match_id`,`created_at`),
  ADD KEY `idx_match_contact_requester` (`requester_id`,`created_at`),
  ADD KEY `fk_match_contact_opponent` (`opponent_id`);

--
-- Indexes for table `match_no_show_reports`
--
ALTER TABLE `match_no_show_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_no_show_reporter_match` (`match_id`,`reporter_id`),
  ADD KEY `idx_no_show_status` (`status`,`created_at`),
  ADD KEY `idx_no_show_accused` (`accused_id`,`created_at`),
  ADD KEY `fk_no_show_reporter` (`reporter_id`),
  ADD KEY `fk_no_show_reviewer` (`reviewed_by`);

--
-- Indexes for table `match_results`
--
ALTER TABLE `match_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mr_match_player` (`match_id`,`submitted_by`),
  ADD KEY `idx_mr_match` (`match_id`),
  ADD KEY `idx_mr_submitted_by` (`submitted_by`),
  ADD KEY `idx_mr_status` (`verification_status`);

--
-- Indexes for table `match_schedules`
--
ALTER TABLE `match_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_match_schedules_match` (`match_id`),
  ADD KEY `idx_match_schedules_status_time` (`status`,`proposed_start_utc`),
  ADD KEY `fk_match_schedules_proposed_by` (`proposed_by`),
  ADD KEY `fk_match_schedules_confirmed_by` (`confirmed_by`),
  ADD KEY `fk_match_schedules_rejected_by` (`rejected_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_sender` (`sender_id`),
  ADD KEY `idx_messages_receiver` (`receiver_id`),
  ADD KEY `idx_messages_tournament` (`tournament_id`),
  ADD KEY `idx_messages_type` (`type`),
  ADD KEY `idx_messages_conversation` (`sender_id`,`receiver_id`,`sent_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`),
  ADD KEY `idx_notif_is_read` (`user_id`,`is_read`),
  ADD KEY `idx_notif_type` (`type`),
  ADD KEY `idx_notif_created_at` (`created_at`),
  ADD KEY `idx_notifications_actor` (`actor_id`);

--
-- Indexes for table `operational_alerts`
--
ALTER TABLE `operational_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pw_reset_user` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payments_order_reference` (`order_reference`),
  ADD KEY `idx_payments_user` (`user_id`,`created_at`),
  ADD KEY `idx_payments_tournament` (`tournament_id`,`status`);

--
-- Indexes for table `payment_webhook_events`
--
ALTER TABLE `payment_webhook_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payment_webhook_event` (`provider`,`event_id`),
  ADD KEY `idx_webhook_order` (`order_reference`);

--
-- Indexes for table `payouts`
--
ALTER TABLE `payouts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payout_user_tournament` (`tournament_id`,`user_id`),
  ADD KEY `fk_payouts_user` (`user_id`);

--
-- Indexes for table `platforms`
--
ALTER TABLE `platforms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_platforms_slug` (`slug`);

--
-- Indexes for table `player_inactivity_strikes`
--
ALTER TABLE `player_inactivity_strikes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inactivity_user` (`user_id`,`created_at`),
  ADD KEY `idx_inactivity_tournament` (`tournament_id`,`created_at`),
  ADD KEY `fk_inactivity_match` (`match_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`attempt_key`),
  ADD KEY `idx_login_attempts_last` (`last_attempt_at`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_refunds_status` (`status`,`created_at`),
  ADD KEY `fk_refunds_payment` (`payment_id`);

--
-- Indexes for table `series_games`
--
ALTER TABLE `series_games`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_series_game_number` (`series_id`,`game_number`),
  ADD UNIQUE KEY `uq_series_game_match` (`match_id`);

--
-- Indexes for table `system_avatars`
--
ALTER TABLE `system_avatars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_system_avatars_file` (`file_path`),
  ADD KEY `idx_system_avatars_active` (`is_active`,`sort_order`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tournaments_share_token` (`share_token`),
  ADD KEY `idx_tournaments_creator` (`creator_id`),
  ADD KEY `idx_tournaments_status` (`status`),
  ADD KEY `idx_tournaments_format` (`format`),
  ADD KEY `idx_tournaments_start` (`start_date`),
  ADD KEY `idx_tournaments_winner` (`winner_id`),
  ADD KEY `idx_tournaments_game_platform` (`game_id`,`platform_id`),
  ADD KEY `idx_tournaments_visibility_status` (`visibility`,`status`),
  ADD KEY `idx_tournaments_funding` (`funding_model`,`entry_fee_amount`),
  ADD KEY `idx_tournaments_cover` (`cover_image_id`),
  ADD KEY `idx_tournaments_share_hash` (`share_token_hash`),
  ADD KEY `fk_tournaments_platform` (`platform_id`);

--
-- Indexes for table `tournament_bracket_slots`
--
ALTER TABLE `tournament_bracket_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bracket_slot` (`tournament_id`,`stage`,`round_number`,`bracket_position`,`slot_number`),
  ADD KEY `idx_bracket_user` (`user_id`),
  ADD KEY `fk_bracket_slot_source` (`source_match_id`);

--
-- Indexes for table `tournament_cancellation_requests`
--
ALTER TABLE `tournament_cancellation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cancel_requests_status` (`status`,`created_at`),
  ADD KEY `fk_cancel_requests_tournament` (`tournament_id`),
  ADD KEY `fk_cancel_requests_requester` (`requested_by`),
  ADD KEY `fk_cancel_requests_reviewer` (`reviewed_by`);

--
-- Indexes for table `tournament_cover_images`
--
ALTER TABLE `tournament_cover_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tournament_cover_file` (`file_path`),
  ADD KEY `idx_tournament_covers_active` (`is_active`,`sort_order`);

--
-- Indexes for table `tournament_groups`
--
ALTER TABLE `tournament_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tournament_group_name` (`tournament_id`,`name`);

--
-- Indexes for table `tournament_group_members`
--
ALTER TABLE `tournament_group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_group_member` (`group_id`,`user_id`),
  ADD KEY `idx_group_members_tournament` (`tournament_id`,`user_id`),
  ADD KEY `fk_group_members_user` (`user_id`);

--
-- Indexes for table `tournament_players`
--
ALTER TABLE `tournament_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tp_tournament_user` (`tournament_id`,`user_id`),
  ADD KEY `idx_tp_tournament` (`tournament_id`),
  ADD KEY `idx_tp_user` (`user_id`),
  ADD KEY `idx_tp_status` (`status`);

--
-- Indexes for table `tournament_prizes`
--
ALTER TABLE `tournament_prizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tournament_series`
--
ALTER TABLE `tournament_series`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_series_tournament` (`tournament_id`),
  ADD KEY `fk_series_player1` (`player1_id`),
  ADD KEY `fk_series_player2` (`player2_id`),
  ADD KEY `fk_series_winner` (`winner_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_points` (`points`),
  ADD KEY `idx_users_whatsapp_opt_in` (`whatsapp_contact_opt_in`);

--
-- Indexes for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD PRIMARY KEY (`user_id`,`achievement_id`),
  ADD KEY `idx_user_achievements_achievement` (`achievement_id`);

--
-- Indexes for table `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_blocks_pair` (`blocker_id`,`blocked_id`),
  ADD KEY `idx_user_blocks_blocked` (`blocked_id`);

--
-- Indexes for table `user_payout_methods`
--
ALTER TABLE `user_payout_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_records`
--
ALTER TABLE `backup_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_reports`
--
ALTER TABLE `contact_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `financial_ledger`
--
ALTER TABLE `financial_ledger`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `game_profiles`
--
ALTER TABLE `game_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_runs`
--
ALTER TABLE `job_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_contact_events`
--
ALTER TABLE `match_contact_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_no_show_reports`
--
ALTER TABLE `match_no_show_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_results`
--
ALTER TABLE `match_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_schedules`
--
ALTER TABLE `match_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `operational_alerts`
--
ALTER TABLE `operational_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payment_webhook_events`
--
ALTER TABLE `payment_webhook_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payouts`
--
ALTER TABLE `payouts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platforms`
--
ALTER TABLE `platforms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `player_inactivity_strikes`
--
ALTER TABLE `player_inactivity_strikes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `series_games`
--
ALTER TABLE `series_games`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_avatars`
--
ALTER TABLE `system_avatars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournaments`
--
ALTER TABLE `tournaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tournament_bracket_slots`
--
ALTER TABLE `tournament_bracket_slots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournament_cancellation_requests`
--
ALTER TABLE `tournament_cancellation_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournament_cover_images`
--
ALTER TABLE `tournament_cover_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `tournament_groups`
--
ALTER TABLE `tournament_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournament_group_members`
--
ALTER TABLE `tournament_group_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournament_players`
--
ALTER TABLE `tournament_players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tournament_prizes`
--
ALTER TABLE `tournament_prizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournament_series`
--
ALTER TABLE `tournament_series`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_blocks`
--
ALTER TABLE `user_blocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_payout_methods`
--
ALTER TABLE `user_payout_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcements_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `contact_reports`
--
ALTER TABLE `contact_reports`
  ADD CONSTRAINT `fk_contact_reports_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contact_reports_reported` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contact_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `fk_disputes_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_disputes_raised_by` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_disputes_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `financial_ledger`
--
ALTER TABLE `financial_ledger`
  ADD CONSTRAINT `fk_ledger_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ledger_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ledger_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `game_profiles`
--
ALTER TABLE `game_profiles`
  ADD CONSTRAINT `fk_game_profiles_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  ADD CONSTRAINT `fk_game_profiles_platform` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `fk_game_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_message_reads`
--
ALTER TABLE `group_message_reads`
  ADD CONSTRAINT `fk_group_reads_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_matches_player1` FOREIGN KEY (`player1_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matches_player2` FOREIGN KEY (`player2_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matches_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matches_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `match_contact_events`
--
ALTER TABLE `match_contact_events`
  ADD CONSTRAINT `fk_match_contact_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_match_contact_opponent` FOREIGN KEY (`opponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_match_contact_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `match_no_show_reports`
--
ALTER TABLE `match_no_show_reports`
  ADD CONSTRAINT `fk_no_show_accused` FOREIGN KEY (`accused_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_no_show_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_no_show_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_no_show_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `match_results`
--
ALTER TABLE `match_results`
  ADD CONSTRAINT `fk_mr_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mr_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `match_schedules`
--
ALTER TABLE `match_schedules`
  ADD CONSTRAINT `fk_match_schedules_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_match_schedules_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_match_schedules_proposed_by` FOREIGN KEY (`proposed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_match_schedules_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_pw_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payouts`
--
ALTER TABLE `payouts`
  ADD CONSTRAINT `fk_payouts_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payouts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `player_inactivity_strikes`
--
ALTER TABLE `player_inactivity_strikes`
  ADD CONSTRAINT `fk_inactivity_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inactivity_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inactivity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `series_games`
--
ALTER TABLE `series_games`
  ADD CONSTRAINT `fk_series_games_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_series_games_series` FOREIGN KEY (`series_id`) REFERENCES `tournament_series` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD CONSTRAINT `fk_tournaments_cover` FOREIGN KEY (`cover_image_id`) REFERENCES `tournament_cover_images` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tournaments_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tournaments_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  ADD CONSTRAINT `fk_tournaments_platform` FOREIGN KEY (`platform_id`) REFERENCES `platforms` (`id`),
  ADD CONSTRAINT `fk_tournaments_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tournament_bracket_slots`
--
ALTER TABLE `tournament_bracket_slots`
  ADD CONSTRAINT `fk_bracket_slot_source` FOREIGN KEY (`source_match_id`) REFERENCES `matches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bracket_slot_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bracket_slot_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tournament_cancellation_requests`
--
ALTER TABLE `tournament_cancellation_requests`
  ADD CONSTRAINT `fk_cancel_requests_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cancel_requests_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cancel_requests_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tournament_groups`
--
ALTER TABLE `tournament_groups`
  ADD CONSTRAINT `fk_tournament_groups_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tournament_group_members`
--
ALTER TABLE `tournament_group_members`
  ADD CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `tournament_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_members_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tournament_players`
--
ALTER TABLE `tournament_players`
  ADD CONSTRAINT `fk_tp_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tournament_prizes`
--
ALTER TABLE `tournament_prizes`
  ADD CONSTRAINT `tournament_prizes_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tournament_prizes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tournament_series`
--
ALTER TABLE `tournament_series`
  ADD CONSTRAINT `fk_series_player1` FOREIGN KEY (`player1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_series_player2` FOREIGN KEY (`player2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_series_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_series_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_achievements`
--
ALTER TABLE `user_achievements`
  ADD CONSTRAINT `fk_user_achievements_achievement` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_achievements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD CONSTRAINT `fk_user_blocks_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_blocks_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_payout_methods`
--
ALTER TABLE `user_payout_methods`
  ADD CONSTRAINT `user_payout_methods_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
