<?php
require_once("Course.php");

/**
 * Class GameNode
 * @property GameNode parent
 * @property Course course
 * @property string folder
 * @property GameNode[] children
 * @property boolean isDirectory
 * @property string name
 * @property string path
 * @property string type
 * @property array data
 */
class GameNode
{
	private $course, $folder, $parent;
	public $id, $path, $name, $isDirectory, $type, $children;
	public $data = null;
	
	/**
	 * @param Course $course
	 * @return GameNode
	 */
	public static function constructGameForCourse($course)
	{
		$json = json_decode(file_get_contents($course->getPath() . '/game.json'), true);
		$node = new GameNode();
		$node->name = "Game";
		$node->type = "Root";
		$node->isDirectory = true;
		$node->folder = "game_files";
		$node->course = $course;
		$node->constructNode(null, $json);
		return $node;
	}
	
	public static function findAssignmentById($id, $course)
	{
		$root = self::constructGameForCourse($course);
		foreach ($root->children as $child) {
			if ($child->id == $id) {
				return $child;
			}
		}
		return null;
	}
	
	public static function findTaskById($id, $course)
	{
		$root = self::constructGameForCourse($course);
		foreach ($root->children as $child) {
			foreach ($child->children as $grandchild) {
				if ($grandchild->id == $id) {
					return $grandchild;
				}
			}
		}
		return null;
	}
	
	protected function constructNode($parent, $fTree)
	{
		if (is_array($fTree)) {
			$this->parent = $parent;
			$this->takeKeyValues($fTree, ['id', 'name', 'type', 'path', 'isDirectory', 'data']);
			if (array_key_exists('children', $fTree)) {
				$this->children = [];
				foreach ($fTree['children'] as $child) {
					$node = new GameNode();
					$node->folder = $this->folder;
					$node->course = $this->course;
					$node->constructNode($this, $child);
					$this->children[] = $node;
				}
			}
		}
	}
	
