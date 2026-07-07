<?php
namespace server\controller\index;
use server\library\lib;
use server\library\db;
use server\model\index as model;
class Profile extends model{
	public static function avatar_upload(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		empty($_FILES) and exit('Access Denied');
		in_array(strtolower(pathinfo($_FILES['avatar']['name'])['extension']), ['png', 'jpg', 'jpeg', 'gif']) or exit('Access Denied');
		$_FILES['avatar']['size'] > 1048576 and exit('Access Denied');
		move_uploaded_file($_FILES['avatar']['tmp_name'], parent::ROOT.'upload/avatar/'.$login['id'].'.png');
		echo $login['id'];
	}
	public static function card_upload(){
		$login = parent::login();
		$login or exit('{"state":-1}');
		lib::request(['token', $_COOKIE['member']]) or exit('{"state":"Access Denied"}');
		empty($_FILES) and exit('{"state":"Access Denied"}');
		in_array(strtolower(pathinfo($_FILES['card']['name'])['extension']), ['png', 'jpg', 'jpeg', 'gif']) or exit('{"state":"Access Denied"}');
		$_FILES['card']['size'] > 2097152 and exit('{"state":"Access Denied"}');
		$stamp = $login['id'].'-card-'.time();
		$temp = parent::ROOT.'upload/temp';
		is_dir($temp) or mkdir($temp, 0777, true);
		move_uploaded_file($_FILES['card']['tmp_name'], $temp.'/'.$stamp.'.png');
		echo '{"state":1,"msg":"'.$stamp.'"}';
	}
	public static function info_save(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$nick = lib::method('member_nick');
		$phone = lib::method('member_phone');
		$link = lib::method('member_link');
		$intro = lib::method('member_intro');
		lib::length($nick, [1, 24]) or exit('Access Denied');
		!strlen($phone) or !preg_match('/[^\d]/', $phone) and !preg_match('/^[0]/', $phone) and strlen($phone) == 11 or exit('Access Denied');
		!strlen($link) or !preg_match('/[^\x21-\x7e]/', $link) and preg_match('/^https?:\/\//', $link) or exit('Access Denied');
		db::update('member', ['nick' => $nick, 'phone' => $phone, 'link' => $link, 'intro' => $intro], ['id' => $login['id']]);
		echo '1';
	}
	public static function pswd_save(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$mpw = lib::method('member_mpw');
		$rpw = lib::method('member_rpw');
		!preg_match('/[^\x21-\x7e]/', $mpw) and lib::length($mpw, [6, 24]) or exit('Access Denied');
		!preg_match('/[^\x21-\x7e]/', $rpw) and lib::length($rpw, [6, 24]) or exit('Access Denied');
		$mpw == $rpw and exit('Access Denied');
		md5($mpw) == $login['pw'] or exit('-2');
		db::update('member', ['pw' => md5($rpw)], ['id' => $login['id']]);
		echo '1';
	}
	public static function auth_save(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$before = lib::method('before');
		$after = lib::method('after');
		$hand = lib::method('hand');
		preg_match('/^'.$login['id'].'-card-\d+$/', $before) and is_file(parent::ROOT.'upload/temp/'.$before.'.png') or exit('Access Denied');
		preg_match('/^'.$login['id'].'-card-\d+$/', $after) and is_file(parent::ROOT.'upload/temp/'.$after.'.png') or exit('Access Denied');
		preg_match('/^'.$login['id'].'-card-\d+$/', $hand) and is_file(parent::ROOT.'upload/temp/'.$hand.'.png') or exit('Access Denied');
		rename(parent::ROOT.'upload/temp/'.$before.'.png', parent::ROOT.'upload/avatar/'.$login['id'].'_before.png');
		rename(parent::ROOT.'upload/temp/'.$after.'.png', parent::ROOT.'upload/avatar/'.$login['id'].'_after.png');
		rename(parent::ROOT.'upload/temp/'.$hand.'.png', parent::ROOT.'upload/avatar/'.$login['id'].'_hand.png');
		db::update('member', ['auth' => 2], ['id' => $login['id']]);
		echo '1';
	}
	public static function priv_save(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$name = lib::method('priv_name');
		$point = lib::method('priv_point');
		$auth = lib::method('priv_auth');
		$phone = lib::method('priv_phone');
		db::update('member', ['priv_name' => $name ? 1 : 0, 'priv_point' => $point ? 1 : 0, 'priv_auth' => $auth ? 1 : 0, 'priv_phone' => $phone ? 1 : 0], ['id' => $login['id']]);
		echo '1';
	}
	public static function buy_save(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$secret = lib::method('secret');
		preg_match('/^[a-z0-9]{32}$/', $secret) or exit('Access Denied');
		$point = db::fetch('buy', 'point', ['secret' => ['=', $secret, 'and'], 'mid' => 0]);
		$point or exit('-2');
		db::update('member', ['point' => ['+', $point]], ['id' => $login['id']]);
		db::update('buy', ['mid' => $login['id'], 'date' => date('Y-m-d H:i:s')], ['secret' => $secret]);
		echo $point;
	}
}
?>