<?php

/**
 * @Note Class for Assignment files and folders
 * Class FSNode
 * @property Course course
 * @property FSNode parent
 * @property FSNode[] children
 */
class FSNode
{
	private $course = null,
		$folder = null,
		$parent = null;
	public
		$children = null,
		$id = null,
		$homeworkId = null,
		$name = null,
		$path = null,
		$type = null,
		$hidden = null,
		$isDirectory = null,
		$show = null,
		$binary = null;
	
	public static function constructTreeForCourseFromOldTree($course)
	{
		$root = $course->getAssignments();
		$root->getItems(); // Parse legacy data
		$assignments = $root->getData();
		if (empty($assignments))
			json(error("ERR003", "No assignments for this course"));
		
		FSNode::assignmentsProcess($assignments, $course->abbrev, $course->getFiles());
		usort($assignments, "FSNode::compareAssignments");
		$path = $course->getPath() . '/assignment_files';
		$tree = FSNode::sniffFolder($path, $path);
		$files = scandir($course->getPath() . '/files');
		if ($files) {
			$files = array_filter($files, "FSNode::notDotDotAndDot");
			FSNode::addItemsToLeaves($tree, $files);
		}
		FSNode::extractInfoFromOldAssignments($tree['children'], $assignments, $course);
		usort($tree['children'], "FSNode::compareAssignments");
		$node = new FSNode();
		$node->course = $course;
		$node->folder = "assignment_files";
		$node->constructNode(null, $tree);
		return $node;
	}
	
	public static function constructTreeForCourse($course, $folder = 'assignment_files', $descriptionFileName = "assignments.json")
	{
		$fTree = FSNode::getUpdatedAssignmentsJson($course, $folder, $descriptionFileName);
		$node = new FSNode();
		$node->course = $course;
		$node->folder = $folder;
		$node->constructNode(null, $fTree);
		return $node;
	}
	
	private function __construct()
	{
	}
	
	public function __get($name)
	{
		return $this->$name;
	}
	
	public function __set($name, $value)
	{
		$this->$name = $value;
	}
	
	public function getRootNode()
	{
		$node = $this;
		while ($node->parent !== null) {
			$node = $node->parent;
		}
		return $node;
	}
	
	public function getJson()
	{
		$json = json_encode($this);
		$json = json_decode($json, true);
		usort($json['children'], "FSNode::compareAssignments");
		self::orderJsonKeys($json);
		$json = json_encode($json);
		$json = preg_replace('/,\s*"[^"]+":null|"[^"]+":null,?/', '', $json);
		$json = json_decode($json, true);
		if (defined(JSON_PRETTY_PRINT)) {
			return json_encode($json, JSON_PRETTY_PRINT);
		} else {
			return json_encode($json);
		}
	}
	
	private static function orderJsonKeys(&$json)
	{
		uksort($json, 'FSNode::sortKeys');
		if (is_array($json)) {
			if (array_key_exists('children', $json)) {
				if ($json['children'] !== null) {
					foreach ($json['children'] as &$child) {
						self::orderJsonKeys($child);
					}
				}
			}
		}
	}
	
	
	public function isTemplateFile()
	{
		if (file_exists($this->getAbsolutePath())) {
			return false;
		} elseif (file_exists($this->course->getPath() . '/files/' . $this->name)) {
			return true;
		}
		throw new Exception("File is not updated and assignments are out of place");
	}
	
	public function getNodeByPath($path)
	{
		if ($path == $this->path) {
			return $this;
		} else {
			foreach ($this->children as $child) {
				if (FSNode::isSubPath($child->path, $path)) {
					return $child->getNodeByPath($path);
				}
			}
		}
		return null;
	}
	
	public function addFile($file, $content)
	{
		if ($this->isDirectory && $this->isLeafFolder()) {
			$path = $this->getAbsolutePath() . "/" . $file['name'];
			$this->createFile($path, $content);
			$relativePath = $this->path . '/' . $file['name'];
			$this->children[] = FSNode::makeFileNode($file, $relativePath, $this);
		}
	}
	
