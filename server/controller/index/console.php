<?php
namespace server\controller\index;
use server\library\tpl;
use server\library\lib;
use server\library\db;
use server\model\index as model;
class Console extends model{
	public static function init(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		list($uri, $keyword, $init) = parent::search(24);
		$where = is_bool($keyword) ? [] : ['mode' => ['=', 0, 'and'], 'mid' => ['=', $login['id'], 'and'], 'name' => ['like', $keyword]];
		$tid = preg_match('/tid:(\d+)/i', $uri, $matches) ? $matches[1] : 0;
		$tid = $tid ? $tid > 1 ? '>' : '=' : '>=';
		$where = $where ?: array_merge($where, ['mode' => ['=', 0, 'and'], 'mid' => ['=', $login['id'], 'and'], 'type' => [$tid, 0]]);
		$tid_link = preg_replace('/\/keyword:[^\s][^\/]*|\/page:\d+|\/tid:\d+/i', '', $uri);
		$tid_link = $tid_link.$init.'tid:';
		list($count, $result, $page) = parent::page(['app', '{}', $where, ['date', 'desc'], 10, $uri]);
		$tpl = new tpl('index');
		$tpl->set('aside', 1);
		$tpl->set('keyword', $keyword);
		$tpl->set('tid', [$tid, $tid_link]);
		$tpl->set('count', $count);
		$tpl->set('result', $result);
		$tpl->set('page', $page);
		$tpl->set('size', function($size){return lib::byte($size);});
		$tpl->set('icon', function($aid, $type){return parent::icon($aid, $type);});
		$tpl->set('name', function($aid){return db::fetch('app', 'name', ['id' => $aid], [], [], '-');});
		$tpl->set('link', function($aid){return parent::PATH.'index.php/index/app/'.base64_encode($aid);});
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_application');
	}
	public static function upload(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$tpl = new tpl('index');
		$tpl->set('aside', 1);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_upload');
	}
	public static function edit(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$slice = explode('/', $_SERVER['PATH_INFO']);
		$id = isset($slice[3]) ? intval($slice[3]) : 0;
		$app = db::fetch('app', '[]', ['mode' => ['=', 0, 'and'], 'mid' => ['=', $login['id'], 'and'], 'id' => $id]);
		$app or exit(header('location:'.parent::PATH.'index.php/console'));
		$tpl = new tpl('index');
		$tpl->set('aside', 1);
		$tpl->set('app', $app);
		$tpl->set('icon', parent::icon($app['icon'], $app['type']));
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_edit');
	}
	public static function webview(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		list($uri, $keyword, $init) = parent::search(24);
		$where = is_bool($keyword) ? [] : ['mode' => ['=', 1, 'and'], 'mid' => ['=', $login['id'], 'and'], 'name' => ['like', $keyword]];
		$tid = preg_match('/tid:(\d+)/i', $uri, $matches) ? $matches[1] : 0;
		$tid = $tid ? $tid > 1 ? '>' : '=' : '>=';
		$where = $where ?: array_merge($where, ['mode' => ['=', 1, 'and'], 'mid' => ['=', $login['id'], 'and'], 'type' => [$tid, 0]]);
		$tid_link = preg_replace('/\/keyword:[^\s][^\/]*|\/page:\d+|\/tid:\d+/i', '', $uri);
		$tid_link = $tid_link.$init.'tid:';
		list($count, $result, $page) = parent::page(['app', '{}', $where, ['date', 'desc'], 10, $uri]);
		$tpl = new tpl('index');
		$tpl->set('aside', 2);
		$tpl->set('keyword', $keyword);
		$tpl->set('tid', [$tid, $tid_link]);
		$tpl->set('count', $count);
		$tpl->set('result', $result);
		$tpl->set('page', $page);
		$tpl->set('size', function($size){return lib::byte($size);});
		$tpl->set('icon', function($aid, $type){return parent::icon($aid, $type);});
		$tpl->set('name', function($aid){return db::fetch('app', 'name', ['id' => $aid], [], [], '-');});
		$tpl->set('link', function($aid){return parent::PATH.'index.php/index/app/'.base64_encode($aid);});
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_webview');
	}
	public static function package(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$tpl = new tpl('index');
		$tpl->set('aside', 2);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_package');
	}
	public static function ssl(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$openssl = function() use($login){
			$crt = parent::ROOT.'upload/ssl/'.$login['id'].'/domain.crt';
			if(is_file($crt) && extension_loaded('openssl')){
				$info = file_get_contents($crt);
				$data = openssl_x509_parse($info);
				return [@$data['subject']['CN'], @date('Y-m-d H:i:s', $data['validTo_time_t'])];
			}
			return ['-', '-'];
		};
		$tpl = new tpl('index');
		$tpl->set('aside', 2);
		$tpl->set('openssl', $openssl);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_ssl');
	}
	public static function buy(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$tpl = new tpl('index');
		$tpl->set('aside', 3);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('buy', lib::filter($GLOBALS['param']['extend']['extend_buy'], true));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_buy');
	}
	public static function price(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$tpl = new tpl('index');
		$tpl->set('aside', 4);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_price');
	}
	public static function notify(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$login['notify'] and db::update('member', ['notify' => 0], ['id' => $login['id']]);
		$tpl = new tpl('index');
		$tpl->set('aside', 5);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_notify');
	}
	public static function info(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$tpl = new tpl('index');
		$tpl->set('aside', 6);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_info');
	}
	public static function pswd(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$tpl = new tpl('index');
		$tpl->set('aside', 7);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_pswd');
	}
	public static function auth(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$card = function($type) use($login){
			$file = 'upload/avatar/'.$login['id'].'_'.$type.'.png';
			return is_file(parent::ROOT.$file) ? '<img src="'.parent::PATH.$file.'">' : '';
		};
		$tpl = new tpl('index');
		$tpl->set('aside', 8);
		$tpl->set('card', $card);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_auth');
	}
	public static function priv(){
		$login = parent::login();
		$login or exit(header('location:'.parent::PATH.'index.php/member'));
		$tpl = new tpl('index');
		$tpl->set('aside', 9);
		$tpl->set('login', $login);
		$tpl->set('avatar', parent::avatar($login['id']));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('console_priv');
	}
	public static function logout(){
		parent::login() or exit('1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		db::delete('session', ['key' => ['=', 'member', 'and'], 'salt' => lib::filter($_COOKIE['member'])]);
		setcookie('member', '', time() - 1, parent::PATH);
		echo '1';
	}
}
?>