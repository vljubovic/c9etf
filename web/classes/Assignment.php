<?php

/**
 * Class Assignment
 * @property array<Assignment> items
 */
class Assignment {
	private $items, $parsedItems = null, $course;
	public $id, $type, $name, $path="", $files, $homework_id, $hidden, $parent, $author="";
	
	// Returns a root Assignment object for course
	public static function forCourse($course) {
		$asgn = new Assignment();
		$asgn->items = [];
		$data = Cache::getFile($course->toString() . "/assignments");
		if ($data !== false)
			$asgn->items = $data;
		
		// Default path for course files
		$asgn->path = $course->getPath() . "/assignment_files";
		$asgn->parent = null;
		$asgn->course = $course;
		return $asgn;
	}
	
	public function getData() {
		return $this->items;
	}
	
	// Return a list of Assignment objects that are below this object in hierarchy
	public function getItems() {
		if (!is_null($this->parsedItems))
			return $this->parsedItems;
		
		$result = [];
		foreach($this->items as $k => $i) {
			$item = new Assignment();
			$item->id = $i['id'];
			if (array_key_exists('type', $i)) $item->type = $i['type']; else $item->type = "";
			$item->name = $i['name'];
			if (array_key_exists('path', $i)) $item->path = $i['path']; else $item->path = "";
			if (array_key_exists('files', $i)) $item->files = $i['files']; else $item->files = [];
			if (array_key_exists('homework_id', $i)) $item->homework_id = $i['homework_id']; else $item->homework_id = 0;
			if (array_key_exists('hidden', $i) && $i['hidden'] == "true") $item->hidden = true; else $item->hidden = false;
			if (array_key_exists('items', $i)) $item->items = $i['items']; 
			else {
				if (array_key_exists('task_files', $i))
					$item->parseLegacyItems($this->items[$k]); // Here we send a reference
				else
					$item->items = [];
			}
			$item->parent = $this;
			$result[] = $item;
		}
		$this->parsedItems = $result;
		return $result;
	}
	
	// Helper function to get root item for this course
	private function getRoot() {
		$root = $this;
		while ($root->parent != null) $root = $root->parent;
		return $root;
	}
	
	// Returns Course object for this assignment
	public function getCourse() {
		return $this->getRoot()->course;
	}
	
	// Convert old data format into new data
	private function parseLegacyItems(&$legacyItem) {
		$tasks = count($legacyItem['task_files']);
		if (array_key_exists('tasks', $legacyItem))
			$tasks = $legacyItem['tasks'];
		
		$legacyItem['items'] = [];
		$this->parsedItems = [];
		
		for ($i = 1; $i <= $tasks; $i++) {
			$item = [];
			$pitem = new Assignment();
			
			$item['id']   = $pitem->id   = $this->id * 1000 + $i;
			$item['name'] = $pitem->name = "Zadatak $i";
			$item['type'] = $pitem->type = "zadatak";
			$item['path'] = $pitem->path = "Z$i";
			$item['files'] = $pitem->files = [];
			if (array_key_exists($i, $legacyItem['task_files']))
				foreach($legacyItem['task_files'][$i] as $file) {
					$fileData = array( "filename" => $file, "binary" => false, "show" => true);
					if ($file[0] == ".") $fileData['show'] = false;
					$item['files'][] = $fileData;
				}
			$pitem->files = $item['files'];
			$pitem->items = $pitem->parsedItems = [];
			$pitem->parent = $this;
			
			$legacyItem['items'][] = $item;
			$this->parsedItems[] = $pitem;
		}
	}
	
	// Add new assignment to the list of items
	public function addItem($item) {
		$newItem = clone $item;
		$newItem->parent = $this;
		$children = $newItem->parsedItems;
		$newItem->parsedItems = [];
		foreach($children as $child)
			$newItem->addItem($child);
		
		// Is id unique?
		if ($this->findById($newItem->id))
			$newItem->setUniqueId();
		
		$this->parsedItems[] = $newItem;
		$newItem->createPaths();
		return $newItem;
	}
	
	// Returns an assignment object with given id that is below current object in the tree
	public function findById($id) {
		if ($this->id === $id) return $this;
		foreach($this->getItems() as $item) {
			$k = $item->findById($id);
			if ($k !== false) return $k;
		}
		return false;
	}
	
