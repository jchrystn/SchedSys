function updateSectionSchedCode() {
    // Get the selected section code and the academic year
    const sectionCode = document.getElementById('modal_section_code').value;
    const ayCode = document.getElementById('ay_code').value;

    // Generate the section schedule code only if both values are present
    if (sectionCode && ayCode) {
        // Replace '-' with a space for the section code and format it
        const formattedSectionCode = sectionCode.replace('-', '_') + '_' + ayCode;
        document.getElementById('modal_section_sched_code').value = formattedSectionCode;
    } else {
        // Clear the section schedule code if either value is missing
        document.getElementById('modal_section_sched_code').value = '';
    }
}

// Ensure that the event listener is added every time the modal is opened
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('createTableModal');

    if (modal) {
        // Bootstrap 5 modal event when shown
        modal.addEventListener('shown.bs.modal', function() {
            const sectionCodeDropdown = document.getElementById('modal_section_code');
            const ayCodeInput = document.getElementById('ay_code');

            // Clear the previous section schedule code when the modal is shown
            document.getElementById('modal_section_sched_code').value = '';

            // Attach event listeners when the modal is shown
            if (sectionCodeDropdown) {
                sectionCodeDropdown.addEventListener('change', updateSectionSchedCode);
            }
            if (ayCodeInput) {
                ayCodeInput.addEventListener('input', updateSectionSchedCode); // Change to 'input' for immediate updates
            }
        });
    }
});
   

document.addEventListener('DOMContentLoaded', function () {
    // Handle the form submission
    document.getElementById('changeColorForm').addEventListener('submit', function (event) {
        event.preventDefault(); // Prevent default form submission

        var formData = new FormData(this);

        fetch('plotSchedule.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update the UI
                    document.querySelectorAll('.shaded-cell').forEach(function (cell) {
                        if (cell.dataset.sectionSchedCode === formData.get('section_sched_code')) {
                            cell.style.backgroundColor = data.color;
                        }
                    });
                    alert('Color updated successfully!');
                } else {
                    console.error('Error:', data.message);
                    alert('Error updating color!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating color!');
            });
    });
});



