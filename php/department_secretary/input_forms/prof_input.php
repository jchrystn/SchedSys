<?php
session_start();
include("../../config.php");

// Ensure the user is logged in as a Department Secretary
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'Department Chairperson') {
  header("Location: ../../login/login.php");
  exit();
}

if (empty($_SESSION['token'])) {
  $_SESSION['token'] = bin2hex(random_bytes(32));
}


$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$dept_code = $_SESSION['dept_code'];
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';

if (isset($_POST['set_session']) && $_POST['set_session'] === 'true' && isset($_POST['prof_unit'])) {
  $_SESSION['program_unit'] = $_POST['prof_unit']; // Set the session variable
}

// Now you can access the session value in the rest of the script
if (isset($_SESSION['program_unit'])) {
  $program_unit = $_SESSION['program_unit'];
  // Use $program_unit for your queries or other logic
} else {
  // echo "Program unit session is not set.";
}


$fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
  $row = $result->fetch_assoc();
  $ay_code = $row['ay_code'];
  $ay_name = $row['ay_name'];
  $semester = $row['semester'];

  // Store ay_code and semester in the session
  $_SESSION['ay_code'] = $ay_code;
  $_SESSION['semester'] = $semester;
}

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
  $row_col = $result_col->fetch_assoc();
  $admin_college_code = $row_col['college_code'];
}

$fetch_info_query = "SELECT reg_adviser, college_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
  $row = $result_reg->fetch_assoc();
  $not_reg_adviser = $row['reg_adviser'];
  $user_college_code = $row['college_code'];

  if ($not_reg_adviser == 1) {
    $current_user_type = "Registration Adviser";
  } else {
    $current_user_type = $user_type = $_SESSION['user_type'];
  }
}

