
// EDIT_ASSIGNMENT.JS - Assignment editor
// Version: 2019/05/28 11:40


// Global array for assignments
var assignments = [];



// HELPER FUNCTIONS

// Get index of currently selected assignment
function assignmentsGetCurrentIdx() {
	var asgnSelect = document.getElementById('assignment_select');
	if (asgnSelect.selectedIndex == -1) return -1;
	var currentId = asgnSelect.options[asgnSelect.selectedIndex].value;
	for (var i=0; i<assignments.length; i++)
		if (assignments[i].id == currentId)
			return i;
	return -1;
}

// Get index of assignment with given id
function assignmentsGetIdxForId(id) {
	for (var i=0; i<assignments.length; i++)
		if (assignments[i].id == id)
			return i;
	return -1;
}

// Get index of parent assignment for assignment with given idx
function assignmentsGetParentIdx(idx) {
	var targetLevel = assignments[idx].level - 1;
	for (var i=idx-1; i>=0; i--) {
		if (assignments[i].level == targetLevel)
			return i;
	}
	return -1;
}

// Get full path for assignment at given index
function assignmentGetPath(idx) {
	var path = assignments[idx].path;
	var level = assignments[idx].level;
	for (var j=idx-1; j>=0; j--) {
		if (assignments[j].level == level - 1) {
			level = assignments[j].level;
			path = assignments[j].path + "/" + path;
		}
	}
	return path;
}


// Get "path name" (name with all parent assignments) for assignment at given index
function assignmentGetName(idx) {
	var name = assignments[idx].name;
	var level = assignments[idx].level;
	for (var j=idx-1; j>=0; j--) {
		if (assignments[j].level == level - 1) {
			level = assignments[j].level;
			name = assignments[j].name + "/" + name;
		}
	}
	return name;
}


// Open tree segment for assignment with given ID
function assignmentsOpen(idx) {
	if (idx == -1) return;
	for (var i=idx + 1; i<assignments.length; i++) 
		if (assignments[i].level == assignments[idx].level + 1)
			assignments[i].visible = 1;
		else if (assignments[i].level <= assignments[idx].level)
			break;
	assignmentsOpen(assignmentsGetParentIdx(idx));
}


// Test if assignment is a task within a homework
function assignmentIsHomework(idx) {
	var asgn = assignments[idx];
	var lastChar = asgn.name.charAt(asgn.name.length - 1);
	var parentIdx = assignmentsGetParentIdx(idx);
	if (parentIdx == -1) return false;
	var parent = assignments[parentIdx];
	return (parent.homework_id && parent.homework_id > 0 && lastChar >= '0' && lastChar <= '9');
}


// Test if assignment at given idx has valid (unique) path
// It's up to the calling function to restore path to previous value if it's invalid
function assignmentValidatePath(idx, showAlert) {
	if (!assignments[idx].folder && !assignments[idx].path.trim()) {
		if (showAlert) alert("Path can't be empty for tasks.");
		return false;
	}
	currentPath = assignmentGetPath(idx);
	
	for (var i=0; i<assignments.length; i++) {
		var path = assignmentGetPath(i);
		if (i != idx && path != "" && path == currentPath) {
			alert("Path " + currentPath + " is the same as "+assignmentGetName(i));
			return false;
		}
	}
	return true;
}


// Check if name is duplicate or empty
function assignmentValidateName(idx, showAlert) {
	var name = assignments[idx].name.trim();
	if (!name) {
		if (showAlert) alert("Assignment name can't be empty.");
		return false;
	}
	
	// Name must be unique among tasks of same level within the same parent
	// Start and end of range with same parent
	var start = assignmentsGetParentIdx(idx); 
	var end = idx;
	while (end < assignments.length && assignments[end].level >= assignments[idx].level)
		end++;
	for (var i=start; i<end; i++) {
		if (i != idx && assignments[i].name.trim() == name) {
			if (showAlert) alert("Assignment with name "+name+" already exists.");
			return false;
		}
	}
	return true;
}


