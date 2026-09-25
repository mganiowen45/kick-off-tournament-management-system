START TRANSACTION;

UPDATE tournament_cover_images
SET file_path = 'assets/tournament-covers/mobile-arena.jpg'
WHERE file_path = 'assets/tournament-covers/mobile-arena.svg';

INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('auto_start_hours_after_fill', '12'),
  ('check_in_window_minutes', '60'),
  ('check_in_close_minutes_before_auto_start', '30')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

UPDATE system_settings
SET setting_value = '12'
WHERE setting_key = 'check_in_delay_hours_after_fill' AND setting_value = '24';

COMMIT;