// echo "$current_user_type";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Validate token
  if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
  }

  // Regenerate token to prevent reuse
  $_SESSION['token'] = bin2hex(random_bytes(32));

  // Sanitize inputs
  $profCode = $conn->real_escape_string(trim($_POST['prof_code']));
  $profType = $conn->real_escape_string(trim($_POST['prof_type']));
  $academicRank = $conn->real_escape_string(trim($_POST['academic_rank']));
  $lastName = ucfirst(strtolower($conn->real_escape_string(trim($_POST['prof_name']))));
  $employStatus = isset($_POST['employ_status']) && $_POST['employ_status'] == '1' ? 1 : 0;

  // Red Adviser values
  $reg_adviser = isset($_POST['reg_adviser']) ? $_POST['reg_adviser'] : 0;
  if (isset($_POST['section_code'])) {
    if (is_array($_POST['section_code'])) {
      $sectionCodes = array_map('trim', $_POST['section_code']); // Already an array
    } else {
      $sectionCodes = array_map('trim', explode(',', $_POST['section_code'])); // Comma-separated string
    }
  } else {
    $sectionCodes = [];
  }
  $active_ay_code = $conn->real_escape_string($_SESSION['active_ay_code'] ?? $ay_code);

  // Session values
  $deptCode = $conn->real_escape_string($_SESSION['dept_code'] ?? '');
  $profUnit = $conn->real_escape_string($_SESSION['program_unit'] ?? '');
  $semester = $conn->real_escape_string($_SESSION['semester'] ?? '');
  $ay_code = $conn->real_escape_string($_SESSION['ay_code'] ?? '');
  $college_code = $conn->real_escape_string($_SESSION['college_code'] ?? '');

  $oldProfCode = $profCode;

  // Check if the last name exists in tbl_prof_acc
  $nameQuery = "SELECT first_name, middle_initial, suffix, last_name 
                FROM tbl_prof_acc 
                WHERE last_name = '$lastName' 
                  AND dept_code = '$deptCode' 
                  AND prof_unit = '$profUnit' 
                  AND semester = '$semester' 
                  AND ay_code = '$ay_code'";
  $nameResult = $conn->query($nameQuery);

  if ($nameResult && $nameResult->num_rows === 0) {
    $_SESSION['modal_message'] = "No professor name exists in the current department and academic period.";
    header("Location:prof_input.php");
    exit;
  }

  $nameRow = $nameResult->fetch_assoc();
  $firstName = ucfirst(strtolower($nameRow['first_name']));
  $middleInitial = $nameRow['middle_initial'];
  $suffix = $nameRow['suffix'];
  $lastName = ucfirst(strtolower($nameRow['last_name']));

  $oldFullNameQuery = "SELECT prof_name FROM tbl_prof 
                       WHERE prof_code = '$profCode' 
                         AND dept_code = '$deptCode' 
                         AND prof_unit = '$profUnit' 
                         AND semester = '$semester' 
                         AND ay_code = '$ay_code'";
  $oldFullNameResult = $conn->query($oldFullNameQuery);

  if ($oldFullNameResult && $oldFullNameResult->num_rows > 0) {
    $oldFullName = $oldFullNameResult->fetch_assoc()['prof_name'];
  }

  // Construct full name
  $fullName = trim("$firstName $middleInitial $lastName $suffix");

  if ($oldFullName !== $fullName) {
    $checkProfQuery = "SELECT prof_name FROM tbl_prof 
                       WHERE prof_name = '$fullName' 
                         AND dept_code = '$deptCode' 
                         AND prof_unit = '$profUnit' 
                         AND semester = '$semester' 
                         AND ay_code = '$ay_code'";
    $checkProfResult = $conn->query($checkProfQuery);

    if ($checkProfResult && $checkProfResult->num_rows > 0) {
      echo '
        <script type="text/javascript">
            window.onload = function() {
                $("#professorExistModal").modal("show");
            }
        </script>';
    } else {
      if ($profType === 'Regular') {
        $employStatus = 2;

        // Generate new prof_code
        $firstInitial = !empty($firstName) ? strtoupper(substr($firstName, 0, 1)) : '';
        $middleInitial = !empty($middleInitial) ? strtoupper(substr($middleInitial, 0, 1)) : '';
        $lastPart = !empty($lastName) ? ucfirst($lastName) : '';
        $updatedProfCode = "$firstInitial$middleInitial$lastPart";

       // Update professor details
$updateProfQuery = "UPDATE tbl_prof 
                    SET prof_code = '$updatedProfCode', 
                        prof_type = 'Regular', 
                        employ_status = $employStatus, 
                        reg_adviser = $reg_adviser, 
                        prof_name = '$fullName',
                        academic_rank = '$academicRank'
                    WHERE prof_code = '$oldProfCode' 
                      AND dept_code = '$deptCode' 
                      AND semester = '$semester' 
                      AND ay_code = '$ay_code' 
                      AND prof_unit = '$profUnit'";

if ($conn->query($updateProfQuery) === TRUE) {

if (isset($_POST['reg_adviser'])) {
    $submittedSections = $_POST['section_code'] ?? [];

    foreach ($submittedSections as $section_code) {
        // Check if the section is already assigned to this adviser in this AY
        $check = $conn->query("
            SELECT * FROM tbl_registration_adviser 
            WHERE section_code = '$section_code' 
              AND dept_code = '$deptCode'
              AND current_ay_code = '$ay_code'
        ");

        // If not yet assigned, insert the new assignment
        if (!$check || $check->num_rows == 0) {
            // Get the curriculum of the section
            $currResult = $conn->query("
                SELECT curriculum FROM tbl_section 
                WHERE section_code = '$section_code' 
                  AND dept_code = '$deptCode'
            ");

            if ($currResult->num_rows > 0) {
                $curriculum = $currResult->fetch_assoc()['curriculum'];

                // Derive the program_code from section_code (assumes space-delimited)
                $program_code = explode(' ', $section_code)[0];

                // Get the number of years for the program
                $yearQuery = "
                    SELECT num_year FROM tbl_program 
                    WHERE program_code = '$program_code' 
                      AND dept_code = '$deptCode' 
                      AND curriculum = '$curriculum'
                ";
                $yearResult = $conn->query($yearQuery);
                $num_year = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc()['num_year'] : null;

                // Insert new registration adviser assignment
                $insert = "
                    INSERT INTO tbl_registration_adviser 
                        (reg_adviser, dept_code, section_code, ay_code_assign, current_ay_code, num_year)
                    VALUES 
                        ('$fullName', '$deptCode', '$section_code', '$ay_code', '$ay_code', '$num_year')
                ";
                $conn->query($insert);
            }
        }

        // Ensure the professor is marked as a registration adviser in both tables
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 1 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 1 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // If no sections were submitted, unset the adviser flag
    if (empty($submittedSections)) {
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 0 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 0 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // Show success modal
    echo '
    <script>
        window.onload = function() {
            $("#sectionSuccessModal").modal("show");
        }
    </script>
    ';

    // Show errors in modal (only if blocked deletions occurred)
    // if (!empty($deletionErrors)) {
    //     $errorListHTML = "<ul>";
    //     foreach ($deletionErrors as $errorMsg) {
    //         $errorListHTML .= "<li>$errorMsg</li>";
    //     }
    //     $errorListHTML .= "</ul>";

    //     echo "
    //     <script>
    //         document.addEventListener('DOMContentLoaded', function () {
    //             document.getElementById('sectionDeleteErrorMessage').innerHTML = `$errorListHTML`;
    //             new bootstrap.Modal(document.getElementById('sectionDeleteErrorModal')).show();
    //         });
    //     </script>
    //     ";
    // }
}





          $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $deptCode . "_" . $ay_code);
          $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
          $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
          $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
          $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");

          // echo "<br>You are here now<br>";
          // Construct the new and old prof_sched_code
          $NewProfSchedCode = $updatedProfCode . "_" . $ay_code;
          $oldProfSchedCode = $profCode . "_" . $ay_code;

          // Prepared statement for fetching teaching_hrs
          $fetchTeachingHoursQuery = $conn->prepare("SELECT teaching_hrs FROM tbl_psched_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $fetchTeachingHoursQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
          $fetchTeachingHoursQuery->execute();
          $teachingHoursResult = $fetchTeachingHoursQuery->get_result();

          if ($teachingHoursResult && $teachingHoursResult->num_rows > 0) {
            $row = $teachingHoursResult->fetch_assoc();
            $teaching_hours = $row['teaching_hrs'];
            // echo "Teaching Hours: $teaching_hours<br>";

            $consultation_hrs = $teaching_hours / 3;
            // echo "Calculated Consultation Hours: $consultation_hrs<br>";

            // Prepare the query for updating consultation_hrs
            $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
            $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

            if ($updateConsultationHrsQuery->execute()) {
              // echo "Consultation hours updated successfully.";
            } else {
              echo "Error updating consultation hours: " . $conn->error;
            }

            // Prepared statement for updating consultation_hrs
            $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
            $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
            if ($updateConsultationHrsQuery->execute()) {
              //           echo '
              //  <script type="text/javascript">
              //      window.onload = function() {
              //          // Show the modal after successful update
              //          $("#updateSuccessModal").modal("show");
              //      }
              //  </script>
              //  ';
            } else {
              echo "Error updating consultation hours: " . $conn->error;
            }
            // Update consultation_hrs in tbl_pcontact_counter
            $updatePcontactConsultationHrsQuery = $conn->prepare("
UPDATE tbl_pcontact_counter 
SET consultation_hrs = ? 
WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
            $updatePcontactConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

            if ($updatePcontactConsultationHrsQuery->execute()) {
              // Fetch the updated consultation_hrs and current_consultation_hrs from tbl_pcontact_counter
              $fetchPcontactConsultationHrsQuery = $conn->prepare("
   SELECT consultation_hrs, current_consultation_hrs 
   FROM tbl_pcontact_counter 
   WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
              $fetchPcontactConsultationHrsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
              $fetchPcontactConsultationHrsQuery->execute();
              $pcontactResult = $fetchPcontactConsultationHrsQuery->get_result();

              if ($pcontactResult && $pcontactResult->num_rows > 0) {
                $row = $pcontactResult->fetch_assoc();
                $updatedConsultationHrs = $row['consultation_hrs'];
                $currentConsultationHrs = $row['current_consultation_hrs'];

                // Check if updated consultation_hrs is less than current_consultation_hrs
                if ($updatedConsultationHrs < $currentConsultationHrs) {
                  // Delete records from $sanitized_pcontact_sched_code
                  $deletePcontactSchedQuery = $conn->prepare("
           DELETE FROM $sanitized_pcontact_sched_code 
           WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
       ");
                  $deletePcontactSchedQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);

                  if ($deletePcontactSchedQuery->execute()) {
                    // Check if there are still records in $sanitized_pcontact_sched_code
                    $checkRemainingRecordsQuery = $conn->prepare("
               SELECT COUNT(*) AS record_count 
               FROM $sanitized_pcontact_sched_code 
               WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
           ");
                    $checkRemainingRecordsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                    $checkRemainingRecordsQuery->execute();
                    $remainingRecordsResult = $checkRemainingRecordsQuery->get_result();

                    if ($remainingRecordsResult && $remainingRecordsResult->num_rows > 0) {
                      $recordCountRow = $remainingRecordsResult->fetch_assoc();
                      $recordCount = $recordCountRow['record_count'];

                      if ($recordCount == 0) {
                        // Delete records from tbl_pcontact_schedstatus
                        $deletePcontactSchedStatusQuery = $conn->prepare("
                       DELETE FROM tbl_pcontact_schedstatus 
                       WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                   ");
                        $deletePcontactSchedStatusQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                        $deletePcontactSchedStatusQuery->execute();

                        // Delete records from tbl_pcontact_counter
                        $deletePcontactCounterQuery = $conn->prepare("
                       DELETE FROM tbl_pcontact_counter 
                       WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                   ");
                        $deletePcontactCounterQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                        $deletePcontactCounterQuery->execute();

                        //       echo '
                        //  <script type="text/javascript">
                        //      window.onload = function() {
                        //          $("#deleteAllSuccessModal").modal("show");
                        //      }
                        //  </script>
                        //  ';
                      }
                    }
                  } else {
                    echo "Error deleting schedule: " . $conn->error;
                  }
                }
              } else {
                // echo "No data found in tbl_pcontact_counter for the specified criteria.";
              }
            } else {
              echo "Error updating consultation hours in tbl_pcontact_counter: " . $conn->error;
            }
          }

          $tablesToUpdateWithName = [
            "tbl_prof_acc"
          ];

          foreach ($tablesToUpdateWithName as $table) {
            // Use prepared statements to avoid SQL injection
            $updateTableQuery = "UPDATE $table 
     SET prof_code = ? 
     WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

            $stmt = $conn->prepare($updateTableQuery);
            $stmt->bind_param("sssss", $updatedProfCode, $lastName, $deptCode, $semester, $ay_code);
            $stmt->execute();
            $stmt->close();
          }

          // Update related tables that store the prof_code
          $tablesToUpdate = [
            "tbl_psched_counter",
            "tbl_pcontact_counter",
            "tbl_pcontact_schedstatus",
            "tbl_psched",
            $sanitized_prof_sched_code,
            $sanitized_pcontact_sched_code
          ];

          foreach ($tablesToUpdate as $table) {
            $updateTableQuery = "UPDATE $table 
                       SET prof_code = '$updatedProfCode', prof_sched_code = '$NewProfSchedCode'
                       WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";

            if (!$conn->query($updateTableQuery)) {
              // Check if the error is a duplicate entry
              if ($conn->errno == 1062) {
                // Skip this table if there's a duplicate entry
                continue;
              }
              // If it's any other error, display it
              echo "Error updating table $table: " . $conn->error;
            }
          }


          $tablesToUpdateWithSchedCode = [
            "tbl_assigned_course",
            $sanitized_room_sched_code,
            $sanitized_section_sched_code,
            $sanitized_ccl_room_sched_code
          ];

          foreach ($tablesToUpdateWithSchedCode as $table) {
            $updateTableQuery = "UPDATE $table 
   SET prof_code = '$updatedProfCode', prof_name = '$fullName'
   WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
            if (!$conn->query($updateTableQuery)) {
              echo "Error updating table $table: " . $conn->error;
            }
          }

          $tablesToUpdateWithoutProfCode = [
            "tbl_prof_schedstatus"
          ];

          foreach ($tablesToUpdateWithoutProfCode as $table) {
            $updateTableQuery = "UPDATE $table 
     SET  prof_sched_code = '$NewProfSchedCode'
     WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
            $conn->query($updateTableQuery);
          }

          // Step 1: Fetch all part-time Job Order Instructor
          $reNumberPartTimeQuery = "
                                SELECT prof_code 
                                FROM tbl_prof 
                                WHERE prof_type = 'Job Order' 
                                  AND employ_status = 1 
                                  AND dept_code = ? 
                                  AND semester = ? 
                                  AND ay_code = ? 
                                  AND prof_unit = ?
                                ORDER BY 
                                  CAST(SUBSTRING(prof_code, LOCATE('PT ', prof_code) + 3, 
                                  LOCATE(' -', prof_code) - LOCATE('PT ', prof_code) - 3) AS UNSIGNED)";
          $stmt = $conn->prepare($reNumberPartTimeQuery);
          $stmt->bind_param("ssss", $deptCode, $semester, $ay_code, $profUnit);
          $stmt->execute();
          $partTimeResult = $stmt->get_result();

          // Step 2: Store results in an array
          $profCodes = [];
          while ($row = $partTimeResult->fetch_assoc()) {
            $profCodes[] = $row['prof_code'];
          }
          $stmt->close();
          $counter = 1;

          foreach ($profCodes as $profCode) {
            // Generate new prof_code
            $newProfCode = "$profUnit PT $counter - " . substr($profCode, strpos($profCode, '-') + 2);

            // Prepare the update query to avoid SQL injection
            $updatePartTimeQuery = "
           UPDATE tbl_prof 
           SET prof_code = ? 
           WHERE prof_code = ? 
             AND dept_code = ? 
             AND semester = ? 
             AND ay_code = ?
             AND prof_unit = ?";

            $updateStmt = $conn->prepare($updatePartTimeQuery);
            $updateStmt->bind_param("ssssss", $newProfCode, $profCode, $deptCode, $semester, $ay_code, $profUnit);

            if ($updateStmt->execute() === TRUE) {
              // Sanitize and create the necessary schedule table names
              $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $deptCode . "_" . $ay_code);
              $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
              $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
              $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
              $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");

              // Construct the new and old prof_sched_code
              $NewProfSchedCode = $newProfCode . "_" . $ay_code;
              $oldProfSchedCode = $profCode . "_" . $ay_code;

              $tablesToUpdateWithName = [
                "tbl_prof_acc"
              ];

              foreach ($tablesToUpdateWithName as $table) {
                // Use prepared statements to avoid SQL injection
                $updateTableQuery = "UPDATE $table 
   SET prof_code = ? 
   WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

                $stmt = $conn->prepare($updateTableQuery);
                $stmt->bind_param("sssss", $newProfCode, $lastName, $deptCode, $semester, $ay_code);
                $stmt->execute();
                $stmt->close();
              }

              // Update related tables that store the prof_code
              $tablesToUpdate = [
                "tbl_psched_counter",
                "tbl_pcontact_counter",
                "tbl_pcontact_schedstatus",
                "tbl_psched",
                $sanitized_prof_sched_code,
                $sanitized_pcontact_sched_code
              ];

              foreach ($tablesToUpdate as $table) {
                $updateTableQuery = "UPDATE $table 
                             SET prof_code = '$newProfCode', prof_sched_code = '$NewProfSchedCode'
                             WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                if (!$conn->query($updateTableQuery)) {
                  echo "Error updating table $table: " . $conn->error;
                }
              }

              $tablesToUpdateWithSchedCode = [
                "tbl_assigned_course",
                $sanitized_room_sched_code,
                $sanitized_section_sched_code,
                $sanitized_ccl_room_sched_code
              ];

              foreach ($tablesToUpdateWithSchedCode as $table) {
                $updateTableQuery = "UPDATE $table 
 SET prof_code = '$newProfCode', prof_name = '$fullName' 
 WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                if (!$conn->query($updateTableQuery)) {
                  echo "Error updating table $table: " . $conn->error;
                }
              }

              $tablesToUpdateWithoutProfCode = [
                "tbl_prof_schedstatus"
              ];

              foreach ($tablesToUpdateWithoutProfCode as $table) {
                $updateTableQuery = "UPDATE $table 
   SET  prof_sched_code = '$NewProfSchedCode'
   WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                $conn->query($updateTableQuery);
              }
            } else {
              echo "Error updating prof_code in tbl_prof: " . $conn->error;
            }
            $counter++;
          }

          $reNumberQuery = "SELECT prof_code 
        FROM tbl_prof 
        WHERE prof_type = 'Job Order' AND employ_status = 3
        AND dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit= ?
        ORDER BY CAST(SUBSTRING(prof_code, LOCATE(' ', prof_code) + 1, LOCATE(' -', prof_code) - LOCATE(' ', prof_code) - 1) AS UNSIGNED)";

          $stmt = $conn->prepare($reNumberQuery);
          $stmt->bind_param("ssss", $deptCode, $semester, $ay_code, $profUnit);
          $stmt->execute();
          $result = $stmt->get_result();

          if (!$result) {
            die("Error with reNumberQuery: " . $conn->error);
          }

          $counter = 1; // Start numbering from 1

          while ($row = $result->fetch_assoc()) {
            $profCode = $row['prof_code'];

            // Validate prof_code format
            if (strpos($profCode, '-') === false || strpos($profCode, ' ') === false) {
              // echo "Invalid prof_code format: $profCode<br>";
              continue;
            }

            // Generate new prof_code for Job Order
            $newProfCode = "$profUnit $counter - " . substr($profCode, strpos($profCode, '-') + 2);

            // Update tbl_prof
            $updateJobOrderQuery = "UPDATE tbl_prof 
                  SET prof_code = ? 
                  WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit = ?";
            $stmt = $conn->prepare($updateJobOrderQuery);
            $stmt->bind_param("ssssss", $newProfCode, $profCode, $deptCode, $semester, $ay_code, $profUnit);

            if ($stmt->execute()) {
              // Sanitize and create the necessary schedule table names
              $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $deptCode . "_" . $ay_code);
              $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
              $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
              $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
              $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");

              // Construct the new and old prof_sched_code
              $NewProfSchedCode = $newProfCode . "_" . $ay_code;
              $oldProfSchedCode = $profCode . "_" . $ay_code;

              $tablesToUpdateWithName = [
                "tbl_prof_acc"
              ];

              foreach ($tablesToUpdateWithName as $table) {
                // Use prepared statements to avoid SQL injection
                $updateTableQuery = "UPDATE $table 
SET prof_code = ? 
WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

                $stmt = $conn->prepare($updateTableQuery);
                $stmt->bind_param("sssss", $newProfCode, $lastName, $deptCode, $semester, $ay_code);
                $stmt->execute();
                $stmt->close();
              }

              // Update related tables that store the prof_code
              $tablesToUpdate = [
                "tbl_psched_counter",
                "tbl_pcontact_counter",
                "tbl_pcontact_schedstatus",
                "tbl_psched",
                $sanitized_prof_sched_code,
                $sanitized_pcontact_sched_code
              ];

              foreach ($tablesToUpdate as $table) {
                $updateTableQuery = "UPDATE $table 
                        SET prof_code = '$newProfCode', prof_sched_code = '$NewProfSchedCode'
                        WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                if (!$conn->query($updateTableQuery)) {
                  echo "Error updating table $table: " . $conn->error;
                }
              }

              $tablesToUpdateWithSchedCode = [
                "tbl_assigned_course",
                $sanitized_room_sched_code,
                $sanitized_section_sched_code,
                $sanitized_ccl_room_sched_code
              ];

              foreach ($tablesToUpdateWithSchedCode as $table) {
                $updateTableQuery = "UPDATE $table 
SET prof_code = '$newProfCode', prof_name = '$fullName' 
WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                if (!$conn->query($updateTableQuery)) {
                  echo "Error updating table $table: " . $conn->error;
                }
              }

              $tablesToUpdateWithoutProfCode = [
                "tbl_prof_schedstatus"
              ];

              foreach ($tablesToUpdateWithoutProfCode as $table) {
                $updateTableQuery = "UPDATE $table 
SET  prof_sched_code = '$NewProfSchedCode'
WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                $conn->query($updateTableQuery);
              }
            } else {
              echo "Error updating prof_code in tbl_prof: " . $conn->error;
            }
            $counter++;
          }
        }
      }


      if ($employStatus === 1) {
        $ptQuery = "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(prof_code, 'PT ', -1), ' - ', 1) AS UNSIGNED)) AS max_number 
      FROM tbl_prof 
      WHERE prof_code LIKE '$profUnit PT %' AND dept_code = '$deptCode'";
        $ptResult = $conn->query($ptQuery);
        $newNumber = $ptResult && $ptResult->num_rows > 0 ? (int) $ptResult->fetch_assoc()['max_number'] + 1 : 1;

        $updatedProfCode = "$profUnit PT $newNumber - $lastName";

        // Step 4: Update the selected professor's details
        $updateProfQuery = "
            UPDATE tbl_prof 
            SET prof_code = '$updatedProfCode', 
                prof_type = 'Job Order', 
                employ_status = 1, 
                reg_adviser = $reg_adviser, 
                academic_rank = '$academicRank', 
                prof_name = '$fullName' 
            WHERE prof_code = '$oldProfCode' 
              AND dept_code = '$deptCode' 
              AND semester = '$semester' 
              AND ay_code = '$ay_code'
              AND prof_unit = '$profUnit'";

        if ($conn->query($updateProfQuery) === TRUE) {

if (isset($_POST['reg_adviser'])) {
    $submittedSections = $_POST['section_code'] ?? [];

    foreach ($submittedSections as $section_code) {
        // Check if the section is already assigned to this adviser in this AY
        $check = $conn->query("
            SELECT * FROM tbl_registration_adviser 
            WHERE section_code = '$section_code' 
              AND dept_code = '$deptCode'
              AND current_ay_code = '$ay_code'
        ");

        // If not yet assigned, insert the new assignment
        if (!$check || $check->num_rows == 0) {
            // Get the curriculum of the section
            $currResult = $conn->query("
                SELECT curriculum FROM tbl_section 
                WHERE section_code = '$section_code' 
                  AND dept_code = '$deptCode'
            ");

            if ($currResult->num_rows > 0) {
                $curriculum = $currResult->fetch_assoc()['curriculum'];

                // Derive the program_code from section_code (assumes space-delimited)
                $program_code = explode(' ', $section_code)[0];

                // Get the number of years for the program
                $yearQuery = "
                    SELECT num_year FROM tbl_program 
                    WHERE program_code = '$program_code' 
                      AND dept_code = '$deptCode' 
                      AND curriculum = '$curriculum'
                ";
                $yearResult = $conn->query($yearQuery);
                $num_year = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc()['num_year'] : null;

                // Insert new registration adviser assignment
                $insert = "
                    INSERT INTO tbl_registration_adviser 
                        (reg_adviser, dept_code, section_code, ay_code_assign, current_ay_code, num_year)
                    VALUES 
                        ('$fullName', '$deptCode', '$section_code', '$ay_code', '$ay_code', '$num_year')
                ";
                $conn->query($insert);
            }
        }

        // Ensure the professor is marked as a registration adviser in both tables
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 1 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 1 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // If no sections were submitted, unset the adviser flag
    if (empty($submittedSections)) {
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 0 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 0 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // Show success modal
    echo '
    <script>
        window.onload = function() {
            $("#sectionSuccessModal").modal("show");
        }
    </script>
    ';

    // Show errors in modal (only if blocked deletions occurred)
    // if (!empty($deletionErrors)) {
    //     $errorListHTML = "<ul>";
    //     foreach ($deletionErrors as $errorMsg) {
    //         $errorListHTML .= "<li>$errorMsg</li>";
    //     }
    //     $errorListHTML .= "</ul>";

    //     echo "
    //     <script>
    //         document.addEventListener('DOMContentLoaded', function () {
    //             document.getElementById('sectionDeleteErrorMessage').innerHTML = `$errorListHTML`;
    //             new bootstrap.Modal(document.getElementById('sectionDeleteErrorModal')).show();
    //         });
    //     </script>
    //     ";
    // }
}




          $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);

          // Prepared statement for fetching teaching_hrs
          $fetchTeachingHoursQuery = $conn->prepare("SELECT teaching_hrs FROM tbl_psched_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $fetchTeachingHoursQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
          $fetchTeachingHoursQuery->execute();
          $teachingHoursResult = $fetchTeachingHoursQuery->get_result();

          if ($teachingHoursResult && $teachingHoursResult->num_rows > 0) {
            $row = $teachingHoursResult->fetch_assoc();
            $teaching_hours = $row['teaching_hrs'];
            // echo "Teaching Hours: $teaching_hours<br>";

            // Calculate consultation_hrs
            $consultation_hrs = ($teaching_hours >= 18) ? 2 : 0;
            // echo "Calculated Consultation Hours: $consultation_hrs<br>";

            // Prepared statement for updating consultation_hrs
            $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
            $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
            if ($updateConsultationHrsQuery->execute()) {
              echo '
              <script type="text/javascript">
                  window.onload = function() {
                      // Show the modal after successful update
                      $("#updateSuccessModal").modal("show");
                  }
              </script>
              ';
            } else {
              echo "Error updating consultation hours: " . $conn->error;
            }
            // Prepared statement for updating consultation_hrs
            $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
            $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
            if ($updateConsultationHrsQuery->execute()) {
              echo '
     <script type="text/javascript">
         window.onload = function() {
             // Show the modal after successful update
             $("#updateSuccessModal").modal("show");
         }
     </script>
     ';
            } else {
              echo "Error updating consultation hours: " . $conn->error;
            }
            // Update consultation_hrs in tbl_pcontact_counter
            $updatePcontactConsultationHrsQuery = $conn->prepare("
UPDATE tbl_pcontact_counter 
SET consultation_hrs = ? 
WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
            $updatePcontactConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

            if ($updatePcontactConsultationHrsQuery->execute()) {
              // Fetch the updated consultation_hrs and current_consultation_hrs from tbl_pcontact_counter
              $fetchPcontactConsultationHrsQuery = $conn->prepare("
   SELECT consultation_hrs, current_consultation_hrs 
   FROM tbl_pcontact_counter 
   WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
              $fetchPcontactConsultationHrsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
              $fetchPcontactConsultationHrsQuery->execute();
              $pcontactResult = $fetchPcontactConsultationHrsQuery->get_result();

              if ($pcontactResult && $pcontactResult->num_rows > 0) {
                $row = $pcontactResult->fetch_assoc();
                $updatedConsultationHrs = $row['consultation_hrs'];
                $currentConsultationHrs = $row['current_consultation_hrs'];

                // Check if updated consultation_hrs is less than current_consultation_hrs
                if ($updatedConsultationHrs < $currentConsultationHrs) {
                  // Delete records from $sanitized_pcontact_sched_code
                  $deletePcontactSchedQuery = $conn->prepare("
           DELETE FROM $sanitized_pcontact_sched_code 
           WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
       ");
                  $deletePcontactSchedQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);

                  if ($deletePcontactSchedQuery->execute()) {
                    // Check if there are still records in $sanitized_pcontact_sched_code
                    $checkRemainingRecordsQuery = $conn->prepare("
               SELECT COUNT(*) AS record_count 
               FROM $sanitized_pcontact_sched_code 
               WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
           ");
                    $checkRemainingRecordsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                    $checkRemainingRecordsQuery->execute();
                    $remainingRecordsResult = $checkRemainingRecordsQuery->get_result();

                    if ($remainingRecordsResult && $remainingRecordsResult->num_rows > 0) {
                      $recordCountRow = $remainingRecordsResult->fetch_assoc();
                      $recordCount = $recordCountRow['record_count'];

                      if ($recordCount == 0) {
                        // Delete records from tbl_pcontact_schedstatus
                        $deletePcontactSchedStatusQuery = $conn->prepare("
                       DELETE FROM tbl_pcontact_schedstatus 
                       WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                   ");
                        $deletePcontactSchedStatusQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                        $deletePcontactSchedStatusQuery->execute();

                        // Delete records from tbl_pcontact_counter
                        $deletePcontactCounterQuery = $conn->prepare("
                       DELETE FROM tbl_pcontact_counter 
                       WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                   ");
                        $deletePcontactCounterQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                        $deletePcontactCounterQuery->execute();

                        echo '
                   <script type="text/javascript">
                       window.onload = function() {
                           $("#deleteAllSuccessModal").modal("show");
                       }
                   </script>
                   ';
                      }
                    }
                  } else {
                    echo "Error deleting schedule: " . $conn->error;
                  }
                }
              } else {
                // echo "No data found in tbl_pcontact_counter for the specified criteria.";
              }
            } else {
              echo "Error updating consultation hours in tbl_pcontact_counter: " . $conn->error;
            }
          }

          $reNumberQuery = "SELECT prof_code 
        FROM tbl_prof 
        WHERE prof_type = 'Job Order' AND employ_status = 3
        AND dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit = ?
        ORDER BY CAST(SUBSTRING(prof_code, LOCATE(' ', prof_code) + 1, LOCATE(' -', prof_code) - LOCATE(' ', prof_code) - 1) AS UNSIGNED)";

          $stmt = $conn->prepare($reNumberQuery);
          $stmt->bind_param("ssss", $deptCode, $semester, $ay_code, $profUnit);
          $stmt->execute();
          $result = $stmt->get_result();

          if (!$result) {
            die("Error with reNumberQuery: " . $conn->error);
          }

          $counter = 1; // Start numbering from 1

          while ($row = $result->fetch_assoc()) {
            $profCode = $row['prof_code'];

            // Validate prof_code format
            if (strpos($profCode, '-') === false || strpos($profCode, ' ') === false) {
              echo "Invalid prof_code format: $profCode<br>";
              continue;
            }

            // Generate new prof_code for Job Order
            $newProfCode = "$profUnit $counter - " . substr($profCode, strpos($profCode, '-') + 2);

            // Update tbl_prof
            $updateJobOrderQuery = "UPDATE tbl_prof 
                  SET prof_code = ? 
                  WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit = ?";
            $stmt = $conn->prepare($updateJobOrderQuery);
            $stmt->bind_param("ssssss", $newProfCode, $profCode, $deptCode, $semester, $ay_code, $profUnit);

            if ($stmt->execute()) {
              // Sanitize and create the necessary schedule table names
              $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $deptCode . "_" . $ay_code);
              $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
              $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
              $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
              $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");

              // Construct the new and old prof_sched_code
              $NewProfSchedCode = $newProfCode . "_" . $ay_code;
              $oldProfSchedCode = $profCode . "_" . $ay_code;

              $tablesToUpdateWithName = [
                "tbl_prof_acc"
              ];

              foreach ($tablesToUpdateWithName as $table) {
                // Use prepared statements to avoid SQL injection
                $updateTableQuery = "UPDATE $table 
                                  SET prof_code = ? 
                                  WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

                $stmt = $conn->prepare($updateTableQuery);
                $stmt->bind_param("sssss", $newProfCode, $lastName, $deptCode, $semester, $ay_code);
                $stmt->execute();
                $stmt->close();
              }

              // Update related tables that store the prof_code
              $tablesToUpdate = [
                "tbl_psched_counter",
                "tbl_pcontact_counter",
                "tbl_pcontact_schedstatus",
                "tbl_psched",
                $sanitized_prof_sched_code,
                $sanitized_pcontact_sched_code
              ];

              foreach ($tablesToUpdate as $table) {
                $updateTableQuery = "UPDATE $table 
                        SET prof_code = '$newProfCode', prof_sched_code = '$NewProfSchedCode'
                        WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                if (!$conn->query($updateTableQuery)) {
                  echo "Error updating table $table: " . $conn->error;
                }
              }

              $tablesToUpdateWithSchedCode = [
                "tbl_assigned_course",
                $sanitized_room_sched_code,
                $sanitized_section_sched_code,
                $sanitized_ccl_room_sched_code
              ];

              foreach ($tablesToUpdateWithSchedCode as $table) {
                $updateTableQuery = "UPDATE $table 
                                  SET prof_code = '$newProfCode', prof_name = '$fullName' 
                                  WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                if (!$conn->query($updateTableQuery)) {
                  echo "Error updating table $table: " . $conn->error;
                }
              }

              $tablesToUpdateWithoutProfCode = [
                "tbl_prof_schedstatus"
              ];

              foreach ($tablesToUpdateWithoutProfCode as $table) {
                $updateTableQuery = "UPDATE $table 
                                    SET  prof_sched_code = '$NewProfSchedCode'
                                    WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                $conn->query($updateTableQuery);
              }
            } else {
              echo "Error updating prof_code in tbl_prof: " . $conn->error;
            }
            $counter++;
          }
        }
      } elseif ($profType === 'Job Order' && $employStatus === 0) {
        $employStatus = 3;

        // Fetch all existing numbers for Job Order Instructor
        $numberQuery = "SELECT id, CAST(SUBSTRING(prof_code, LOCATE(' ', prof_code) + 1, 
                              LOCATE(' -', prof_code) - LOCATE(' ', prof_code) - 1) AS UNSIGNED) AS prof_number
                        FROM tbl_prof
                        WHERE prof_type = 'Job Order'
                          AND employ_status = $employStatus
                          AND dept_code = '$deptCode'
                          AND semester = '$semester'
                          AND ay_code = '$ay_code'
                          AND prof_unit = '$profUnit'
                        ORDER BY prof_number ASC";

        $numberResult = $conn->query($numberQuery);
        $existingNumbers = [];
        $profIds = [];

        if ($numberResult && $numberResult->num_rows > 0) {
          // Collect all existing numbers and IDs
          while ($row = $numberResult->fetch_assoc()) {
            $existingNumbers[] = $row['prof_number'];
            $profIds[] = $row['id'];
          }

          // Reassign numbers to eliminate gaps
          $newNumber = 1;
          foreach ($existingNumbers as $index => $existingNumber) {
            if ($existingNumber != $newNumber) {
              // Update the prof_code for this professor to make numbering consecutive
              $profId = $profIds[$index];
              $updatedProfCode = "$profUnit $newNumber - $lastName";

              $updateNumberQuery = "UPDATE tbl_prof 
                                          SET prof_code = '$updatedProfCode' 
                                          WHERE id = $profId AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code' AND prof_unit = '$profUnit'";
              $conn->query($updateNumberQuery);
            }
            $newNumber++;
          }
        } else {
          // If no existing Job Order Instructor, start from 1
          $newNumber = 1;
        }

        // Assign the next consecutive number for the new professor
        $updatedProfCode = "$profUnit $newNumber - $lastName";

        // Update the selected professor to Job Order
        $updateSelectedQuery = "UPDATE tbl_prof 
                                SET prof_code = '$updatedProfCode', 
                                    prof_type = 'Job Order', 
                                    employ_status = 3, 
                                    reg_adviser = $reg_adviser, 
                                    academic_rank = '$academicRank',
                                    prof_name = '$fullName'
                                WHERE prof_code = '$oldProfCode' 
                                  AND dept_code = '$deptCode' 
                                  AND semester = '$semester' 
                                  AND ay_code = '$ay_code'
                                  AND prof_unit = '$profUnit'";

        if ($conn->query($updateSelectedQuery) === TRUE) {

if (isset($_POST['reg_adviser'])) {
    $submittedSections = $_POST['section_code'] ?? [];

    foreach ($submittedSections as $section_code) {
        // Check if the section is already assigned to this adviser in this AY
        $check = $conn->query("
            SELECT * FROM tbl_registration_adviser 
            WHERE section_code = '$section_code' 
              AND dept_code = '$deptCode'
              AND current_ay_code = '$ay_code'
        ");

        // If not yet assigned, insert the new assignment
        if (!$check || $check->num_rows == 0) {
            // Get the curriculum of the section
            $currResult = $conn->query("
                SELECT curriculum FROM tbl_section 
                WHERE section_code = '$section_code' 
                  AND dept_code = '$deptCode'
            ");

            if ($currResult->num_rows > 0) {
                $curriculum = $currResult->fetch_assoc()['curriculum'];

                // Derive the program_code from section_code (assumes space-delimited)
                $program_code = explode(' ', $section_code)[0];

                // Get the number of years for the program
                $yearQuery = "
                    SELECT num_year FROM tbl_program 
                    WHERE program_code = '$program_code' 
                      AND dept_code = '$deptCode' 
                      AND curriculum = '$curriculum'
                ";
                $yearResult = $conn->query($yearQuery);
                $num_year = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc()['num_year'] : null;

                // Insert new registration adviser assignment
                $insert = "
                    INSERT INTO tbl_registration_adviser 
                        (reg_adviser, dept_code, section_code, ay_code_assign, current_ay_code, num_year)
                    VALUES 
                        ('$fullName', '$deptCode', '$section_code', '$ay_code', '$ay_code', '$num_year')
                ";
                $conn->query($insert);
            }
        }

        // Ensure the professor is marked as a registration adviser in both tables
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 1 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 1 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // If no sections were submitted, unset the adviser flag
    if (empty($submittedSections)) {
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 0 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 0 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // Show success modal
    echo '
    <script>
        window.onload = function() {
            $("#sectionSuccessModal").modal("show");
        }
    </script>
    ';

    // Show errors in modal (only if blocked deletions occurred)
    // if (!empty($deletionErrors)) {
    //     $errorListHTML = "<ul>";
    //     foreach ($deletionErrors as $errorMsg) {
    //         $errorListHTML .= "<li>$errorMsg</li>";
    //     }
    //     $errorListHTML .= "</ul>";

    //     echo "
    //     <script>
    //         document.addEventListener('DOMContentLoaded', function () {
    //             document.getElementById('sectionDeleteErrorMessage').innerHTML = `$errorListHTML`;
    //             new bootstrap.Modal(document.getElementById('sectionDeleteErrorModal')).show();
    //         });
    //     </script>
    //     ";
    // }
}




          $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);

          // Prepared statement for fetching teaching_hrs
          $fetchTeachingHoursQuery = $conn->prepare("SELECT teaching_hrs FROM tbl_psched_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $fetchTeachingHoursQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
          $fetchTeachingHoursQuery->execute();
          $teachingHoursResult = $fetchTeachingHoursQuery->get_result();

          if ($teachingHoursResult && $teachingHoursResult->num_rows > 0) {
            $row = $teachingHoursResult->fetch_assoc();
            $teaching_hours = $row['teaching_hrs'];
            // echo "Teaching Hours: $teaching_hours<br>";

            $consultation_hrs = ($teaching_hours >= 18) ? 2 : 0;
            // echo "Calculated Consultation Hours: $consultation_hrs<br>";

            // Prepared statement for updating consultation_hrs
            $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
            $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
            if ($updateConsultationHrsQuery->execute()) {
              echo '
              <script type="text/javascript">
                  window.onload = function() {
                      // Show the modal after successful update
                      $("#updateSuccessModal").modal("show");
                  }
              </script>
              ';
            } else {
              echo "Error updating consultation hours: " . $conn->error;
            }

            // Prepared statement for updating consultation_hrs
            $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
            $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
            if ($updateConsultationHrsQuery->execute()) {
              echo '
          <script type="text/javascript">
              window.onload = function() {
                  // Show the modal after successful update
                  $("#updateSuccessModal").modal("show");
              }
          </script>
          ';
            } else {
              echo "Error updating consultation hours: " . $conn->error;
            }
            // Update consultation_hrs in tbl_pcontact_counter
            $updatePcontactConsultationHrsQuery = $conn->prepare("
    UPDATE tbl_pcontact_counter 
    SET consultation_hrs = ? 
    WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
            $updatePcontactConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

            if ($updatePcontactConsultationHrsQuery->execute()) {
              // Fetch the updated consultation_hrs and current_consultation_hrs from tbl_pcontact_counter
              $fetchPcontactConsultationHrsQuery = $conn->prepare("
        SELECT consultation_hrs, current_consultation_hrs 
        FROM tbl_pcontact_counter 
        WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
    ");
              $fetchPcontactConsultationHrsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
              $fetchPcontactConsultationHrsQuery->execute();
              $pcontactResult = $fetchPcontactConsultationHrsQuery->get_result();

              if ($pcontactResult && $pcontactResult->num_rows > 0) {
                $row = $pcontactResult->fetch_assoc();
                $updatedConsultationHrs = $row['consultation_hrs'];
                $currentConsultationHrs = $row['current_consultation_hrs'];

                // Check if updated consultation_hrs is less than current_consultation_hrs
                if ($updatedConsultationHrs < $currentConsultationHrs) {
                  // Delete records from $sanitized_pcontact_sched_code
                  $deletePcontactSchedQuery = $conn->prepare("
                DELETE FROM $sanitized_pcontact_sched_code 
                WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
            ");
                  $deletePcontactSchedQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);

                  if ($deletePcontactSchedQuery->execute()) {
                    // Check if there are still records in $sanitized_pcontact_sched_code
                    $checkRemainingRecordsQuery = $conn->prepare("
                    SELECT COUNT(*) AS record_count 
                    FROM $sanitized_pcontact_sched_code 
                    WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                ");
                    $checkRemainingRecordsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                    $checkRemainingRecordsQuery->execute();
                    $remainingRecordsResult = $checkRemainingRecordsQuery->get_result();

                    if ($remainingRecordsResult && $remainingRecordsResult->num_rows > 0) {
                      $recordCountRow = $remainingRecordsResult->fetch_assoc();
                      $recordCount = $recordCountRow['record_count'];

                      if ($recordCount == 0) {
                        // Delete records from tbl_pcontact_schedstatus
                        $deletePcontactSchedStatusQuery = $conn->prepare("
                            DELETE FROM tbl_pcontact_schedstatus 
                            WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                        ");
                        $deletePcontactSchedStatusQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                        $deletePcontactSchedStatusQuery->execute();

                        // Delete records from tbl_pcontact_counter
                        $deletePcontactCounterQuery = $conn->prepare("
                            DELETE FROM tbl_pcontact_counter 
                            WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                        ");
                        $deletePcontactCounterQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                        $deletePcontactCounterQuery->execute();

                        echo '
                        <script type="text/javascript">
                            window.onload = function() {
                                $("#deleteAllSuccessModal").modal("show");
                            }
                        </script>
                        ';
                      }
                    }
                  } else {
                    echo "Error deleting schedule: " . $conn->error;
                  }
                }
              } else {
                // echo "No data found in tbl_pcontact_counter for the specified criteria.";
              }
            } else {
              echo "Error updating consultation hours in tbl_pcontact_counter: " . $conn->error;
            }
          }
          // Step 1: Fetch all part-time Job Order Instructor
          $reNumberPartTimeQuery = "SELECT prof_code 
                          FROM tbl_prof 
                          WHERE prof_type = 'Job Order' AND employ_status = 1
                            AND dept_code = '$deptCode' 
                            AND semester = '$semester' 
                            AND ay_code = '$ay_code'
                            AND prof_unit = '$profUnit'
                          ORDER BY CAST(SUBSTRING(prof_code, LOCATE('PT ', prof_code) + 3, 
                                LOCATE(' -', prof_code) - LOCATE('PT ', prof_code) - 3) AS UNSIGNED)";

          $partTimeResult = $conn->query($reNumberPartTimeQuery);

          // Step 2: Store results in an array
          $profCodes = [];
          while ($row = $partTimeResult->fetch_assoc()) {
            $profCodes[] = $row['prof_code'];
          }

          // Step 3: Update prof_code with continuous numbering
          $counter = 1;
          foreach ($profCodes as $profCode) {
            // Generate new prof_code
            $newProfCode = "$profUnit PT $counter - " . substr($profCode, strpos($profCode, '-') + 2);

            // Update the professor's code in tbl_prof
            $updatePartTimeQuery = "UPDATE tbl_prof 
                                      SET prof_code = '$newProfCode' 
                                      WHERE prof_code = '$profCode' 
                                        AND dept_code = '$deptCode' 
                                        AND semester = '$semester' 
                                        AND ay_code = '$ay_code'
                                        AND prof_unit = '$profUnit'";

            if ($conn->query($updatePartTimeQuery) === TRUE) {

              // Update the room and section schedules
              $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
              $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
              $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
              $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
              $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

              // Update prof_sched_code
              $NewProfSchedCode = $newProfCode . "_" . $ay_code;
              $oldProfSchedCode = $profCode . "_" . $ay_code;


              $tablesToUpdateWithName = [
                "tbl_prof_acc"
              ];

              foreach ($tablesToUpdateWithName as $table) {
                // Use prepared statements to avoid SQL injection
                $updateTableQuery = "UPDATE $table 
                                   SET prof_code = ? 
                                   WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

                $stmt = $conn->prepare($updateTableQuery);
                $stmt->bind_param("sssss", $newProfCode, $lastName, $deptCode, $semester, $ay_code);
                $stmt->execute();
                $stmt->close();
              }

              // Update related tables that store the prof_code
              $tablesToUpdate = [
                "tbl_psched_counter",
                "tbl_pcontact_counter",
                "tbl_pcontact_schedstatus",
                "tbl_psched",
                $sanitized_prof_sched_code,
                $sanitized_pcontact_sched_code
              ];

              foreach ($tablesToUpdate as $table) {
                $updateTableQuery = "UPDATE $table 
                                  SET prof_code = '$newProfCode', 
                                       prof_sched_code = '$NewProfSchedCode'
                                    WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                if (!$conn->query($updateTableQuery)) {
                  echo "Error updating table $table: " . $conn->error;
                }
              }

              $tablesToUpdateWithSchedCode = [
                "tbl_assigned_course",
                $sanitized_room_sched_code,
                $sanitized_section_sched_code,
                $sanitized_ccl_room_sched_code
              ];

              foreach ($tablesToUpdateWithSchedCode as $table) {
                $updateTableQuery = "UPDATE $table 
                                 SET prof_code = '$newProfCode', prof_name = '$fullName' 
                                 WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                if (!$conn->query($updateTableQuery)) {
                  echo "Error updating table $table: " . $conn->error;
                }
              }

              $tablesToUpdateWithoutProfCode = [
                "tbl_prof_schedstatus"
              ];

              foreach ($tablesToUpdateWithoutProfCode as $table) {
                $updateTableQuery = "UPDATE $table 
                                   SET  prof_sched_code = '$NewProfSchedCode'
                                   WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
                $conn->query($updateTableQuery);
              }
            } else {
              echo "Error updating prof_code in tbl_prof: " . $conn->error;
            }
            $counter++;
          }
        }
      }

      // Update the prof_code in tbl_prof for the selected professor
      $updateQuery = "UPDATE tbl_prof 
                    SET prof_code = '$updatedProfCode', 
                        prof_type = '$profType', 
                        academic_rank = '$academicRank', 
                        prof_name = '$fullName', 
                        employ_status = '$employStatus' 
                    WHERE prof_code = '$oldProfCode' 
                    AND dept_code = '$deptCode' 
                    AND semester = '$semester' 
                    AND ay_code = '$ay_code'
                    AND prof_unit = '$profUnit'";

      if ($conn->query($updateQuery) === TRUE) {
        echo '
  <script type="text/javascript">
      window.onload = function() {
          // Show the modal after successful update
          $("#updateSuccessModal").modal("show");
      }
  </script>
  ';

        // Sanitizing table names
        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
        $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
        $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
        $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
        $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

        // Update prof_sched_code
        $updatedProfSchedCode = $updatedProfCode . "_" . $ay_code;
        $oldProfSchedCode = $oldProfCode . "_" . $ay_code;

        // Prepared statement for fetching teaching_hrs
        $fetchTeachingHoursQuery = $conn->prepare("SELECT teaching_hrs FROM tbl_psched_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
        $fetchTeachingHoursQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
        $fetchTeachingHoursQuery->execute();
        $teachingHoursResult = $fetchTeachingHoursQuery->get_result();

        if ($teachingHoursResult && $teachingHoursResult->num_rows > 0) {
          $row = $teachingHoursResult->fetch_assoc();
          $teaching_hours = $row['teaching_hrs'];
          // echo "Teaching Hours: $teaching_hours<br>";

          // Calculate consultation_hrs
          $consultation_hrs = 0; // Default to 0 if not calculated
          if ($profType === "Regular") {
            $consultation_hrs = $teaching_hours / 3;
          } elseif ($profType === "Job Order") {
            $consultation_hrs = ($teaching_hours >= 18) ? 2 : 0;
          }
          // echo "Calculated Consultation Hours: $consultation_hrs<br>";

          // Prepared statement for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
          if ($updateConsultationHrsQuery->execute()) {
            echo '
            <script type="text/javascript">
                window.onload = function() {
                    // Show the modal after successful update
                    $("#updateSuccessModal").modal("show");
                }
            </script>
            ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }

          // Prepared statement for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
          if ($updateConsultationHrsQuery->execute()) {
            echo '
     <script type="text/javascript">
         window.onload = function() {
             // Show the modal after successful update
             $("#updateSuccessModal").modal("show");
         }
     </script>
     ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }
          // Update consultation_hrs in tbl_pcontact_counter
          $updatePcontactConsultationHrsQuery = $conn->prepare("
UPDATE tbl_pcontact_counter 
SET consultation_hrs = ? 
WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
          $updatePcontactConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

          if ($updatePcontactConsultationHrsQuery->execute()) {
            // Fetch the updated consultation_hrs and current_consultation_hrs from tbl_pcontact_counter
            $fetchPcontactConsultationHrsQuery = $conn->prepare("
   SELECT consultation_hrs, current_consultation_hrs 
   FROM tbl_pcontact_counter 
   WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
            $fetchPcontactConsultationHrsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
            $fetchPcontactConsultationHrsQuery->execute();
            $pcontactResult = $fetchPcontactConsultationHrsQuery->get_result();

            if ($pcontactResult && $pcontactResult->num_rows > 0) {
              $row = $pcontactResult->fetch_assoc();
              $updatedConsultationHrs = $row['consultation_hrs'];
              $currentConsultationHrs = $row['current_consultation_hrs'];

              // Check if updated consultation_hrs is less than current_consultation_hrs
              if ($updatedConsultationHrs < $currentConsultationHrs) {
                // Delete records from $sanitized_pcontact_sched_code
                $deletePcontactSchedQuery = $conn->prepare("
           DELETE FROM $sanitized_pcontact_sched_code 
           WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
       ");
                $deletePcontactSchedQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);

                if ($deletePcontactSchedQuery->execute()) {
                  // Check if there are still records in $sanitized_pcontact_sched_code
                  $checkRemainingRecordsQuery = $conn->prepare("
               SELECT COUNT(*) AS record_count 
               FROM $sanitized_pcontact_sched_code 
               WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
           ");
                  $checkRemainingRecordsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                  $checkRemainingRecordsQuery->execute();
                  $remainingRecordsResult = $checkRemainingRecordsQuery->get_result();

                  if ($remainingRecordsResult && $remainingRecordsResult->num_rows > 0) {
                    $recordCountRow = $remainingRecordsResult->fetch_assoc();
                    $recordCount = $recordCountRow['record_count'];

                    if ($recordCount == 0) {
                      // Delete records from tbl_pcontact_schedstatus
                      $deletePcontactSchedStatusQuery = $conn->prepare("
                       DELETE FROM tbl_pcontact_schedstatus 
                       WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                   ");
                      $deletePcontactSchedStatusQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                      $deletePcontactSchedStatusQuery->execute();

                      // Delete records from tbl_pcontact_counter
                      $deletePcontactCounterQuery = $conn->prepare("
                       DELETE FROM tbl_pcontact_counter 
                       WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                   ");
                      $deletePcontactCounterQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                      $deletePcontactCounterQuery->execute();

                      echo '
                   <script type="text/javascript">
                       window.onload = function() {
                           $("#deleteAllSuccessModal").modal("show");
                       }
                   </script>
                   ';
                    }
                  }
                } else {
                  echo "Error deleting schedule: " . $conn->error;
                }
              }
            } else {
              // echo "No data found in tbl_pcontact_counter for the specified criteria.";
            }
          } else {
            echo "Error updating consultation hours in tbl_pcontact_counter: " . $conn->error;
          }
        }

        $tablesToUpdateWithName = [
          "tbl_prof_acc"
        ];

        foreach ($tablesToUpdateWithName as $table) {
          // Use prepared statements to avoid SQL injection
          $updateTableQuery = "UPDATE $table 
                               SET prof_code = ? 
                               WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

          $stmt = $conn->prepare($updateTableQuery);
          $stmt->bind_param("sssss", $updatedProfCode, $lastName, $deptCode, $semester, $ay_code);
          $stmt->execute();
          $stmt->close();
        }

        // Update related tables
        $tablesToUpdateWithProfSchedCode = [
          "tbl_psched_counter",
          "tbl_pcontact_counter",
          "tbl_pcontact_schedstatus",
          "tbl_psched",
          $sanitized_prof_sched_code,
          $sanitized_pcontact_sched_code
        ];

        foreach ($tablesToUpdateWithProfSchedCode as $table) {
          $updateTableQuery = "UPDATE $table 
                                   SET prof_code = '$updatedProfCode', 
                                       prof_sched_code = '$updatedProfSchedCode'
                                   WHERE prof_code = '$oldProfCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
          $conn->query($updateTableQuery);
        }

        $tablesToUpdateWithoutProfSchedCode = [
          "tbl_assigned_course",
          $sanitized_room_sched_code,
          $sanitized_section_sched_code,
          $sanitized_ccl_room_sched_code
        ];

        foreach ($tablesToUpdateWithoutProfSchedCode as $table) {
          $updateTableQuery = "UPDATE $table 
                                   SET prof_code = '$updatedProfCode', prof_name = '$fullName'
                                   WHERE prof_code = '$oldProfCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
          $conn->query($updateTableQuery);
        }


        $tablesToUpdateWithoutProfCode = [
          "tbl_prof_schedstatus"
        ];

        foreach ($tablesToUpdateWithoutProfCode as $table) {
          $updateTableQuery = "UPDATE $table 
                               SET  prof_sched_code = '$updatedProfSchedCode'
                               WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
          $conn->query($updateTableQuery);
        }
      }
    }
  } else {
    if ($profType === 'Regular') {
      $employStatus = 2;

      // Prepare the new prof_code for Regular professor (e.g., JCAbutin)
      $firstInitial = !empty($firstName) ? strtoupper(substr($firstName, 0, 1)) : '';
      $middleInitial = !empty($middleInitial) ? strtoupper(substr($middleInitial, 0, 1)) : '';
      $lastPart = !empty($lastName) ? ucfirst($lastName) : '';
      $updatedProfCode = "$firstInitial$middleInitial$lastPart"; // Format: JCAbutin

      // Prepare the query to update the professor's record
      $updateProfQuery = "UPDATE tbl_prof 
     SET prof_code = '$updatedProfCode', 
         prof_type = 'Regular', 
         employ_status = $employStatus, 
         reg_adviser = $reg_adviser, 
         prof_name = '$fullName',
         academic_rank = '$academicRank'
     WHERE prof_code = '$oldProfCode' 
       AND dept_code = '$deptCode' 
       AND semester = '$semester' 
       AND ay_code = '$ay_code'
       AND prof_unit = '$profUnit'";

      // Execute the update query
      if ($conn->query($updateProfQuery) === TRUE) {

if (isset($_POST['reg_adviser'])) {
    $submittedSections = $_POST['section_code'] ?? [];

    foreach ($submittedSections as $section_code) {
        // Check if the section is already assigned to this adviser in this AY
        $check = $conn->query("
            SELECT * FROM tbl_registration_adviser 
            WHERE section_code = '$section_code' 
              AND dept_code = '$deptCode'
              AND current_ay_code = '$ay_code'
        ");

        // If not yet assigned, insert the new assignment
        if (!$check || $check->num_rows == 0) {
            // Get the curriculum of the section
            $currResult = $conn->query("
                SELECT curriculum FROM tbl_section 
                WHERE section_code = '$section_code' 
                  AND dept_code = '$deptCode'
            ");

            if ($currResult->num_rows > 0) {
                $curriculum = $currResult->fetch_assoc()['curriculum'];

                // Derive the program_code from section_code (assumes space-delimited)
                $program_code = explode(' ', $section_code)[0];

                // Get the number of years for the program
                $yearQuery = "
                    SELECT num_year FROM tbl_program 
                    WHERE program_code = '$program_code' 
                      AND dept_code = '$deptCode' 
                      AND curriculum = '$curriculum'
                ";
                $yearResult = $conn->query($yearQuery);
                $num_year = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc()['num_year'] : null;

                // Insert new registration adviser assignment
                $insert = "
                    INSERT INTO tbl_registration_adviser 
                        (reg_adviser, dept_code, section_code, ay_code_assign, current_ay_code, num_year)
                    VALUES 
                        ('$fullName', '$deptCode', '$section_code', '$ay_code', '$ay_code', '$num_year')
                ";
                $conn->query($insert);
            }
        }

        // Ensure the professor is marked as a registration adviser in both tables
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 1 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 1 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // If no sections were submitted, unset the adviser flag
    if (empty($submittedSections)) {
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 0 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 0 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // Show success modal
    echo '
    <script>
        window.onload = function() {
            $("#sectionSuccessModal").modal("show");
        }
    </script>
    ';

    // Show errors in modal (only if blocked deletions occurred)
    // if (!empty($deletionErrors)) {
    //     $errorListHTML = "<ul>";
    //     foreach ($deletionErrors as $errorMsg) {
    //         $errorListHTML .= "<li>$errorMsg</li>";
    //     }
    //     $errorListHTML .= "</ul>";

    //     echo "
    //     <script>
    //         document.addEventListener('DOMContentLoaded', function () {
    //             document.getElementById('sectionDeleteErrorMessage').innerHTML = `$errorListHTML`;
    //             new bootstrap.Modal(document.getElementById('sectionDeleteErrorModal')).show();
    //         });
    //     </script>
    //     ";
    // }
}





        // Sanitize and create the necessary schedule table names
        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $deptCode . "_" . $ay_code);
        $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
        $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
        $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
        $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");

        // Construct the new and old prof_sched_code
        $NewProfSchedCode = $updatedProfCode . "_" . $ay_code;
        $oldProfSchedCode = $profCode . "_" . $ay_code;

        // Prepared statement for fetching teaching_hrs
        $fetchTeachingHoursQuery = $conn->prepare("SELECT teaching_hrs FROM tbl_psched_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
        $fetchTeachingHoursQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
        $fetchTeachingHoursQuery->execute();
        $teachingHoursResult = $fetchTeachingHoursQuery->get_result();

        if ($teachingHoursResult && $teachingHoursResult->num_rows > 0) {
          $row = $teachingHoursResult->fetch_assoc();
          $teaching_hours = $row['teaching_hrs'];
          // echo "Teaching Hours: $teaching_hours<br>";

          $consultation_hrs = $teaching_hours / 3;
          // echo "Calculated Consultation Hours: $consultation_hrs<br>";

          // Prepare the query for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

          if ($updateConsultationHrsQuery->execute()) {
            echo '
            <script type="text/javascript">
                window.onload = function() {
                    // Show the modal after successful update
                    $("#updateSuccessModal").modal("show");
                }
            </script>
            ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }

          // Prepared statement for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
          if ($updateConsultationHrsQuery->execute()) {
            echo '
          <script type="text/javascript">
              window.onload = function() {
                  // Show the modal after successful update
                  $("#updateSuccessModal").modal("show");
              }
          </script>
          ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }
          // Update consultation_hrs in tbl_pcontact_counter
          $updatePcontactConsultationHrsQuery = $conn->prepare("
    UPDATE tbl_pcontact_counter 
    SET consultation_hrs = ? 
    WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
          $updatePcontactConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

          if ($updatePcontactConsultationHrsQuery->execute()) {
            // Fetch the updated consultation_hrs and current_consultation_hrs from tbl_pcontact_counter
            $fetchPcontactConsultationHrsQuery = $conn->prepare("
        SELECT consultation_hrs, current_consultation_hrs 
        FROM tbl_pcontact_counter 
        WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
    ");
            $fetchPcontactConsultationHrsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
            $fetchPcontactConsultationHrsQuery->execute();
            $pcontactResult = $fetchPcontactConsultationHrsQuery->get_result();

            if ($pcontactResult && $pcontactResult->num_rows > 0) {
              $row = $pcontactResult->fetch_assoc();
              $updatedConsultationHrs = $row['consultation_hrs'];
              $currentConsultationHrs = $row['current_consultation_hrs'];

              // Check if updated consultation_hrs is less than current_consultation_hrs
              if ($updatedConsultationHrs < $currentConsultationHrs) {
                // Delete records from $sanitized_pcontact_sched_code
                $deletePcontactSchedQuery = $conn->prepare("
                DELETE FROM $sanitized_pcontact_sched_code 
                WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
            ");
                $deletePcontactSchedQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);

                if ($deletePcontactSchedQuery->execute()) {
                  // Check if there are still records in $sanitized_pcontact_sched_code
                  $checkRemainingRecordsQuery = $conn->prepare("
                    SELECT COUNT(*) AS record_count 
                    FROM $sanitized_pcontact_sched_code 
                    WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                ");
                  $checkRemainingRecordsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                  $checkRemainingRecordsQuery->execute();
                  $remainingRecordsResult = $checkRemainingRecordsQuery->get_result();

                  if ($remainingRecordsResult && $remainingRecordsResult->num_rows > 0) {
                    $recordCountRow = $remainingRecordsResult->fetch_assoc();
                    $recordCount = $recordCountRow['record_count'];

                    if ($recordCount == 0) {
                      // Delete records from tbl_pcontact_schedstatus
                      $deletePcontactSchedStatusQuery = $conn->prepare("
                            DELETE FROM tbl_pcontact_schedstatus 
                            WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                        ");
                      $deletePcontactSchedStatusQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                      $deletePcontactSchedStatusQuery->execute();

                      // Delete records from tbl_pcontact_counter
                      $deletePcontactCounterQuery = $conn->prepare("
                            DELETE FROM tbl_pcontact_counter 
                            WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                        ");
                      $deletePcontactCounterQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                      $deletePcontactCounterQuery->execute();

                      echo '
                        <script type="text/javascript">
                            window.onload = function() {
                                $("#deleteAllSuccessModal").modal("show");
                            }
                        </script>
                        ';
                    }
                  }
                } else {
                  echo "Error deleting schedule: " . $conn->error;
                }
              }
            } else {
              // echo "No data found in tbl_pcontact_counter for the specified criteria.";
            }
          } else {
            echo "Error updating consultation hours in tbl_pcontact_counter: " . $conn->error;
          }
        }

        $tablesToUpdateWithName = [
          "tbl_prof_acc"
        ];

        foreach ($tablesToUpdateWithName as $table) {
          // Use prepared statements to avoid SQL injection
          $updateTableQuery = "UPDATE $table 
   SET prof_code = ? 
   WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

          $stmt = $conn->prepare($updateTableQuery);
          $stmt->bind_param("sssss", $updatedProfCode, $lastName, $deptCode, $semester, $ay_code);
          $stmt->execute();
          $stmt->close();
        }

        // Update related tables that store the prof_code
        $tablesToUpdate = [
          "tbl_psched_counter",
          "tbl_pcontact_counter",
          "tbl_pcontact_schedstatus",
          "tbl_psched",
          $sanitized_prof_sched_code,
          $sanitized_pcontact_sched_code
        ];

        foreach ($tablesToUpdate as $table) {
          $updateTableQuery = "UPDATE $table 
                     SET prof_code = '$updatedProfCode', prof_sched_code = '$NewProfSchedCode'
                     WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";

          if (!$conn->query($updateTableQuery)) {
            // Check if the error is a duplicate entry
            if ($conn->errno == 1062) {
              // Skip this table if there's a duplicate entry
              continue;
            }
            // If it's any other error, display it
            echo "Error updating table $table: " . $conn->error;
          }
        }


        $tablesToUpdateWithSchedCode = [
          "tbl_assigned_course",
          $sanitized_room_sched_code,
          $sanitized_section_sched_code,
          $sanitized_ccl_room_sched_code
        ];

        foreach ($tablesToUpdateWithSchedCode as $table) {
          $updateTableQuery = "UPDATE $table 
 SET prof_code = '$updatedProfCode', prof_name = '$fullName' 
 WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
          if (!$conn->query($updateTableQuery)) {
            echo "Error updating table $table: " . $conn->error;
          }
        }

        $tablesToUpdateWithoutProfCode = [
          "tbl_prof_schedstatus"
        ];

        foreach ($tablesToUpdateWithoutProfCode as $table) {
          $updateTableQuery = "UPDATE $table 
   SET  prof_sched_code = '$NewProfSchedCode'
   WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
          $conn->query($updateTableQuery);
        }

        // Step 1: Fetch all part-time Job Order Instructor
        $reNumberPartTimeQuery = "
                              SELECT prof_code 
                              FROM tbl_prof 
                              WHERE prof_type = 'Job Order' 
                                AND employ_status = 1 
                                AND dept_code = ? 
                                AND semester = ? 
                                AND ay_code = ? 
                                AND prof_unit = ?
                              ORDER BY 
                                CAST(SUBSTRING(prof_code, LOCATE('PT ', prof_code) + 3, 
                                LOCATE(' -', prof_code) - LOCATE('PT ', prof_code) - 3) AS UNSIGNED)";
        $stmt = $conn->prepare($reNumberPartTimeQuery);
        $stmt->bind_param("ssss", $deptCode, $semester, $ay_code, $profUnit);
        $stmt->execute();
        $partTimeResult = $stmt->get_result();

        // Step 2: Store results in an array
        $profCodes = [];
        while ($row = $partTimeResult->fetch_assoc()) {
          $profCodes[] = $row['prof_code'];
        }
        $stmt->close();
        $counter = 1;

        foreach ($profCodes as $profCode) {
          // Generate new prof_code
          $newProfCode = "$profUnit PT $counter - " . substr($profCode, strpos($profCode, '-') + 2);

          // Prepare the update query to avoid SQL injection
          $updatePartTimeQuery = "
         UPDATE tbl_prof 
         SET prof_code = ? 
         WHERE prof_code = ? 
           AND dept_code = ? 
           AND semester = ? 
           AND ay_code = ?
           AND prof_unit = ?";

          $updateStmt = $conn->prepare($updatePartTimeQuery);
          $updateStmt->bind_param("ssssss", $newProfCode, $profCode, $deptCode, $semester, $ay_code, $profUnit);

          if ($updateStmt->execute() === TRUE) {
            // Sanitize and create the necessary schedule table names
            $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $deptCode . "_" . $ay_code);
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
            $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");

            // Construct the new and old prof_sched_code
            $NewProfSchedCode = $newProfCode . "_" . $ay_code;
            $oldProfSchedCode = $profCode . "_" . $ay_code;

            $tablesToUpdateWithName = [
              "tbl_prof_acc"
            ];

            foreach ($tablesToUpdateWithName as $table) {
              // Use prepared statements to avoid SQL injection
              $updateTableQuery = "UPDATE $table 
 SET prof_code = ? 
 WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

              $stmt = $conn->prepare($updateTableQuery);
              $stmt->bind_param("sssss", $newProfCode, $lastName, $deptCode, $semester, $ay_code);
              $stmt->execute();
              $stmt->close();
            }

            // Update related tables that store the prof_code
            $tablesToUpdate = [
              "tbl_psched_counter",
              "tbl_pcontact_counter",
              "tbl_pcontact_schedstatus",
              "tbl_psched",
              $sanitized_prof_sched_code,
              $sanitized_pcontact_sched_code
            ];

            foreach ($tablesToUpdate as $table) {
              $updateTableQuery = "UPDATE $table 
                           SET prof_code = '$newProfCode', prof_sched_code = '$NewProfSchedCode'
                           WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              if (!$conn->query($updateTableQuery)) {
                echo "Error updating table $table: " . $conn->error;
              }
            }

            $tablesToUpdateWithSchedCode = [
              "tbl_assigned_course",
              $sanitized_room_sched_code,
              $sanitized_section_sched_code,
              $sanitized_ccl_room_sched_code
            ];

            foreach ($tablesToUpdateWithSchedCode as $table) {
              $updateTableQuery = "UPDATE $table 
SET prof_code = '$newProfCode', prof_name = '$fullName' 
WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              if (!$conn->query($updateTableQuery)) {
                echo "Error updating table $table: " . $conn->error;
              }
            }

            $tablesToUpdateWithoutProfCode = [
              "tbl_prof_schedstatus"
            ];

            foreach ($tablesToUpdateWithoutProfCode as $table) {
              $updateTableQuery = "UPDATE $table 
 SET  prof_sched_code = '$NewProfSchedCode'
 WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              $conn->query($updateTableQuery);
            }
          } else {
            echo "Error updating prof_code in tbl_prof: " . $conn->error;
          }
          $counter++;
        }

        $reNumberQuery = "SELECT prof_code 
      FROM tbl_prof 
      WHERE prof_type = 'Job Order' AND employ_status = 3
      AND dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit =?
      ORDER BY CAST(SUBSTRING(prof_code, LOCATE(' ', prof_code) + 1, LOCATE(' -', prof_code) - LOCATE(' ', prof_code) - 1) AS UNSIGNED)";

        $stmt = $conn->prepare($reNumberQuery);
        $stmt->bind_param("ssss", $deptCode, $semester, $ay_code, $profUnit);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
          die("Error with reNumberQuery: " . $conn->error);
        }

        $counter = 1; // Start numbering from 1

        while ($row = $result->fetch_assoc()) {
          $profCode = $row['prof_code'];

          // Validate prof_code format
          if (strpos($profCode, '-') === false || strpos($profCode, ' ') === false) {
            // echo "Invalid prof_code format: $profCode<br>";
            continue;
          }

          // Generate new prof_code for Job Order
          $newProfCode = "$profUnit $counter - " . substr($profCode, strpos($profCode, '-') + 2);

          // Update tbl_prof
          $updateJobOrderQuery = "UPDATE tbl_prof 
                SET prof_code = ? 
                WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit = ?";
          $stmt = $conn->prepare($updateJobOrderQuery);
          $stmt->bind_param("ssssss", $newProfCode, $profCode, $deptCode, $semester, $ay_code, $profUnit);

          if ($stmt->execute()) {
            // Sanitize and create the necessary schedule table names
            $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $deptCode . "_" . $ay_code);
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
            $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");

            // Construct the new and old prof_sched_code
            $NewProfSchedCode = $newProfCode . "_" . $ay_code;
            $oldProfSchedCode = $profCode . "_" . $ay_code;

            $tablesToUpdateWithName = [
              "tbl_prof_acc"
            ];

            foreach ($tablesToUpdateWithName as $table) {
              // Use prepared statements to avoid SQL injection
              $updateTableQuery = "UPDATE $table 
SET prof_code = ? 
WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

              $stmt = $conn->prepare($updateTableQuery);
              $stmt->bind_param("sssss", $newProfCode, $lastName, $deptCode, $semester, $ay_code);
              $stmt->execute();
              $stmt->close();
            }

            // Update related tables that store the prof_code
            $tablesToUpdate = [
              "tbl_psched_counter",
              "tbl_pcontact_counter",
              "tbl_pcontact_schedstatus",
              "tbl_psched",
              $sanitized_prof_sched_code,
              $sanitized_pcontact_sched_code
            ];

            foreach ($tablesToUpdate as $table) {
              $updateTableQuery = "UPDATE $table 
                      SET prof_code = '$newProfCode', prof_sched_code = '$NewProfSchedCode'
                      WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              if (!$conn->query($updateTableQuery)) {
                echo "Error updating table $table: " . $conn->error;
              }
            }

            $tablesToUpdateWithSchedCode = [
              "tbl_assigned_course",
              $sanitized_room_sched_code,
              $sanitized_section_sched_code,
              $sanitized_ccl_room_sched_code
            ];

            foreach ($tablesToUpdateWithSchedCode as $table) {
              $updateTableQuery = "UPDATE $table 
SET prof_code = '$newProfCode', prof_name = '$fullName'  
WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              if (!$conn->query($updateTableQuery)) {
                echo "Error updating table $table: " . $conn->error;
              }
            }

            $tablesToUpdateWithoutProfCode = [
              "tbl_prof_schedstatus"
            ];

            foreach ($tablesToUpdateWithoutProfCode as $table) {
              $updateTableQuery = "UPDATE $table 
SET  prof_sched_code = '$NewProfSchedCode'
WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              $conn->query($updateTableQuery);
            }
          } else {
            echo "Error updating prof_code in tbl_prof: " . $conn->error;
          }
          $counter++;
        }
      }
    }


    if ($employStatus === 1) {
      $ptQuery = "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(prof_code, 'PT ', -1), ' - ', 1) AS UNSIGNED)) AS max_number 
    FROM tbl_prof 
    WHERE prof_code LIKE '$profUnit PT %' AND dept_code = '$deptCode' AND prof_unit = '$profUnit'";
      $ptResult = $conn->query($ptQuery);
      $newNumber = $ptResult && $ptResult->num_rows > 0 ? (int) $ptResult->fetch_assoc()['max_number'] + 1 : 1;

      $updatedProfCode = "$profUnit PT $newNumber - $lastName";

      // Step 4: Update the selected professor's details
      $updateProfQuery = "
          UPDATE tbl_prof 
          SET prof_code = '$updatedProfCode', 
              prof_type = 'Job Order', 
              employ_status = 1, 
               reg_adviser = $reg_adviser, 
              academic_rank = '$academicRank', 
              prof_name = '$fullName' 
          WHERE prof_code = '$oldProfCode' 
            AND dept_code = '$deptCode' 
            AND semester = '$semester' 
            AND ay_code = '$ay_code'
            AND prof_unit = '$profUnit'";

      if ($conn->query($updateProfQuery) === TRUE) {

if (isset($_POST['reg_adviser'])) {
    $submittedSections = $_POST['section_code'] ?? [];

    foreach ($submittedSections as $section_code) {
        // Check if the section is already assigned to this adviser in this AY
        $check = $conn->query("
            SELECT * FROM tbl_registration_adviser 
            WHERE section_code = '$section_code' 
              AND dept_code = '$deptCode'
              AND current_ay_code = '$ay_code'
        ");

        // If not yet assigned, insert the new assignment
        if (!$check || $check->num_rows == 0) {
            // Get the curriculum of the section
            $currResult = $conn->query("
                SELECT curriculum FROM tbl_section 
                WHERE section_code = '$section_code' 
                  AND dept_code = '$deptCode'
            ");

            if ($currResult->num_rows > 0) {
                $curriculum = $currResult->fetch_assoc()['curriculum'];

                // Derive the program_code from section_code (assumes space-delimited)
                $program_code = explode(' ', $section_code)[0];

                // Get the number of years for the program
                $yearQuery = "
                    SELECT num_year FROM tbl_program 
                    WHERE program_code = '$program_code' 
                      AND dept_code = '$deptCode' 
                      AND curriculum = '$curriculum'
                ";
                $yearResult = $conn->query($yearQuery);
                $num_year = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc()['num_year'] : null;

                // Insert new registration adviser assignment
                $insert = "
                    INSERT INTO tbl_registration_adviser 
                        (reg_adviser, dept_code, section_code, ay_code_assign, current_ay_code, num_year)
                    VALUES 
                        ('$fullName', '$deptCode', '$section_code', '$ay_code', '$ay_code', '$num_year')
                ";
                $conn->query($insert);
            }
        }

        // Ensure the professor is marked as a registration adviser in both tables
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 1 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 1 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // If no sections were submitted, unset the adviser flag
    if (empty($submittedSections)) {
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 0 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 0 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // Show success modal
    echo '
    <script>
        window.onload = function() {
            $("#sectionSuccessModal").modal("show");
        }
    </script>
    ';

    // Show errors in modal (only if blocked deletions occurred)
    // if (!empty($deletionErrors)) {
    //     $errorListHTML = "<ul>";
    //     foreach ($deletionErrors as $errorMsg) {
    //         $errorListHTML .= "<li>$errorMsg</li>";
    //     }
    //     $errorListHTML .= "</ul>";

    //     echo "
    //     <script>
    //         document.addEventListener('DOMContentLoaded', function () {
    //             document.getElementById('sectionDeleteErrorMessage').innerHTML = `$errorListHTML`;
    //             new bootstrap.Modal(document.getElementById('sectionDeleteErrorModal')).show();
    //         });
    //     </script>
    //     ";
    // }
}



        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);

        // Prepared statement for fetching teaching_hrs
        $fetchTeachingHoursQuery = $conn->prepare("SELECT teaching_hrs FROM tbl_psched_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
        $fetchTeachingHoursQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
        $fetchTeachingHoursQuery->execute();
        $teachingHoursResult = $fetchTeachingHoursQuery->get_result();

        if ($teachingHoursResult && $teachingHoursResult->num_rows > 0) {
          $row = $teachingHoursResult->fetch_assoc();
          $teaching_hours = $row['teaching_hrs'];
          // echo "Teaching Hours: $teaching_hours<br>";

          $consultation_hrs = ($teaching_hours >= 18) ? 2 : 0;
          // echo "Calculated Consultation Hours: $consultation_hrs<br>";

          // Prepare the query for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

          if ($updateConsultationHrsQuery->execute()) {
            echo '
            <script type="text/javascript">
                window.onload = function() {
                    // Show the modal after successful update
                    $("#updateSuccessModal").modal("show");
                }
            </script>
            ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }

          // Prepared statement for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
          if ($updateConsultationHrsQuery->execute()) {
            echo '
       <script type="text/javascript">
           window.onload = function() {
               // Show the modal after successful update
               $("#updateSuccessModal").modal("show");
           }
       </script>
       ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }
          // Update consultation_hrs in tbl_pcontact_counter
          $updatePcontactConsultationHrsQuery = $conn->prepare("
 UPDATE tbl_pcontact_counter 
 SET consultation_hrs = ? 
 WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
          $updatePcontactConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

          if ($updatePcontactConsultationHrsQuery->execute()) {
            // Fetch the updated consultation_hrs and current_consultation_hrs from tbl_pcontact_counter
            $fetchPcontactConsultationHrsQuery = $conn->prepare("
     SELECT consultation_hrs, current_consultation_hrs 
     FROM tbl_pcontact_counter 
     WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
 ");
            $fetchPcontactConsultationHrsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
            $fetchPcontactConsultationHrsQuery->execute();
            $pcontactResult = $fetchPcontactConsultationHrsQuery->get_result();

            if ($pcontactResult && $pcontactResult->num_rows > 0) {
              $row = $pcontactResult->fetch_assoc();
              $updatedConsultationHrs = $row['consultation_hrs'];
              $currentConsultationHrs = $row['current_consultation_hrs'];

              // Check if updated consultation_hrs is less than current_consultation_hrs
              if ($updatedConsultationHrs < $currentConsultationHrs) {
                // Delete records from $sanitized_pcontact_sched_code
                $deletePcontactSchedQuery = $conn->prepare("
             DELETE FROM $sanitized_pcontact_sched_code 
             WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
         ");
                $deletePcontactSchedQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);

                if ($deletePcontactSchedQuery->execute()) {
                  // Check if there are still records in $sanitized_pcontact_sched_code
                  $checkRemainingRecordsQuery = $conn->prepare("
                 SELECT COUNT(*) AS record_count 
                 FROM $sanitized_pcontact_sched_code 
                 WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
             ");
                  $checkRemainingRecordsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                  $checkRemainingRecordsQuery->execute();
                  $remainingRecordsResult = $checkRemainingRecordsQuery->get_result();

                  if ($remainingRecordsResult && $remainingRecordsResult->num_rows > 0) {
                    $recordCountRow = $remainingRecordsResult->fetch_assoc();
                    $recordCount = $recordCountRow['record_count'];

                    if ($recordCount == 0) {
                      // Delete records from tbl_pcontact_schedstatus
                      $deletePcontactSchedStatusQuery = $conn->prepare("
                         DELETE FROM tbl_pcontact_schedstatus 
                         WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                     ");
                      $deletePcontactSchedStatusQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                      $deletePcontactSchedStatusQuery->execute();

                      // Delete records from tbl_pcontact_counter
                      $deletePcontactCounterQuery = $conn->prepare("
                         DELETE FROM tbl_pcontact_counter 
                         WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                     ");
                      $deletePcontactCounterQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                      $deletePcontactCounterQuery->execute();

                      echo '
                     <script type="text/javascript">
                         window.onload = function() {
                             $("#deleteAllSuccessModal").modal("show");
                         }
                     </script>
                     ';
                    }
                  }
                } else {
                  echo "Error deleting schedule: " . $conn->error;
                }
              }
            } else {
              // echo "No data found in tbl_pcontact_counter for the specified criteria.";
            }
          } else {
            echo "Error updating consultation hours in tbl_pcontact_counter: " . $conn->error;
          }
        }

        $reNumberQuery = "SELECT prof_code 
      FROM tbl_prof 
      WHERE prof_type = 'Job Order' AND employ_status = 3
      AND dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit = ?
      ORDER BY CAST(SUBSTRING(prof_code, LOCATE(' ', prof_code) + 1, LOCATE(' -', prof_code) - LOCATE(' ', prof_code) - 1) AS UNSIGNED)";

        $stmt = $conn->prepare($reNumberQuery);
        $stmt->bind_param("ssss", $deptCode, $semester, $ay_code, $profUnit);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
          die("Error with reNumberQuery: " . $conn->error);
        }

        $counter = 1; // Start numbering from 1

        while ($row = $result->fetch_assoc()) {
          $profCode = $row['prof_code'];

          // Validate prof_code format
          if (strpos($profCode, '-') === false || strpos($profCode, ' ') === false) {
            echo "Invalid prof_code format: $profCode<br>";
            continue;
          }

          // Generate new prof_code for Job Order
          $newProfCode = "$profUnit $counter - " . substr($profCode, strpos($profCode, '-') + 2);

          // Update tbl_prof
          $updateJobOrderQuery = "UPDATE tbl_prof 
                SET prof_code = ? 
                WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit = ? ";
          $stmt = $conn->prepare($updateJobOrderQuery);
          $stmt->bind_param("ssssss", $newProfCode, $profCode, $deptCode, $semester, $ay_code, $profUnit);

          if ($stmt->execute()) {
            // Sanitize and create the necessary schedule table names
            $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $deptCode . "_" . $ay_code);
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
            $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");
            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$deptCode}_{$ay_code}");

            // Construct the new and old prof_sched_code
            $NewProfSchedCode = $newProfCode . "_" . $ay_code;
            $oldProfSchedCode = $profCode . "_" . $ay_code;

            $tablesToUpdateWithName = [
              "tbl_prof_acc"
            ];

            foreach ($tablesToUpdateWithName as $table) {
              // Use prepared statements to avoid SQL injection
              $updateTableQuery = "UPDATE $table 
                                SET prof_code = ? 
                                WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

              $stmt = $conn->prepare($updateTableQuery);
              $stmt->bind_param("sssss", $newProfCode, $lastName, $deptCode, $semester, $ay_code);
              $stmt->execute();
              $stmt->close();
            }

            // Update related tables that store the prof_code
            $tablesToUpdate = [
              "tbl_psched_counter",
              "tbl_pcontact_counter",
              "tbl_pcontact_schedstatus",
              "tbl_psched",
              $sanitized_prof_sched_code,
              $sanitized_pcontact_sched_code
            ];

            foreach ($tablesToUpdate as $table) {
              $updateTableQuery = "UPDATE $table 
                      SET prof_code = '$newProfCode', prof_sched_code = '$NewProfSchedCode'
                      WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              if (!$conn->query($updateTableQuery)) {
                echo "Error updating table $table: " . $conn->error;
              }
            }

            $tablesToUpdateWithSchedCode = [
              "tbl_assigned_course",
              $sanitized_room_sched_code,
              $sanitized_section_sched_code,
              $sanitized_ccl_room_sched_code
            ];

            foreach ($tablesToUpdateWithSchedCode as $table) {
              $updateTableQuery = "UPDATE $table 
                                SET prof_code = '$newProfCode', prof_name = '$fullName'  
                                WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              if (!$conn->query($updateTableQuery)) {
                echo "Error updating table $table: " . $conn->error;
              }
            }

            $tablesToUpdateWithoutProfCode = [
              "tbl_prof_schedstatus"
            ];

            foreach ($tablesToUpdateWithoutProfCode as $table) {
              $updateTableQuery = "UPDATE $table 
                                  SET  prof_sched_code = '$NewProfSchedCode'
                                  WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              $conn->query($updateTableQuery);
            }
          } else {
            echo "Error updating prof_code in tbl_prof: " . $conn->error;
          }
          $counter++;
        }
      }
    } elseif ($profType === 'Job Order' && $employStatus === 0) {
      $employStatus = 3;

      // Fetch maximum number for Job Order Instructor with employ_status = 3
      $maxNumberQuery = "SELECT MAX(CAST(SUBSTRING(prof_code, LOCATE(' ', prof_code) + 1, 
                                 LOCATE(' -', prof_code) - LOCATE(' ', prof_code) - 1) AS UNSIGNED)) AS max_number 
                       FROM tbl_prof 
                       WHERE prof_type = 'Job Order' 
                         AND employ_status = $employStatus 
                         AND dept_code = '$deptCode' 
                         AND semester = '$semester' 
                         AND ay_code = '$ay_code'
                         AND prof_unit = '$profUnit'";

      $maxNumberResult = $conn->query($maxNumberQuery);
      $newNumber = ($maxNumberResult && $maxNumberResult->num_rows > 0)
        ? (int) $maxNumberResult->fetch_assoc()['max_number'] + 1
        : 1;


      // Prepare the updated prof_code
      $updatedProfCode = "$profUnit $newNumber - $lastName";

      // Update the selected professor to Job Order
      $updateSelectedQuery = "UPDATE tbl_prof 
                SET prof_code = '$updatedProfCode', 
                    prof_type = 'Job Order', 
                    employ_status = 3, 
                    reg_adviser = $reg_adviser, 
                    academic_rank = '$academicRank',
                    prof_name = '$fullName'
                WHERE prof_code = '$oldProfCode' 
                  AND dept_code = '$deptCode' 
                  AND semester = '$semester' 
                  AND ay_code = '$ay_code'
                  AND prof_unit = '$profUnit'";

      if ($conn->query($updateSelectedQuery) === TRUE) {
if (isset($_POST['reg_adviser'])) {
    $submittedSections = $_POST['section_code'] ?? [];

    foreach ($submittedSections as $section_code) {
        // Check if the section is already assigned to this adviser in this AY
        $check = $conn->query("
            SELECT * FROM tbl_registration_adviser 
            WHERE section_code = '$section_code' 
              AND dept_code = '$deptCode'
              AND current_ay_code = '$ay_code'
        ");

        // If not yet assigned, insert the new assignment
        if (!$check || $check->num_rows == 0) {
            // Get the curriculum of the section
            $currResult = $conn->query("
                SELECT curriculum FROM tbl_section 
                WHERE section_code = '$section_code' 
                  AND dept_code = '$deptCode'
            ");

            if ($currResult->num_rows > 0) {
                $curriculum = $currResult->fetch_assoc()['curriculum'];

                // Derive the program_code from section_code (assumes space-delimited)
                $program_code = explode(' ', $section_code)[0];

                // Get the number of years for the program
                $yearQuery = "
                    SELECT num_year FROM tbl_program 
                    WHERE program_code = '$program_code' 
                      AND dept_code = '$deptCode' 
                      AND curriculum = '$curriculum'
                ";
                $yearResult = $conn->query($yearQuery);
                $num_year = ($yearResult && $yearResult->num_rows > 0) ? $yearResult->fetch_assoc()['num_year'] : null;

                // Insert new registration adviser assignment
                $insert = "
                    INSERT INTO tbl_registration_adviser 
                        (reg_adviser, dept_code, section_code, ay_code_assign, current_ay_code, num_year)
                    VALUES 
                        ('$fullName', '$deptCode', '$section_code', '$ay_code', '$ay_code', '$num_year')
                ";
                $conn->query($insert);
            }
        }

        // Ensure the professor is marked as a registration adviser in both tables
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 1 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 1 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // If no sections were submitted, unset the adviser flag
    if (empty($submittedSections)) {
        $conn->query("
            UPDATE tbl_prof_acc SET reg_adviser = 0 
            WHERE last_name = '$lastName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
        $conn->query("
            UPDATE tbl_prof SET reg_adviser = 0 
            WHERE prof_name = '$fullName' 
              AND dept_code = '$deptCode' 
              AND ay_code = '$ay_code' 
              AND semester = '$semester'
        ");
    }

    // Show success modal
    echo '
    <script>
        window.onload = function() {
            $("#sectionSuccessModal").modal("show");
        }
    </script>
    ';

    // Show errors in modal (only if blocked deletions occurred)
    // if (!empty($deletionErrors)) {
    //     $errorListHTML = "<ul>";
    //     foreach ($deletionErrors as $errorMsg) {
    //         $errorListHTML .= "<li>$errorMsg</li>";
    //     }
    //     $errorListHTML .= "</ul>";

    //     echo "
    //     <script>
    //         document.addEventListener('DOMContentLoaded', function () {
    //             document.getElementById('sectionDeleteErrorMessage').innerHTML = `$errorListHTML`;
    //             new bootstrap.Modal(document.getElementById('sectionDeleteErrorModal')).show();
    //         });
    //     </script>
    //     ";
    // }
}




        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);

        // Prepared statement for fetching teaching_hrs
        $fetchTeachingHoursQuery = $conn->prepare("SELECT teaching_hrs FROM tbl_psched_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
        $fetchTeachingHoursQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
        $fetchTeachingHoursQuery->execute();
        $teachingHoursResult = $fetchTeachingHoursQuery->get_result();

        if ($teachingHoursResult && $teachingHoursResult->num_rows > 0) {
          $row = $teachingHoursResult->fetch_assoc();
          $teaching_hours = $row['teaching_hrs'];
          // echo "Teaching Hours: $teaching_hours<br>";

          $consultation_hrs = ($teaching_hours >= 18) ? 2 : 0;
          // echo "Calculated Consultation Hours: $consultation_hrs<br>";

          // Prepare the query for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

          if ($updateConsultationHrsQuery->execute()) {
            echo '
            <script type="text/javascript">
                window.onload = function() {
                    // Show the modal after successful update
                    $("#updateSuccessModal").modal("show");
                }
            </script>
            ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }

          // Prepared statement for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
          if ($updateConsultationHrsQuery->execute()) {
            echo '
            <script type="text/javascript">
                window.onload = function() {
                    // Show the modal after successful update
                    $("#updateSuccessModal").modal("show");
                }
            </script>
            ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }

          // Prepared statement for updating consultation_hrs
          $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
          $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
          if ($updateConsultationHrsQuery->execute()) {
            echo '
     <script type="text/javascript">
         window.onload = function() {
             // Show the modal after successful update
             $("#updateSuccessModal").modal("show");
         }
     </script>
     ';
          } else {
            echo "Error updating consultation hours: " . $conn->error;
          }
          // Update consultation_hrs in tbl_pcontact_counter
          $updatePcontactConsultationHrsQuery = $conn->prepare("
UPDATE tbl_pcontact_counter 
SET consultation_hrs = ? 
WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
          $updatePcontactConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

          if ($updatePcontactConsultationHrsQuery->execute()) {
            // Fetch the updated consultation_hrs and current_consultation_hrs from tbl_pcontact_counter
            $fetchPcontactConsultationHrsQuery = $conn->prepare("
   SELECT consultation_hrs, current_consultation_hrs 
   FROM tbl_pcontact_counter 
   WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
            $fetchPcontactConsultationHrsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
            $fetchPcontactConsultationHrsQuery->execute();
            $pcontactResult = $fetchPcontactConsultationHrsQuery->get_result();

            if ($pcontactResult && $pcontactResult->num_rows > 0) {
              $row = $pcontactResult->fetch_assoc();
              $updatedConsultationHrs = $row['consultation_hrs'];
              $currentConsultationHrs = $row['current_consultation_hrs'];

              // Check if updated consultation_hrs is less than current_consultation_hrs
              if ($updatedConsultationHrs < $currentConsultationHrs) {
                // Delete records from $sanitized_pcontact_sched_code
                $deletePcontactSchedQuery = $conn->prepare("
           DELETE FROM $sanitized_pcontact_sched_code 
           WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
       ");
                $deletePcontactSchedQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);

                if ($deletePcontactSchedQuery->execute()) {
                  // Check if there are still records in $sanitized_pcontact_sched_code
                  $checkRemainingRecordsQuery = $conn->prepare("
               SELECT COUNT(*) AS record_count 
               FROM $sanitized_pcontact_sched_code 
               WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
           ");
                  $checkRemainingRecordsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                  $checkRemainingRecordsQuery->execute();
                  $remainingRecordsResult = $checkRemainingRecordsQuery->get_result();

                  if ($remainingRecordsResult && $remainingRecordsResult->num_rows > 0) {
                    $recordCountRow = $remainingRecordsResult->fetch_assoc();
                    $recordCount = $recordCountRow['record_count'];

                    if ($recordCount == 0) {
                      // Delete records from tbl_pcontact_schedstatus
                      $deletePcontactSchedStatusQuery = $conn->prepare("
                       DELETE FROM tbl_pcontact_schedstatus 
                       WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                   ");
                      $deletePcontactSchedStatusQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                      $deletePcontactSchedStatusQuery->execute();

                      // Delete records from tbl_pcontact_counter
                      $deletePcontactCounterQuery = $conn->prepare("
                       DELETE FROM tbl_pcontact_counter 
                       WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                   ");
                      $deletePcontactCounterQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                      $deletePcontactCounterQuery->execute();

                      echo '
                   <script type="text/javascript">
                       window.onload = function() {
                           $("#deleteAllSuccessModal").modal("show");
                       }
                   </script>
                   ';
                    }
                  }
                } else {
                  echo "Error deleting schedule: " . $conn->error;
                }
              }
            } else {
              // echo "No data found in tbl_pcontact_counter for the specified criteria.";
            }
          } else {
            echo "Error updating consultation hours in tbl_pcontact_counter: " . $conn->error;
          }
        }


        // Step 1: Fetch all part-time Job Order Instructor
        $reNumberPartTimeQuery = "SELECT prof_code 
                        FROM tbl_prof 
                        WHERE prof_type = 'Job Order' AND employ_status = 1
                          AND dept_code = '$deptCode' 
                          AND semester = '$semester' 
                          AND ay_code = '$ay_code'
                          AND prof_unit = '$profUnit'
                        ORDER BY CAST(SUBSTRING(prof_code, LOCATE('PT ', prof_code) + 3, 
                              LOCATE(' -', prof_code) - LOCATE('PT ', prof_code) - 3) AS UNSIGNED)";

        $partTimeResult = $conn->query($reNumberPartTimeQuery);

        // Step 2: Store results in an array
        $profCodes = [];
        while ($row = $partTimeResult->fetch_assoc()) {
          $profCodes[] = $row['prof_code'];
        }

        // Step 3: Update prof_code with continuous numbering
        $counter = 1;
        foreach ($profCodes as $profCode) {
          // Generate new prof_code
          $newProfCode = "$profUnit PT $counter - " . substr($profCode, strpos($profCode, '-') + 2);

          // Update the professor's code in tbl_prof
          $updatePartTimeQuery = "UPDATE tbl_prof 
                                    SET prof_code = '$newProfCode' 
                                    WHERE prof_code = '$profCode' 
                                      AND dept_code = '$deptCode' 
                                      AND semester = '$semester' 
                                      AND ay_code = '$ay_code'
                                      AND prof_unit = '$profUnit'";

          if ($conn->query($updatePartTimeQuery) === TRUE) {

            // Update the room and section schedules
            $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

            // Update prof_sched_code
            $NewProfSchedCode = $newProfCode . "_" . $ay_code;
            $oldProfSchedCode = $profCode . "_" . $ay_code;

            $tablesToUpdateWithName = [
              "tbl_prof_acc"
            ];

            foreach ($tablesToUpdateWithName as $table) {
              // Use prepared statements to avoid SQL injection
              $updateTableQuery = "UPDATE $table 
                                 SET prof_code = ? 
                                 WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

              $stmt = $conn->prepare($updateTableQuery);
              $stmt->bind_param("sssss", $newProfCode, $lastName, $deptCode, $semester, $ay_code);
              $stmt->execute();
              $stmt->close();
            }

            // Update related tables that store the prof_code
            $tablesToUpdate = [
              "tbl_psched_counter",
              "tbl_pcontact_counter",
              "tbl_pcontact_schedstatus",
              "tbl_psched",
              $sanitized_prof_sched_code,
              $sanitized_pcontact_sched_code
            ];

            foreach ($tablesToUpdate as $table) {
              $updateTableQuery = "UPDATE $table 
                                SET prof_code = '$newProfCode', 
                                     prof_sched_code = '$NewProfSchedCode'
                                  WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              if (!$conn->query($updateTableQuery)) {
                echo "Error updating table $table: " . $conn->error;
              }
            }

            $tablesToUpdateWithSchedCode = [
              "tbl_assigned_course",
              $sanitized_room_sched_code,
              $sanitized_section_sched_code,
              $sanitized_ccl_room_sched_code
            ];

            foreach ($tablesToUpdateWithSchedCode as $table) {
              $updateTableQuery = "UPDATE $table 
                               SET prof_code = '$newProfCode', prof_name = '$fullName'  
                               WHERE prof_code = '$profCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              if (!$conn->query($updateTableQuery)) {
                echo "Error updating table $table: " . $conn->error;
              }
            }

            $tablesToUpdateWithoutProfCode = [
              "tbl_prof_schedstatus"
            ];

            foreach ($tablesToUpdateWithoutProfCode as $table) {
              $updateTableQuery = "UPDATE $table 
                                 SET  prof_sched_code = '$NewProfSchedCode'
                                 WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
              $conn->query($updateTableQuery);
            }
          } else {
            echo "Error updating prof_code in tbl_prof: " . $conn->error;
          }
          $counter++;
        }
      }
    }

    // Update the prof_code in tbl_prof for the selected professor
    $updateQuery = "UPDATE tbl_prof 
                  SET prof_code = '$updatedProfCode', 
                      prof_type = '$profType', 
                      academic_rank = '$academicRank', 
                      prof_name = '$fullName', 
                      employ_status = '$employStatus' 
                  WHERE prof_code = '$oldProfCode' 
                  AND dept_code = '$deptCode' 
                  AND semester = '$semester' 
                  AND ay_code = '$ay_code'
                  AND prof_unit = '$profUnit'";

    if ($conn->query($updateQuery) === TRUE) {
      echo '
<script type="text/javascript">
    window.onload = function() {
        // Show the modal after successful update
        $("#updateSuccessModal").modal("show");
    }
</script>
';

      // Sanitizing table names
      $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
      $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
      $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
      $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
      $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

      // Update prof_sched_code
      $updatedProfSchedCode = $updatedProfCode . "_" . $ay_code;
      $oldProfSchedCode = $oldProfCode . "_" . $ay_code;

      // Prepared statement for fetching teaching_hrs
      $fetchTeachingHoursQuery = $conn->prepare("SELECT teaching_hrs FROM tbl_psched_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
      $fetchTeachingHoursQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
      $fetchTeachingHoursQuery->execute();
      $teachingHoursResult = $fetchTeachingHoursQuery->get_result();

      if ($teachingHoursResult && $teachingHoursResult->num_rows > 0) {
        $row = $teachingHoursResult->fetch_assoc();
        $teaching_hours = $row['teaching_hrs'];
        // echo "Teaching Hours: $teaching_hours<br>";

        // Calculate consultation_hrs
        $consultation_hrs = 0; // Default to 0 if not calculated
        if ($profType === "Regular") {
          $consultation_hrs = $teaching_hours / 3;
        } elseif ($profType === "Job Order") {
          $consultation_hrs = ($teaching_hours >= 18) ? 2 : 0;
        }
        // echo "Calculated Consultation Hours: $consultation_hrs<br>";

        // Prepared statement for updating consultation_hrs
        $updateConsultationHrsQuery = $conn->prepare("UPDATE tbl_psched_counter SET consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?");
        $updateConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);
        if ($updateConsultationHrsQuery->execute()) {
          echo '
          <script type="text/javascript">
              window.onload = function() {
                  // Show the modal after successful update
                  $("#updateSuccessModal").modal("show");
              }
          </script>
          ';
        } else {
          echo "Error updating consultation hours: " . $conn->error;
        }
        // Update consultation_hrs in tbl_pcontact_counter
        $updatePcontactConsultationHrsQuery = $conn->prepare("
    UPDATE tbl_pcontact_counter 
    SET consultation_hrs = ? 
    WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
");
        $updatePcontactConsultationHrsQuery->bind_param('dssss', $consultation_hrs, $oldProfCode, $semester, $ay_code, $dept_code);

        if ($updatePcontactConsultationHrsQuery->execute()) {
          // Fetch the updated consultation_hrs and current_consultation_hrs from tbl_pcontact_counter
          $fetchPcontactConsultationHrsQuery = $conn->prepare("
        SELECT consultation_hrs, current_consultation_hrs 
        FROM tbl_pcontact_counter 
        WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
    ");
          $fetchPcontactConsultationHrsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
          $fetchPcontactConsultationHrsQuery->execute();
          $pcontactResult = $fetchPcontactConsultationHrsQuery->get_result();

          if ($pcontactResult && $pcontactResult->num_rows > 0) {
            $row = $pcontactResult->fetch_assoc();
            $updatedConsultationHrs = $row['consultation_hrs'];
            $currentConsultationHrs = $row['current_consultation_hrs'];

            // Check if updated consultation_hrs is less than current_consultation_hrs
            if ($updatedConsultationHrs < $currentConsultationHrs) {
              // Delete records from $sanitized_pcontact_sched_code
              $deletePcontactSchedQuery = $conn->prepare("
                DELETE FROM $sanitized_pcontact_sched_code 
                WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
            ");
              $deletePcontactSchedQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);

              if ($deletePcontactSchedQuery->execute()) {
                // Check if there are still records in $sanitized_pcontact_sched_code
                $checkRemainingRecordsQuery = $conn->prepare("
                    SELECT COUNT(*) AS record_count 
                    FROM $sanitized_pcontact_sched_code 
                    WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                ");
                $checkRemainingRecordsQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                $checkRemainingRecordsQuery->execute();
                $remainingRecordsResult = $checkRemainingRecordsQuery->get_result();

                if ($remainingRecordsResult && $remainingRecordsResult->num_rows > 0) {
                  $recordCountRow = $remainingRecordsResult->fetch_assoc();
                  $recordCount = $recordCountRow['record_count'];

                  if ($recordCount == 0) {
                    // Delete records from tbl_pcontact_schedstatus
                    $deletePcontactSchedStatusQuery = $conn->prepare("
                            DELETE FROM tbl_pcontact_schedstatus 
                            WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                        ");
                    $deletePcontactSchedStatusQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                    $deletePcontactSchedStatusQuery->execute();

                    // Delete records from tbl_pcontact_counter
                    $deletePcontactCounterQuery = $conn->prepare("
                            DELETE FROM tbl_pcontact_counter 
                            WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?
                        ");
                    $deletePcontactCounterQuery->bind_param('ssss', $oldProfCode, $semester, $ay_code, $dept_code);
                    $deletePcontactCounterQuery->execute();

                    echo '
                        <script type="text/javascript">
                            window.onload = function() {
                                $("#deleteAllSuccessModal").modal("show");
                            }
                        </script>
                        ';
                  }
                }
              } else {
                echo "Error deleting schedule: " . $conn->error;
              }
            }
          } else {
            // echo "No data found in tbl_pcontact_counter for the specified criteria.";
          }
        } else {
          echo "Error updating consultation hours in tbl_pcontact_counter: " . $conn->error;
        }
      }

      $tablesToUpdateWithName = [
        "tbl_prof_acc"
      ];

      foreach ($tablesToUpdateWithName as $table) {
        // Use prepared statements to avoid SQL injection
        $updateTableQuery = "UPDATE $table 
                             SET prof_code = ? 
                             WHERE last_name = ? AND dept_code = ? AND semester = ? AND ay_code = ?";

        $stmt = $conn->prepare($updateTableQuery);
        $stmt->bind_param("sssss", $updatedProfCode, $lastName, $deptCode, $semester, $ay_code);
        $stmt->execute();
        $stmt->close();
      }

      // Update related tables
      $tablesToUpdateWithProfSchedCode = [
        "tbl_psched_counter",
        "tbl_pcontact_counter",
        "tbl_pcontact_schedstatus",
        "tbl_psched",
        $sanitized_prof_sched_code,
        $sanitized_pcontact_sched_code
      ];

      foreach ($tablesToUpdateWithProfSchedCode as $table) {
        $updateTableQuery = "UPDATE $table 
                                 SET prof_code = '$updatedProfCode', 
                                     prof_sched_code = '$updatedProfSchedCode'
                                 WHERE prof_code = '$oldProfCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
        $conn->query($updateTableQuery);
      }

      $tablesToUpdateWithoutProfSchedCode = [
        "tbl_assigned_course",
        $sanitized_room_sched_code,
        $sanitized_section_sched_code,
        $sanitized_ccl_room_sched_code
      ];

      foreach ($tablesToUpdateWithoutProfSchedCode as $table) {
        $updateTableQuery = "UPDATE $table 
                                 SET prof_code = '$updatedProfCode', prof_name = '$fullName'
                                 WHERE prof_code = '$oldProfCode' AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
        $conn->query($updateTableQuery);
      }


      $tablesToUpdateWithoutProfCode = [
        "tbl_prof_schedstatus"
      ];

      foreach ($tablesToUpdateWithoutProfCode as $table) {
        $updateTableQuery = "UPDATE $table 
                             SET  prof_sched_code = '$updatedProfSchedCode'
                             WHERE prof_sched_code = '$oldProfSchedCode'AND dept_code = '$deptCode' AND semester = '$semester' AND ay_code = '$ay_code'";
        $conn->query($updateTableQuery);
      }
    }
  }
}


// Handle GET request for filtering
if (isset($_GET['prof_unit'])) {
  $search_unit = $_GET['prof_unit'];
}
if (isset($_GET['prof_type'])) {
  $search_prof = $_GET['prof_type'];
}
if (isset($_GET['prof_code_name'])) {
  $search_prof_code_name = $_GET['prof_code_name'];
}

// Fetch records from the database with filtering
$sql = "SELECT * FROM tbl_prof WHERE dept_code = ?";
$params = [$dept_code];
$types = "s";

if (!empty($search_unit)) {
  $sql .= " AND prof_unit = ?";
  $params[] = $search_unit;
  $types .= "s";
}

if (!empty($search_prof)) {
  $sql .= " AND prof_type = ?";
  $params[] = $search_prof;
  $types .= "s";
}

if (!empty($search_prof_code_name)) {
  $sql .= " AND (prof_code LIKE ? OR prof_name LIKE ?)";
  $params[] = "%$search_prof_code_name%";
  $params[] = "%$search_prof_code_name%";
  $types .= "ss";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all filtered records
$records = [];
while ($row = $result->fetch_assoc()) {
  $records[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <title>Professor Input</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="orig-logo.png">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <link rel="stylesheet" href="../../../css/department_secretary/input_forms/prof_input.css">
</head>

<body>
  <?php
  if ($_SESSION['user_type'] == 'Department Chairperson' && $admin_college_code == $user_college_code): ?>
    <?php
    $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
    include($IPATH . "professor_navbar.php");
    ?>
  <?php else: ?>
    <?php
    $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>
  <?php endif; ?>

  <?php if ($user_type == "Department Chairperson") { ?>

    <h2 class="title">INSTRUCTOR LIST</h2>
  <?php } ?>
  <section class="prof-input">

    <ul class="nav nav-tabs" id="myTab" role="tablist">
      <?php if ($user_type == "Department Secretary") { ?>
        <li class="nav-item">
          <a class="nav-link" id="program-tab" href="program_input.php" aria-controls="program"
            aria-selected="false">Program Input</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="course-tab" href="course_input.php" aria-controls="course"
            aria-selected="false">Checklist Input</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="section-tab" href="section_input.php" aria-controls="section"
            aria-selected="false">Section Input</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" id="room-tab" href="classroom_input.php" aria-controls="room" aria-selected="false">Room
            Input</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" id="prof-tab" href="#" aria-controls="prof" aria-selected="false"
            data-bs-toggle="modal" data-bs-target="#profUnitModal">Instructor Input</a>
        </li>

        <li class="nav-item">
          <a class="nav-link " id="signatory-tab" href="signatory_input.php" aria-controls="signatory"
            aria-selected="true">Signatory Input</a>
        </li>

      <?php } ?>
    </ul>


    <div class="table-wrapper">
      <form id="prof-form" action="prof_input.php" method="POST">
        <table class="table">
          <thead>
            <tr>
              <th>Instructor Code</th>
              <th>Appointment</th>
              <th>Rank</th>
              <th>Name</th>
              <!-- <th>Reg Adviser</th> ✅ Added this -->
              <th>Teaching Hrs</th>
              <th>Prep Hrs</th>
              <th>Consultation Hrs</th>
              <th>Advicees
              </th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (isset($_SESSION['program_unit'])) {
              $program_unit = $_SESSION['program_unit'];

              if (isset($semester, $ay_code, $dept_code)) {
                $query = "
    SELECT 
        p.prof_code, 
        p.prof_type, 
        p.academic_rank, 
        p.employ_status, 
        p.reg_adviser,
        SUBSTRING_INDEX(SUBSTRING_INDEX(p.prof_name, ' ', -1), ' ', 1) AS last_name,
        COALESCE(c.teaching_hrs, 0) AS teaching_hrs,
        COALESCE(c.prep_hrs, 0) AS prep_hrs,
        COALESCE(c.consultation_hrs, 0) AS consultation_hrs,
        GROUP_CONCAT(DISTINCT r.section_code ORDER BY r.section_code SEPARATOR ', ') AS section_code
    FROM tbl_prof p
    LEFT JOIN tbl_psched_counter c 
        ON p.prof_code = c.prof_code 
        AND c.semester = '$semester' 
        AND c.ay_code = '$ay_code'
    LEFT JOIN tbl_registration_adviser r 
        ON r.reg_adviser = p.prof_name
        and r.current_ay_code = '$ay_code'
    WHERE p.dept_code = '$dept_code' 
        AND p.prof_unit = '$program_unit' 
        AND p.ay_code = '$ay_code' 
        AND p.semester = '$semester'
    GROUP BY p.prof_code
";
                $result = $conn->query($query);

                if ($result === FALSE) {
                  echo "Error fetching data: " . $conn->error;
                  exit;
                }
              }
            } else {
              echo "Program unit session not found.";
              exit;
            }
            ?>


            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr onclick="showModal(
    '<?= htmlspecialchars($row['prof_code']) ?>',
    '<?= htmlspecialchars($row['prof_type']) ?>',
    '<?= htmlspecialchars($row['academic_rank']) ?>',
    '<?= htmlspecialchars($row['last_name']) ?>',
    '<?= htmlspecialchars($row['employ_status']) ?>',
    '<?= htmlspecialchars($row['reg_adviser']) ?>',
    '<?= htmlspecialchars($row['section_code']) ?>'
)">
                  <td><?= htmlspecialchars($row['prof_code']) ?></td>
                  <td><?= htmlspecialchars($row['prof_type']) ?></td>
                  <td><?= htmlspecialchars($row['academic_rank']) ?></td>
                  <td><?= htmlspecialchars($row['last_name']) ?></td>
                  <td><?= htmlspecialchars($row['teaching_hrs']) ?></td>
                  <td><?= htmlspecialchars($row['prep_hrs']) ?></td>
                  <td><?= htmlspecialchars(round($row['consultation_hrs'])) ?></td>
                  <td title="<?= htmlspecialchars($row['section_code']) ?>">
                    <?= htmlspecialchars(mb_strimwidth($row['section_code'], 0, 20, '...')) ?>
                  </td>
                </tr>

              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="8">No Instructor Records Found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </form>
    </div>


    <!-- Modal -->
    <div class="modal" id="profModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body">
            <form id="edit-prof-form" method="POST" action="prof_input.php">
              <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
              <input type="hidden" id="employ_status" class="form-control" readonly>

              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="modal-title">Edit Instructor Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <!-- Professor Code -->
              <div class="mb-3">
                <!-- <label for="profCode" class="form-label">Professor Code</label> -->
                <input type="text" id="profCode" name="prof_code" class="form-control" readonly>
              </div>

              <!-- Professor Appointment -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <!-- <label for="profType" class="form-label">Professor Appointment</label> -->
                  <select id="profType" name="prof_type" class="form-select">
                    <option value="">Instructor Appointment</option>
                    <option value="Regular">Regular</option>
                    <option value="Job Order">Job Order</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <div class="form-check d-flex align-items-center justify-content-start">
                    <input class="form-check-input custom-checkbox" type="checkbox" id="employStatus"
                      name="employ_status" value="1">
                    <label class="form-check-label custom-label ms-2" for="employStatus">Part Time</label>
                  </div>
                </div>
              


              <!-- Red Adviser Checkbox -->
              <?php if ($user_type == "Department Chairperson") { ?>

              <div class="col-md-3">
                <div class="form-check d-flex align-items-center justify-content-center">
                  <input class="form-check-input custom-checkbox" type="checkbox" id="showSectionDropdown"
                    name="reg_adviser" value="1">
                  <label class="form-check-label custom-label ms-2" for="showSectionDropdown">Reg Adviser</label>
                </div>
              </div>
              </div>

              <!-- Section Dropdown Container -->

              <div class="mt-3" id="sectionDropdownContainer" style="display: none;">
                <div id="dropdownsWrapper">
                  <div class="row dropdown-group align-items-center mb-2">
                    <div class="col-10">
                      <select name="section_code[]" class="form-select section-select">
                        <option value="">Select Section</option>
                        <?php
                        $dept_code = $_SESSION['dept_code'] ?? '';
                        $semester = $_SESSION['semester'] ?? '';
                        $current_section = $_GET['current_section'] ?? '';
                        $ay_code = $_SESSION['ay_code'] ?? '';

                        $query = "
            SELECT section_code 
            FROM tbl_section 
            WHERE year_level = 1 
            AND dept_code = '$dept_code' 
            AND semester = '$semester'
            AND ay_code = '$ay_code'
            AND (
              section_code NOT IN (
                  SELECT section_code FROM tbl_registration_adviser
              )
              OR section_code = '$current_section'
            )
          ";
                        $result = mysqli_query($conn, $query);
                        $sectionOptions = [];
                        while ($row = mysqli_fetch_assoc($result)) {
                          $code = htmlspecialchars($row['section_code']);
                          echo "<option value=\"$code\">$code</option>";
                          $sectionOptions[] = "<option value=\\\"$code\\\">$code</option>";
                        }
                        ?>
                        <input type="hidden" id="sectionOptionsData"
                          value='[<?php echo implode(',', $sectionOptions); ?>]'>
                      </select>
                    </div>
                    <div class="col-2 d-flex gap-1">
                      <button type="button" class="w-50 add-dropdown-btn" title="Add Section">+</button>
                    </div>
                  </div>
                </div>
              </div>

               <?php } ?>

              <!-- JavaScript -->
              <script>
                document.addEventListener("DOMContentLoaded", function () {
                  const sectionDropdownContainer = document.getElementById("sectionDropdownContainer");
                  const dropdownsWrapper = document.getElementById("dropdownsWrapper");

                  // Show/hide the dropdown section
                  document.getElementById("showSectionDropdown").addEventListener("change", function () {
                    sectionDropdownContainer.style.display = this.checked ? "block" : "none";
                  });

                  // Delegate click for add/delete buttons
                  dropdownsWrapper.addEventListener("click", function (e) {
                    if (e.target.classList.contains("add-dropdown-btn")) {
                      const group = e.target.closest(".dropdown-group");
                      const newGroup = group.cloneNode(true);
                      const newSelect = newGroup.querySelector("select");

                      // Reset select value and HTML
                      const template = document.getElementById('section-options-template');
                      if (template) {
                        newSelect.innerHTML = template.innerHTML;
                      }
                      newSelect.value = "";

                      // Add delete button if not present
                      if (!newGroup.querySelector(".delete-dropdown-btn")) {
                        const btnCol = newGroup.querySelector(".col-2");
                        const deleteBtn = document.createElement("button");
                        deleteBtn.type = "button";
                        deleteBtn.className = "btn btn-outline-danger w-50 delete-dropdown-btn";
                        deleteBtn.style.fontWeight = "bold";
                        deleteBtn.title = "Remove Section";
                        deleteBtn.innerText = "−";
                        btnCol.appendChild(deleteBtn);
                      }

                      dropdownsWrapper.appendChild(newGroup);
                      updateDropdownOptions();
                    }

                    if (e.target.classList.contains("delete-dropdown-btn")) {
                      const group = e.target.closest(".dropdown-group");
                      group.remove();
                      updateDropdownOptions();
                    }
                  });

                  // Update dropdown options on change
                  dropdownsWrapper.addEventListener("change", function (e) {
                    if (e.target.classList.contains("section-select")) {
                      updateDropdownOptions();
                    }
                  });

                  function updateDropdownOptions() {
                    const selectedValues = Array.from(document.querySelectorAll(".section-select"))
                      .map(select => select.value)
                      .filter(val => val !== "");

                    const allDropdowns = document.querySelectorAll(".section-select");
                    const options = JSON.parse(document.getElementById("sectionOptionsData").value);

                    allDropdowns.forEach(select => {
                      const currentValue = select.value;
                      select.innerHTML = options.join('');

                      Array.from(select.options).forEach(option => {
                        if (option.value && option.value !== currentValue && selectedValues.includes(option.value)) {
                          option.disabled = true;
                        }
                      });

                      select.value = currentValue;
                    });
                  }
                });
              </script>



              <!-- Academic Rank -->

              <div class="mb-3 mt-3">
                <!-- <label for="academicRank" class="form-label">Academic Rank</label> -->
                <select id="academicRank" name="academic_rank" class="form-select">
                  <option value="">Select Appointment</option>
                  <?php
                  $sql = "SELECT appointment_code FROM tbl_appointment";
                  $result = $conn->query($sql);

                  if ($result === FALSE) {
                    echo "<option>Error fetching data: " . htmlspecialchars($conn->error) . "</option>";
                  } else {
                    while ($row = $result->fetch_assoc()) {
                      $appointmentCode = htmlspecialchars($row['appointment_code']);
                      echo "<option value='$appointmentCode'>$appointmentCode</option>";
                    }
                  }
                  ?>
                </select>
              </div>



              <!-- Professor Name -->
              <div class="mb-3">
                <input list="profNames" id="profName" name="prof_name" class="form-control"
                  placeholder="Select or Enter Instructor Name">
                <datalist id="profNames">
                  <?php
                  session_start();

                  // Ensure necessary session and variables are set
                  if (isset($_SESSION['program_unit'])) {
                    $prof_unit = $_SESSION['program_unit'];

                    if (isset($dept_code) && isset($semester) && isset($ay_code)) {

                      // Query for instructors
                      $dropdownQuery = "
            SELECT last_name, first_name, middle_initial, suffix 
            FROM tbl_prof_acc 
            WHERE dept_code = ? 
              AND prof_unit = ? 
              AND status = 'approve' 
              AND semester = ? 
              AND ay_code = ?
        ";

                      $stmt = $conn->prepare($dropdownQuery);
                      $stmt->bind_param("ssss", $dept_code, $prof_unit, $semester, $ay_code);
                      $stmt->execute();
                      $dropdownResult = $stmt->get_result();

                      if (!$dropdownResult) {
                        echo "<option>Error fetching data: " . htmlspecialchars($conn->error) . "</option>";
                      } elseif ($dropdownResult->num_rows === 0) {
                        echo "<option>No Instructor found for the selected criteria.</option>";
                      } else {
                        while ($row = $dropdownResult->fetch_assoc()) {
                          // Build full name
                          $lastName = htmlspecialchars($row['last_name']);
                          $firstName = htmlspecialchars($row['first_name']);
                          $middleInitial = htmlspecialchars($row['middle_initial'] ?? '');
                          $suffix = htmlspecialchars($row['suffix'] ?? '');

                          $fullName = trim("$firstName $middleInitial $lastName $suffix");

                          // Check if the full name already exists in tbl_prof
                          $checkProfSql = "SELECT 1 FROM tbl_prof WHERE prof_name = ? AND dept_code = ? AND ay_code = ?";
                          $checkProfStmt = $conn->prepare($checkProfSql);
                          $checkProfStmt->bind_param("sss", $fullName, $dept_code, $ay_code);
                          $checkProfStmt->execute();
                          $checkProfResult = $checkProfStmt->get_result();

                          if ($checkProfResult->num_rows === 0) {
                            echo "<option value='$lastName'>$fullName</option>";
                          }

                          $checkProfStmt->close();
                        }
                      }

                      $stmt->close();

                    } else {
                      echo "<option>Required data missing (dept_code, semester, or ay_code).</option>";
                    }
                  } else {
                    echo "<option>Program unit not found in session.</option>";
                  }
                  ?>

                </datalist>
              </div>



              <!-- Buttons -->
              <div class="btn-inline-group">
                <button type="submit" class="btn btn-primary btn-update-delete">Save Changes</button>
                <button type="button" class="btn btn-danger btn-update-delete" data-bs-dismiss="modal">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal HTML -->
    <div class="modal fade" id="professorExistModal" tabindex="-1" aria-labelledby="professorExistModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-body">
          <p>Professor Name already exists in the system. Please choose another.</p>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>


        <!-- Modal HTML -->
    <div class="modal fade" id="sectionDeleteErrorModal" tabindex="-1" aria-labelledby="sectionDeleteErrorModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-body" id="sectionDeleteErrorMessage">
        <!-- Message inserted dynamically -->
      </div>
      </div>
    </div>






    <!-- Success Modal -->
    <!-- <div class="modal fade" id="updateSuccessModal" tabindex="-1" aria-labelledby="updateSuccessModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-body">
          <p>The Instructor's information has been updated successfully!</p>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div> -->

    <!-- Success Modal -->
    <div class="modal fade" id="sectionSuccessModal" tabindex="-1" aria-labelledby="sectionSuccessModalLabel"
      aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-body">
          <p>The Chosen Section Already Exists. Please Choose Another</p>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>

  </section>



  <script>

    function showModal(profCode, profType, academicRank, profName, employ_status, regAdviser, sectionCode) {
      document.getElementById('profCode').value = profCode;
      document.getElementById('profType').value = profType;
      document.getElementById('academicRank').value = academicRank;
      document.getElementById('profName').value = profName;

      const employStatusCheckbox = document.getElementById('employStatus');
      if (employStatusCheckbox) {
        employStatusCheckbox.checked = employ_status === "1" || employ_status === 1;
        employStatusCheckbox.disabled = profType === 'Regular';
        if (profType === 'Regular') {
          employStatusCheckbox.checked = false;
        }
      }

      const regAdviserCheckbox = document.getElementById('showSectionDropdown');
      if (regAdviserCheckbox) {
        regAdviserCheckbox.checked = regAdviser === "1" || regAdviser === 1;
      }


      const sectionContainer = document.getElementById("sectionDropdownContainer");
      const dropdownsWrapper = document.getElementById("dropdownsWrapper");

      if (sectionContainer && dropdownsWrapper) {
        sectionContainer.style.display = regAdviser === "1" || regAdviser === 1 ? "block" : "none";
        dropdownsWrapper.innerHTML = '';

        const sectionCodes = sectionCode ? sectionCode.split(',') : [];
        const template = document.getElementById('section-options-template');

        // Helper: Clone and return options from template
        function getSectionOptions() {
          if (!template) return '';
          return template.content.cloneNode(true);
        }

        sectionCodes.forEach((code, index) => {
          const group = document.createElement('div');
          group.className = 'row dropdown-group align-items-center mb-2';
          group.innerHTML = `
      <div class="col-10">
        <select name="section_code[]" class="form-select section-select" required></select>
      </div>
      <div class="col-2 d-flex gap-1">
        <button type="button" class="w-50 add-dropdown-btn" title="Add Section">+</button>
      </div>
    `;

          if (index !== 0) {
            const btnCol = group.querySelector(".col-2");
            const deleteBtn = document.createElement("button");
            deleteBtn.type = "button";
            deleteBtn.className = "btn btn-outline-danger w-50 delete-dropdown-btn";
            deleteBtn.style.fontWeight = "bold";
            deleteBtn.title = "Remove Section";
            deleteBtn.innerText = "−";
            btnCol.appendChild(deleteBtn);
          }

          const select = group.querySelector('select');
          const optionsFragment = getSectionOptions();
          if (optionsFragment) {
            select.appendChild(optionsFragment);
          }

          // Add current value if missing in options
          const found = Array.from(select.options).some(opt => opt.value === code);
          if (!found && code) {
            const newOption = new Option(code, code);
            select.add(newOption);
          }
          select.value = code;

          dropdownsWrapper.appendChild(group);
        });

        // If no exises
        if (sectionCodes.length === 0) {
          const group = document.createElement('div');
          group.className = 'row dropdown-group align-items-center mb-2';
          group.innerHTML = `
      <div class="col-10">
        <select name="section_code[]" class="form-select section-select"></select>
      </div>
      <div class="col-2 d-flex gap-1">
        <button type="button" class="w-50 add-dropdown-btn" title="Add Section">+</button>
      </div>
    `;

          const select = group.querySelector('select');
          const optionsFragment = getSectionOptions();
          if (optionsFragment) {
            select.appendChild(optionsFragment);
          }

          dropdownsWrapper.appendChild(group);
        }

        // Ensure UI updates after DOM is populated
        setTimeout(() => updateDropdownOptions(), 100);
      }



      const profTypeSelect = document.getElementById('profType');
      function handleProfTypeChange() {
        if (employStatusCheckbox) {
          if (profTypeSelect.value === 'Regular') {
            employStatusCheckbox.disabled = true;
            employStatusCheckbox.checked = false;
          } else {
            employStatusCheckbox.disabled = false;
          }
        }
      }

      if (profTypeSelect) {
        profTypeSelect.removeEventListener('change', handleProfTypeChange);
        profTypeSelect.addEventListener('change', handleProfTypeChange);
      }

      handleProfTypeChange();

      const modalElement = document.getElementById('profModal');
      if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
      } else {
        console.error('Modal element not found!');
      }
    }

function submitEditForm() {
  const profCode = document.getElementById('profCode').value.trim();
  const profType = document.getElementById('profType').value.trim();
  const academicRank = document.getElementById('academicRank').value.trim();
  const profName = document.getElementById('profName').value.trim();

  const isPartTime = document.getElementById('employStatus').checked ? 1 : 0;
  const isRegAdviser = document.getElementById('showSectionDropdown').checked ? 1 : 0;

  if (!profCode || !profType || !academicRank || !profName) {
    alert("Please fill out all fields before saving changes.");
    return;
  }

  if (isRegAdviser) {
    const sectionSelects = document.querySelectorAll('.section-select');
    let validSection = false;

    sectionSelects.forEach(select => {
      if (select.value.trim() !== '') {
        validSection = true;
      }
    });

    if (!validSection) {
      alert("Please select at least one section if Reg Adviser is checked.");
      return;
    }
  }

  const formData = new FormData();
  formData.append('prof_code', profCode);
  formData.append('prof_type', profType);
  formData.append('employ_status', isPartTime);
  formData.append('academic_rank', academicRank);
  formData.append('prof_name', profName);
  formData.append('reg_adviser', isRegAdviser);

  // Append all selected section codes
  if (isRegAdviser) {
    const sectionSelects = document.querySelectorAll('.section-select');
    sectionSelects.forEach(select => {
      if (select.value.trim() !== '') {
        formData.append('section_code[]', select.value.trim());
      }
    });
  }

  fetch('prof_input.php', {
    method: 'POST',
    body: formData,
  })
    .then(response => response.text())
    .then(data => {
      document.getElementById('edit-prof-form').style.display = 'none';
      location.reload(); // Reload or update UI dynamically
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Failed to save changes.');
    });
}



    function togglePartTimeCheckbox() {
      const profType = document.getElementById('profType').value;
      const employStatusCheckbox = document.getElementById('employStatus');
      employStatusCheckbox.disabled = (profType === 'Regular');
      if (profType === 'Regular') {
        employStatusCheckbox.checked = false;

      }
    }
  </script>
      <?php if ($user_type == "Department Chairperson") { ?>
  <!-- Hidden template for section options -->
  <template id="section-options-template">
    <option value="">Select Section</option>
    <?php
    $query = "
    SELECT section_code 
    FROM tbl_section 
    WHERE year_level = 1 
      AND dept_code = '$dept_code' 
      AND semester = '$semester'
      AND ay_code = '$ay_code'
      AND (
        section_code NOT IN (
            SELECT section_code FROM tbl_registration_adviser
        )
        OR section_code = '$current_section'
      )
  ";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
      $code = htmlspecialchars($row['section_code']);
      echo "<option value=\"$code\">$code</option>";
    }
    ?>
  </template>

  <div class="mt-3" id="sectionDropdownContainer">
    <div id="dropdownsWrapper"></div>
  </div>
<?php } ?>
</body>

</html>