	private function orderJsonKeys($json)
	{
		uksort($json, 'self::sortKeys');
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
	
	protected function sortKeys($a, $b)
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
	
	
	public function addGameAssignment($name, $displayName, $active, $points, $challengePoints, $id)
	{
		$node = new GameNode();
		$node->course = $this->course;
		$node->folder = $this->folder;
		$node->parent = $this;
		
		$node->path = "/$name";
		$this->createFolder($this->getAbsolutePath() . $node->path);
		
		$node->id = $id;
		$node->name = $displayName;
		
		$node->isDirectory = true;
		$node->type = 'assignment';
		$node->data['active'] = $active;
		$node->data['points'] = $points;
		$node->data['challengePoints'] = $challengePoints;
		
		$this->children[] = $node;
	}
	
	public function addAssignmentTask($id, $name, $displayName, $category, $hint)
	{
		if ($this->type === "assignment") {
			$node = $this->constructChild();
			
			$node->path = $this->path . "/$name";
			$this->createFolder($node->getAbsolutePath());
			
			$node->id = $id;
			$node->name = $displayName;
			
			$node->isDirectory = true;
			$node->type = 'task';
			$node->data['category'] = $category;
			$node->data['hint'] = $hint;
			$filename = "task.html";
			$content = $this->extractContentFromTemplateFile($filename);
			$node->addFileToTask($filename,$content);
			$filename = "main.c";
			$content = $this->extractContentFromTemplateFile($filename);
			$node->addFileToTask($filename,$content);
			$filename = ".autotest";
			$content = $this->extractContentFromTemplateFile($filename);
			$node->addFileToTask($filename,$content);
			
			$this->children[] = $node;
		} else {
			throw new Exception("You cannot add a Task here. This is not an Assignment.");
		}
	}
	
	public function addFileToTask($name, $content = "", $binary = false, $show = true)
	{
		if ($this->type === "task") {
			$node = $this->constructChild();
			
			$node->name = $name;
			$node->path = $this->path . "/$name";
			$node->isDirectory = false;
			
			$node->type = "file";
			$node->data["binary"] = $binary;
			$node->data["show"] = $show;
			$this->children[] = $node;
			
			touch($node->getAbsolutePath());
			file_put_contents($node->getAbsolutePath(), $content);
		} else {
			throw new Exception("This is not a Task. You cannot add a file here");
		}
	}
	
	public function editFile($content = null, $binary = null, $show = null)
	{
		if ($this->type === "file") {
			if ($content !== null) {
				file_put_contents($this->getAbsolutePath(), $content);
			}
			if ($binary !== null) {
				$this->data["binary"] = $binary;
			}
			if ($show !== null) {
				$this->data["show"] = $show;
			}
		} else {
			throw new Exception("This is not a file");
		}
	}
	
	public function editAssignment($name, $active, $points, $challenge_pts)
	{
		$this->name = $name;
		$this->data['active'] = $active;
		$this->data['points'] = $points;
		$this->data['challengePoints'] = $challenge_pts;
	}
	
	public function getFileContent()
	{
		return file_get_contents($this->getAbsolutePath());
	}
	
	public function editTask($name, $category, $hint)
	{
		$this->name = $name;
		$this->data['category'] = $category;
		$this->data['hint'] = $hint;
	}
	
	public function deleteFile()
	{
		if ($this->type === "file") {
			if (file_exists($this->getAbsolutePath())) {
				unlink($this->getAbsolutePath());
			}
			$this->unlinkChildFromParent();
		} else {
			throw new Exception("This is not a file");
		}
	}
	
	public function deleteTask()
	{
		if ($this->type === "task") {
			self::rRmdir($this->getAbsolutePath());
			$this->unlinkChildFromParent();
		}
	}
	
	public function deleteAssignment()
	{
		if ($this->type === "assignment") {
			self::rRmdir($this->getAbsolutePath());
			$this->unlinkChildFromParent();
		}
	}
	
	public function getJson()
	{
		$json = json_encode($this->getRootNode());
		$json = json_decode($json, true);
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
	
	protected static function rRmdir($dir)
	{
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . DIRECTORY_SEPARATOR . $object))
						GameNode::rRmdir($dir . DIRECTORY_SEPARATOR . $object);
					else
						unlink($dir . DIRECTORY_SEPARATOR . $object);
				}
			}
			rmdir($dir);
		}
	}
	
	protected function takeKeyValues($fTree, $keys)
	{
		if (is_array($fTree)) {
			foreach ($keys as $key) {
				if (array_key_exists($key, $fTree)) {
					$this->$key = $fTree[$key];
				}
			}
		}
	}
	
	public function __set($name, $value)
	{
		$this->$name = $value;
	}
	
	public function __get($name)
	{
		return $this->$name;
	}
	
	private function getRootNode()
	{
		$node = $this;
		while ($node->parent !== null) {
			$node = $node->parent;
		}
		return $node;
	}
	
	public function getAbsolutePath()
	{
		return $this->course->getPath() . "/$this->folder" . $this->path;
	}
	
	private function createFolder(string $path)
	{
		if (file_exists($path)) {
			throw new Exception("Folder already exists");
		} else {
			mkdir($path);
		}
	}
	
	/**
	 * @return GameNode
	 */
	private function constructChild(): GameNode
	{
		$node = new GameNode();
		$node->course = $this->course;
		$node->folder = $this->folder;
		$node->parent = $this;
		return $node;
	}
	
	private function unlinkChildFromParent(): void
	{
		$parent = $this->parent;
		for ($i = 0; $i < $parent->children; $i++) {
			if ($parent->children[$i]->path == $this->path) {
				unset($parent->children[$i]);
				break;
			}
		}
	}
	
	/**
	 * @param string $filename
	 * @return false|string
	 */
	private function extractContentFromTemplateFile(string $filename)
	{
		$content = file_get_contents($this->course->getPath() . '/templates/' . $filename);
		if ($content == false) {
			$content = "";
		}
		return $content;
	}
}