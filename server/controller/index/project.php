<?php
namespace server\controller\index;
use server\library\lib;
use server\library\db;
use server\model\index as model;
class Project extends model{
	public static function icon_upload(){
		$login = parent::login();
		$login or exit('{"state":-1}');
		lib::request(['token', $_COOKIE['member']]) or exit('{"state":"Access Denied"}');
		empty($_FILES) and exit('{"state":"Access Denied"}');
		in_array(strtolower(pathinfo($_FILES['icon']['name'])['extension']), ['png', 'jpg', 'jpeg', 'gif']) or exit('{"state":"Access Denied"}');
		$_FILES['icon']['size'] > 2097152 and exit('{"state":"Access Denied"}');
		$stamp = $login['id'].'-icon-'.time();
		$temp = parent::ROOT.'upload/temp';
		is_dir($temp) or mkdir($temp, 0777, true);
		move_uploaded_file($_FILES['icon']['tmp_name'], $temp.'/'.$stamp.'.png');
		echo '{"state":1,"msg":"'.$stamp.'"}';
	}
	private static function update($name, $icon, $file, $identity, $version, $minos, $type, $mode, $mid, $id){
		$size = filesize($file);
		$dir = parent::ROOT.'upload/app/';
		$ext = strtolower(pathinfo($file)['extension']);
		$img = md5($mid.'-icon-'.time()).'.png';
		$doc = md5(uniqid($mid.'-file-'.lib::ip().'-', true)).'.'.$ext;
		@unlink($dir.db::fetch('app', 'icon', ['id' => $id]));
		@unlink($dir.db::fetch('app', 'file', ['id' => $id]));
		@rename($icon, $dir.$img) or copy($icon, $dir.$img);
		@rename($file, $dir.$doc) or copy($file, $dir.$doc);
		db::update('app', ['aid' => 0], ['aid' => $id]);
		db::update('app', ['name' => $name, 'identity' => $identity, 'version' => $version, 'minos' => $minos, 'type' => $type, 'aid' => 0, 'icon' => $img, 'file' => $doc, 'size' => $size, 'date' => date('Y-m-d H:i:s')], ['id' => $id]);
		echo '1';
	}
	private static function insert($name, $icon, $file, $identity, $version, $minos, $type, $mode, $mid, $id=0){
		$size = filesize($file);
		$dir = parent::ROOT.'upload/app/';
		$ext = strtolower(pathinfo($file)['extension']);
		$img = md5($mid.'-icon-'.time()).'.png';
		$doc = md5(uniqid($mid.'-file-'.lib::ip().'-', true)).'.'.$ext;
		@rename($icon, $dir.$img) or copy($icon, $dir.$img);
		@rename($file, $dir.$doc) or copy($file, $dir.$doc);
		db::insert('app', ['mid' => $mid, 'name' => $name, 'identity' => $identity, 'version' => $version, 'minos' => $minos, 'type' => $type, 'mode' => $mode, 'icon' => $img, 'file' => $doc, 'size' => $size, 'date' => date('Y-m-d H:i:s')]);
		echo '1';
	}
	private static function ios($name, $url, $icon, $mid, $time){
		$identity = substr(md5($time), 8, 16);
		$file = parent::ROOT.'upload/temp/'.$mid.'-file-'.$time.'.mobileconfig';
		$img = parent::ROOT.'upload/temp/'.$icon.'.png';
		$data = fread(fopen($img, 'rb'), filesize($img));
		$base64 = trim(chunk_split(base64_encode($data)));
		$setup = file_get_contents(parent::ROOT.'client/pack/webview/ios.mobileconfig');
		$setup = str_replace(['[icon]', '[url]', '[identity]', '[name]'], [$base64, $url, $identity, lib::convert($name, true)], $setup);
		fwrite(fopen($file, 'w+'), $setup);
		$version = preg_match('/<integer>(\d)<\/integer>/', $setup, $matches) ? $matches[1] : '';
		self::insert($name, $img, $file, $identity, number_format($version, 1), 'ios', 2, 1, $mid);
	}
	private static function android($name, $url, $icon, $mid, $time){
		$folder = parent::ROOT.'client/pack/webview/';
		$dir = parent::ROOT.'upload/temp/'.$mid.'-package-'.$time;
		require parent::ROOT.'server/pack/archive/zip.php';
		$zip = new \server\pack\archive\zip($folder.'android.zip');
		is_string($zip->export($dir)) and exit('zip:Access Denied');
		$identity = 'com.t'.$time;
		$setup = file_get_contents($dir.'/apktool.yml');
		$version = preg_match('/versionName: \'(.*?)\'/', $setup, $matches) ? $matches[1] : '';
		$minos = preg_match('/minSdkVersion: \'(\d+)\'/', $setup, $matches) ? $matches[1] : '';
		$mark = file_get_contents($dir.'/AndroidManifest.xml');
		$mark = str_replace('package="com.appid', 'package="'.$identity, $mark);
		fwrite(fopen($dir.'/AndroidManifest.xml', 'w+'), $mark);
		$link = file_get_contents($dir.'/smali/com/appid/MainActivity.smali');
		$link = str_replace('[url]', $url, $link);
		fwrite(fopen($dir.'/smali/com/appid/MainActivity.smali', 'w+'), $link);
		$title = file_get_contents($dir.'/res/values/strings.xml');
		$title = str_replace('[name]', lib::convert($name, true), $title);
		fwrite(fopen($dir.'/res/values/strings.xml', 'w+'), $title);
		$img = parent::ROOT.'upload/temp/'.$icon.'.png';
		parent::launcher(72, 72, $img, $dir.'/res/mipmap-hdpi/ic_launcher.png');
		parent::launcher(162, 162, $img, $dir.'/res/mipmap-hdpi/ic_launcher_foreground.png');
		parent::launcher(72, 72, $img, $dir.'/res/mipmap-hdpi/ic_launcher_round.png');
		parent::launcher(48, 48, $img, $dir.'/res/mipmap-mdpi/ic_launcher.png');
		parent::launcher(108, 108, $img, $dir.'/res/mipmap-mdpi/ic_launcher_foreground.png');
		parent::launcher(48, 48, $img, $dir.'/res/mipmap-mdpi/ic_launcher_round.png');
		parent::launcher(96, 96, $img, $dir.'/res/mipmap-xhdpi/ic_launcher.png');
		parent::launcher(216, 216, $img, $dir.'/res/mipmap-xhdpi/ic_launcher_foreground.png');
		parent::launcher(96, 96, $img, $dir.'/res/mipmap-xhdpi/ic_launcher_round.png');
		parent::launcher(144, 144, $img, $dir.'/res/mipmap-xxhdpi/ic_launcher.png');
		parent::launcher(324, 324, $img, $dir.'/res/mipmap-xxhdpi/ic_launcher_foreground.png');
		parent::launcher(144, 144, $img, $dir.'/res/mipmap-xxhdpi/ic_launcher_round.png');
		parent::launcher(192, 192, $img, $dir.'/res/mipmap-xxxhdpi/ic_launcher.png');
		parent::launcher(432, 432, $img, $dir.'/res/mipmap-xxxhdpi/ic_launcher_foreground.png');
		parent::launcher(192, 192, $img, $dir.'/res/mipmap-xxxhdpi/ic_launcher_round.png');
		function_exists('exec') or exit('exec:Access Denied');
		$part = preg_match('/win/i', PHP_OS) ? ['winnt', '.exe', '.bat'] : ['linux', '', ''];
		exec($folder.$part[0].'/bin/java'.$part[1].' -jar '.$folder.'apktool.jar b '.$dir.' -o '.$dir.'/unsigned.apk');
		exec($folder.$part[0].'/zipalign'.$part[1].' -v 4 '.$dir.'/unsigned.apk '.$dir.'/aligned.apk');
		exec($folder.$part[0].'/apksigner'.$part[2].' sign --ks '.$folder.'release.jks --ks-pass pass:release --out '.$dir.'/signed.apk '.$dir.'/aligned.apk');
		is_file($dir.'/signed.apk') or exit('file:Access Denied');
		self::insert($name, $img, $dir.'/signed.apk', $identity, $version, parent::sdk($minos), 0, 1, $mid);
	}
	public static function package(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$way = lib::method('way') ? 'ios' : 'android';
		$name = lib::method('name');
		$url = lib::method('url');
		$icon = lib::method('icon');
		$point = intval($GLOBALS['param']['extend']['extend_webview']);
		!$GLOBALS['param']['extend']['extend_auth'] or $login['auth'] == 1 or exit('-2');
		$login['point'] < $point and exit('-3');
		lib::length($name, [1, 24]) or exit('Access Denied');
		!preg_match('/[^\x21-\x7e]/', $url) and preg_match('/^https?:\/\//', $url) and lib::length($url, 128) or exit('Access Denied');
		preg_match('/^'.$login['id'].'-icon-\d+$/', $icon) and is_file(parent::ROOT.'upload/temp/'.$icon.'.png') or exit('Access Denied');
		db::update('member', ['point' => ['-', $point]], ['id' => $login['id']]);
		self::$way($name, $url, $icon, $login['id'], time());
	}
	private static function ipa($temp, $stamp, $mid, $id){
		$dir = parent::ROOT.'upload/temp/'.$mid.'-upload-'.time();
		require parent::ROOT.'server/pack/archive/zip.php';
		$zip = new \server\pack\archive\zip($temp.'/'.$stamp.'.ipa');
		is_string($zip->export($dir, ['/^Payload\/.*.app\/Info.plist$/', '/^Payload\/.*.app\/[^\/]*.png$/'])) and exit('zip:Access Denied');
		$plist = glob($dir.'/Payload/*.app/Info.plist');
		require parent::ROOT.'server/pack/ios/ipa.php';
		$ipa = new \server\pack\ios\ipa($plist[0]);
		$info = $ipa->parser();
		$name = lib::convert($info['label']);
		$option = [parent::ROOT.'client/view/index/css/ios.png', $dir.'.png', $id ? 'update' : 'insert'];
		$regexp = substr($info['icon'], -4) == '.png' ? $info['icon'] : $info['icon'].'*png';
		$png = glob($dir.'/Payload/*.app/'.str_replace('..', '', $regexp));
		$png and is_file($png[0]) and $ipa->icon($png[0], $option[1]) or copy($option[0], $option[1]);
		self::{$option[2]}($name, $option[1], $temp.'/'.$stamp.'.ipa', $info['identity'], $info['version'], 'ios '.$info['minos'].'+', 1, 0, $mid, $id);
	}
	private static function apk($temp, $stamp, $mid, $id){
		function_exists('exec') or exit('exec:Access Denied');
		$aapt = preg_match('/win/i', PHP_OS) ? 'winnt/aapt.exe' : 'linux/aapt';
		exec(parent::ROOT.'client/pack/webview/'.$aapt.' dump badging '.$temp.'/'.$stamp.'.apk', $output);
		$filter = function($pattern) use($output){
			$callback = array_values(array_filter($output, function($chunk) use($pattern){
				return preg_match($pattern, $chunk);
			}))[0];
			return preg_match($pattern, $callback, $matches) ? $matches[1] : '';
		};
		$identity = $filter('/package: name=\'(.*?)\'/');
		$version = $filter('/versionName=\'(.*?)\'/');
		$minos = parent::sdk($filter('/sdkVersion:\'(\d+)\'/'));
		$name = lib::convert($filter('/application-label-[zh\-CN]+:\'(.*?)\'|application: label=\'(.*?)\'/'));
		$img = $filter('/application: label=\'.*?\' icon=\'(.*?)\'/');
		$regexp = substr($img, -4) == '.xml' ? explode('/', $img)[0].'/.*/'.basename($img, '.xml').'.png' : $img;
		$dir = parent::ROOT.'upload/temp/'.$mid.'-upload-'.time();
		require parent::ROOT.'server/pack/archive/zip.php';
		$zip = new \server\pack\archive\zip($temp.'/'.$stamp.'.apk');
		is_string($zip->export($dir, ['/^'.str_replace(['/', '..'], ['\/', ''], $regexp).'$/'])) and exit('zip:Access Denied');
		$find = glob($dir.'/'.str_replace(['.*', '..'], ['*', ''], $regexp));
		if(!$find || !is_file($find[0])){
			require parent::ROOT.'server/pack/android/apk.php';
			$apk = new \server\pack\android\apk($temp.'/'.$stamp.'.apk');
			$icon = str_replace('..', '', $apk->parser()['icon']);
			is_string($zip->export($dir, ['/^'.str_replace('/', '\/', $icon).'$/'])) and exit('zip:Access Denied');
			$find = glob($dir.'/'.$icon);
		}
		$option = [$find, [$dir.'.png', parent::ROOT.'client/view/index/css/android.png'], $id ? 'update' : 'insert'];
		$find and is_file($find[0]) or array_splice($option, 0, 1) and copy($option[0][1], $option[0][0]);
		self::{$option[count($option) - 1]}($name, $option[0][0], $temp.'/'.$stamp.'.apk', $identity, $version, $minos, 0, 0, $mid, $id);
	}
	public static function file_upload(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		empty($_FILES) and exit('Access Denied');
		$type = strtolower(pathinfo($_FILES['file']['name'])['extension']);
		in_array($type, ['apk', 'ipa']) or exit('Access Denied');
		$id = intval(lib::method('id'));
		!$id or db::fetch('app', 'id', ['mode' => ['=', 0, 'and'], 'mid' => ['=', $login['id'], 'and'], 'id' => $id]) or exit('Access Denied');
		!$GLOBALS['param']['extend']['extend_auth'] or $login['auth'] == 1 or exit('-2');
		$stamp = $login['id'].'-file-'.time();
		$temp = parent::ROOT.'upload/temp';
		is_dir($temp) or mkdir($temp, 0777, true);
		move_uploaded_file($_FILES['file']['tmp_name'], $temp.'/'.$stamp.'.'.$type);
		self::$type($temp, $stamp, $login['id'], $id);
	}
	public static function ssl_upload(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		empty($_FILES) and exit('Access Denied');
		strtolower(pathinfo($_FILES['ssl']['name'])['extension']) == 'zip' or exit('Access Denied');
		$_FILES['ssl']['size'] > 262144 and exit('Access Denied');
		$dir = parent::ROOT.'upload/temp/'.$login['id'].'-ssl-'.time();
		is_dir($dir) or mkdir($dir, 0777, true);
		move_uploaded_file($_FILES['ssl']['tmp_name'], $dir.'.zip');
		require parent::ROOT.'server/pack/archive/zip.php';
		$zip = new \server\pack\archive\zip($dir.'.zip');
		is_string($zip->export($dir, ['/^.*.pem$/', '/^.*.key$/'])) and exit('zip:Access Denied');
		$pem = glob($dir.'/*.pem');
		$key = glob($dir.'/*.key');
		$pem and $key or exit('-2');
		function_exists('exec') or exit('exec:Access Denied');
		exec('openssl rsa -in '.$key[0].' -out '.$key[0].'.key');
		is_file($key[0].'.key') or exit('file:Access Denied');
		$separator = str_pad('BEGIN CERTIFICATE', 27, '-', STR_PAD_BOTH);
		$crt = file_get_contents($pem[0]);
		$crt = explode($separator, $crt);
		fwrite(fopen($dir.'/domain.crt', 'w+'), trim($separator.$crt[1]));
		fwrite(fopen($dir.'/chain.crt', 'w+'), $separator.$crt[2]);
		$folder = parent::ROOT.'upload/ssl/'.$login['id'];
		is_dir($folder) or mkdir($folder, 0777, true);
		@rename($key[0].'.key', $folder.'/domain.key') or copy($key[0].'.key', $folder.'/domain.key');
		@rename($dir.'/domain.crt', $folder.'/domain.crt') or copy($dir.'/domain.crt', $folder.'/domain.crt');
		@rename($dir.'/chain.crt', $folder.'/chain.crt') or copy($dir.'/chain.crt', $folder.'/chain.crt');
		echo '1';
	}
	public static function mobileconfig(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$id = intval(lib::method('id'));
		$file = db::fetch('app', 'file', ['type' => ['>', 0, 'and'], 'mode' => ['=', 1, 'and'], 'mid' => ['=', $login['id'], 'and'], 'id' => $id]);
		$file or exit('Access Denied');
		$dir = parent::ROOT.'upload/app/';
		$doc = md5(uniqid($login['id'].'-file-'.lib::ip().'-', true)).'.mobileconfig';
		$folder = parent::ROOT.'upload/ssl/'.$login['id'].'/';
		is_file($folder.'chain.crt') and is_file($folder.'domain.crt') and is_file($folder.'domain.key') or exit('-2');
		function_exists('exec') or exit('exec:Access Denied');
		exec('openssl smime -sign -in '.$dir.$file.' -out '.$dir.$doc.' -signer '.$folder.'domain.crt -inkey '.$folder.'domain.key -certfile '.$folder.'chain.crt -outform der -nodetach');
		is_file($dir.$doc) or exit('file:Access Denied');
		@unlink($dir.$file);
		$size = filesize($dir.$doc);
		db::update('app', ['type' => 3, 'file' => $doc, 'size' => $size], ['id' => $id]);
		echo '1';
	}
	public static function with(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$aid = intval(lib::method('aid'));
		$id = intval(lib::method('id'));
		$he = db::fetch('app', '[]', ['mid' => ['=', $login['id'], 'and'], 'id' => $aid]);
		$me = db::fetch('app', '[]', ['mid' => ['=', $login['id'], 'and'], 'id' => $id]);
		$he and $me or exit('-2');
		!$he['aid'] and !$me['aid'] or exit('-3');
		$he['mode'] == $me['mode'] or exit('-4');
		$he['type'] != $me['type'] or exit('-5');
		db::update('app', ['aid' => $id], ['id' => $aid]);
		db::update('app', ['aid' => $aid], ['id' => $id]);
		echo '1';
	}
	public static function cancel(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$id = intval(lib::method('id'));
		db::fetch('app', 'id', ['aid' => ['>', 0, 'and'], 'mid' => ['=', $login['id'], 'and'], 'id' => $id]) or exit('Access Denied');
		db::update('app', ['aid' => 0], ['aid' => ['=', $id, 'or'], 'id' => $id]);
		echo '1';
	}
	public static function edit(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$id = intval(lib::method('id'));
		$name = lib::method('name');
		$identity = lib::method('identity');
		$version = lib::method('version');
		$minos = lib::method('minos');
		$icon = lib::method('icon');
		$png = db::fetch('app', 'icon', ['mode' => ['=', 0, 'and'], 'mid' => ['=', $login['id'], 'and'], 'id' => $id]);
		$png or exit('Access Denied');
		lib::length($name, [1, 24]) or exit('Access Denied');
		!preg_match('/[^\x21-\x7e]/', $identity) and lib::length($identity, [1, 64]) or exit('Access Denied');
		!preg_match('/[^\x21-\x7e]/', $version) and lib::length($version, [1, 32]) or exit('Access Denied');
		!preg_match('/[^\x00-\x7f]/', $minos) and lib::length($minos, [1, 32]) or exit('Access Denied');
		$array = ['name' => $name, 'identity' => $identity, 'version' => $version, 'minos' => $minos];
		if(preg_match('/^'.$login['id'].'-icon-\d+$/', $icon)){
			$dir = parent::ROOT.'upload/app/';
			$img = md5($login['id'].'-icon-'.time()).'.png';
			$icon = parent::ROOT.'upload/temp/'.$icon.'.png';
			is_file($icon) or exit('Access Denied');
			@unlink($dir.$png);
			@rename($icon, $dir.$img) or copy($icon, $dir.$img);
			$array = array_merge($array, ['icon' => $img]);
		}
		db::update('app', $array, ['id' => $id]);
		echo '1';
	}
	public static function delete(){
		$login = parent::login();
		$login or exit('-1');
		lib::request(['token', $_COOKIE['member']]) or exit('Access Denied');
		$id = lib::checkbox('checkbox');
		$result = db::fetch('app', '{}', ['mid' => ['=', $login['id'], 'and'], 'id' => ['in', $id]]);
		foreach($result as $object){
			@unlink(parent::ROOT.'upload/app/'.$object->icon);
			@unlink(parent::ROOT.'upload/app/'.$object->file);
			db::update('app', ['aid' => 0], ['aid' => $object->id]);
			db::delete('app', ['id' => $object->id]);
		}
		echo '1';
	}
}
?>