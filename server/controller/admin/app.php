<?php
namespace server\controller\admin;
use server\library\tpl;
use server\library\lib;
use server\library\db;
use server\model\admin as model;
class App extends model{
	public static function init(){
		parent::login() or exit(header('location:'.parent::PATH.'index.php/admin/login'));
		list($uri, $keyword, $init) = parent::search(24);
		$where = is_bool($keyword) ? [] : ['name' => ['like', $keyword]];
		$letter = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'W', 'X', 'Y', 'Z'];
		$alpha = preg_match('/alpha:([a-z])/i', $uri, $matches) ? strtoupper($matches[1]) : 0;
		$alpha = in_array($alpha, $letter) ? $alpha : 0;
		$where = $where ?: array_merge($where, $alpha ? ['name' => ['alpha', $alpha]] : []);
		$tid = preg_match('/tid:(\d+)/i', $uri, $matches) ? $matches[1] : 0;
		$tid = $tid ? $tid > 1 ? '>' : '=' : '>=';
		$where = $where ?: array_merge($where, $tid == '>=' ? [] : ['type' => [$tid, 0]]);
		$mid = preg_match('/mid:(\d+)/i', $uri, $matches) ? $matches[1] : 0;
		$mid = $mid ? $mid > 1 ? '>' : '=' : '>=';
		$where = $where ?: array_merge($where, $mid == '>=' ? [] : ['mode' => [$mid, 0]]);
		$uid = preg_match('/uid:(\d+)/i', $uri, $matches) ? $matches[1] : 0;
		$where = $where ?: array_merge($where, $uid ? ['mid' => $uid] : []);
		$link = preg_replace('/\/keyword:[^\s][^\/]*|\/page:\d+|\/alpha:[a-z]|\/tid:\d+|\/mid:\d+|\/uid:\d+/i', '', $uri);
		$limit = preg_match('/limit:(\d+)/i', $uri, $matches) ? $matches[1] : 0;
		$limit = in_array($limit, [10, 30, 40, 50]) ? $limit : 20;
		$limit_link = preg_replace('/\/page:\d+|\/limit:\d+/i', '', $uri);
		$limit_link = $limit_link.$init.'limit:';
		list($count, $result, $page) = parent::page(['app', '{}', $where, ['date', 'desc'], $limit, $uri]);
		$tpl = new tpl('admin');
		$tpl->set('keyword', $keyword);
		$tpl->set('alpha', [$alpha, $link.$init.'alpha:', $letter]);
		$tpl->set('tid', [$tid, $link.$init.'tid:']);
		$tpl->set('mid', [$mid, $link.$init.'mid:']);
		$tpl->set('limit', [$limit, $limit_link]);
		$tpl->set('count', $count);
		$tpl->set('result', $result);
		$tpl->set('page', $page);
		$tpl->set('size', function($size){return lib::byte($size);});
		$tpl->set('icon', function($aid, $type){return parent::icon($aid, $type);});
		$tpl->set('link', function($aid){return parent::PATH.'index.php/index/app/'.base64_encode($aid);});
		$tpl->set('name', function($mid){return db::fetch('member', 'name', ['id' => $mid], [], [], '-');});
		$tpl->set('const', ['path' => parent::PATH, 'charset' => parent::CHARSET]);
		$tpl->get('app');
	}
	public static function delete(){
		parent::login() or exit('-1');
		lib::request(['token', $_COOKIE['account']]) or exit('Access Denied');
		$id = lib::checkbox('checkbox');
		$result = db::fetch('app', '{}', ['id' => ['in', $id]]);
		db::update('app', ['aid' => 0], ['aid' => ['in', $id]]);
		db::delete('app', ['id' => ['in', $id]]);
		foreach($result as $object){
			@unlink(parent::ROOT.'upload/app/'.$object->icon);
			@unlink(parent::ROOT.'upload/app/'.$object->file);
		}
		echo '1';
	}
}
?>