// Find unused ID for new assignment
function assignmentsGetNewId() {
	var maxId = 0;
	for (var i=0; i<assignments.length; i++)
		if (assignments[i].id > maxId) 
			maxId = assignments[i].id;
	return maxId+1;
}


// UI FUNCTIONS

// Fill the assignments SELECT widget with data
function assignmentsRender() {
	var asgnSelect = document.getElementById('assignment_select');
	for(i = asgnSelect.options.length - 1 ; i >= 0 ; i--) {
		asgnSelect.remove(i);
	}
	
	var shown=0;
	for (var i=0; i<assignments.length; i++) {
		var option = document.createElement("option");
		option.value = assignments[i].id;
		option.text = assignments[i].name;
		for (var j=0; j<assignments[i].level; j++)
			option.text = "__" + option.text;
		if (assignments[i].visible == 0) option.style.display = "none";
		else shown++;
		asgnSelect.add(option);
		if (assignments[i].selected == 1) asgnSelect.value = assignments[i].id;
	}
	asgnSelect.size = shown;
}


// Simulate clicking on an entry in the assignments SELECT widget with given ID
function assignmentsClickOn(asgn_id) {
	var asgnSelect = document.getElementById('assignment_select');
	var idx = assignmentsGetIdxForId(asgn_id);
	if (idx >= 0) {
		asgnSelect.selectedIndex = idx;
		assignmentsChangeSelected();
	}
}

// Perform actions when user changes currently selected assignment
function assignmentsChangeSelected() {
	// Clear selected/visible fields in data array
	for (var i=0; i<assignments.length; i++) {
		assignments[i].selected = 0;
		if (assignments[i].level > 1) assignments[i].visible = 0;
	}
	
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) return;
	
	var asgn = assignments[idx];
	assignments[idx].selected = 1;
	assignmentsOpen(idx);
	
	assignmentsRender();
	
	
	document.getElementById("folderForm").style.display = "inline";
	document.getElementById("name").value = asgn.name;
	document.getElementById("path").value = asgn.path;
	if (asgn.hidden) document.getElementById("hidden").checked = true;
	else document.getElementById("hidden").checked = false;
	if (asgn.homework_id) document.getElementById("homework_id").value = asgn.homework_id;
	else document.getElementById("homework_id").value = "";
	if (asgn.author) document.getElementById("author").value = asgn.author;
	else document.getElementById("author").value = "";
	
	if (asgn.folder) {
		document.getElementById("filesDiv").style.display = "none";
	} else {
		document.getElementById("filesDiv").style.display = "inline";
		var filesSelect = document.getElementById("filesSelect");
		for(i = filesSelect.options.length - 1 ; i >= 0 ; i--) {
			filesSelect.remove(i);
		}
		
		//document.getElementById('fileName').value = "";
		var span = document.getElementById('fileNameSpan');
		while( span.firstChild ) {
			span.removeChild( span.firstChild );
		}
		document.getElementById('fileBinary').checked = false;
		document.getElementById('fileShow').checked = false;
		
		if (asgn.files.length < 4) filesSelect.size = 4; else filesSelect.size = asgn.files.length;
		
		for (var i=0; i<asgn.files.length; i++) {
			var option = document.createElement("option");
			option.value = asgn.files[i].filename;
			option.text = asgn.files[i].filename;
			filesSelect.add(option);
		}
		
		// What files can be generated for this assignment?
		assignmentsGenerateFile(false, "");
	}
	
	document.getElementById('assignmentChangeMessage').style.display = "none";
	document.getElementById('uploadFileWrapper').style.display='none';
	document.getElementById("path").style.backgroundColor = "#fff";
	document.getElementById("name").style.backgroundColor = "#fff";
}


