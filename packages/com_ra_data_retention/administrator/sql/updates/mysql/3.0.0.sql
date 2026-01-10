CREATE TABLE IF NOT EXISTS `j8eh1_ra_retention_journal` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`ordering` int  NULL  DEFAULT 0,
`type` varchar(11)  NOT NULL,
`start` varchar(25) NOT NULL,
`finish` varchar(25) NULL,
PRIMARY KEY (`id`)
,KEY `idx_type` (`type`)
,KEY `idx_start` (`start`)
);

CREATE TABLE IF NOT EXISTS `j8eh1_ra_retention_journal_entries` (
`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
`ordering` int  NULL  DEFAULT 0,
`journal` int NOT NULL,
`type` varchar(11)  NOT NULL,
`time` varchar(25) NOT NULL,
`summary` varchar(255) NOT NULL,
`data` varchar(1024) NULL,
PRIMARY KEY (`id`)
,KEY `idx_type` (`type`)
,KEY `idx_time` (`time`)
);