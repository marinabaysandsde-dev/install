<?php
namespace server\model;
use server\library\lib;
use server\library\db;
use \ReflectionMethod;
class index{
	const ROOT = BASE_ROOT;
	const PATH = BASE_PATH;
	const CHARSET = BASE_CHARSET;
	private static function stat(){
		if(empty($_SERVER['QUERY_STRING']) && empty($_SERVER['HTTP_X_REQUESTED_WITH'])){
			$ip = lib::ip();
			$date = date('Y-m-d H:i:s');
			if($id = db::fetch('stat', 'id', ['date' => ['date', '=0', 'and'], 'ip' => $ip])){
				db::update('stat', ['pv' => ['+', 1], 'date' => $date], ['id' => $id]);
			}else{
				db::insert('stat', ['ip' => $ip, 'pv' => 1, 'date' => $date]);
			}
		}
	}
	public static function router($slice){
		$controller = $GLOBALS['param']['basic']['power'] && isset($slice[1]) && ctype_alpha($slice[1]) ? $slice[1] : 'index';
		$function = $GLOBALS['param']['basic']['power'] ? isset($slice[2]) ? $slice[2] : 'init' : 'notice';
		$loader = self::ROOT.'server/controller/index/'.$controller.'.php';
		if(!is_file(self::ROOT.'upload/install.lock')){
			header('location:'.self::PATH.'index.php/install');
		}elseif(is_file($loader)){
			require $loader;
			$object = 'server\controller\index\\'.ucfirst($controller);
			$let = function() use($object, $function){
				$method = new ReflectionMethod($object, $function);
				return $method->isPublic() && $method->class != __CLASS__;
			};
			method_exists($object, $function) and $let() and $object::$function(self::stat());
		}
	}
	protected static function login(){
		$online = intval($GLOBALS['param']['shield']['shield_online']);
		$salt = isset($_COOKIE['member']) ? lib::filter($_COOKIE['member']) : '';
		$id = db::fetch('session', 'val', ['key' => ['=', 'member', 'and'], 'time' => ['>', time() - $online * 86400, 'and'], 'salt' => $salt]);
		return db::fetch('member', '[]', ['id' => ['=', $id, 'and'], 'lock' => 0]);
	}
	protected static function page($array){
		list($table, $field, $where, $order, $limit, $uri) = $array;
		$slice = explode('/', $_SERVER['PATH_INFO']);
		$init = isset($slice[2]) ? '/' : '/init/';
		$match = preg_match('/page:(\d+)/i', $uri, $matches) ? $matches[1] : 0;
		$replace = preg_replace('/\/page:\d+/i', '', $uri);
		$link = $replace.$init.'page:';
		$count = db::fetch($table, '()', $where);
		$total = ceil(($count ?: 1) / $limit);
		$now = $match ? $match > $total ? $total : $match : 1;
		$result = db::fetch($table, $field, $where, $order, [$limit * ($now - 1), $limit]);
		$page = '<ul class="pagination"><li><a href="'.$link.'1">&lt;</a></li>';
		if($now > 1){
			$page .= '<li><a href="'.$link.($now - 1).'">&lt;&lt;</a></li>';
		}
		if($total < 11){
			for($i = 1; $i <= $total; $i++){
				if($i == $now){
					$page .= '<li class="active"><span>'.$i.'</span></li>';
				}else{
					$page .= '<li><a href="'.$link.$i.'">'.$i.'</a></li>';
				}
			}
		}else{
			if($now > ($total - 10)){
				$page .= '<li><span class="ellipsis">...</span></li>';
				for($i = $total - 9; $i <= $total; $i++){
					if($i == $now){
						$page .= '<li class="active"><span>'.$i.'</span></li>';
					}else{
						$page .= '<li><a href="'.$link.$i.'">'.$i.'</a></li>';
					}
				}
			}else{
				for($i = $now; $i < $now + 5; $i++){
					if($i == $now){
						$page .= '<li class="active"><span>'.$i.'</span></li>';
					}else{
						$page .= '<li><a href="'.$link.$i.'">'.$i.'</a></li>';
					}
				}
				$page .= '<li><span class="ellipsis">...</span></li>';
				for($i = $total - 4; $i <= $total; $i++){
					$page .= '<li><a href="'.$link.$i.'">'.$i.'</a></li>';
				}
			}
		}
		if($now < $total){
			$page .= '<li><a href="'.$link.($now + 1).'">&gt;&gt;</a></li>';
		}
		$page .= '<li><a href="'.$link.$total.'">&gt;</a></li></ul>';
		return [$count, $result, $page];
	}
	protected static function search($size){
		$uri = str_replace('\'', '%27', $_SERVER['REQUEST_URI']);
		$keyword = preg_match('/keyword:([^\s][^\/]*)/i', $uri, $matches) ? rawurldecode($matches[1]) : false;
		$keyword = is_bool($keyword) ? false : lib::length(lib::filter(lib::convert($keyword)), $size, true);
		$slice = explode('/', $_SERVER['PATH_INFO']);
		$init = isset($slice[2]) ? '/' : '/init/';
		return [$uri, $keyword, $init];
	}
	protected static function launcher($width, $height, $src, $path){
		list($s_width, $s_height, $s_type) = getimagesize($src);
		switch($s_type){
			case IMAGETYPE_GIF:
				$simage = imagecreatefromgif($src);
				break;
			case IMAGETYPE_JPEG:
				$simage = imagecreatefromjpeg($src);
				break;
			default:
				$simage = imagecreatefrompng($src);
		}
		$pimage = imagecreatetruecolor($width, $height);
		$bg = imagecolorallocatealpha($pimage, 255, 255, 255, 0);
		imagefill($pimage, 0, 0, $bg);
		imagecolortransparent($pimage, $bg);
		$ratio_w = $width / $s_width;
		$ratio_h = $height / $s_height;
		$ratio = $ratio_w < $ratio_h ? $ratio_h : $ratio_w;
		$tmp_w = intval($width / $ratio);
		$tmp_h = intval($height / $ratio);
		$tmp_img = imagecreatetruecolor($tmp_w, $tmp_h);
		$color = imagecolorallocate($tmp_img, 255, 255, 255);
		imagecolortransparent($tmp_img, $color);
		imagefill($tmp_img, 0, 0, $color);
		$s_x = intval(($s_width - $tmp_w) / 2);
		$s_y = intval(($s_height - $tmp_h) / 2);
		imagecopy($tmp_img, $simage, 0, 0, $s_x, $s_y, $tmp_w, $tmp_h);
		imagecopyresampled($pimage, $tmp_img, 0, 0, 0, 0, $width, $height, $tmp_w, $tmp_h);
		imagedestroy($tmp_img);
		imagesavealpha($pimage, true);
		imagepng($pimage, $path);
		imagedestroy($simage);
		imagedestroy($pimage);
	}
	protected static function sdk($level){
		$api = ['9' => '2.3', '10' => '2.3.3', '11' => '3.0', '12' => '3.1', '13' => '3.2', '14' => '4.0', '15' => '4.0.3', '16' => '4.1', '17' => '4.2', '18' => '4.3', '19' => '4.4', '20' => '4.4W', '21' => '5.0', '22' => '5.1', '23' => '6.0', '24' => '7.0', '25' => '7.1', '26' => '8.0', '27' => '8.1', '28' => '9', '29' => '10', '30' => '11', '31' => '12', '32' => '12L', '33' => '13', '34' => '14', '35' => '15', '36' => '16'];
		return array_key_exists($level, $api) ? 'android '.$api[$level].'+' : 'android';
	}
	protected static function device($type){
		$platform = ['pc' => 'windows|macintosh', 'ios' => 'iphone|ipad', 'aos' => 'android|mobile', 'app' => 'micromessenger|\sqq\/'];
		return preg_match('/'.$platform[$type].'/i', $_SERVER['HTTP_USER_AGENT']);
	}
	protected static function icon($uid, $type){
		$type = is_bool($type) ? db::fetch('app', 'type', ['id' => $uid]) : $type;
		$form = $type ? 'ios' : 'android';
		$alt = self::PATH.'client/view/index/css/'.$form.'.png';
		$img = is_numeric($uid) ? db::fetch('app', 'icon', ['id' => $uid]) : $uid;
		$file = 'upload/app/'.$img;
		return is_file(self::ROOT.$file) ? self::PATH.$file : $alt;
	}
	protected static function avatar($uid){
		$file = 'upload/avatar/'.$uid.'.png';
		$default = self::PATH.'client/view/index/css/avatar.png';
		return is_file(self::ROOT.$file) ? self::PATH.$file.'?'.time() : $default;
	}
}
?>