document.addEventListener('DOMContentLoaded', () => {
    const newRoomCodeInput = document.getElementById('new_room_code');
    const newProfCodeInput = document.getElementById('new_prof_code');
    const newCourseCodeInput = document.getElementById('new_course_code');
    const day = document.getElementById('day');
    const startTime = document.getElementById('time_start');
    const endTime = document.getElementById('time_end');
    const collegeCode = document.getElementById('college_code').value;
    const SecCollegeCode = document.getElementById('section_college_code').value;
    const cclCollegeCode = document.getElementById('ccl_college_code').value;
    const adminCollegeCode = document.getElementById('admin_college_code').value;

    console.log("Section College Code:", collegeCode);
    console.log("CCL College Code:", cclCollegeCode);


    const userType = document.getElementById('user_type').value;

    let lastClickedCell = null; // Store the last clicked cell
    const shadedCells = document.querySelectorAll('.shaded-cell');

    shadedCells.forEach(cell => {
        cell.addEventListener('click', () => {
            const cellDetails = JSON.parse(cell.getAttribute('data-details'));
            const userDeptCode = document.getElementById('user_dept_code').value;

            const userEmail = document.getElementById('user_email').value; // Get the user's email from the input

            const newRoomCodeSelect = document.getElementById('new_room_code');
            newRoomCodeSelect.style.display = 'block';

            const newroomCodeFiltered = document.getElementById('new_room_code_filtered');
            newroomCodeFiltered.style.display = 'none';

            // Function to show the access denied modal
            function showAccessDeniedModal(deptCode) {
                // Clear any existing list items
                var conflictList = document.getElementById('conflictList');
                conflictList.innerHTML = '';

                // Create a new list item with the access denied message
                var li = document.createElement('li');
                li.textContent = "You don't have permission to edit this schedule.";
                conflictList.appendChild(li);

                // Show the modal
                var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                myModal.show();
                resetForm();
                hideScheduleButtons();
            }

            if (cellDetails.dept_code === userDeptCode) {
                if (userType === 'CCL Head' && cellDetails.class_type === 'lec') {
                    showAccessDeniedModal(cellDetails.dept_code);
                    return;
                } else if (userType === 'CCL Head' && cellDetails.class_type === 'lab') {
                    if (cellDetails.allowed_rooms === 'lecR') {
                        showAccessDeniedModal(cellDetails.dept_code);
                        return;
                    } else {
                        proceedWithEditing(cell, cellDetails);
                    }
                } else {
                    proceedWithEditing(cell, cellDetails);
                }
            } else if (cellDetails.shared_to && cellDetails.shared_to === userEmail) {
                // Allow editing if shared_to matches user_email
                proceedWithEditing(cell, cellDetails);
                document.getElementById('deleteScheduleBtn').style.display = 'none';
                document.getElementById('shareScheduleBtn').style.display = 'none';
                // True indicates conversion to form
            }else if (userType === 'CCL Head' && cellDetails.class_type === 'lab' && cellDetails.computer_room === 1 ) {
                // Allow editing if shared_to matches user_email
                proceedWithEditing(cell, cellDetails);
                return;
                // True indicates conversion to form
            }else if (userType === 'CCL Head' && cellDetails.class_type === 'lec') {
                // Allow editing if shared_to matches user_email
                showAccessDeniedModal(cellDetails.dept_code);
                return;
                // True indicates conversion to form
            }else if (
                (userType === 'Department Secretary') &&
                cellDetails.class_type === 'lab' &&
                cclCollegeCode === collegeCode
            ){
                // Allow editing if shared_to matches user_email
                proceedWithEditing(cell, cellDetails);
                return;
                // True indicates conversion to form
            } else {
                showAccessDeniedModal(cellDetails.dept_code);
                return;
            }
        });
    });


    function proceedWithEditing(cell, cellDetails) {
        // Check if the clicked cell is the same as the last clicked cell
        if (lastClickedCell === cell) {
            // Reset filter and display original cell data
            resetFilter();
            displayDetails(cellDetails);
        } else {
            // Set the current cell as the last clicked cell
            lastClickedCell = cell;

            // Fetch all rooms and professors

            fetchAllProfessorsForDatalist(cellDetails.prof_code);

            fetchAllOldRoomsForDatalist(cellDetails.room_code);
            // Display the details (including room and prof codes) from the clicked cell
            displayDetails(cellDetails);


        }

        // Show the update and delete buttons
        document.getElementById('filter').style.display = 'none';

        if (collegeCode == SecCollegeCode) {
        document.getElementById('new_filter').style.display = 'inline';
        }else{
            document.getElementById('new_filter').style.display = 'none';
        }

        document.getElementById('updateScheduleBtn').style.display = 'inline';
        document.getElementById('deleteScheduleBtn').style.display = 'inline';
        // document.getElementById('shareScheduleBtn').style.display = 'inline';

        if(userType === "Department Chairperson"){
            document.getElementById('shareScheduleBtn').style.display = 'none';
        }else{
            document.getElementById('shareScheduleBtn').style.display = 'inline';

        }

        // if(collegeCode ===adminCollegeCode){
        //     document.getElementById('shareScheduleBtn').style.display = 'none';
        // }else{
        //  document.getElementById('shareScheduleBtn').style.display = 'inline';

        // }


        // Check the sec_sched_id to determine shared_sched type
        const sharedSchedType = cellDetails.shared_sched; // Adjust this field based on your data structure
        const sharedTo = document.getElementById('shared_to').value;
        const userEmail = document.getElementById('user_email').value;
        const classType = document.getElementById('class_type');
        // Set read-only properties based on shared_sched_type

        if (sharedTo && sharedSchedType && sharedTo === userEmail) {
            if (sharedSchedType === 'room') {
                // If shared by room, set new_room_code to editable and new_prof_code to read-only
                newRoomCodeInput.readOnly = false;
                newProfCodeInput.readOnly = true;
                newCourseCodeInput.readOnly = true;
                day.disabled = true;
                startTime.disabled = true;
                endTime.disabled = true;
                classType.disabled = true;

            } else if (sharedSchedType === 'prof') {
                // If shared by professor, set new_prof_code to editable and new_room_code to read-only
                newProfCodeInput.readOnly = false;
                newRoomCodeInput.readOnly = true;
                newCourseCodeInput.readOnly = true;
                day.disabled = true;
                startTime.disabled = true;
                endTime.disabled = true;
                classType.disabled = true;
            }
        } else if (sharedTo && sharedSchedType && sharedTo !== userEmail) {
            if (sharedSchedType === "room") {
                newProfCodeInput.readOnly = false;
                newCourseCodeInput.readOnly = true;
                newRoomCodeInput.readOnly = true;
                classType.disabled = true;

            }
            else if (sharedSchedType === "prof") {
                if ((userType === "Department Secretary" || userType === "Department Chairperson") && cellDetails.allowed_rooms === 'lecR') {
                    newRoomCodeInput.readOnly = false;
                } else {
                    newRoomCodeInput.readOnly = true;
                }
                newProfCodeInput.readOnly = true;
                newCourseCodeInput.readOnly = true;
                classType.disabled = true;
            }
            day.disabled = true;
            startTime.disabled = true;
            endTime.disabled = true;
            document.getElementById('deleteScheduleBtn').style.display = 'none';
        }
        else {
            if ((userType === "Department Secretary" || userType === "Department Chairperson") && cellDetails.class_type === 'lab' && (cclCollegeCode === collegeCode)) {
                if (cellDetails.computer_room === 1) {
                    newCourseCodeInput.readOnly = true;
                    day.disabled = true;
                    startTime.disabled = true;
                    endTime.disabled = true;
                    classType.disabled = true;
                    newProfCodeInput.readOnly = false;
                    newRoomCodeInput.readOnly = true;
                    document.getElementById('deleteScheduleBtn').style.display = 'none';
                    document.getElementById('shareScheduleBtn').style.display = 'none';
                }
            }  else if ((userType === "Department Secretary" || userType === "Department Chairperson") && cellDetails.class_type === 'lab' && (cclCollegeCode !== collegeCode) && empty(sharedTo) ) {
                if (cellDetails.computer_room === 1) {
                    newCourseCodeInput.readOnly = false;
                    day.disabled = false;
                    startTime.disabled = false;
                    endTime.disabled = false;
                    classType.disabled = false;
                    newProfCodeInput.readOnly = false;
                    newRoomCodeInput.readOnly = false;
                    document.getElementById('deleteScheduleBtn').style.display = 'inline';
                    document.getElementById('shareScheduleBtn').style.display = 'inline';
                }
            }else if (userType === "CCL Head" && cellDetails.class_type === 'lab') {
                if (cellDetails.computer_room === 1) {
                    newProfCodeInput.readOnly = true
                }
            } else {
                newProfCodeInput.readOnly = false;
                newRoomCodeInput.readOnly = false;
                newCourseCodeInput.readOnly = false;
                day.disabled = false;
                startTime.disabled = false;
                endTime.disabled = false;
                classType.disabled = false;
            }
        }

        if (sharedTo && sharedSchedType) {
            document.getElementById('shareScheduleBtn').style.display = 'none';
            document.getElementById('UnShareScheduleBtn').style.display = 'block';
            document.getElementById('updateScheduleBtn').style.display = 'block';
        }
        else {
            if(userType === "CCL Head"){
            document.getElementById('shareScheduleBtn').style.display = 'none';
            }
            document.getElementById('UnShareScheduleBtn').style.display = 'none';
            document.getElementById('updateScheduleBtn').style.display = 'block';
        }


    }
    const blankCells = document.querySelectorAll('.blankCells');


    blankCells.forEach(cell => {
        cell.addEventListener('click', () => {
            const day = cell.getAttribute('data-day');
            const timeStart = cell.getAttribute('data-start-time') || '07:00:00';
            const newCourseCodeInput = document.getElementById('course_code');
            newCourseCodeInput.readOnly = false;

            document.getElementById('day').value = day;
            document.getElementById('time_start').value = timeStart;
            document.getElementById('time_end').value = ''; // Clear time end

            // Reset form inputs and fetch room options
            resetForm();
            hideScheduleButtons();

            // Hide or show buttons and elements accordingly
            document.getElementById('new_filter').style.display = 'none';
            if (collegeCode == SecCollegeCode) {
                document.getElementById('filter').style.display = 'inline';
                }else{
                    document.getElementById('filter').style.display = 'none';
                }

          
            document.getElementById('shareScheduleBtn').style.display = 'none';
            document.getElementById('UnShareScheduleBtn').style.display = 'none';



            lastClickedCell = null; // Reset last clicked cell
        });
    });


    // Check if buttons should be visible based on PHP session
  
});