// Update data in the internal array from form fields
function assignmentsUpdate() {
	var idx = assignmentsGetCurrentIdx();
	
	var tmp = assignments[idx].path;
	assignments[idx].path = document.getElementById("path").value;
	if (!assignmentValidatePath(idx, true)) {
		assignments[idx].path = tmp;
		document.getElementById("path").style.backgroundColor = "#fcc";
		document.getElementById("path").focus();
		return;
	}
	
	var tmp = assignments[idx].name;
	assignments[idx].name = document.getElementById("name").value;
	if (!assignmentValidateName(idx, true)) {
		assignments[idx].name = tmp;
		document.getElementById("name").style.backgroundColor = "#fcc";
		document.getElementById("name").focus();
		return;
	}
	
	if (document.getElementById("hidden").checked) 
		assignments[idx].hidden = true; 
	else 
		assignments[idx].false;
	if (document.getElementById("homework_id").value) 
		assignments[idx].homework_id = document.getElementById("homework_id").value;
	else
		assignments[idx].homework_id = 0;
	assignments[idx].author = document.getElementById("author").value;

	document.getElementById('assignmentChangeMessage').style.display = "inline";
	document.getElementById('assignmentChangeMessage').innerHtml = 'Assignment changed';
	document.getElementById("path").style.backgroundColor = "#fff";
	document.getElementById("name").style.backgroundColor = "#fff";
	assignmentsRender();
	
	assignmentsSendToServer();
}


// Move assignment up/down
function assignmentsUpDown(up) {
	var i = assignmentsGetCurrentIdx();
	if (i == -1) return;
	
	// Find all subitems of currently selected item
	var start=i, end=-1, pos = -1;
	do {
		i++;
	} while(i<assignments.length && assignments[i].level > assignments[start].level);
	end = i;
	
	// Construct a new reordered array of assignments
	var newasgn = []
	if (up) {
		// Find previous assignment of same (or lower) level
		pos = start;
		do {
			pos--;
		} while (pos >= 0 && assignments[pos].level > assignments[start].level);
		if (pos < 0 || assignments[pos].level < assignments[start].level) pos++;
		
	} else {
		// Find next assignment of same (or lower) level
		// Since block [start,end] get inserted at pos, we will move start-end to next assignment
		pos = start;
		start = end;
		do {
			end++;
		} while (end < assignments.length && assignments[end].level > assignments[start].level);
	}
	for (var i=0; i<pos; i++)
		newasgn.push(assignments[i]);
	for (var i=start; i<end; i++)
		newasgn.push(assignments[i]);
	for (var i=pos; i<start; i++)
		newasgn.push(assignments[i]);
	for (var i=end; i<assignments.length; i++)
		newasgn.push(assignments[i]);
	assignments = newasgn;
	assignmentsRender();
	assignmentsSendToServer();
}


// Perform actions when user changes currently selected file in filelist
function assignmentsFileSelected() {
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) return;
	
	var filesSelect = document.getElementById("filesSelect");
	var currentFile = filesSelect.options[filesSelect.selectedIndex].value;
	
	for (var j=0; j<assignments[idx].files.length; j++) {
		if (assignments[idx].files[j].filename == currentFile) {
			//document.getElementById('fileName').value = assignments[idx].files[j].filename;
			var span = document.getElementById('fileNameSpan');
			while( span.firstChild ) {
				span.removeChild( span.firstChild );
			}
			span.appendChild( document.createTextNode(assignments[idx].files[j].filename) );
			
			document.getElementById('fileBinary').checked = assignments[idx].files[j].binary;
			document.getElementById('fileShow').checked = assignments[idx].files[j].show;
		}
	}
	
	document.getElementById('assignmentChangeMessage').style.display='none';
	document.getElementById('uploadFileWrapper').style.display='none';
}


