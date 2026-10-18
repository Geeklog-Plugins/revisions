-- Staging-only Static Pages revision pilot. Prefix uses Geeklog table mapping.
-- Replace gl_revisions_sp_pilot with YOUR site's actual table prefix.
CREATE TABLE gl_revisions_sp_pilot (
  revision_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  operation_id CHAR(64) NOT NULL,
  sp_id VARCHAR(128) NOT NULL,
  current_sp_id VARCHAR(128) DEFAULT NULL,
  actor_id MEDIUMINT UNSIGNED NOT NULL,
  old_hash CHAR(64) NOT NULL,
  snapshot_json MEDIUMTEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'prepared',
  created_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  PRIMARY KEY (revision_id),
  UNIQUE KEY operation_id (operation_id),
  KEY page_history (sp_id,status,revision_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
