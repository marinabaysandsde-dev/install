<?php
namespace server\pack\android;
class apk{
	private $zip;
	private $line;
	private $size;
	private $stream;
	private $packageid;
	private $stringpool;
	private $buffer = [];
	private $resources = [];
	public function __construct($file, $stream=0){
		if(is_resource($stream)){
			$this->resource($stream);
		}else{
			$this->zip = new \ZipArchive();
			$this->zip->open($file);
			$xml = $this->zip->getStream('AndroidManifest.xml');
			$arsc = $this->zip->getStream('resources.arsc');
			$this->zip->close();
			$this->manifest($xml);
			$this->resource($arsc);
			$this->decompress();
		}
	}
	public function parser(){
		$filter = function($node, $attr){
			return preg_match('/<'.$node.'\s.*?'.$attr.'="(.*?)"/', $this->line, $matches) ? $matches[1] : '';
		};
		$ascii = function($list){
			foreach($list as $item){
				$order = mb_detect_encoding($item, ['ASCII', 'UTF-8', 'GB2312', 'GBK']);
				if(preg_match('/[^\x00-\x7f]/', $item) && @iconv($order, 'GB2312', $item)){
					return $item;
				}
			}
			return $list[0];
		};
		$identity = $filter('manifest', 'package');
		$version = $filter('manifest', 'versionName');
		$minos = @hexdec($filter('uses-sdk', 'minSdkVersion'));
		$label = @$ascii($this->resources[strtolower($filter('application', 'label'))]);
		$icon = @$this->resources[strtolower($filter('application', 'icon'))][0];
		return ['identity' => $identity, 'version' => $version, 'minos' => $minos, 'label' => $label, 'icon' => $icon];
	}
	private function manifest($stream){
		while(!feof($stream)){
			$this->buffer[] = ord(fread($stream, 1));
		}
		$offset = $this->shift(2, 2);
		$data = $this->shift(4, 4);
		while($offset < $data - 8){
			$mode = $this->shift($offset, 2);
			$chunk = $this->shift($offset + 4, 4);
			if($offset + $chunk > $data){
				break;
			}
			switch($mode){
				case 0x0001:
					$char = $this->shift($offset + 16, 4) & 0x00000100;
					$sit = $offset + $this->shift($offset + 2, 2);
					$sat = $sit + $this->shift($offset + 8, 4) * 4;
					break;
				case 0x0102:
					break 2;
			}
			$offset += $chunk;
		}
		$number = 0;
		$this->line = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n";
		while($offset < count($this->buffer)){
			$way = $this->shift($offset, 2);
			$preset = $this->shift($offset + 20, 4);
			switch($way){
				case 0x0104:
					while($offset < count($this->buffer)){
						$curr = $this->shift($offset, 4);
						$offset += 4;
						if(!$curr){
							break;
						}
					}
					break;
				case 0x0103:
					$offset += 24;
					$element = $this->convert($sit, $sat, $preset, $char);
					$this->line .= '</'.$element.">\r\n";
					break;
				case 0x0102:
					$offset += 36;
					$element = $this->convert($sit, $sat, $preset, $char);
					$total = $this->shift($offset - 8, 4);
					$part = '';
					for($i = 0; $i < $total; $i++){
						$offset += 20;
						$judge = $this->shift($offset - 12, 4);
						$keep = $this->convert($sit, $sat, $judge, $char);
						$code = '0x'.dechex($this->shift($offset - 4, 4));
						$val = $judge != 0xffffffff && $judge != -1 ? $keep : $code;
						$key = $this->convert($sit, $sat, $this->shift($offset - 16, 4), $char);
						$part .= ' '.$key.'="'.$val.'"';
					}
					$this->line .= '<'.$element.$part.">\r\n";
					break;
				case 0x0101:
					$offset += 24;
					$number--;
					if($number == -1){
						break 2;
					}
					break;
				case 0x0100:
					$offset += 24;
					$number++;
					break;
				case 0x0000:
					$offset += 4;
			}
		}
	}
	private function shift($offset, $bit){
		$short = $this->buffer[$offset + 1] << 8 & 0xff00 | $this->buffer[$offset] & 0xff;
		$long = $this->buffer[$offset + 3] << 24 & 0xff000000 | $this->buffer[$offset + 2] << 16 & 0xff0000 | $short;
		$calc = $bit < 4 ? $short : $long;
		$size = PHP_INT_SIZE - $bit << 3;
		return $calc << $size >> $size;
	}
	private function utf8($offset){
		$digit = $this->buffer[$offset];
		$offset += $digit & 0x80 ? 2 : 1;
		$digit = $this->buffer[$offset];
		$offset += 1;
		if($digit & 0x80){
			$lower = $this->buffer[$offset] & 0xff;
			$length = (($digit & 0x7f) << 8) + $lower;
			$offset += 1;
		}else{
			$length = $digit;
		}
		$stop = $offset + $length;
		$string = '';
		for($i = $offset; $i < $stop; $i++){
			$string .= chr($this->buffer[$i]);
		}
		return $string;
	}
	private function utf16($offset){
		$length = $this->buffer[$offset + 1] << 8 & 0xff00 | $this->buffer[$offset] & 0xff;
		$offset += 2;
		$stop = $offset + $length * 2;
		$string = '';
		for($i = $offset; $i < $stop; $i++){
			$string .= chr($this->buffer[$i]);
		}
		return mb_convert_encoding($string, 'UTF-8', 'UTF-16LE');
	}
	private function convert($sit, $sat, $offset, $char){
		$offset = $sat + $this->shift($sit + $offset * 4, 4);
		return $char ? $this->utf8($offset) : $this->utf16($offset);
	}
	private function resource($stream){
		$meta = stream_get_meta_data($stream);
		$this->stream = $meta['seekable'] ? $stream : $this->toMemoryStream($stream);
		rewind($this->stream);
		fseek($this->stream, 0, SEEK_END);
		$this->size = ftell($this->stream);
		rewind($this->stream);
	}
	private function toMemoryStream($stream, $length=0){
		$size = 0;
		$memoryStream = fopen('php://memory', 'wb+');
		while(!feof($stream)){
			$buf = fread($stream, 128);
			$bufSize = strlen($buf);
			$size += $bufSize;
			if($length > 0 && $size >= $length){
				$over = $size - $length;
				fputs($memoryStream, substr($buf, 0, $bufSize - $over));
				if($over > 0){
					fseek($stream, -$over, SEEK_CUR);
				}
				break;
			}
			fputs($memoryStream, $buf);
		}
		return $memoryStream;
	}
	private function copyBytes($length){
		return new self(0, $this->toMemoryStream($this->stream, $length));
	}
	private function seek($offset){
		fseek($this->stream, $offset);
	}
	private function backSeek($offset){
		fseek($this->stream, $offset, SEEK_CUR);
	}
	private function position(){
		return ftell($this->stream);
	}
	private function size(){
		return $this->size;
	}
	private function readByte(){
		return ord($this->read(1));
	}
	private function read($length){
		return fread($this->stream, $length);
	}
	private function readInt16LE(){
		return $this->unpackInt16($this->unpackInt32() == 1 ? $this->read(2) : strrev($this->read(2)));
	}
	private function readInt32LE(){
		return $this->unpackInt32($this->unpackInt32() == 1 ? $this->read(4) : strrev($this->read(4)));
	}
	private function unpackInt16($value){
		list(, $int) = unpack('s*', $value);
		return $int;
	}
	private function unpackInt32($value="\x01\x00\x00\x00"){
		list(, $int) = unpack('l*', $value);
		return $int;
	}
	private function decompress(){
		$this->readInt16LE();
		$this->readInt16LE();
		$size = $this->readInt32LE();
		$this->readInt32LE();
		$realStringsCount = 0;
		while(true){
			$pos = $this->position();
			$chunkType = $this->readInt16LE();
			$this->readInt16LE();
			$chunkSize = $this->readInt32LE();
			if($chunkType == 0x0001){
				if($realStringsCount == 0){
					$this->seek($pos);
					$this->stringpool = $this->processStringPool($this->copyBytes($chunkSize));
				}
				$realStringsCount++;
			}elseif($chunkType == 0x0200){
				$this->seek($pos);
				$this->processPackage($this->copyBytes($chunkSize));
			}
			$this->seek($pos + $chunkSize);
			if($this->position() == $size){
				break;
			}
		}
	}
	private function processStringPool($data){
		$data->readInt16LE();
		$data->readInt16LE();
		$data->readInt32LE();
		$stringsCount = $data->readInt32LE();
		$data->readInt32LE();
		$flags = $data->readInt32LE();
		$stringsStart = $data->readInt32LE();
		$data->readInt32LE();
		$offsets = [];
		for($i = 0; $i < $stringsCount; $i++){
			$offsets[$i] = $data->readInt32LE();
		}
		$strings = [];
		for($i = 0; $i < $stringsCount; $i++){
			$lastPosition = $data->position();
			$pos = $stringsStart + $offsets[$i];
			$data->seek($pos);
			$len = $data->position();
			$data->seek($lastPosition);
			if($len < 0){
				$data->readInt16LE();
			}
			$pos += 2;
			if($flags & 256){
				$length = 0;
				$data->seek($pos);
				while($data->readByte()){
					$length++;
				}
				if($length > 0){
					$data->seek($pos);
					$strings[$i] = $data->read($length);
				}else{
					$strings[$i] = '';
				}
			}else{
				$strings[$i] = '';
				$data->seek($pos);
				while($c = $data->read(1)){
					$strings[$i] .= $c;
				}
			}
		}
		return $strings;
	}
	private function processPackage($data){
		$data->readInt16LE();
		$data->readInt16LE();
		$data->readInt32LE();
		$this->packageid = $data->readInt32LE();
		$data->read(256);
		$typeStringsStart = $data->readInt32LE();
		$data->readInt32LE();
		$keyStringsStart = $data->readInt32LE();
		$data->readInt32LE();
		$data->seek($typeStringsStart);
		$data->seek($keyStringsStart);
		$data->readInt16LE();
		$data->readInt16LE();
		$keySize = $data->readInt32LE();
		$data->seek($keyStringsStart);
		$data->seek($keyStringsStart + $keySize);
		while(true){
			$pos = $data->position();
			$chunkType = $data->readInt16LE();
			$data->readInt16LE();
			$chunkSize = $data->readInt32LE();
			if($chunkType == 0x0202){
				$data->seek($pos);
				$this->processTypeSpec($data->copyBytes($chunkSize));
			}elseif($chunkType == 0x0201){
				$data->seek($pos);
				$this->processType($data->copyBytes($chunkSize));
			}
			$data->seek($pos + $chunkSize);
			if($data->position() == $data->size()){
				break;
			}
		}
	}
	private function processTypeSpec($data){
		$data->readInt16LE();
		$data->readInt16LE();
		$data->readInt32LE();
		$data->readByte();
		$data->readByte();
		$data->readInt16LE();
		$entriesCount = $data->readInt32LE();
		$flags = [];
		for($i = 0; $i < $entriesCount; ++$i){
			$flags[$i] = $data->readInt32LE();
		}
	}
	private function processType($data){
		$data->readInt16LE();
		$headerSize = $data->readInt16LE();
		$data->readInt32LE();
		$id = $data->readByte();
		$data->readByte();
		$data->readInt16LE();
		$entriesCount = $data->readInt32LE();
		$data->readInt32LE();
		$config_size = $data->readInt32LE();
		$data->seek($headerSize - $config_size);
		$this->processConfig($data->copyBytes($config_size));
		$entryIndices = [];
		for($i = 0; $i < $entriesCount; ++$i){
			$entryIndices[$i] = $data->readInt32LE();
		}
		for($i = 0; $i < $entriesCount; ++$i){
			if($entryIndices[$i] == -1){
				continue;
			}
			$resourceId = $this->packageid << 24 | $id << 16 | $i;
			$data->readInt16LE();
			$entryFlag = $data->readInt16LE();
			$data->readInt32LE();
			$resourceIdString = '0x'.dechex($resourceId);
			if(($entryFlag & 0x0001) == 0){
				$data->readInt16LE();
				$data->readByte();
				$valueDataType = $data->readByte();
				$valueData = $data->readInt32LE();
				if($valueDataType == 0x03){
					$value = $this->stringpool[$valueData];
					$this->putResource($resourceIdString, $value);
				}elseif($valueDataType == 0x01){
					$referenceIdString = '0x'.dechex($valueData);
					$this->putReferenceResource($resourceIdString, $referenceIdString);
				}else{
					$this->putResource($resourceIdString, $valueData);
				}
			}else{
				$data->readInt32LE();
				$entryCount = $data->readInt32LE();
				for($j = 0; $j < $entryCount; ++$j){
					$data->readInt32LE();
					$data->readInt16LE();
					$data->readByte();
					$data->readByte();
					$data->readInt32LE();
				}
			}
		}
	}
	private function processConfig($bytes){
		$config = [];
		$config['size'] = $bytes->readInt32LE();
		$config['mcc'] = $bytes->readInt16LE();
		$config['mnc'] = $bytes->readInt16LE();
		$bytes->backSeek(-4);
		$config['imsi'] = $bytes->readInt32LE();
		$config['language'] = $bytes->read(2);
		$config['country'] = $bytes->read(2);
		$bytes->backSeek(-4);
		$config['locale'] = $bytes->read(4);
		$config['orientation'] = $bytes->readByte();
		$config['touchscreen'] = $bytes->readByte();
		$config['density'] = $bytes->readInt16LE();
		$bytes->backSeek(-4);
		$config['screenType'] = $bytes->readInt32LE();
		$config['keyboard'] = $bytes->readByte();
		$config['navigation'] = $bytes->readByte();
		$config['inputFlags'] = $bytes->readByte();
		$config['inputPad0'] = $bytes->readByte();
		$bytes->backSeek(-4);
		$config['input'] = $bytes->readInt32LE();
		if($bytes->size() > 36){
			$config['screenWidth'] = $bytes->readInt16LE();
			$config['screenHeight'] = $bytes->readInt16LE();
			$bytes->backSeek(-4);
			$config['screenSize'] = $bytes->readInt32LE();
			$config['sdVersion'] = $bytes->readInt16LE();
			$config['minorVersion'] = $bytes->readInt16LE();
			$bytes->backSeek(-4);
			$config['version'] = $bytes->readInt32LE();
			$config['screenLayout'] = $bytes->readByte();
			$config['uiMode'] = $bytes->readByte();
			$config['smallestScreenWidthDp'] = $bytes->readInt16LE();
			$bytes->backSeek(-4);
			$config['screenConfig'] = $bytes->readInt32LE();
			$config['screenWidthDp'] = $bytes->readByte();
			$config['screenHeightDp'] = $bytes->readByte();
			$bytes->backSeek(-4);
			$config['screenSizeDp'] = $bytes->readInt32LE();
			$config['localeScript'] = $bytes->readInt32LE();
			$config['localeVariant'] = $bytes->read(8);
		}
		return $config;
	}
	private function putResource($resourceId, $value){
		$key = strtolower($resourceId);
		if(!array_key_exists($key, $this->resources)){
			$this->resources[$key] = [];
		}
		$this->resources[$key][] = $value;
	}
	private function putReferenceResource($resourceId, $valueData){
		$key = strtolower($resourceId);
		if(array_key_exists($key, $this->resources)){
			$values = $this->resources[$key];
			foreach($values as $value){
				$this->putResource($valueData, $value);
			}
		}
	}
}
?>