	private function getReplacedTemplatePlaceholders($code){
		$parent = $this->parent;
		$grand = $this->parent;
		if ($grand->parent !== null) {
			$grand = $grand->parent;
		}
		$title = $grand->name . ", " . $parent->name;
		$code = str_replace("===TITLE===", $title, $code);
		$code = str_replace("===COURSE===", $this->course->name, $code);
		
		foreach (Cache::getFile("years.json") as $year)
			if ($year['id'] == $this->course->year)
				$year_name = $year['name'];
		$code = str_replace("===YEAR===", $year_name, $code);
		return $code;
	}
	
	public function getFileContent()
	{
		if (!file_exists($this->getAbsolutePath())) {
			if (file_exists($this->course->getPath() . '/files/' . $this->name)) {
				$content = file_get_contents($this->course->getPath() . '/files/' . $this->name);
				$content = $this->getReplacedTemplatePlaceholders($content);
				if ($content == false) {
					throw new Exception("Could not read file!");
				}
				return $content;
			}
			throw new Exception("File not found!");
		}
		if (!$this->isDirectory) {
			$content = file_get_contents($this->getAbsolutePath());
			if ($content == false) {
				throw new Exception("Could not read file!");
			}
			return $content;
		}
		throw new Exception("Server error on getFileContent in FSNode");
	}
	
	public function editFile($content, $show = null, $binary = null)
	{
		if (file_exists($this->getAbsolutePath())) {
			if ($content !== null) {
				file_put_contents($this->getAbsolutePath(), $content);
			}
			if ($show !== null) {
				$this->show = $show;
			}
			if ($binary !== null) {
				$this->binary = $binary;
			}
		}
	}
	
	public function editFolder($displayName, $type = null, $hidden = null, $homework_id = null)
	{
		if (file_exists($this->getAbsolutePath())) {
			if ($displayName !== null) {
				$this->name = $displayName;
			}
			if ($type !== null) {
				$this->type = $type;
			}
			if ($hidden !== null) {
				$this->hidden = $hidden;
			}
			if ($homework_id !== null) {
				$this->homeworkId = $homework_id;
			}
		}
	}
	
	public function deleteFile()
	{
		if (file_exists($this->getAbsolutePath())) {
			unlink($this->getAbsolutePath());
		}
		$parent = $this->parent;
		for ($i = 0; $i < $parent->children; $i++) {
			if ($parent->children[$i]->path == $this->path) {
				unset($parent->children[$i]);
				break;
			}
		}
	}
	
	public function deleteFolder()
	{
		FSNode::rRmdir($this->getAbsolutePath());
		$parent = $this->parent;
		$this->parent = null;
		for ($i = 0; $i < $parent->children; $i++) {
			if ($parent->children[$i]->path == $this->path) {
				unset($parent->children[$i]);
				break;
			}
		}
	}
	
	public function addFolder($name, $displayName, $type = 'tutorial', $hidden = false, $homeworkId = null, $id = null)
	{
		if ($this->isDirectory && $this->doesNotContainFiles()) {
			$path = $this->getAbsolutePath() . '/' . $name;
			$this->createFolder($path);
			$this->children[] = FSNode::makeFolderNode($name, $displayName, $type, $hidden, $homeworkId, $this, $id);
		}
	}
	
	private function getAbsolutePath()
	{
		return $this->course->getPath() . "/$this->folder" . $this->path;
	}
	
