
//for the menu and sidebar - wag alisin
function toggleDropdown(element) {
    element.classList.toggle("change");
    document.querySelector('.sidebar').classList.toggle('active');
}

function toggleDropdownContent(event) {
    event.preventDefault();
    const dropdownContent = event.target.nextElementSibling;
    if (dropdownContent) {
        dropdownContent.classList.toggle('show');
    }
}

window.onclick = function (event) {
    if (!event.target.closest('.hamburger-menu') && !event.target.closest('.sidebar')) {
        var sidebar = document.querySelector('.sidebar');
        if (sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }

        var hamburger = document.querySelector('.hamburger-menu');
        if (hamburger.classList.contains('change')) {
            hamburger.classList.remove('change');
        }

        var dropdowns = document.querySelectorAll('.dropdown-content.show');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
}
   //////
    function updateSectionSchedCode() {
        const sectionCode = document.getElementById('modal_section_code').value;
        const ayCode = document.getElementById('ay_code').value;
        const sectionSchedCode = sectionCode.replace('-', '_') + '_' + ayCode;
        document.getElementById('modal_section_sched_code').value = sectionSchedCode;
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('modal_section_code').addEventListener('change', updateSectionSchedCode);
        document.getElementById('ay_code').addEventListener('change', updateSectionSchedCode);
    });

//////


function displayDetails(details) {
    document.getElementById('section_sched_code').value = details.section_sched_code;
    document.getElementById('sec_sched_id').value = details.sec_sched_id;
    document.getElementById('semester').value = details.semester;
    document.getElementById('day').value = details.day;
    document.getElementById('time_start').value = details.time_start;
    document.getElementById('time_end').value = details.time_end;
    document.getElementById('course_code').value = details.course_code;
    document.getElementById('new_room_code').value = details.room_code;
    document.getElementById('new_prof_code').value = details.prof_code;

    toggleRoomCodeInput(details.room_code);
    toggleProfessorCodeInput(details.prof_code);

    // Show the update and delete buttons
    document.getElementById('updateScheduleBtn').style.display = 'inline';
    document.getElementById('deleteScheduleBtn').style.display = 'inline';

    // Hide the plot schedule button
    document.getElementById('plotScheduleBtn').style.display = 'none';
}

function toggleRoomCodeInput(roomCode) {
    const roomCodeLabel = document.getElementById('room_code_label');
    const newRoomCodeLabel = document.getElementById('new_room_code_label');
    const roomCodeSelect = document.getElementById('room_code');
    const newRoomCodeSelect = document.getElementById('new_room_code');

    // Set the room code select's value based on the provided room code
    roomCodeSelect.value = roomCode;

    // Show new room code label and select, hide room code label and select
    roomCodeLabel.style.display = 'none';
    roomCodeSelect.style.display = 'none';

    newRoomCodeLabel.style.display = 'block';
    newRoomCodeSelect.style.display = 'block';
}

function toggleProfessorCodeInput(profCode) {
    const profCodeLabel = document.getElementById('prof_code_label');
    const newProfCodeLabel = document.getElementById('new_prof_code_label');
    const profCodeSelect = document.getElementById('prof_code');
    const newProfCodeSelect = document.getElementById('new_prof_code');

    // Set the professor code select's value based on the provided professor code
    profCodeSelect.value = profCode;

    // Show new professor code label and select, hide professor code label and select
    profCodeLabel.style.display = 'none';
    profCodeSelect.style.display = 'none';

    newProfCodeLabel.style.display = 'block';
    newProfCodeSelect.style.display = 'block';
}

function hideScheduleButtons() {
    document.getElementById('updateScheduleBtn').style.display = 'none';
    document.getElementById('deleteScheduleBtn').style.display = 'none';
    document.getElementById('plotScheduleBtn').style.display = 'inline';
}

function resetRoomCodeInput() {
    const roomCodeLabel = document.getElementById('room_code_label');
    const newRoomCodeLabel = document.getElementById('new_room_code_label');
    const roomCodeSelect = document.getElementById('room_code');
    const newRoomCodeSelect = document.getElementById('new_room_code');

    // Show room code label and select, hide new room code label and select
    roomCodeLabel.style.display = 'block';
    roomCodeSelect.style.display = 'block';

    newRoomCodeLabel.style.display = 'none';
    newRoomCodeSelect.style.display = 'none';
}

function resetProfessorCodeInput() {
    const profCodeLabel = document.getElementById('prof_code_label');
    const newProfCodeLabel = document.getElementById('new_prof_code_label');
    const profCodeSelect = document.getElementById('prof_code');
    const newProfCodeSelect = document.getElementById('new_prof_code');

    // Show professor code label and select, hide new professor code label and select
    profCodeLabel.style.display = 'block';
    profCodeSelect.style.display = 'block';

    newProfCodeLabel.style.display = 'none';
    newProfCodeSelect.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const shadedCells = document.querySelectorAll('.shaded-cell');

    shadedCells.forEach(cell => {
        cell.addEventListener('click', () => {
            const cellDetails = JSON.parse(cell.getAttribute('data-details'));
            displayDetails(cellDetails);

            // Show the update and delete buttons when a shaded cell is clicked
            document.getElementById('updateScheduleBtn').style.display = 'inline';
            document.getElementById('deleteScheduleBtn').style.display = 'inline';

            // Hide the plot schedule button
            document.getElementById('plotScheduleBtn').style.display = 'none';
        });
    });

    const blankCells = document.querySelectorAll('td:not(.shaded-cell)');

    blankCells.forEach(cell => {
        cell.addEventListener('click', () => {
            const day = cell.getAttribute('data-day');
            const timeStart = cell.getAttribute('data-start-time');

            document.getElementById('day').value = day;
            document.getElementById('time_start').value = timeStart;
            document.getElementById('time_end').value = ''; // Clear time end

            // Reset room code and professor code inputs
            resetRoomCodeInput();
            resetProfessorCodeInput();
            hideScheduleButtons();
        });
    });

    // Initially hide the update and delete buttons
    document.getElementById('updateScheduleBtn').style.display = 'none';
    document.getElementById('deleteScheduleBtn').style.display = 'none';
});