function resetFilter() {
    // Reset the filter inputs (room and professor dropdowns)
    document.getElementById('new_room_code').value = '';
    document.getElementById('new_prof_code').value = '';

}

function displayDetails(details) {
    document.getElementById('section_sched_code').value = details.section_sched_code;
    document.getElementById('sec_sched_id').value = details.sec_sched_id;
    document.getElementById('semester').value = details.semester;
    document.getElementById('day').value = details.day;
    document.getElementById('time_start').value = details.time_start;
    document.getElementById('time_end').value = details.time_end;
    document.getElementById('course_code').value = details.course_code;
    document.getElementById('new_course_code').value = details.course_code;
    document.getElementById('room_code').value = details.room_code;
    document.getElementById('prof_code').value = details.prof_code;
    document.getElementById('new_room_code').value = details.room_code;
    document.getElementById('new_prof_code').value = details.prof_code;
    document.getElementById('sched_dept_code').value = details.dept_code;
    document.getElementById('shared_to').value = details.shared_to;
    document.getElementById('shared_sched').value = details.shared_sched;
    document.getElementById('class_type').value = details.class_type;
    document.getElementById('allowed_rooms').value = details.allowed_rooms;
    document.getElementById('computer_room').value = details.computer_room;

    // Toggle the visibility of the input fields accordingly
    toggleRoomCodeInput();
    toggleProfessorCodeInput();

    // Hide course code input, show new course code input
    document.getElementById('course_code').style.display = 'none';
    document.getElementById('course_code_label').style.display = 'none';

    document.getElementById('new_course_code_label').style.display = 'inline';
    document.getElementById('new_course_code').style.display = 'inline';

    // Hide the plot schedule button
    document.getElementById('plotScheduleBtn').style.display = 'none';
}

