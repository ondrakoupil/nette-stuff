-- Adminer 3.3.3 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `userfilemanager`;
CREATE TABLE `userfilemanager` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `context` varchar(5) COLLATE utf8_czech_ci DEFAULT NULL,
  `filename` varchar(10) COLLATE utf8_czech_ci NOT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT '1' COMMENT 'Jen pro testování callbacku',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

INSERT INTO `userfilemanager` (`id`, `context`, `filename`, `valid`) VALUES
(1,	NULL,	'a.txt',	1),
(2,	'100',	'abc.txt',	1),
(3,	'20a',	'def.txt',	1),
(4,	NULL,	'xyz.txt',	0),
(5,	'100',	'jkl.txt',	0);

-- 2013-11-18 20:38:24
