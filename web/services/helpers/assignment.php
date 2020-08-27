<?php

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
function compareAssignments($a, $b)
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

function sniffFolder($folder_path, $discarded_part_of_path)
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
			$result['children'][] = sniffFolder($path, $discarded_part_of_path);
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
function checkAdminAccess(Course $course, string $login): void
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

function addItemsToLeaves(&$node, $items)
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
					addItemsToLeaves($child, $items);
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
function extractInfoFromOldAssignments(&$assignments, $old, $course)
{
	foreach ($assignments as &$assignment) {
		foreach ($old as $item) {
			$path = substr($item['path'], strlen($course->abbrev));
			if ($assignment['path'] == $path) {
				if ($item['id']) {
					$assignment['id'] = $item['id'];
				}
				if ($item['type']) {
					$assignment['type'] = $item['type'];
				}
				if ($item['name']) {
					$assignment['name'] = $item['name'];
				}
				if ($item['hidden']) {
					$assignment['hidden'] = $item['hidden'];
				}
				if ($assignment['children']) {
					extractInfoFromOldAssignments($assignment['children'], $item['items'], $course);
				}
				uksort($assignment, 'sortKeys');
			}
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