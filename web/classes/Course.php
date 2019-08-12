<?php

require("Assignment.php");
require("Cache.php");
require("Group.php");
require("User.php");

class Course {
	public $id, $year, $external;
	public $name, $abbrev, $data;
	
	// Return course with given id and external status, in current year
	// If you want a different year, just change $year attribute
	public static function find($id, $external) {
		global $conf_current_year; 
		
		foreach(Course::getAll() as $course) {
			if ($course->id == $id && $course->external == $external) {
				$course->year = $conf_current_year;
				return $course;
			}
		}
		throw new Exception("Course not found");
	}
	
	
	// Return short string representation of Course object
	// For example: X2_14 is external course (X) with id 2 in year 14
	public function toString() {
		$str = $this->id . "_" . $this->year;
		if ($this->external) $str = "X$str";
		return $str;
	}

	// Create Course object from string representation given in toString
	// For example: X2_14 is external course (X) with id 2 in year 14
	public static function fromString($string) {
		if (strlen($string) < 1 || !strstr($string, "_")) throw new Exception("Course not found");
		if ($string[0] == "X") {
			$external = true;
			$string = substr($string, 1);
		} else $external = false;
		list($id, $year) = explode("_", $string);
		
		$course = Course::find($id, $external);
		$course->year = $year;
		return $course;
	}

	// Returns a string with portion of GET request URL with encoded course data 
	public function urlPart() {
		$part = "course=" . $this->id . "&amp;year=" . $this->year;
		if ($this->external) $part .= "&amp;X";
		return $part;
	}
	
	// Returns a string with HTML form with hidden fields, useful for creating POST forms
	public function htmlForm() {
		$data = '<input type="hidden" name="action" value="create">
	<input type="hidden" name="course" value="' . $this->id . '">
	<input type="hidden" name="year" value="' . $this->year . '">
	<input type="hidden" name="external" value="' . ($this->external?"1":"0") . '">';
		return $data;
	}
	
	// Creates a new Course object with all course data encoded using urlPart() function
	public static function fromRequest() {
		if (!isset($_REQUEST['course'])) throw new Exception("Course not found");
		
		$id = intval($_REQUEST['course']);
		$external = false;
		if (isset($_REQUEST['X']) || isset($_REQUEST['external']) && intval($_REQUEST['external']) == 1)
			$external = true;
		
		$course = Course::find($id, $external);
		
		if (isset($_REQUEST['year']))
			$course->year = intval($_REQUEST['year']);
		return $course;
	}
	
	// Return string with standard name of folder for this course (abbreviation+year name)
	// For example: OR2016 is course with abbreviation OR in year 2016
	// Note however that there may be multiple courses with same abbreviation so this function is approximate at best
	public function folderName() {
		global $conf_current_year;
		if ($this->year == $conf_current_year) return $this->abbrev;
		foreach(Cache::getFile("years.json") as $year)
			if ($year['id'] == $this->year)
				return $this->abbrev . substr($year['name'], 0, strpos($year['name'], "/"));
		return $this->abbrev;

	}
	
	// Create Course object from folder name in user workspace
	// For example: OR2016 is course with abbreviation OR in year 2016
	// Note however that there may be multiple courses with same abbreviation so this function is approximate at best
	public static function fromFolder($string) {
		global $conf_current_year;
		foreach(Course::getAll() as $course)
			if ($course->folderName() == $string) {
                $course->year = $conf_current_year;
				return $course;
            }
		return false;
	}
	
	// Return list of all currently known courses
	public static function getAll() {
		$result = [];
		foreach(Cache::getFile("courses.json") as $c) {
			$course = new Course();
			$course->id = $c['id'];
			$course->name = $c['name'];
			$course->abbrev = $c['abbrev'];
			$course->external = ($c['type'] == "external");
			$course->data = $c;
			$result[] = $course;
		}
		return $result;
	}
	
	// System path for Course
	public function getPath() {
		global $conf_data_path;
		return $conf_data_path . "/" . $this->toString();
	}
	
	// Returns true if given user is admin for current course
	public function isAdmin($username) {
		global $conf_sysadmins;
		
		if (in_array($username, $conf_sysadmins)) return true;
		
		$user = new User($username);
		foreach($user->permissions() as $perm)
			if ($perm == $this->toString())
				return true;
		return false;
	}
	
	// Return a list of courses for which given user is admin in given year
	// If year parameter is ommitted, use current year
	public static function forAdmin($username, $year = 0) {
		global $conf_current_year;
		
		if ($year == 0) $year = $conf_current_year;
		$result = [];
		foreach(Course::getAll() as $course) {
			$course->year = $year;
			if ($course->isAdmin($username) && file_exists($course->getPath()))
				$result[] = $course;
		}
		return $result;
	}
	
	// Returns true if given user is student for current course
	public function isStudent($username) {
		$user_courses = Cache::getFile("user_courses/$username.json");
		if ($user_courses === false || empty($user_courses)) 
			return false;
		return (in_array($this->toString(), $user_courses['student']));
	}
	
	// Return a list of courses for which given user is enrolled as student in given year
	// If year parameter is ommitted, use current year
	// If year is -1, get data for all years
	public static function forStudent($username, $year = 0) {
		global $conf_current_year;
		
		$result = [];
		if ($year == 0) $year = $conf_current_year;
		if ($year == -1) {
			foreach(Cache::getFile("years.json") as $year)
				$result = array_merge($result, Course::forStudent($username, $year['id']));
			return $result;
		}
		
		$user_courses = Cache::getFile("user_courses/$username.json");
		if ($user_courses === false || empty($user_courses)) 
			return $result;
		
		foreach(Course::getAll() as $course) {
			$course->year = $year;
			if (in_array($course->toString(), $user_courses['student']))
				$result[] = $course;
		}
		return $result;
	}
	
	// Return a list of assignments defined for this course
	// Actually returns a single Assignment object with all assignments inside its $items property
	public function getAssignments() {
		return Assignment::forCourse($this);
	}
	
	// Return a list of Groups defined for this course
	public function getGroups() {
		return Group::forCourse($this);
	}
	
	
	// Get list of global course files
	public function getFiles() {
		// There is no configured list, we simply read a folder
		$files_path = $this->getPath() . "/files";
		if (!file_exists($files_path)) mkdir($files_path, 0700, true);
		
		$files = scandir($files_path); $count = count($files);
		for ($i=0; $i<$count; $i++) {
			if (is_dir($files_path . "/" . $files[$i]) || $files[$i] == "..") 
				unset($files[$i]);
		}
		return $files;
	}
	
	// Add file to global course files
	// Parameters: $name - name for file, $location - its current location from which it will be copied
	public function addFile($name, $location) {
		// There is no configured list, we simply read a folder
		$files_path = $this->getPath() . "/files";
		if (!file_exists($files_path)) mkdir($files_path, 0700, true);
		
		copy($location, $files_path . "/" . $name);
	}
}


?>
