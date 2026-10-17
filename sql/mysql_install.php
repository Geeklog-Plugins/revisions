<?php
$_SQL[] = "CREATE TABLE {$_TABLES['revisions_baseline']} (
sp_id varchar(128) NOT NULL, snapshot_json mediumtext NOT NULL,
content_hash char(64) NOT NULL, updated_at datetime NOT NULL,
PRIMARY KEY (sp_id)) ENGINE=InnoDB";
$_SQL[] = "CREATE TABLE {$_TABLES['revisions_history']} (
revision_id bigint unsigned NOT NULL AUTO_INCREMENT,
sp_id varchar(128) NOT NULL, actor_id mediumint unsigned NOT NULL DEFAULT 0,
snapshot_json mediumtext NOT NULL, content_hash char(64) NOT NULL,
created_at datetime NOT NULL, PRIMARY KEY (revision_id),
KEY page_history (sp_id, revision_id)) ENGINE=InnoDB";
