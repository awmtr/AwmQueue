CREATE TABLE `awm_queue_sequence` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL,
  `task` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  `params` BLOB,
  `priority` TINYINT(3) UNSIGNED NOT NULL DEFAULT '2',
  `date` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `source` (`source`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPRESSED
