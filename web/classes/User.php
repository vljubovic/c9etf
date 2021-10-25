<?php

class User {
	public $login, $realname, $email, $ipAddress, $online;
	
	public function __construct($login) {
		global $users;
		$users = User::getAll();
		if (!array_key_exists($login, $users))
			throw new Exception("User $login not found");
		
		$this->login = $login;
		$this->realname = $users[$login]->realname;
		$this->email = $users[$login]->email;
		$this->ipAddress = $users[$login]->ipAddress;
		$this->online = $users[$login]->online;
	}
	
	public function permissions() {
		$perms = Cache::getFile("permissions.json");
		if (array_key_exists($this->login, $perms))
			return $perms[$this->login];
		return array();
	}
	
	/**
	 * Test if user has access to admin interface
	 * @param string $login
	 * @return bool
	 */
	public function isAdmin($login) {
		global $conf_admin_users;
		return in_array($login, $conf_admin_users);
	}
	
	/**
	 * @return User[] All users from users file
	 */
	public static function getAll() {
		global $conf_base_path;
		
		$users_file = $conf_base_path . "/users";
		$users = [];
		eval(file_get_contents($users_file));
		$result = [];
		
		foreach($users as $login => $data) {
			$reflector = new ReflectionClass("User");
			$u = $reflector->newInstanceWithoutConstructor();
			$u->login = $login;
			$u->realname = "";
			if (array_key_exists('realname', $data))
				$u->realname = $data['realname'];
			if (empty(trim($u->realname)))
				$u->realname = $login;
			if (array_key_exists('email', $data))
				$u->email = $data['email'];
			if (array_key_exists('ip_address', $data))
				$u->ipAddress = $data['ip_address'];
			$u->online = ($data['status'] == "active");
			$result[$login] = $u;
		}
		return $result;
	}
	
	/**
	 * @return string User home path
	 */
	public function homePath() {
		global $conf_home_path;
		$username_efn = escape_filename($this->login);
		return $conf_home_path . "/" . substr($username_efn,0,1) . "/" . $username_efn;
	}
	
	/**
	 * @return string User workspace
	 */
	public function workspacePath() {
		return $this->homePath() . "/workspace";
	}
}


