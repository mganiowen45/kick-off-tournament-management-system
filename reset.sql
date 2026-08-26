-- =========================================
-- KICK_OFF DATABASE RESET SCRIPT
-- Removes all data but keeps all tables/views
-- =========================================

SET FOREIGN_KEY_CHECKS = 0;

-- Child tables first
TRUNCATE TABLE disputes;
TRUNCATE TABLE match_results;
TRUNCATE TABLE matches;
TRUNCATE TABLE messages;
TRUNCATE TABLE notifications;
TRUNCATE TABLE tournament_players;

-- Parent tables last
TRUNCATE TABLE tournaments;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================
-- RESET COMPLETE
-- =========================================