// Remove file from filelist
function assignmentsRemoveFile() {
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) return;
	
	var filesSelect = document.getElementById("filesSelect");
	if (filesSelect.selectedIndex == -1) return;
	
	var currentFile = filesSelect.options[filesSelect.selectedIndex].value;
	
	var ok = confirm("Are you sure you want to delete file "+currentFile+"?\n\nThis operation can not be undone.");
	if (!ok) return;
	
	for (var j=0; j<assignments[idx].files.length; j++) {
		if (assignments[idx].files[j].filename == currentFile) {
			assignments[idx].files.splice(j,1);
			break;
		}
	}
	
	filesSelect.remove(filesSelect.selectedIndex);
	assignmentsUpdate();
}


// View currently selected file
function assignmentsViewFile() {
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) return;
	var asgn_id = assignments[idx].id;
	
	var filesSelect = document.getElementById("filesSelect");
	if (filesSelect.selectedIndex == -1) return;
	var currentFile = filesSelect.options[filesSelect.selectedIndex].value;
	var fileData = assignments[idx].files[filesSelect.selectedIndex];
	
	var url = "/assignment/ws.php?action=getFile&file="+currentFile+"&task_direct="+asgn_id+"&"+courseUrlPart+"&view";
	
	// Prepare file viewer widget
	var span = document.getElementById('showFileFilename');
	while( span.firstChild ) {
		span.removeChild( span.firstChild );
	}
	span.appendChild( document.createTextNode(currentFile) );
	document.getElementById('showFileDownloadLink').addEventListener('click', function() {
		location.replace(url);
	}, false);
	
	// Handling binary files
	if (fileData.binary) {
		if (currentFile.includes(".png") || currentFile.includes(".jpg") || currentFile.includes(".jpeg") || currentFile.includes(".gif")) {
			document.getElementById('showFileContents').innerHTML = "<img src='" + url + "'>";
		} else {
			document.getElementById('showFileContents').innerHTML = "<p style='color: red;'>Can't show this kind of file...</p>";
		}
		document.getElementById('showFileWrapper').style.display = "inline-block";
		return;
	}
	
	// We will use getFile service to fetch text file from server and show inside widget
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			if (xmlhttp.responseText.includes('"success": "false"')) {
				try {
					var result = JSON.parse(xmlhttp.responseText);
					if (result.code == "ERR006") {
						alert("File not found on server!\nYou should delete the file and then upload or create it again.");
						return;
					}
				} catch(e) {
					// Response is not JSON after all... just show the file
				}
			}
			
			document.getElementById('showFileContents').innerText = xmlhttp.responseText;
			document.getElementById('showFileWrapper').style.display = "inline-block";
		}
	}
	xmlhttp.open("GET", url, true);
	xmlhttp.send();
}


// Upload a new file
function assignmentsUploadFile() {
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) return;
	
	var filesSelect = document.getElementById("filesSelect");
	
	// This code uploads file from a file selection widget using addFile web service
	var file = document.getElementById('uploadFileWidget').files[0];
	console.log(file);
	var xmlhttp = new XMLHttpRequest();
	var formData = new FormData();
	
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			try {
				var result = JSON.parse(xmlhttp.responseText);
				if (result.success && result.success == "true") {
					var found = false;
					for (var i=0; i < assignments[idx].files.length; i++)
						if (assignments[idx].files[i].filename == file.name)
							found = true;
					if (!found) {
						var option = document.createElement("option");
						option.value = file.name;
						option.text = file.name;
						filesSelect.add(option);
						
						assignments[idx].files.push({"filename" : file.name, "binary":false, "show":false});
						assignmentsSendToServer();
					}
				}
				else
					alert("Upload failed");
			} catch(e) {
				alert("Upload failed");
			}
			document.getElementById('uploadFileWrapper').style.display='none';
			document.getElementById('uploadProgress').style.display="none";
		}
	}
	
	xmlhttp.upload.addEventListener('progress', function(e) {
		var percent_complete = (e.loaded / e.total)*100;
		document.getElementById('uploadProgressBar').value = percent_complete;
	});

	formData.append("add", file); // is it possible to rename???
	formData.append("task_direct", assignments[idx].id);
	xmlhttp.open("POST", '/assignment/ws.php?action=addFile&'+courseUrlPart);
	xmlhttp.send(formData);
	document.getElementById('uploadProgress').style.display="block";
}