function toggleRoomCodeInput() {
    const roomCodeLabel = document.getElementById('room_code_label');
    const roomCodeSelect = document.getElementById('room_code');
    const roomCodeFiltered = document.getElementById('room_code_filtered');

    const newRoomCodeLabel = document.getElementById('new_room_code_label');
    newRoomCodeLabel.style.display = 'block';



    // Show new room code label and select, hide room code label and select
    roomCodeLabel.style.display = 'none';
    roomCodeSelect.style.display = 'none';
    roomCodeFiltered.style.display = 'none';


}

function fetchAllOldRoomsForDatalist(selectedRoomCode = '') {
    const dept_code = document.getElementById('user_dept_code').value;
    const semester = document.getElementById('semester').value;
    const ay_code = document.getElementById('ay_code').value;
    const user_type =  document.getElementById('user_type').value;

    const xhr = new XMLHttpRequest();
    xhr.open('GET', `fetchAllRoom.php?fetch=old_rooms&dept_code=${dept_code}&semester=${semester}&ay_code=${ay_code}&user_type=${user_type}`, true);
    xhr.onload = function () {
        if (this.status === 200) {
            const options = this.responseText;
            populateOldRoomDatalist(options, selectedRoomCode);
        }
    };
    xhr.send();
}

function populateOldRoomDatalist(options, selectedRoomCode) {
    const roomDatalist = document.getElementById('old_rooms');
    roomDatalist.innerHTML = options; // Populate the datalist with options
}

function fetchAllRoomsForDatalist(selectedRoomCode = '') {
    const dept_code = document.getElementById('user_dept_code').value;
    const semester = document.getElementById('semester').value;
    const ay_code = document.getElementById('ay_code').value;
    const user_type =  document.getElementById('user_type').value;
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `fetchAllRoom.php?fetch=old_rooms&dept_code=${dept_code}&semester=${semester}&ay_code=${ay_code}&user_type=${user_type}`, true);
    xhr.onload = function () {
        if (this.status === 200) {
            const options = this.responseText;
            populateRoomDatalist(options, selectedRoomCode);
        }
    };
    xhr.send();
}

function populateRoomDatalist(options, selectedRoomCode) {
    const roomDatalist = document.getElementById('rooms');
    roomDatalist.innerHTML = options; // Populate the datalist with options
}


function toggleProfessorCodeInput() {
    const profCodeLabel = document.getElementById('prof_code_label');
    const newProfCodeLabel = document.getElementById('new_prof_code_label');
    const profCodeSelect = document.getElementById('prof_code');
    const newProfCodeSelect = document.getElementById('new_prof_code');

    // Show new professor code label and select, hide professor code label and select
    profCodeLabel.style.display = 'none';
    profCodeSelect.style.display = 'none';
    newProfCodeLabel.style.display = 'block';
    newProfCodeSelect.style.display = 'block';
}



////
function fetchAllProfessorsForDatalist(selectedProfCode = '') {
    const dept_code = document.getElementById('user_dept_code').value;
    const semester = document.getElementById('semester').value;
    const ay_code = document.getElementById('ay_code').value;
   
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `fetchAllProf.php?fetch=professors&dept_code=${dept_code}&semester=${semester}&ay_code=${ay_code}`, true);
    xhr.onload = function () {
        if (this.status === 200) {
            const options = this.responseText;
            populateProfessorDatalist(options, selectedProfCode);
        }
    };
    xhr.send();
}