	private static function rRmdir($dir)
	{
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
						FSNode::rRmdir($dir . DIRECTORY_SEPARATOR . $object);
					else
						unlink($dir . DIRECTORY_SEPARATOR . $object);
				}
			}
			rmdir($dir);
		}
	}
	
	private function constructNode($parent, $fTree)
	{
		if (is_array($fTree)) {
			$this->parent = $parent;
			$this->takeKeyValues($fTree, ['id', 'name', 'type', 'path', 'homework_id', 'isDirectory', 'hidden', 'binary', 'show']);
			if (array_key_exists('children', $fTree)) {
				$this->children = [];
				foreach ($fTree['children'] as $child) {
					$node = new FSNode();
					$node->folder = $this->folder;
					$node->course = $this->course;
					$node->constructNode($this, $child);
					$this->children[] = $node;
				}
			}
		}
	}
	
	
	private static function addItemsToLeaves(&$node, $items)
	{
		if ($node['isDirectory']) {
			$leaf = true;
			foreach ($node['children'] as $child) {
				if ($child['isDirectory']) {
					$leaf = false;
				}
			}
			if ($leaf) {
				foreach ($items as $key => $item) {
					$node['children'][] = array(
						'name' => $item,
						'path' => $node['path'] . '/' . $item,
						'isDirectory' => false,
						'show' => true,
						'binary' => false
					);
				}
			} else {
				foreach ($node['children'] as &$child) {
					if ($child['isDirectory']) {
						FSNode::addItemsToLeaves($child, $items);
					}
				}
			}
		}
	}
	
	private static function notDotDotAndDot($item)
	{
		return !($item == '.' || $item == '..');
	}
	
	/**
	 * @param $assignments
	 * @param array<Assignment> $old
	 * @param Course $course
	 */
	private static function extractInfoFromOldAssignments(&$assignments, array $old, $course)
	{
		foreach ($assignments as &$assignment) {
			foreach ($old as $item) {
				$path = '';
				if (!$assignment['isDirectory']) {
					if (!is_string($item) && array_key_exists('filename', $item)) {
						$path = substr($assignment['path'], 0, strrpos($assignment['path'], '/')) . '/' . $item['filename'];
					} else {
						$path = substr($assignment['path'], 0, strrpos($assignment['path'], '/')) . '/' . $item;
					}
				} else {
					$path = substr($item['path'], strlen($course->abbrev));
				}
				
				if ($assignment['path'] == $path) {
					if (!is_string($item) && array_key_exists('id', $item)) {
						$assignment['id'] = $item['id'];
					}
					if (!is_string($item) && array_key_exists('type', $item)) {
						$assignment['type'] = $item['type'];
					}
					if (!is_string($item) && array_key_exists('name', $item)) {
						$assignment['name'] = $item['name'];
					}
					if (!is_string($item) && array_key_exists('hidden', $item)) {
						$assignment['hidden'] = $item['hidden'];
					}
					if (!is_string($item) && array_key_exists('homework_id', $item)) {
						$assignment['homeworkId'] = $item['homework_id'];
					}
					if (!$assignment['isDirectory']) {
						$assignment['show'] = true;
						$assignment['binary'] = false;
						if (!is_string($item) && array_key_exists('show', $item)) {
							$assignment['show'] = $item['show'];
						}
						if (!is_string($item) && array_key_exists('binary', $item)) {
							$assignment['binary'] = $item['binary'];
						}
					}
					if (array_key_exists('children', $assignment)) {
						if (array_key_exists('items', $item) && count($item['items']) !== 0) {
							FSNode::extractInfoFromOldAssignments($assignment['children'], $item['items'], $course);
						} else {
							FSNode::extractInfoFromOldAssignments($assignment['children'], $item['files'], $course);
						}
					}
					uksort($assignment, 'FSNode::sortKeys');
				}
			}
		}
	}
	
	
	private static function sortKeys($a, $b)
	{
		if ($a == 'id') {
			return -1;
		} elseif ($a == 'name') {
			if ($b == 'id') {
				return 1;
			} else {
				return -1;
			}
		} elseif ($a == 'path') {
			if ($b == 'id' || $b == 'name') {
				return 1;
			} else {
				return -1;
			}
		} elseif ($a == 'type') {
			if ($b == 'id' || $b == 'name' || $b == 'path') {
				return 1;
			} else {
				return -1;
			}
		} elseif ($a == 'hidden') {
			if ($b == 'id' || $b == 'name' || $b == 'path' || $b == 'type') {
				return 1;
			} else {
				return -1;
			}
		} elseif ($a == 'isDirectory') {
			if ($b == 'id' || $b == 'name' || $b == 'path' || $b == 'type' || $b == 'hidden') {
				return 1;
			} else {
				return -1;
			}
		} else {
			return 1;
		}
	}
	
	private static function assignmentsProcess(&$assignments, $parentPath, $courseFiles)
	{
		global $login, $conf_admin_users;
		
		foreach ($assignments as $key => $value) {
			if (array_key_exists('hidden', $value) && $value['hidden'] == "true" && !in_array($login, $conf_admin_users) && $login != "test") {
				unset($assignments[$key]);
				continue;
			}
			if (array_key_exists('path', $value) && !empty($value['path']))
				$path = $assignments[$key]['path'] = $parentPath . "/" . $assignments[$key]['path'];
			else
				$path = $assignments[$key]['path'] = $parentPath;
			
			if (array_key_exists('files', $value)) {
				foreach ($courseFiles as $cfile) {
					$found = false;
					$ccfile = $cfile;
					if (is_array($cfile) && array_key_exists('filename', $cfile))
						$ccfile = $cfile['filename'];
					foreach ($assignments[$key]['files'] as $file) {
						$ffile = $file;
						if (is_array($file) && array_key_exists('filename', $file))
							$ffile = $file['filename'];
						if ($ccfile == $ffile)
							$found = true;
					}
					if (!$found)
						$assignments[$key]['files'][] = $cfile;
				}
			}
			
			if (array_key_exists('items', $value))
				FSnode::assignmentsProcess($assignments[$key]['items'], $path, $courseFiles);
		}
	}