// Update options for currently selected file
function assignmentsFileChange() {
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) return;
	
	var filesSelect = document.getElementById("filesSelect");
	if (filesSelect.selectedIndex == -1) return;
	
	var currentFile = filesSelect.options[filesSelect.selectedIndex].value;
	
	for (var j=0; j<assignments[idx].files.length; j++) {
		if (assignments[idx].files[j].filename == currentFile) {
			//document.getElementById('fileName').value = assignments[idx].files[j].filename;
			assignments[idx].files[j].binary = document.getElementById('fileBinary').checked;
			assignments[idx].files[j].show = document.getElementById('fileShow').checked;
			document.getElementById('assignmentChangeMessage').innerHtml = 'File changed';
			document.getElementById('assignmentChangeMessage').style.display='block';
			assignmentsSendToServer();
		}
	}
}


// Generate file
function assignmentsGenerateFile(doGenerate) {
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) return;
	var asgn = assignments[idx];
	
	// Test to see what files can be generated
	var generate = [ ".autotest" ];
	if (assignmentIsHomework(idx))
		generate.push(".zadaca");
	
	// Remove files that already exist
	for (var i=0; i<asgn.files.length; i++) {
		for (var j=0; j<generate.length; j++) {
			if (asgn.files[i].filename == generate[j]) {
				generate.splice(j,1);
				break;
			}
		}
	}
	
	if (doGenerate && generate.length > 0) {
		var filename = generate[generate.length-1];
		// We will use a web service generateFile to create it
		var xmlhttp = new XMLHttpRequest();
		var url = "/assignment/ws.php?action=generateFile&file="+filename+"&task_direct="+asgn.id+"&"+courseUrlPart;
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
				try {
					var result = JSON.parse(xmlhttp.responseText);
					if (result.success && result.success == "true") {
						var option = document.createElement("option");
						option.value = filename;
						option.text = filename;
						filesSelect.add(option);
						
						assignments[idx].files.push({"filename" : filename, "binary": false, "show": false});
						
						if (generate.length <= 1)
							document.getElementById("createFileButton").style.display = "none";
						assignmentsSendToServer();
					}
					else
						alert("Generating file failed");
				} catch(e) {
					alert("Generating file failed");
				}
			}
		}
		xmlhttp.open("GET", url, true);
		xmlhttp.send();
	}
	
	if (generate.length <= 0) {
		document.getElementById("createFileButton").style.display = "none";
	} else {
		document.getElementById("createFileButton").style.display = "inline";
	}
}



// Add a new folder to the end of the list of assignments
function assignmentsAddFolder() {
	var defaultName = "New folder";
	var newId = assignmentsGetNewId();
	var assignment = {
		id: newId,
		type: "folder",
		name: defaultName,
		path: "",
		folder: true,
		files: [],
		homework_id: null,
		hidden: false,
		author: authorName,
		level: 1,
		visible: 1,
		selected: 1
	};
	
	assignments.push(assignment);
	
	// Test if name already exists
	var idx = assignments.length - 1;
	var nr = 1;
	while (!assignmentValidateName(idx, false)) {
		assignments[idx].name = defaultName + " " + nr;
		nr++;
	}
	
	assignmentsRender();
	assignmentsClickOn(newId);
	assignmentsSendToServer();
}


