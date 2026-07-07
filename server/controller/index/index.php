<?php
namespace server\controller\index;
use server\library\tpl;
use server\library\lib;
use server\library\db;
use server\model\index as model;
class Index extends model{
	public static function init(){
		$tpl = new tpl('index');
		$tpl->set('login', parent::login());
		$tpl->set('avatar', function($uid){return parent::avatar($uid);});
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('index');
	}
	public static function notice(){
		$GLOBALS['param']['basic']['power'] and exit(header('location:'.parent::PATH));
		$tpl = new tpl('index');
		$tpl->set('notice', lib::filter($GLOBALS['param']['basic']['notice'], true));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('notice');
	}
	public static function app(){
		$slice = explode('/', $_SERVER['PATH_INFO']);
		$id = isset($slice[3]) ? intval(base64_decode($slice[3])) : 0;
		$app = db::fetch('app', '[]', ['id' => $id]);
		$app or exit(header('location:'.parent::PATH));
		if($app['aid']){
			$with = db::fetch('app', '[]', ['id' => $app['aid']]);
			if(parent::device('ios')){
				$app = $app['type'] ? $app : $with;
			}elseif(parent::device('aos') || !parent::device('pc')){
				$app = $app['type'] ? $with : $app;
			}
		}
		$tpl = new tpl('index');
		$tpl->set('app', $app);
		$tpl->set('browser', parent::device('app'));
		$tpl->set('icon', parent::icon($app['icon'], $app['type']));
		$tpl->set('size', function($size){return lib::byte($size);});
		$tpl->set('with', db::fetch('app', '[]', ['id' => $app['aid']]));
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('app');
	}
	public static function hash(){
		lib::request() or exit('{"state":"Access Denied"}');
		$id = intval(base64_decode(lib::method('id')));
		$captcha = lib::method('captcha');
		if($GLOBALS['param']['extend']['extend_captcha']){
			ctype_alnum($captcha) and strlen($captcha) == 4 or exit('{"state":"Access Denied"}');
			$salt = isset($_COOKIE['captcha']) ? lib::filter($_COOKIE['captcha']) : '';
			$val = db::fetch('session', 'val', ['key' => ['=', 'captcha', 'and'], 'time' => ['>', time() - 300, 'and'], 'salt' => $salt]);
			$val and $val == strtolower($captcha) or exit('{"state":-4}');
		}
		$app = db::fetch('app', '[]', ['id' => $id]);
		$app or exit('{"state":"Access Denied"}');
		if(parent::device('ios')){
			$app['type'] or exit('{"state":-1}');
		}elseif(parent::device('aos') || !parent::device('pc')){
			$app['type'] and exit('{"state":-2}');
		}
		$point = intval($GLOBALS['param']['extend']['extend_down']);
		$credit = db::fetch('member', 'point', ['id' => $app['mid']]);
		$credit < $point and exit('{"state":-3}');
		db::update('member', ['point' => ['-', $point]], ['id' => $app['mid']]);
		db::update('app', ['down' => ['+', 1]], ['id' => $id]);
		$secret = md5(uniqid($id.'-'.lib::ip().'-', true));
		db::insert('hash', ['secret' => $secret, 'name' => $app['name'], 'identity' => $app['identity'], 'icon' => $app['icon'], 'file' => $app['file']]);
		echo '{"state":1,"msg":"'.$secret.'"}';
	}
	public static function plist(){
		$slice = explode('/', $_SERVER['PATH_INFO']);
		$secret = isset($slice[3]) ? lib::filter($slice[3]) : '';
		$hash = db::fetch('hash', '[]', ['secret' => $secret]);
		$hash or exit(header('location:'.parent::PATH));
		db::delete('hash', ['secret' => $secret]);
		header('content-type:application/xml;charset=utf-8');
		$file = 'https://'.$_SERVER['HTTP_HOST'].parent::PATH.'upload/app/'.$hash['file'];
		$icon = 'https://'.$_SERVER['HTTP_HOST'].parent::icon($hash['icon'], 1);
		$search = ['{file}', '{icon}', '{identity}', '{name}'];
		$replace = [$file, $icon, $hash['identity'], lib::convert($hash['name'], true)];
		$subject = file_get_contents(parent::ROOT.'client/pack/webview/ios.plist');
		echo str_replace($search, $replace, $subject);
	}
	public static function down(){
		$slice = explode('/', $_SERVER['PATH_INFO']);
		$secret = isset($slice[3]) ? lib::filter($slice[3]) : '';
		$hash = db::fetch('hash', '[]', ['secret' => $secret]);
		$hash or exit(header('location:'.parent::PATH));
		db::delete('hash', ['secret' => $secret]);
		header('location:'.parent::PATH.'upload/app/'.$hash['file']);
	}
}
?>