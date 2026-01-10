CREATE TABLE IF NOT EXISTS `#__ra_retention_categories` (
`id` int(10) NOT NULL AUTO_INCREMENT ,
`type` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
`months` int(10) NOT NULL ,
`catid` int(11) NOT NULL ,
PRIMARY KEY (`id`)
)
ENGINE=MyISAM DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
AUTO_INCREMENT=1
;
