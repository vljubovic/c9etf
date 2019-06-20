<?php 

class Cache {
	private static $instance = null;
	private $cache = array();
	
	private function __construct() {
		
	}
	
	public static function getInstance() {
		if (self::$instance == null)
			self::$instance = new Cache();
		return self::$instance;
	}
	
	public static function getFile($file) {
		if (self::$instance == null)
			self::$instance = new Cache();
		
		return self::$instance->get_file($file);
	}
	
	public function get_file($file) {
		global $conf_data_path;
		
		if (!array_key_exists($file, $this->cache)) {
			$path = $conf_data_path . "/" . $file;
			if (file_exists($path))
				$this->cache[$file] = json_decode(file_get_contents($path), true);
			else
				$this->cache[$file] = false;
		}
		
		return $this->cache[$file];
	}
}

?>
