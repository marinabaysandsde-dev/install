<?php
ini_set('date.timezone', 'Asia/Shanghai');
ini_set('default_charset', 'utf-8');
define('BASE_NAME', '刀客源码网');
define('BASE_SITE', 'www.dkewl.com');
define('BASE_VERSION', 'app');
define('BASE_BUILD', '20251016');
define('BASE_ROOT', str_replace('\\', '/', substr(__DIR__, 0, -14)));
define('BASE_PATH', str_ireplace($_SERVER['DOCUMENT_ROOT'], '', BASE_ROOT));
define('BASE_CHARSET', ini_get('default_charset'));
define('BASE_DBCHARSET', str_replace('-', '', BASE_CHARSET));
define('BASE_DBHOST', '');
define('BASE_DBPORT', '');
define('BASE_DBUSER', '');
define('BASE_DBPW', '');
define('BASE_DBNAME', '');
define('BASE_DBTABLEPRE', '');
?>