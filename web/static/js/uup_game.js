
// UUP_GAME.JS - Functions for UUP Game services

// Get list of all assignments
function uupg_get_assignments(callback) {
    let xmlhttp = new XMLHttpRequest();
    let url = "services/uup_game.php?action=getAssignments&A";

    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            let data = JSON.parse(xmlhttp.responseText);
            callback(data.data.children);
        }
    }
    xmlhttp.open("GET", url, true);
    xmlhttp.send();
}


// Get the task that student is currently working on in given assignment
function uupg_get_current_task(username, asgnId, callback) {
    let xmlhttp = new XMLHttpRequest();
    let url = "services/game_statistics.php?action=studentInfo&student=" + username;

    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            let data = JSON.parse(xmlhttp.responseText);
            for (var id in data.data) {
                if (data.data.hasOwnProperty(id) && id == asgnId) {
                    var currentTask = data.data[id].find(function(task) {
                        return task.status === "CURRENT TASK";
                    });
                    if (currentTask) callback(currentTask.task_id);
                    return;
                }
            }
            return callback(false);
        }
    }
    xmlhttp.open("GET", url, true);
    xmlhttp.send();
}


// Deploy one file from UUP Game to student
function uupg_deploy_file_to_student(username, taskId, fileName) {
    let xmlhttp = new XMLHttpRequest();
    let url = "services/uup_game.php?action=deployFileToStudent&username=" + username + "&taskId=" + taskId + "&fileName=" + fileName;

    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            let data = JSON.parse(xmlhttp.responseText);
            if (!data.success) {
                console.error(data.message)
            }
        }
    }
    xmlhttp.open("GET", url, true);
    xmlhttp.send();
}