function populateProfessorDatalist(options, selectedProfCode) {
    const profDatalist = document.getElementById('professors');
    profDatalist.innerHTML = options; // Populate the datalist with options

}
/////

function fetchAllOldProfessorsForDatalist(selectedProfCode = '') {
    const dept_code = document.getElementById('user_dept_code').value;
    const semester = document.getElementById('semester').value;
    const ay_code = document.getElementById('ay_code').value;
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `fetchAllProf.php?fetch=old_professors&dept_code=${dept_code}&semester=${semester}&ay_code=${ay_code}`, true);
    xhr.onload = function () {
        if (this.status === 200) {
            const options = this.responseText;
            populateOldProfessorDatalist(options, selectedProfCode);
        }
    };
    xhr.send();
}

function populateOldProfessorDatalist(options, selectedProfCode) {
    const profDatalist = document.getElementById('old_professors');
    profDatalist.innerHTML = options; // Populate the datalist with options

}


/////
function resetForm() {
    const day = document.getElementById('day');
    const startTime = document.getElementById('time_start');
    const endTime = document.getElementById('time_end');
    const classType = document.getElementById('class_type');
    const userType = document.getElementById('user_type').value;

    day.disabled = false;
    startTime.disabled = false;
    endTime.disabled = false;
    classType.disabled = false;
    document.getElementById('course_code').value = '';
    document.getElementById('new_course_code').value = '';
    document.getElementById('room_code').value = '';
    document.getElementById('prof_code').value = '';
    classType.value = ' ';

    resetRoomCodeInput();
    resetProfessorCodeInput();
    fetchAllOldRoomsForDatalist();
    const classTypeSelect = document.getElementById('class_type');
    const lecOption = classTypeSelect.querySelector('option[value="lec"]');
    const labOption = classTypeSelect.querySelector('option[value="lab"]');
    const notAvailable = classTypeSelect.querySelector('option[value="n/a"]');

    if (userType === 'CCL Head') {
        lecOption.style.display = 'none';
        labOption.style.display = 'block';
        classType.value = 'lab';
        notAvailable.style.display = 'none';
    } else if (userType === 'Department Secretary' || userType === "Department Chairperson") {
        lecOption.style.display = 'block';
        labOption.style.display = 'block';
        notAvailable.style.display = 'none';
    }

}

function hideScheduleButtons() {
    document.getElementById('updateScheduleBtn').style.display = 'none';
    document.getElementById('deleteScheduleBtn').style.display = 'none';
    document.getElementById('plotScheduleBtn').style.display = 'inline';
    document.getElementById('shareScheduleBtn').style.display = 'none';
    document.getElementById('UnShareScheduleBtn').style.display = 'none';
    document.getElementById('new_course_code').style.display = 'none';
    document.getElementById('new_course_code_label').style.display = 'none';
    document.getElementById('course_code').style.display = 'inline';
    document.getElementById('course_code_label').style.display = 'inline';
}

function resetRoomCodeInput() {
    const roomCodeLabel = document.getElementById('room_code_label');
    const newRoomCodeLabel = document.getElementById('new_room_code_label');
    const roomCodeSelect = document.getElementById('room_code');
    const newRoomCodeSelect = document.getElementById('new_room_code');
    const newRoomCodeFiltered = document.getElementById('new_room_code_filtered');
    const RoomCodeFiltered = document.getElementById('room_code_filtered');

    roomCodeLabel.style.display = 'block';
    roomCodeSelect.style.display = 'block';
    newRoomCodeLabel.style.display = 'none';
    newRoomCodeSelect.style.display = 'none';
    newRoomCodeFiltered.style.display = 'none';
    RoomCodeFiltered.style.display = 'none';
    RoomCodeFiltered.disabled = true;
}

function resetProfessorCodeInput() {
    const profCodeLabel = document.getElementById('prof_code_label');
    const newProfCodeLabel = document.getElementById('new_prof_code_label');
    const profCodeSelect = document.getElementById('prof_code');
    const newProfCodeSelect = document.getElementById('new_prof_code');

    profCodeLabel.style.display = 'block';
    profCodeSelect.style.display = 'block';
    newProfCodeLabel.style.display = 'none';
    newProfCodeSelect.style.display = 'none';
}


