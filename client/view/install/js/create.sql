DROP TABLE IF EXISTS `{tablepre}account`;

CREATE TABLE `{tablepre}account` (
	`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` varchar(24) NOT NULL,
	`pw` char(32) NOT NULL,
	`ip` varchar(15) NOT NULL,
	`date` datetime NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE (`name`)
) ENGINE=MyISAM DEFAULT CHARSET={charset};

DROP TABLE IF EXISTS `{tablepre}session`;

CREATE TABLE `{tablepre}session` (
	`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`salt` char(32) NOT NULL,
	`key` varchar(16) NOT NULL,
	`val` varchar(32) NOT NULL,
	`time` int(10) UNSIGNED NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE (`salt`,`key`)
) ENGINE=MyISAM DEFAULT CHARSET={charset};

DROP TABLE IF EXISTS `{tablepre}shield`;

CREATE TABLE `{tablepre}shield` (
	`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`ip` varchar(15) NOT NULL,
	`key` varchar(16) NOT NULL,
	`val` tinyint(3) UNSIGNED NOT NULL,
	`time` int(10) UNSIGNED NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET={charset};

DROP TABLE IF EXISTS `{tablepre}stat`;

CREATE TABLE `{tablepre}stat` (
	`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`ip` varchar(15) NOT NULL,
	`pv` smallint(5) UNSIGNED NOT NULL,
	`date` datetime NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET={charset};

DROP TABLE IF EXISTS `{tablepre}member`;

CREATE TABLE `{tablepre}member` (
	`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` varchar(32) NOT NULL,
	`pw` char(32) NOT NULL,
	`ip` varchar(15) NOT NULL,
	`nick` varchar(24) NOT NULL,
	`phone` varchar(11) NOT NULL DEFAULT '',
	`link` varchar(128) NOT NULL DEFAULT '',
	`intro` varchar(255) NOT NULL DEFAULT '',
	`priv_name` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
	`priv_point` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
	`priv_auth` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
	`priv_phone` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
	`notify` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
	`point` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
	`auth` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
	`lock` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
	`reg` datetime NOT NULL,
	`login` int(10) UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	UNIQUE (`name`)
) ENGINE=MyISAM DEFAULT CHARSET={charset};

DROP TABLE IF EXISTS `{tablepre}buy`;

CREATE TABLE `{tablepre}buy` (
	`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`mid` int(10) UNSIGNED NOT NULL DEFAULT '0',
	`secret` char(32) NOT NULL,
	`point` mediumint(8) UNSIGNED NOT NULL,
	`date` datetime NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE (`secret`)
) ENGINE=MyISAM DEFAULT CHARSET={charset};

DROP TABLE IF EXISTS `{tablepre}app`;

CREATE TABLE `{tablepre}app` (
	`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`mid` int(10) UNSIGNED NOT NULL,
	`name` varchar(24) NOT NULL DEFAULT '',
	`identity` varchar(64) NOT NULL DEFAULT '',
	`version` varchar(32) NOT NULL DEFAULT '',
	`minos` varchar(32) NOT NULL DEFAULT '',
	`type` tinyint(3) UNSIGNED NOT NULL,
	`mode` tinyint(3) UNSIGNED NOT NULL,
	`aid` int(10) UNSIGNED NOT NULL DEFAULT '0',
	`icon` varchar(128) NOT NULL,
	`file` varchar(128) NOT NULL,
	`down` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
	`size` bigint(20) UNSIGNED NOT NULL,
	`date` datetime NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET={charset};

DROP TABLE IF EXISTS `{tablepre}hash`;

CREATE TABLE `{tablepre}hash` (
	`id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`secret` char(32) NOT NULL,
	`name` varchar(24) NOT NULL DEFAULT '',
	`identity` varchar(64) NOT NULL DEFAULT '',
	`icon` varchar(128) NOT NULL,
	`file` varchar(128) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE (`secret`)
) ENGINE=MyISAM DEFAULT CHARSET={charset};