// Add a new task to the currently selected folder
function assignmentsAddTask() {
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) {
		alert("Please select a folder first.");
		return;
	}
	while (!assignments[idx].folder) idx--;
	
	var taskNo = 1, i = idx + 1, level = assignments[idx].level + 1;
	var namePart = "Zadatak";
	while (i<assignments.length && assignments[i].level >= level) {
		if (assignments[i].level == level && assignments[i].name == namePart+" "+taskNo)
			taskNo++;
		i++;
	}
	
	var newId = assignmentsGetNewId();
	var assignment = {
		id: newId,
		type: "task",
		name: namePart+" "+taskNo,
		path: "Z"+taskNo,
		folder: false,
		files: [],
		homework_id: null,
		hidden: false,
		author: authorName,
		level: level,
		visible: 1,
		selected: 1
	};
	if (i == assignments.length)
		assignments.push(assignment);
	else
		assignments.splice(i, 0, assignment);
	
	// If path is already use, prepend Z
	while(!assignmentValidatePath(i, false))
		assignments[i].path = "Z" + assignments[i].path;
	
	assignmentsRender();
	assignmentsClickOn(newId);
	assignmentsSendToServer();
}


// Delete assignment
function assignmentsDelete() {
	var idx = assignmentsGetCurrentIdx();
	if (idx == -1) {
		alert("No assignment selected.");
		return;
	}
		
	var ok = confirm("Are you sure you want to delete assignment "+assignments[idx].name+"?\n\nThis operation can not be undone.");
	if (!ok) return;

	var i=idx+1;
	while(i < assignments.length && assignments[i].level > assignments[idx].level)
		i++;
	
	assignments.splice(idx, i-idx);
	assignmentsRender();	
	assignmentsSendToServer();
}


// Convert assignment array into appropriate form and send to server
function assignmentsSendToServer() {
	// Since there are no references in JavaScript, we must use recursion to populate the tree
	var assignmentTree = assignmentGetTreeNodes(0, 1);
	
	// Submit via web service
	var xmlhttp = new XMLHttpRequest();
	var url = "/assignment/ws.php?action=updateAssignments&"+courseUrlPart;
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			try {
				var result = JSON.parse(xmlhttp.responseText);
				if (result.success && result.success == "true") {
					// Do nothing
					console.log(result);
				}
				else
					alert("Updating data failed - your changes were not recorded on server. Try again later.");
			} catch(e) {
				alert("Updating data failed - your changes were not recorded on server. Try again later.");
			}
		}
	}
	var formData = new FormData();
	formData.append("data", JSON.stringify(assignmentTree));
	xmlhttp.open("POST", url, true);
	xmlhttp.send(formData);
}


function assignmentGetTreeNodes(idx, level) {
	var subtree = [];
	var asgn = false;
	//console.log("assignmentGetTreeNodes "+idx+" "+level);
	//if (level == 10) return;
	for (var i=idx; i<assignments.length+1; i++) {
		if (i == assignments.length || assignments[i].level <= level) {
			if (asgn) {
				// Delete properties that we use for rendering
				delete asgn.level;
				delete asgn.parent;
				delete asgn.visible;
				delete asgn.selected;
				
				// Reduce footpring further
				//if (!asgn.hidden) delete asgn.hidden;
				if (!asgn.author) delete asgn.author;
				if (!asgn.homework_id) delete asgn.homework_id;
				if (asgn.folder && asgn.files && asgn.files.length == 0) delete asgn.files;
				if (!asgn.folder && !asgn.items) asgn.items = [];
				
				// Things get stupid sometimes
				if (!asgn.hidden) asgn.hidden = "false";
				
				delete asgn.folder; // ?
				subtree.push(asgn);
				//console.log("assignments push "+i);
			}
			if (i == assignments.length || assignments[i].level < level) break;
			
			//console.log("asgn = assignments "+i);
			asgn = JSON.parse(JSON.stringify(assignments[i])); //Deep copy
			if (asgn.folder) asgn.items = [];
		} else {
			//console.log("recursion "+i+" level "+level);
			asgn.items = assignmentGetTreeNodes(i, level+1);
			while (i < assignments.length && assignments[i].level > level) i++;
			i--;
			//console.log("skipping to "+i+" level "+level);
			//if (i==2) return;
		}
	}
	//console.log("assignmentGetTreeNodes return");
	//console.log(subtree);
	return subtree;
}
