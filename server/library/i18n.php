<?php
// Simple i18n loader
if(!defined('BASE_ROOT')){
    return;
}
$__i18n_translations = [];
$lang = defined('BASE_LANG') ? BASE_LANG : 'vi';
$lang_file = BASE_ROOT.'client/view/lang/'.$lang.'.php';
if(is_file($lang_file)){
    $arr = include $lang_file;
    if(is_array($arr)) $__i18n_translations = $arr;
}
function __i18n_get($key){
    global $__i18n_translations;
    return isset($__i18n_translations[$key]) ? $__i18n_translations[$key] : $key;
}
// expose short helper for templates
if(!function_exists('__')){
    function __($k){ return __i18n_get($k); }
}
?>
