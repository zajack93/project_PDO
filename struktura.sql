
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `pesel` int(11) unsigned NOT NULL,
  `firstname` varchar(128) DEFAULT NULL,
  `lastname` varchar(128) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `update_counter` int(11) DEFAULT '0',
  PRIMARY KEY (`pesel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
