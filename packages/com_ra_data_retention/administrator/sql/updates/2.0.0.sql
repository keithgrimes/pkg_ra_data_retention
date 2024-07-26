DROP TABLE `#__ra_retention_categories`;
CREATE TABLE IF NOT EXISTS `#__ra_retention_categories` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`state` TINYINT(1)  NULL  DEFAULT 1,
`ordering` INT(11)  NULL  DEFAULT 0,
`checked_out` INT(11)  UNSIGNED,
`checked_out_time` DATETIME NULL  DEFAULT NULL ,
`created_by` INT(11)  NULL  DEFAULT 0,
`modified_by` INT(11)  NULL  DEFAULT 0,
`catid` INT(11)  NOT NULL ,
`type` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
`months` INT(10)  NOT NULL ,
PRIMARY KEY (`id`)
,KEY `idx_state` (`state`)
,KEY `idx_checked_out` (`checked_out`)
,KEY `idx_created_by` (`created_by`)
,KEY `idx_modified_by` (`modified_by`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;