-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 26, 2026 at 08:41 PM
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
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `player1_id` int(11) NOT NULL,
  `player2_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL DEFAULT 1,
  `status` enum('scheduled','pending_result','confirmed','disputed','walkover','cancelled') NOT NULL DEFAULT 'scheduled',
  `player1_score` int(11) DEFAULT NULL,
  `player2_score` int(11) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `is_draw` tinyint(1) NOT NULL DEFAULT 0,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `played_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`id`, `tournament_id`, `player1_id`, `player2_id`, `round_number`, `status`, `player1_score`, `player2_score`, `winner_id`, `is_draw`, `scheduled_at`, `played_at`, `confirmed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 5, 4, 2, 'confirmed', 3, 1, 5, 0, '2026-06-20 17:00:00', '2026-06-20 17:35:00', '2026-06-20 17:50:00', '2026-03-20 13:57:40', '2026-03-20 13:57:40'),
(2, 1, 5, 7, 3, 'pending_result', NULL, NULL, NULL, 0, '2026-06-26 17:00:00', NULL, NULL, '2026-03-20 13:57:40', '2026-03-20 13:57:40');

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

--
-- Dumping data for table `match_results`
--

INSERT INTO `match_results` (`id`, `match_id`, `submitted_by`, `claimed_result`, `my_score`, `opponent_score`, `screenshot_url`, `notes`, `verification_status`, `submitted_at`) VALUES
(1, 1, 5, 'win', 3, 1, '/uploads/results/demo-match1-player5.jpg', NULL, 'confirmed', '2026-03-20 13:57:40'),
(2, 1, 4, 'loss', 1, 3, '/uploads/results/demo-match1-player4.jpg', NULL, 'confirmed', '2026-03-20 13:57:40');

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

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `tournament_id`, `type`, `content`, `is_read`, `sent_at`) VALUES
(1, 7, 5, NULL, 'direct', 'Demo message: are you ready for tonight?', 0, '2026-03-20 13:57:40'),
(2, 5, 7, NULL, 'direct', 'Demo message: ready, good luck.', 1, '2026-03-20 13:57:40'),
(3, 7, 5, NULL, 'direct', 'Demo message: see you at 8.', 0, '2026-03-20 13:57:40'),
(4, 2, NULL, 1, 'group', 'QF matches are now scheduled! Check the bracket for your matchup.', 0, '2026-03-20 13:57:40'),
(5, 6, NULL, 1, 'group', 'QF results are in - PHANTOM X beat ZERO HOUR 2-0!', 0, '2026-03-20 13:57:40'),
(6, 5, NULL, 1, 'group', 'My match vs GhostDribbler is tonight at 8PM. Will post result after!', 0, '2026-03-20 13:57:40'),
(7, 9, NULL, 2, 'group', 'Demo message: welcome to the fixture group.', 0, '2026-03-24 21:31:46'),
(8, 9, NULL, 3, 'group', 'Demo message: please coordinate your match time.', 0, '2026-03-24 21:47:24'),
(9, 11, NULL, 3, 'group', 'Demo message: shared match room code.', 0, '2026-03-24 21:47:55'),
(10, 9, NULL, 3, 'group', 'Demo message: confirm availability.', 0, '2026-03-24 21:59:00'),
(11, 11, NULL, 3, 'group', 'Demo message: availability confirmed.', 0, '2026-03-24 21:59:41'),
(12, 9, NULL, 3, 'group', 'Demo message: good match.', 0, '2026-03-24 21:59:58');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `body` text NOT NULL,
  `type` enum('result_confirmed','result_disputed','dispute_resolved','match_reminder','new_message','tournament_update','tournament_joined','round_advanced','account_warning','system') NOT NULL DEFAULT 'system',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `link_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `body`, `type`, `is_read`, `link_url`, `created_at`) VALUES
