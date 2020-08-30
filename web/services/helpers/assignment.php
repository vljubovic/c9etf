<?php

require_once "common.php";


// Helper recursive function for getAssignments
function assignments_process(&$assignments, $parentPath, $courseFiles)
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
			assignments_process($assignments[$key]['items'], $path, $courseFiles);
	}
}

// Sort assigments by type, then by name (natural)
function compare_assignments($a, $b)
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

function sniff_folder($folder_path, $discarded_part_of_path)
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
			$result['children'][] = sniff_folder($path, $discarded_part_of_path);
		} else {
			$result['children'][] = array('name' => $item, 'path' => substr($folder_path . '/' . $item, strlen($discarded_part_of_path)), 'isDirectory' => false);
		}
	}
	return $result;
}

/**
 * @param Course $course
 * @param string $login
 */
function check_admin_access(Course $course, string $login): void
{
	try {
		if (!$course->isAdmin($login)) {
			error("403", "You are not an admin on this course");
		}
	} catch (Exception $e) {
		error("500", $e->getMessage());
	}
}

function check_filename($filename)
{
	$ref = null;
	$matches = preg_match('/[^\/?*:{};]+/', $filename, $ref);
	$contains_backslash = boolval(strpos($filename, "\\"));
	if ($filename !== "" && $filename !== "." && $filename !== ".." && $matches && $ref !== null && $ref[0] == $filename && !$contains_backslash) {
		return true;
	} else {
		return false;
	}
}

function json($data)
{
	if (defined("JSON_PRETTY_PRINT"))
		print json_encode($data, JSON_PRETTY_PRINT);
	else
		print json_encode($data);
	exit();
}

function assignment_replace_template_parameters($code, $course, $task)
{
	$title = $task->parent->name . ", " . $task->name;
	$code = str_replace("===TITLE===", $title, $code);
	$code = str_replace("===COURSE===", $course->name, $code);
	
	foreach (Cache::getFile("years.json") as $year)
		if ($year['id'] == $course->year)
			$year_name = $year['name'];
	$code = str_replace("===YEAR===", $year_name, $code);
	
	if (!empty($task->author))
		$code = str_replace("===AUTHOR===", $task->author, $code);
	
	return $code;
}

function add_items_to_leaves(&$node, $items)
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
				$node['children'][] = array('name' => $item, 'path' => $node['path'] . '/' . $item, 'isDirectory' => false);
			}
		} else {
			foreach ($node['children'] as &$child) {
				if ($child['isDirectory']) {
					add_items_to_leaves($child, $items);
				}
			}
		}
	}
}

function notDotDotAndDot($item)
{
	return !($item == '.' || $item == '..');
}

/**
 * @param $assignments
 * @param array<Assignment> $old
 * @param Course $course
 */
function extractInfoFromOldAssignments(&$assignments, array $old, Course $course)
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
						extractInfoFromOldAssignments($assignment['children'], $item['items'], $course);
					} else {
						extractInfoFromOldAssignments($assignment['children'], $item['files'], $course);
					}
				}
				uksort($assignment, 'sortKeys');
			}
		}
	}
}


function get_updated_assignments_from_old_format(Course $course)
{
	$root = $course->getAssignments();
	$root->getItems(); // Parse legacy data
	$assignments = $root->getData();
	if (empty($assignments))
		json(error("ERR003", "No assignments for this course"));
	
	assignments_process($assignments, $course->abbrev, $course->getFiles());
	usort($assignments, "compare_assignments");
	$path = $course->getPath() . '/assignment_files';
	$tree = sniff_folder($path, $path);
	$files = scandir($course->getPath() . '/files');
	if ($files) {
		$files = array_filter($files, "notDotDotAndDot");
		add_items_to_leaves($tree, $files);
	}
	extractInfoFromOldAssignments($tree['children'], $assignments, $course);
	
	usort($tree['children'], "compare_assignments");
	return $tree;

//	message("Assignments updated for course: $course->name");
}

function addFileToTree(&$tree, string $path, array $file)
{
	if (array_key_exists('path', $tree)) {
		if ($path == $tree['path']) {
			$tree['children'][] = array(
				'path' => $path . "/" . $file['name'],
				'name' => $file['name'],
				'isDirectory' => false,
				'show' => $file['show'],
				'binary' => $file['binary'],
			);
			return true;
		} else {
			foreach ($tree['children'] as &$child) {
				if (isSubPath($path, $child['path'])) {
					return addFileToTree($tree, $path, $file);
				}
			}
			return false;
		}
	}
	return false;
}

function deleteFromTree(&$tree, string $path)
{
	if (array_key_exists('children', $tree)) {
		for ($i = 0; $i < count($tree['children']); $i++) {
			if ($tree['children'][$i]['path'] == $path) {
				unset($tree['children'][$i]);
				return true;
			} elseif (isSubPath($tree['children'][$i]['path'], $path)) {
				return deleteFromTree($tree['children'][$i], $path);
			}
		}
		return false;
	}
	return false;
}

/**
 * @param Course $course
 * @return mixed
 */
function getAssignmentFilesystemTree(Course $course)
{
	$path = $course->getPath() . '/assignment_files';
	$files = scandir($course->getPath() . '/files');
	$tree = sniff_folder($path, $path);
	if ($files) {
		$files = array_filter($files, "notDotDotAndDot");
		add_items_to_leaves($tree, $files);
	}
	return $tree;
}

function get_updated_assignments_json(Course $course)
{
	$path = $course->getPath() . '/assignments.json';
	$tree = json_decode(file_get_contents($path), true);
	$fTree = getAssignmentFilesystemTree($course);
	mergeTreesIntoFirst($fTree, $tree);
	return $fTree;
}

function mergeTreesIntoFirst(&$a, $b)
{
	takeKeysIfTheyExistAndExcludeIfAlreadyPresentInAAndP($a, $b, ['id', 'name', 'path', 'type', 'hidden'], ['path']);
	if (is_array($a) && is_array($b) && array_key_exists('children', $a) && array_key_exists('children', $b)) {
		foreach ($a['children'] as &$child) {
			foreach ($b['children'] as $c) {
				if ($child['path'] == $c['path']) {
					mergeTreesIntoFirst($child, $c);
				}
			}
		}
	}
	uksort($a, 'sortKeys');
}

function takeKeysIfTheyExistAndExcludeIfAlreadyPresentInAAndP(&$a, $b, $keys, $p)
{
	if (is_array($a) && is_array($b)) {
		foreach ($keys as $key) {
			if (array_key_exists($key, $b)) {
				if ($key !== 'path') {
					$a[$key] = $b[$key];
				}
			}
		}
	}
}

function addFileToTreeAndSaveToFile(Course $course, string $path, array $file)
{
	$assignments_file_path = $course->getPath() . '/assignments.json';
	if (!file_exists($assignments_file_path)) {
		get_updated_assignments_from_old_format($course);
	}
	$content = file_get_contents($assignments_file_path);
	$result = addFileToTree($content, $path, $file);
	if ($result) {
		if (defined("JSON_PRETTY_PRINT")) {
			file_put_contents($assignments_file_path, json_encode($content, JSON_PRETTY_PRINT));
		} else {
			file_put_contents($assignments_file_path, json_encode($content));
		}
	}
}

function sortKeys($a, $b)
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