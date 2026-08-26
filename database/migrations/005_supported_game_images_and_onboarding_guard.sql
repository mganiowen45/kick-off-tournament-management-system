ALTER TABLE games
  ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER slug;

UPDATE games
SET image_path = CASE slug
  WHEN 'efootball' THEN 'assets/supported_games/eFootball.jpg'
  WHEN 'ea-sports-fc' THEN 'assets/supported_games/EA_sports.jpg'
  WHEN 'dream-league-soccer' THEN 'assets/supported_games/DLS.jpg'
  ELSE image_path
END
WHERE slug IN ('efootball', 'ea-sports-fc', 'dream-league-soccer');
