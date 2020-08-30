<?php
require_once("./../services/helpers/assignment.php");

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
		$parent = null;
	public
		$children = null,
		$id = null,
		$homework_id = null,
		$name = null,
		$path = null,
		$type = null,
		$hidden = null,
		$isDirectory = null,
		$show = null,
		$binary = null;
	
	public static function constructTreeForCourse($course)
	{
		$fTree = get_updated_assignments_json($course);
		$node = new FSNode();
		$node->course = $course;
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
		$json = preg_replace('/,?\s*"[^"]+":null|"[^"]+":null,?/', '', $json);
		$json = json_decode($json, true);
		if (defined(JSON_PRETTY_PRINT)) {
			return json_encode($json, JSON_PRETTY_PRINT);
		} else {
			return json_encode($json);
		}
	}
	
	public function getNodeByPath($path)
	{
		if ($path == $this->path) {
			return $this;
		} else {
			foreach ($this->children as $child) {
				if (isSubPath($child->path, $path)) {
					return $this->getNodeByPath($path);
				}
			}
		}
		return null;
	}
	
	public function addFile($file, $content)
	{
		if ($this->isDirectory && $this->isLeafFolder()) {
			$path = $this->path . "/" . $file['name'];
			$this->createFile($path, $content);
			$this->children[] = FSNode::makeFileNode($file, $path, $this);
		}
	}
	
	public function editFile($content, $show = null, $binary = null)
	{
		if (file_exists($this->path)) {
			if ($content !== null) {
				file_put_contents($this->path, $content);
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
		if (file_exists($this->path)) {
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
				$this->homework_id = $homework_id;
			}
		}
	}
	
	public function deleteFile() {
		if (file_exists($this->path)) {
			unlink($this->path);
		}
		$parent = $this->parent;
		for ($i = 0; $i < $parent->children; $i++) {
			if ($parent->children[$i]->path == $this->path) {
				unset($parent->children[$i]);
				break;
			}
		}
	}
	
	public function deleteFolder(){
		$this->rRmdir($this->path);
		$parent = $this->parent;
		for ($i = 0; $i < $parent->children; $i++) {
			if ($parent->children[$i]->path == $this->path) {
				unset($parent->children[$i]);
				break;
			}
		}
	}
	private function rRmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
						rrmdir($dir. DIRECTORY_SEPARATOR .$object);
					else
						unlink($dir. DIRECTORY_SEPARATOR .$object);
				}
			}
			rmdir($dir);
		}
	}
	public function addFolder($name, $displayName, $type = 'tutorial', $hidden = false, $homeworkId = null)
	{
		if ($this->isDirectory && $this->doesNotContainFiles()) {
			$path = $this->path . '/' . $name;
			$this->createFolder($path);
			$this->children[] = FSNode::makeFolderNode($name, $displayName, $type, $hidden, $homeworkId, $this);
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
					$node->course = $this->course;
					$node->constructNode($this, $child);
					$this->children[] = $node;
				}
			}
		}
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
			file_put_contents(basename($path), $content);
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
	
	private static function makeFolderNode($path, $displayName, string $type, bool $hidden, $homeworkId, $parent)
	{
		$maxId = FSNode::getMaxId($parent);
		$child = new FSNode();
		$child->id = $maxId;
		$child->parent = $parent;
		$child->path = $path;
		$child->isDirectory = true;
		$child->name = $displayName;
		$child->type = $type;
		$child->hidden = $hidden;
		$child->homework_id = $homeworkId;
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
	
}