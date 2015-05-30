-- Adminer 3.3.3 MySQL dump

SET NAMES utf8;
SET foreign_key_checks = 0;
SET time_zone = 'SYSTEM';
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `userprefs`;
CREATE TABLE `userprefs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

INSERT INTO `userprefs` (`id`, `text`) VALUES
(1,	'a:5:{s:6:\"letter\";s:1:\"A\";s:6:\"number\";i:10;s:5:\"array\";a:4:{i:0;i:10;i:1;i:11;i:2;i:12;i:3;i:13;}s:5:\"assoc\";a:2:{s:5:\"color\";s:4:\"Blue\";s:4:\"size\";s:2:\"XL\";}s:8:\"long key\";s:3:\"yes\";}');

-- 2013-10-29 21:40:31
