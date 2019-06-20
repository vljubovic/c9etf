<?php

class User {
	public $login, $realname;
	
	public function __construct($login) {
		global $conf_base_path, $users;
		
		$this->login = $login;
		
		$users_file = $conf_base_path . "/users";
		eval(file_get_contents($users_file));
		
		$this->realname = "";
		if (array_key_exists('realname', $users[$login]))
			$this->realname = $users[$login]['realname'];
		if (empty(trim($this->realname)))
			$this->realname = $login;
	}
	
	public function permissions() {
		$perms = Cache::getFile("permissions.json");
		if (array_key_exists($this->login, $perms))
			return $perms[$this->login];
		return array();
	}
	
	// Test if user has access to admin interface
	public function isAdmin($login) {
		global $conf_admin_users;
		return in_array($login, $conf_admin_users);
	}
}

?>