	// Update configuration files with data in current object
	// Recursively updates all parents etc.
	// Call this function whenever assignment data is changed, new assignments are added etc
	public function update() {
		if ($this->parent !== null)
			$this->parent->update();
		else
			$this->updateFromRoot();
	}
	
	private function updateFromRoot() {
		global $conf_data_path;
		if ($this->parsedItems !== null) {
			$this->items = [];
			foreach($this->parsedItems as $pi) {
				$item = [];
				$item['id'] = $pi->id;
				$item['name'] = $pi->name;
				$item['type'] = $pi->type;
				if ($pi->path != "") $item['path'] = $pi->path;
				if (count($pi->files) > 0) $item['files'] = $pi->files;
				if ($pi->homework_id > 0) $item['homework_id'] = $pi->homework_id;
				if ($pi->hidden) $item['hidden'] = "true"; else $item['hidden'] = "false";
				if ($pi->parsedItems !== null) $pi->updateFromRoot();
				$item['items'] = $pi->items;
				$this->items[] = $item;
			}
		}
		if ($this->parent === null) {
			$path = $conf_data_path . "/" . $this->course->toString() . "/assignments";
			file_put_contents($path, json_encode($this->items, JSON_PRETTY_PRINT));
		}
	}
	
	// Returns filesystem path where files for this assignment are stored
	// If $workspace is true, this gives relative path within user workspace, otherwise
	// absolute path to serverside storage
	public function filesPath($workspace = false) {
		$str = "";
		if ($this->parent !== null) {
			$str = $this->parent->filesPath($workspace);
			if ($this->path != "")
				$str .= "/" . $this->path;
		} else {
			if ($workspace)
				return $this->course->abbrev;
			else 
				return $this->path;
		}
		return $str;
	}
	
	// Returns Assignment object corresponding to path in user workspace
	// For example: OR2016/T4/Z1 will return object within course OR2016 whose path is T4/Z1
	// Note that this uses Course::fromFolder() which is inexact
	// Returns false on failure
	/**
	 * @param string $path
	 * @return false|Assignment
	 */
	public static function fromWorkspacePath($path) {
		$startpos = strpos($path, "/");
		if (!$startpos) return false;
		
		$course = Course::fromFolder(substr($path, 0, $startpos));
		$path = substr($path, $startpos+1);
		if (!$course) return false;
		
		return $course->getAssignments()->findByPath($path);
	}
	
	// Find assignment by path (within workspace / course folder)
	private function findByPath($path) {
		$pos = strpos($path, "/");
		if ($pos) {
			$segment = substr($path, 0, $pos);
			$path = substr($path, $pos+1);
		} else {
			$segment = $path;
			$path = "";
		}
		foreach($this->getItems() as $item)
			if ($item->path === $segment)
				if ($path == "")
					return $item;
				else
					return $item->findByPath($path);
		return false;
	}
	
	
	// Create paths for assignment on serverside storage, also for all items in it
	public function createPaths() {
		$path = $this->filesPath();
		if (!file_exists($path)) mkdir($path, 0755, true);
		foreach ($this->getItems() as $item) $item->createPaths();
	}
	
	// Adds a file to files list, also copies from temporary location
	public function addFile($file, $location) {
		$this->createPaths();
		if (!in_array($file, $this->files)) $this->files[] = $file;
		copy($location, $this->filesPath() . "/" . $file['filename']);
		return $this->filesPath() . "/" . $file['filename'];
	}
	
	// Find maximum number of leaf-level tasks for all assignments in hierarchy
	// (Used to render table view, otherwise not very useful)
	public function maxTasks() {
		if (is_null($this->parsedItems)) $this->getItems();
		$max = $count = 0;
		foreach($this->parsedItems as $item) {
			if ((!is_array($item->items) || count($item->items) == 0) && (is_null($item->parsedItems) || count($item->parsedItems) == 0))
				$count++;
			else {
				$sub = $item->maxTasks();
				if ($sub > $max) $max = $sub;
			}
		}
		if ($count > $max) return $count; 
		return $max;
	}
	
	// Set current assignment ID to a unique value in this tree
	public function setUniqueId() {
		$this->id = $this->uniqueId();
	}
	
	private function uniqueId() {
		if ($this->parent !== null) return $this->parent->uniqueId();
		return $this->maxId() + 1;
	}
	
	private function maxId() {
		$max = $this->id;
		foreach($this->getItems() as $item) {
			$imax = $item->maxId();
			if ($imax > $max) $max = $imax;
		}
		return $max;
	}
}

?>