// Sort assigments by type, then by name (natural)
	private static function compareAssignments($a, $b)
	{
		if (array_key_exists('type', $a) && $a['type'] == $b['type']) return strnatcmp($a['name'], $b['name']);
		if (array_key_exists('type', $a) && $a['type'] == "tutorial") return -1;
		if (array_key_exists('type', $b) && $b['type'] == "tutorial") return 1;
		if (array_key_exists('type', $a) && $a['type'] == "homework") return -1;
		if (array_key_exists('type', $b) && $b['type'] == "homework") return 1;
		// Other types are considered equal
		if (array_key_exists('name', $a))
			return strnatcmp($a['name'], $b['name']);
		return -1;
	}
	
	private static function sniffFolder($folder_path, $discarded_part_of_path)
	{
		$result['name'] = basename($folder_path);
		$result['path'] = substr($folder_path, strlen($discarded_part_of_path));
		$result['isDirectory'] = true;
		$result['children'] = [];
		foreach (scandir($folder_path) as $item) {
			$path = $folder_path . '/' . $item;
			if ($item == '..' || $item == '.') {
				continue;
			}
			if (is_dir($path)) {
				$result['children'][] = FSNode::sniffFolder($path, $discarded_part_of_path);
			} else {
				$result['children'][] = array(
					'name' => $item,
					'path' => substr($folder_path . '/' . $item, strlen($discarded_part_of_path)),
					'isDirectory' => false,
					'show' => true,
					'binary' => false
				);
			}
		}
		return $result;
	}
	
	private function takeKeyValues($fTree, $keys)
	{
		if (is_array($fTree)) {
			foreach ($keys as $key) {
				if (array_key_exists($key, $fTree)) {
					$this->$key = $fTree[$key];
				}
			}
		}
	}
	
	private function createFolder($path)
	{
		if (file_exists($path)) {
			throw new Exception("Folder already exists");
		} else {
			mkdir($path);
		}
	}
	
	private function createFile($path, $content)
	{
		if (file_exists($path)) {
			throw new Exception("File already exists");
		} else {
			touch($path);
			file_put_contents($path, $content);
		}
	}
	
	private function isLeafFolder()
	{
		if ($this->isDirectory) {
			foreach ($this->children as $child) {
				if ($child->isDirectory == true) {
					return false;
				}
			}
			return true;
		}
		return false;
	}
	
	private function doesNotContainFiles()
	{
		$files = scandir($this->course->getPath() . '/files');
		$files = array_filter($files, "notDotDotAndDot");
		if ($this->isDirectory) {
			foreach ($this->children as $child) {
				if (
					$child->isDirectory == false
					&& !in_array($child->name, $files)
					|| (
						in_array($child->name, $files)
						&& file_exists($child->path)
					)
				) {
					return false;
				}
			}
			return true;
		}
		return false;
	}
	
	/**
	 * @param FSNode $parent
	 * @return int
	 */
	private static function getMaxId($parent)
	{
		if ($parent == null) {
			return 1;
		} else {
			while (true) {
				if ($parent->parent == null) {
					break;
				} else {
					$parent = $parent->parent;
				}
			}
			$id = 1;
			self::recursiveMaxId($parent, $id);
			return $id;
		}
	}
	
	/**
	 * @param FSNode $node
	 * @param $id
	 */
	private static function recursiveMaxId($node, &$id)
	{
		if ($node->id !== null) {
			if ($node->id > $id) {
				$id = $node->id;
			}
		}
		if ($node->children !== null) {
			foreach ($node->children as $child) {
				self::recursiveMaxId($child, $id);
			}
		}
	}
	
	private static function makeFolderNode($name, $displayName, string $type, bool $hidden, $homeworkId, $parent, $id = null)
	{
		$maxId = FSNode::getMaxId($parent);
		$child = new FSNode();
		if ($id === null) {
			$child->id = $maxId + 1;
		} else {
			$child->id = $id;
		}
		$child->parent = $parent;
		$child->path = $parent->path . '/' . $name;
		$child->isDirectory = true;
		$child->name = $displayName;
		$child->type = $type;
		$child->hidden = $hidden;
		$child->homeworkId = $homeworkId;
		return $child;
	}
	
	private static function makeFileNode($file, $path, $parent)
	{
		$child = new FSNode();
		$child->parent = $parent;
		$child->path = $path;
		$child->isDirectory = false;
		$child->takeKeyValues($file, ['name', 'show', 'binary']);
		return $child;
	}
	
	
	private static function isSubPath($path1, $path2)
	{
		$one = explode('/', $path1);
		$two = explode('/', $path2);
		$result = true;
		if (count($one) > count($two)) {
			for ($i = 0; $i < count($two); $i++) {
				if ($one[$i] !== $two[$i]) {
					$result = false;
				}
			}
		} else {
			for ($i = 0; $i < count($one); $i++) {
				if ($one[$i] !== $two[$i]) {
					$result = false;
				}
			}
		}
		return $result;
	}
	
	/**
	 * @param Course $course
	 * @param $folder
	 * @return mixed
	 */
	private static function getAssignmentFilesystemTree($course, $folder)
	{
		$path = $course->getPath() . '/' . $folder . '';
		$files = scandir($course->getPath() . '/files');
		$tree = FSNode::sniffFolder($path, $path);
		if ($files) {
			$files = array_filter($files, "FSNode::notDotDotAndDot");
			FSNode::addItemsToLeaves($tree, $files);
		}
		return $tree;
	}
	
	private static function takeKeysIfTheyExist(&$a, $b, $keys)
	{
		if (is_array($a) && is_array($b)) {
			foreach ($keys as $key) {
				if (array_key_exists($key, $b)) {
					$a[$key] = $b[$key];
				}
			}
		}
	}
	
	private static function mergeTreesIntoFirst(&$a, $b)
	{
		FSNode::takeKeysIfTheyExist($a, $b, ['id', 'name', 'type', 'hidden', 'show', 'binary', 'homeworkId']);
		if (is_array($a) && is_array($b) && array_key_exists('children', $a) && array_key_exists('children', $b)) {
			foreach ($a['children'] as &$child) {
				foreach ($b['children'] as $c) {
					if ($child['path'] == $c['path']) {
						FSNode::mergeTreesIntoFirst($child, $c);
					}
				}
			}
		}
		uksort($a, 'FSNode::sortKeys');
	}
	
	private static function getUpdatedAssignmentsJson(Course $course, $folder, $descriptionFileName)
	{
		$path = $course->getPath() . '/' . $descriptionFileName;
		$tree = json_decode(file_get_contents($path), true);
		$fTree = FSNode::getAssignmentFilesystemTree($course, $folder);
		FSNode::mergeTreesIntoFirst($fTree, $tree);
		return $fTree;
	}
}