(1, 5, 'Result confirmed', 'Your 3-1 win vs SkillzDribbler has been confirmed.', 'result_confirmed', 0, '/tournament_detail.html', '2026-03-20 13:57:44'),
(2, 5, 'New message', 'GhostDribbler sent you a message.', 'new_message', 0, '/chat.html', '2026-03-20 13:57:44'),
(3, 5, 'Match reminder', 'You have a match tonight at 8:00 PM vs GhostDribbler.', 'match_reminder', 0, '/tournament_detail.html', '2026-03-20 13:57:44'),
(4, 2, 'New dispute', 'A conflict was raised on match #2. Please review.', 'result_disputed', 0, '/admin_disputes.html', '2026-03-20 13:57:44'),
(5, 9, 'New player joined', 'A new player joined your tournament: legends clash', 'tournament_joined', 0, 'tournament_detail.html', '2026-03-21 09:03:02'),
(6, 11, 'New player joined', 'A new player joined your tournament: unyamaa', 'tournament_joined', 0, 'tournament_detail.html', '2026-03-24 21:46:56');

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
  `format` enum('1v1','knockout','league') NOT NULL,
  `status` enum('draft','open','active','completed','cancelled') NOT NULL DEFAULT 'open',
  `visibility` enum('public','private') NOT NULL DEFAULT 'public',
  `max_players` int(11) NOT NULL DEFAULT 16,
  `current_players` int(11) NOT NULL DEFAULT 0,
  `prize_pool` varchar(100) DEFAULT NULL,
  `match_legs` enum('best_of_1','best_of_3','best_of_5') NOT NULL DEFAULT 'best_of_3',
  `current_round` int(11) NOT NULL DEFAULT 0,
  `start_date` date NOT NULL,
  `registration_deadline` date NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournaments`
--

INSERT INTO `tournaments` (`id`, `creator_id`, `name`, `description`, `game`, `format`, `status`, `visibility`, `max_players`, `current_players`, `prize_pool`, `match_legs`, `current_round`, `start_date`, `registration_deadline`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'Elite Knockout Cup - S4', 'The premier season 4 knockout tournament. 32 players battle through elimination rounds.', 'eFootball (PES Mobile)', 'knockout', 'active', 'public', 32, 6, '$2,000', 'best_of_3', 3, '2026-06-15', '2026-06-13', NULL, '2026-03-20 13:57:40', '2026-03-20 13:57:40'),
(2, 9, 'legends clash', 'Fair play game', 'eFootball (PES Mobile)', '1v1', 'open', 'public', 4, 2, '500', 'best_of_1', 0, '2026-03-21', '2026-03-23', NULL, '2026-03-21 09:00:02', '2026-03-21 09:03:02'),
(3, 11, 'unyamaa', 'fair play', 'eFootball (PES Mobile)', '1v1', 'open', 'public', 2, 2, '00', 'best_of_1', 0, '2026-03-25', '2026-03-26', NULL, '2026-03-24 21:46:32', '2026-03-24 21:46:56');

-- --------------------------------------------------------

--
-- Table structure for table `tournament_players`
--

CREATE TABLE `tournament_players` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('registered','active','eliminated','withdrawn') NOT NULL DEFAULT 'registered',
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

INSERT INTO `tournament_players` (`id`, `tournament_id`, `user_id`, `status`, `league_points`, `wins`, `losses`, `draws`, `goals_for`, `goals_against`, `current_round`, `is_eliminated`, `joined_at`) VALUES
(1, 1, 2, 'active', 0, 3, 0, 0, 0, 0, 3, 0, '2026-03-20 13:57:40'),
(2, 1, 3, 'active', 0, 2, 1, 0, 0, 0, 3, 0, '2026-03-20 13:57:40'),
(3, 1, 4, 'active', 0, 2, 1, 0, 0, 0, 3, 0, '2026-03-20 13:57:40'),
(4, 1, 5, 'active', 0, 2, 1, 0, 0, 0, 3, 0, '2026-03-20 13:57:40'),
(5, 1, 6, 'active', 0, 2, 1, 0, 0, 0, 3, 0, '2026-03-20 13:57:40'),
(6, 1, 7, 'active', 0, 2, 1, 0, 0, 0, 3, 0, '2026-03-20 13:57:40'),
(7, 2, 9, 'active', 0, 0, 0, 0, 0, 0, 1, 0, '2026-03-21 09:00:02'),
(8, 2, 10, 'registered', 0, 0, 0, 0, 0, 0, 1, 0, '2026-03-21 09:03:02'),
(9, 3, 11, 'active', 0, 0, 0, 0, 0, 0, 1, 0, '2026-03-24 21:46:32'),
(10, 3, 9, 'registered', 0, 0, 0, 0, 0, 0, 1, 0, '2026-03-24 21:46:56');

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
  `preferred_game` varchar(80) NOT NULL DEFAULT 'eFootball (PES Mobile)',
  `role` enum('player','admin') NOT NULL DEFAULT 'player',
  `status` enum('active','warned','banned') NOT NULL DEFAULT 'active',
  `points` int(11) NOT NULL DEFAULT 0,
  `wins` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `draws` int(11) NOT NULL DEFAULT 0,
  `total_matches` int(11) NOT NULL DEFAULT 0,
  `avatar_url` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `country`, `preferred_game`, `role`, `status`, `points`, `wins`, `losses`, `draws`, `total_matches`, `avatar_url`, `bio`, `created_at`, `last_login`, `updated_at`) VALUES
(1, 'admin', 'admin@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Platform', 'Admin', 'Tanzania', 'eFootball (PES Mobile)', 'admin', 'active', 0, 0, 0, 0, 0, NULL, NULL, '2026-03-20 13:57:40', NULL, '2026-03-20 13:57:40'),
(2, 'GoalMaster_KE', 'goalmaster@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Kenya', 'eFootball (PES Mobile)', 'player', 'active', 5340, 42, 5, 1, 48, NULL, NULL, '2026-03-20 13:57:40', NULL, '2026-03-20 13:57:40'),
(3, 'Striker_TZ', 'striker-tz@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Tanzania', 'eFootball (PES Mobile)', 'player', 'active', 4910, 35, 4, 1, 40, NULL, NULL, '2026-03-20 13:57:40', NULL, '2026-03-20 13:57:40'),
(4, 'PenaltyKing99', 'penaltyking@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Uganda', 'FIFA Mobile', 'player', 'active', 4620, 30, 5, 1, 36, NULL, NULL, '2026-03-20 13:57:40', NULL, '2026-03-20 13:57:40'),
(5, 'Striker_FC', 'striker-fc@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Tanzania', 'eFootball (PES Mobile)', 'player', 'active', 1240, 14, 4, 0, 18, NULL, NULL, '2026-03-20 13:57:40', NULL, '2026-03-20 13:57:40'),
(6, 'TacticalFC_UG', 'tacticalfc@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Uganda', 'eFootball (PES Mobile)', 'player', 'active', 3870, 25, 7, 0, 32, NULL, NULL, '2026-03-20 13:57:40', NULL, '2026-03-20 13:57:40'),
(7, 'GhostDribbler', 'ghostdribbler@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Kenya', 'eFootball (PES Mobile)', 'player', 'active', 3510, 20, 7, 1, 28, NULL, NULL, '2026-03-20 13:57:40', NULL, '2026-03-20 13:57:40'),
(8, 'DemoPlayer08', 'demo-player-08@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Tanzania', 'eFootball (PES Mobile)', 'player', 'active', 0, 0, 0, 0, 0, NULL, NULL, '2026-03-20 14:10:21', NULL, '2026-03-20 14:10:21'),
(9, 'DemoPlayer09', 'demo-player-09@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Tanzania', 'eFootball (PES Mobile)', 'player', 'active', 0, 0, 0, 0, 0, NULL, NULL, '2026-03-20 14:12:39', '2026-03-24 21:58:08', '2026-03-24 21:58:08'),
(10, 'DemoPlayer10', 'demo-player-10@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Tanzania', 'eFootball (PES Mobile)', 'player', 'active', 0, 0, 0, 0, 0, NULL, NULL, '2026-03-21 09:02:54', NULL, '2026-03-21 09:02:54'),
(11, 'DemoPlayer11', 'demo-player-11@example.test', '$2y$12$DEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMOHASHDEMO', 'Demo', 'Player', 'Tanzania', 'eFootball (PES Mobile)', 'player', 'active', 0, 0, 0, 0, 0, NULL, NULL, '2026-03-24 21:45:19', NULL, '2026-03-24 21:45:19');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_active_tournaments`
-- (See below for the actual view)
--
CREATE TABLE `vw_active_tournaments` (
`id` int(11)
,`name` varchar(100)
,`format` enum('1v1','knockout','league')
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
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_disputes_match` (`match_id`),
  ADD KEY `idx_disputes_status` (`status`),
  ADD KEY `idx_disputes_raised_by` (`raised_by`),
  ADD KEY `idx_disputes_resolved_by` (`resolved_by`);

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
  ADD KEY `idx_matches_round` (`tournament_id`,`round_number`);

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
  ADD KEY `idx_notif_created_at` (`created_at`);

--
-- Indexes for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tournaments_creator` (`creator_id`),
  ADD KEY `idx_tournaments_status` (`status`),
  ADD KEY `idx_tournaments_format` (`format`),
  ADD KEY `idx_tournaments_start` (`start_date`);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_points` (`points`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `match_results`
--
ALTER TABLE `match_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tournaments`
--
ALTER TABLE `tournaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tournament_players`
--
ALTER TABLE `tournament_players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `fk_disputes_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_disputes_raised_by` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_disputes_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_matches_player1` FOREIGN KEY (`player1_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matches_player2` FOREIGN KEY (`player2_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matches_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_matches_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `match_results`
--
ALTER TABLE `match_results`
  ADD CONSTRAINT `fk_mr_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mr_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD CONSTRAINT `fk_tournaments_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `tournament_players`
--
ALTER TABLE `tournament_players`
  ADD CONSTRAINT `fk_tp_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
