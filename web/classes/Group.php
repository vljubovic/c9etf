<?php 

class Group {
	public $id, $name, $course;
	private $members = null;
	
	
	/**
	 * @param string $id
	 * @return Group
	 * @throws Exception
	 */
	public static function fromId($id) {
		$id = basename($id);
		$data = Cache::getFile("groups/$id");
		if ($data === false)
			throw new Exception("Unknown group");
		
		$group = new Group();
		$group->id = $id;
		$group->name = $data['name'];
		$group->course = Course::fromString($data['course']);
		$group->members = $data['members'];
		return $group;
	}
	
	
	/**
	 * @param Course $course
	 * @return Group[] Groups for given course
	 */
	public static function forCourse($course) {
		$result = [];
		$groups = Cache::getFile($course->toString() . "/groups");
		if ($groups === false) return $result;
		foreach($groups as $id => $name) {
			if ($id == "last_update") continue;
			$group = new Group();
			$group->id = $id;
			$group->name = $name;
			$group->course = $course;
			$result[] = $group;
		}
		return $result;
	}
	
	/**
	 * @Note Reads group members from file if not initialized
	 * @return User[] Members of group
	 * @throws Exception
	 */
	public function getMembers() {
		if ($this->members === null) {
			$data = Cache::getFile("groups/" . $this->id);
			if ($data === false)
				throw new Exception("Unknown group");
			$this->members = $data['members'];
		}
		return $this->members;
	}
	
	/**
	 * @param bool $online
	 * @param string|bool $course
	 * @return Group Group consisting of all users currently enrolled into course
	 * @throws Exception
	 */
	public static function allEnrolled($online = false, $course = false) {
		$group = new Group();
		if ($online)
			$group->name = "Users currently online";
		else
			$group->name = "All enrolled users";
		$group->members = [];
		if ($course)
			$group->course = Course::fromString($course);
			
		foreach(User::getAll() as $login => $user) {
			if ($online && !$user->online) continue;
			if ($course && !$group->course->isStudent($login)) continue;
			$group->members[$login] = $user->realname;
		}
		return $group;
	}
}


