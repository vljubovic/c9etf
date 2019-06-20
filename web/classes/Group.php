<?php 

class Group {
	public $id, $name, $course;
	private $members = null;
	
	
	// Create a Group object from string ID
	public static function fromId($id) {
		$group = basename($_REQUEST['group']);
		
		$data = Cache::getFile("groups/$group");
		if ($data === false)
			throw new Exception("Unknown group");
		
		$group = new Group();
		$group->name = $data['name'];
		$group->course = Course::fromString($data['course']);
		$group->members = $data['members'];
		return $group;
	}
	
	
	// Return all groups for given course
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
	
	
	// Read group members from file if not initialized
	public function getMembers() {
		if ($this->members === null) {
			$data = Cache::getFile("groups/$group");
			if ($data === false)
				throw new Exception("Unknown group");
			$this->members = $data['members'];
		}
		return $this->members;
	}
}

?>