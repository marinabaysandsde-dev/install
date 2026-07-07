<?php
namespace server\pack\ios;
class ipa{
	private $pos;
	private $info;
	private $list;
	private $type;
	private $plist;
	private $offset;
	private $refsize;
	public function __construct($plist, $type=0){
		if($type > 0){
			$this->type = $type;
			$this->plist = $plist;
		}else{
			$info = file_get_contents($plist);
			if(substr($info, 0, 6) == 'bplist'){
				$this->info = $info;
				$this->list = $this->parseBinaryString()->toArray();
			}else{
				$dom = new \DOMDocument();
				$dom->loadXML($info);
				$this->list = $this->parseXmlString($dom->documentElement);
			}
		}
	}
	public function parser(){
		$identity = @$this->list['CFBundleIdentifier'];
		$version = @$this->list['CFBundleShortVersionString'];
		$minos = @$this->list['MinimumOSVersion'];
		$label = @$this->list['CFBundleDisplayName'];
		$icon = @$this->list['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles'][0];
		return ['identity' => $identity, 'version' => $version, 'minos' => $minos, 'label' => $label, 'icon' => $icon];
	}
	public function icon($src, $path){
		$buffer = file_get_contents($src);
		if(@imagecreatefromstring($buffer)){
			$target = $buffer;
		}else{
			$target = substr($buffer, 0, 8);
			$breakLoop = false;
			$chunkPos = 8;
			$idatAcc = '';
			while($chunkPos < strlen($buffer)){
				$skip = false;
				$chunkLength = unpack('N', substr($buffer, $chunkPos, $chunkPos + 4));
				$chunkLength = array_shift($chunkLength);
				$chunkType = substr($buffer, $chunkPos + 4, 4);
				$chunkData = substr($buffer, $chunkPos + 8, $chunkLength);
				$chunkCRC = unpack('N', substr($buffer, $chunkPos + $chunkLength + 8, 4));
				$chunkCRC = array_shift($chunkCRC);
				$chunkPos += $chunkLength + 12;
				if($chunkType == 'IHDR'){
					$width = unpack('N', substr($chunkData, 0, 4));
					$width = array_shift($width);
					$height = unpack('N', substr($chunkData, 4, 8));
					$height = array_shift($height);
				}elseif($chunkType == 'IDAT'){
					$idatAcc .= $chunkData;
					$skip = true;
				}elseif($chunkType == 'CgBI'){
					$skip = true;
				}elseif($chunkType == 'IEND'){
					$bufSize = $width * $height * 4 + $height;
					$chunkData = zlib_decode($idatAcc, $bufSize);
					$chunkType = 'IDAT';
					$newdata = '';
					for($y = 0; $y < $height; $y++){
						$i = strlen($newdata);
						$newdata .= $chunkData[$i];
						for($x = 0; $x < $width; $x++){
							$i = strlen($newdata);
							$newdata .= $chunkData[$i + 2];
							$newdata .= $chunkData[$i + 1];
							$newdata .= $chunkData[$i + 0];
							$newdata .= $chunkData[$i + 3];
						}
					}
					$chunkData = $newdata;
					$chunkData = zlib_encode($chunkData, ZLIB_ENCODING_DEFLATE);
					$chunkLength = strlen($chunkData);
					$chunkCRC = crc32($chunkType.$chunkData);
					$breakLoop = true;
				}
				if(!$skip){
					$target .= pack('N', $chunkLength);
					$target .= $chunkType;
					if($chunkLength > 0){
						$target .= $chunkData;
						$target .= pack('N', $chunkCRC);
					}
				}
				if($breakLoop){
					break;
				}
			}
		}
		return file_put_contents($path, $target);
	}
	private function parseXmlString($node, $that=0){
		foreach($node->childNodes as $child){
			$key = '';
			$ps = $child->previousSibling;
			while($ps && $ps->nodeName == '#text' && $ps->previousSibling){
				$ps = $ps->previousSibling;
			}
			if($ps && $ps->nodeName == 'key'){
				$key = $ps->firstChild->nodeValue;
			}
			switch($child->nodeName){
				case 'date':
					$value = new self($this->dateValue($child->nodeValue), 1);
					break;
				case 'data':
					$value = new self($child->nodeValue, 2);
					break;
				case 'string':
					$value = new self($child->nodeValue, 1);
					break;
				case 'real':
				case 'integer':
					$value = new self($child->nodeName == 'real' ? floatval($child->nodeValue) : intval($child->nodeValue), 1);
					break;
				case 'true':
				case 'false':
					$value = new self($child->nodeName == 'true', 1);
					break;
				case 'array':
				case 'dict':
					$value = new self([], $child->nodeName == 'array' ? 3 : 4);
					$this->parseXmlString($child, $value);
					if($value->type == 4){
						$hsh = $value->getValue();
						if(isset($hsh['CF$UID']) && count($hsh) == 1){
							$value = new self($hsh['CF$UID']->getValue(), 1);
						}
					}
					break;
				default:
					continue 2;
			}
			if($that->type == 3){
				$that->plist[] = $value;
			}elseif($that->type == 4){
				$that->plist[$key] = $value;
			}else{
				return $value->toArray();
			}
		}
	}
	private function dateValue($val){
		if(preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z/', $val, $matches)){
			return strtotime($matches[1].'-'.$matches[2].'-'.$matches[3].' '.$matches[4].':'.$matches[5].':'.$matches[6]);
		}
	}
	private function getValue(){
		return $this->type == 2 ? base64_decode($this->plist) : $this->plist;
	}
	private function toArray(){
		$push = [];
		switch($this->type){
			case 4:
				foreach($this->plist as $key => $item){
					$push[$key] = $item->toArray();
				}
				break;
			case 3:
				foreach($this->plist as $item){
					$push[] = $item->toArray();
				}
				break;
			case 2:
				$push = base64_decode($this->plist);
				break;
			case 1:
				$push = $this->plist;
		}
		return $push;
	}
	private function readBinaryNullType($length){
		switch($length){
			case 0:
				return 0;
			case 8:
				return new self(false, 1);
			case 9:
				return new self(true, 1);
			case 15:
				return 15;
		}
	}
	private function make64Int($hi, $lo){
		$hilo = sprintf('%u', $lo);
		if(PHP_INT_SIZE > 4){
			return intval($hi) << 32 | intval($lo);
		}elseif(function_exists('gmp_mul')){
			return gmp_strval(gmp_add(gmp_mul($hi, '4294967296'), $hilo));
		}elseif(function_exists('bcmul')){
			return bcadd(bcmul($hi, '4294967296'), $hilo);
		}elseif(class_exists('Math_BigInteger')){
			$bi = new \Math_BigInteger($hi);
			return $bi->multiply(new \Math_BigInteger('4294967296'))->add(new \Math_BigInteger($hilo))->toString();
		}
	}
	private function readBinaryInt($length){
		$nbytes = 1 << $length;
		$buff = substr($this->info, $this->pos, $nbytes);
		$this->pos += $nbytes;
		$val = '';
		switch($length){
			case 0:
				$val = unpack('C', $buff);
				$val = $val[1];
				break;
			case 1:
				$val = unpack('n', $buff);
				$val = $val[1];
				break;
			case 2:
				$val = unpack('N', $buff);
				$val = $val[1];
				break;
			case 3:
				$words = unpack('Nhighword/Nlowword', $buff);
				$val = $this->make64Int($words['highword'], $words['lowword']);
		}
		return new self($val, 1);
	}
	private function readBinaryRealDate($length, $type){
		$nbytes = 1 << $length;
		$buff = substr($this->info, $this->pos, $nbytes);
		$this->pos += $nbytes;
		$val = '';
		switch($length){
			case 2:
				$val = unpack('f', strrev($buff));
				$val = $val[1];
				break;
			case 3:
				$val = unpack('d', strrev($buff));
				$val = $val[1];
		}
		return new self($type < 3 ? $val : $val + 978307200, 1);
	}
	private function readBinaryDataString($length, $type){
		if($length == 0){
			$buff = '';
		}else{
			$buff = substr($this->info, $this->pos, $length);
			$this->pos += $length;
		}
		return $type < 5 ? new self(base64_encode($buff), 2) : new self($buff, 1);
	}
	private function readBinaryUnicodeString($length){
		$buff = substr($this->info, $this->pos, $length * 2);
		$this->pos += $length * 2;
		return new self(mb_convert_encoding($buff, 'UTF-8', 'UTF-16BE'), 1);
	}
	private function unpackWithSize($nbytes, $buff){
		$formats = ['C*', 'n*', 'N*', 'N*'];
		$format = $formats[$nbytes - 1];
		if($nbytes == 3){
			$buff = "\0".implode("\0", mb_str_split($buff, 3));
		}
		return unpack($format, $buff);
	}
	private function readBinaryArray($length){
		$ary = new self([], 3);
		if($length != 0){
			$buff = substr($this->info, $this->pos, $this->refsize * $length);
			$this->pos += $this->refsize * $length;
			$objects = $this->unpackWithSize($this->refsize, $buff);
			for($i = 0; $i < $length; ++$i){
				$object = $this->readBinaryObjectAt($objects[$i + 1] + 1);
				$ary->plist[] = $object;
			}
		}
		return $ary;
	}
	private function readBinaryDict($length){
		$dict = new self([], 4);
		if($length != 0){
			$buff = substr($this->info, $this->pos, $this->refsize * $length);
			$this->pos += $this->refsize * $length;
			$keys = $this->unpackWithSize($this->refsize, $buff);
			$buff = substr($this->info, $this->pos, $this->refsize * $length);
			$this->pos += $this->refsize * $length;
			$objects = $this->unpackWithSize($this->refsize, $buff);
			for($i = 0; $i < $length; ++$i){
				$key = $this->readBinaryObjectAt($keys[$i + 1] + 1);
				$object = $this->readBinaryObjectAt($objects[$i + 1] + 1);
				$dict->plist[$key->getValue()] = $object;
			}
		}
		return $dict;
	}
	private function readBinaryObject(){
		$buff = substr($this->info, $this->pos, 1);
		$this->pos++;
		$object_length = unpack('C*', $buff);
		$object_length = $object_length[1] & 0xF;
		$buff = unpack('H*', $buff);
		$buff = $buff[1];
		$object_type = substr($buff, 0, 1);
		if($object_type != '0' && $object_length == 15){
			$object_length = $this->readBinaryObject();
			$object_length = $object_length->getValue();
		}
		$retval = '';
		switch($object_type){
			case '0':
				$retval = $this->readBinaryNullType($object_length);
				break;
			case '1':
				$retval = $this->readBinaryInt($object_length);
				break;
			case '2':
			case '3':
				$retval = $this->readBinaryRealDate($object_length, $object_type);
				break;
			case '4':
			case '5':
				$retval = $this->readBinaryDataString($object_length, $object_type);
				break;
			case '6':
				$retval = $this->readBinaryUnicodeString($object_length);
				break;
			case '8':
				$num = $this->readBinaryInt($object_length);
				$retval = new self($num->getValue(), 1);
				break;
			case 'a':
				$retval = $this->readBinaryArray($object_length);
				break;
			case 'd':
				$retval = $this->readBinaryDict($object_length);
		}
		return $retval;
	}
	private function readBinaryObjectAt($pos){
		$this->pos = $this->offset[$pos];
		return $this->readBinaryObject();
	}
	private function parseBinaryString(){
		$buff = substr($this->info, -32);
		$infos = unpack('x6/Coffset_size/Cobject_ref_size/x4/Nnumber_of_objects/x4/Ntop_object/x4/Ntable_offset', $buff);
		$coded_offset_table = substr($this->info, $infos['table_offset'], $infos['number_of_objects'] * $infos['offset_size']);
		$formats = ['', 'C*', 'n*', '', 'N*'];
		if($infos['offset_size'] == 3){
			$this->offset = [''];
			while($coded_offset_table){
				$str = unpack('H6', $coded_offset_table);
				$this->offset[] = hexdec($str[1]);
				$coded_offset_table = substr($coded_offset_table, 3);
			}
		}else{
			$this->offset = unpack($formats[$infos['offset_size']], $coded_offset_table);
		}
		$this->refsize = $infos['object_ref_size'];
		return $this->readBinaryObjectAt($infos['top_object'] + 1);
	}
}
?>