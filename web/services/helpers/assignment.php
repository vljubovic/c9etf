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

function sniffFolder($folder_path, $root_path)
{
	$result['name'] = basename($folder_path);
	$result['path'] = substr($folder_path, strlen($root_path));
	$result['isDirectory'] = true;
	$result['children'] = [];
	foreach (scandir($folder_path) as $item) {
		$path = $folder_path . '/' . $item;
		if ($item == '..' || $item == '.') {
			continue;
		}
		if (is_dir($path)) {
			$result['children'][] = sniffFolder($path, $root_path);
		} else {
			$result['children'][] = array('name' => $item, 'path' => substr($folder_path.$item, strlen($root_path)),'isDirectory' => false);
		}
	}
	return $result;
}
