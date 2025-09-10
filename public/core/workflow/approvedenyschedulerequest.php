<?php

// Load configuration first
require_once(__DIR__ . '/../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");
//echo $_POST['schedule_id'].isset($_POST['action']).$_POST['sch-8'];

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

require_once(__DIR__ . "/../fpdf/fpdf.php");
require_once(__DIR__ . "/../../vendor/setasign/fpdi/src/autoload.php");
date_default_timezone_set("Asia/Kolkata");

// Add error logging
error_log("Schedule approval request received - User: " . $_SESSION['user_name'] . ", Action: " . $_POST['action'] . ", Type: " . $_POST['sch_type']);

// Validate required parameters
if (!isset($_POST['action']) || !isset($_POST['sch_type']) || !isset($_POST['schedule_id'])) {
    error_log("Missing required parameters in schedule approval request");
    echo 'error:missing_params';
    exit();
}

// Validate session
if (!isset($_SESSION['user_name']) || !isset($_SESSION['department_id'])) {
    error_log("Invalid session in schedule approval request");
    echo 'error:invalid_session';
    exit();
}

// Validate user permissions
$isDeptHead = isset($_SESSION['is_dept_head']) && $_SESSION['is_dept_head'] == 'Yes';
$isQAHead = isset($_SESSION['is_qa_head']) && $_SESSION['is_qa_head'] == 'Yes';
$isEngDept = $_SESSION['department_id'] == 1;
$isQADept = $_SESSION['department_id'] == 8 || $_SESSION['department_id'] == 9;

error_log("User permissions - Dept Head: " . ($isDeptHead ? 'Yes' : 'No') . 
          ", QA Head: " . ($isQAHead ? 'Yes' : 'No') . 
          ", Eng Dept: " . ($isEngDept ? 'Yes' : 'No') . 
          ", QA Dept: " . ($isQADept ? 'Yes' : 'No'));

// Process the request based on user role and action
try {
    if ($_POST['action'] == 'reject') {
        if ($_POST['sch_type'] == 'val') {
            DB::query("DELETE FROM tbl_val_wf_schedule_requests WHERE schedule_id=%i", intval($_POST['schedule_id']));
            DB::query("DELETE FROM tbl_proposed_val_schedules WHERE schedule_id=%i", intval($_POST['schedule_id']));

            if ($isEngDept && $isDeptHead) {
                DB::insert('log', [
                    'change_type' => 'tran_vsch_rej_eng',
                    'table_name' => '',
                    'change_description' => 'Validation schedule rejected by Eng Dept Head. SchID:' . $_POST['schedule_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
                echo 'vsch_rej_edh';
            } else if ($isQADept && $isQAHead) {
                DB::insert('log', [
                    'change_type' => 'tran_vsch_rej_qa',
                    'table_name' => '',
                    'change_description' => 'Validation schedule rejected by QA Head. SchID:' . $_POST['schedule_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
                echo 'vsch_rej_qah';
            } else {
                error_log("Invalid permissions for rejection - User: " . $_SESSION['user_name']);
                echo 'error:invalid_permissions';
            }
        } else if ($_POST['sch_type'] == 'rt') {
            DB::query("DELETE FROM tbl_routine_test_wf_schedule_requests WHERE schedule_id=%i", intval($_POST['schedule_id']));
            DB::query("DELETE FROM tbl_proposed_routine_test_schedules WHERE schedule_id=%i", intval($_POST['schedule_id']));

            if ($isEngDept && $isDeptHead) {
                DB::insert('log', [
                    'change_type' => 'tran_rsch_rej_eng',
                    'table_name' => 'tbl_routine_test_wf_schedule_requests',
                    'change_description' => 'Routine Tests schedule rejected by Eng Dept Head. SchID:' . $_POST['schedule_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
                echo 'rsch_rej_edh';
            } else if ($isQADept && $isQAHead) {
                DB::insert('log', [
                    'change_type' => 'tran_rsch_rej_qa',
                    'table_name' => '',
                    'change_description' => 'Routine schedule rejected by QA Head. SchID:' . $_POST['schedule_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
                echo 'rsch_rej_qah';
            }
        } else {
            error_log("Invalid schedule type requested: " . $_POST['sch_type']);
            echo 'error:invalid_schedule_type';
        }
    } else if ($_POST['action'] == 'approve') {
        if ($isEngDept && $isDeptHead) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'sch-') === 0) {
                    if ($_POST['sch_type'] == 'val') {
                        $results = DB::query("update tbl_proposed_val_schedules set val_wf_planned_start_date='" . $_POST[$key] . "' where proposed_sch_row_id='" . substr($key, 4, strlen($key)) . "'");
                    } else {
                        $results = DB::query("update tbl_proposed_routine_test_schedules set routine_test_wf_planned_start_date='" . $_POST[$key] . "' where proposed_sch_row_id='" . substr($key, 4, strlen($key)) . "'");
                    }
                }

                if ($_POST['sch_type'] == 'val') {
                    DB::query("update tbl_val_wf_schedule_requests set schedule_request_status='2' where schedule_id=" . $_POST['schedule_id']);
                } else {
                    DB::query("update tbl_routine_test_wf_schedule_requests set schedule_request_status='2' where schedule_id=" . $_POST['schedule_id']);
                }
            }

            if ($_POST['sch_type'] == 'val') {
                DB::insert('log', [
                    'change_type' => 'tran_vsch_app_eng',
                    'table_name' => '',
                    'change_description' => 'Validation schedule approved by Eng Dept Head. SchID:' . $_POST['schedule_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
            } else {
                DB::insert('log', [
                    'change_type' => 'tran_rsch_app_eng',
                    'table_name' => '',
                    'change_description' => 'Routine Tests schedule approved by Eng Dept Head. SchID:' . $_POST['schedule_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
            }

            $pdf = new Fpdi();
            if ($_POST['sch_type'] == 'val') {
                $pdfPath = __DIR__ . '/../../uploads/schedule-report-' . $_SESSION['unit_id'] . '-' . $_POST['schedule_id'] . '.pdf';
                if (!file_exists($pdfPath)) {
                    error_log("PDF file not found: " . $pdfPath);
                    echo 'error:pdf_missing';
                    exit();
                }
                $pageCount = $pdf->setSourceFile($pdfPath);
            } else {
                $pdfPath = __DIR__ . '/../../uploads/rt-schedule-report-' . $_SESSION['unit_id'] . '-' . $_POST['schedule_id'] . '.pdf';
                if (!file_exists($pdfPath)) {
                    error_log("PDF file not found: " . $pdfPath);
                    echo 'error:pdf_missing';
                    exit();
                }
                $pageCount = $pdf->setSourceFile($pdfPath);
            }
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplIdx = $pdf->importPage($pageNo, PdfReader\PageBoundaries::MEDIA_BOX);
                $s = $pdf->getTemplatesize($tplIdx);

                $pdf->addPage($s['orientation'], $s);

                $pdf->useTemplate($tplIdx);
            }

            $pdf->SetFont('Arial', 'B', 10);

            $pdf->SetXY(20, 83);
            $pdf->Cell(50, 30, 'Schedule Reviewed By:', 1, 0, 'C');
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 10, $_SESSION['user_name'] . "\n" . "Engg / User Department (Cipla Ltd.)" . "\n" . 'Date: ' . date("d.m.Y H:i:s"), 1, 'C');

            if ($_POST['sch_type'] == 'val') {
                $pdf->Output(__DIR__ . '/../../uploads/schedule-report-' . $_SESSION['unit_id'] . '-' . $_POST['schedule_id'] . '.pdf', 'F');
                echo 'vsch_app_edh';
            } else {
                $pdf->Output(__DIR__ . '/../../uploads/rt-schedule-report-' . $_SESSION['unit_id'] . '-' . $_POST['schedule_id'] . '.pdf', 'F');
                echo 'rsch_app_edh';
            }
        } else if ($isQADept && $isQAHead) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'sch-') === 0) {
                    if ($_POST['sch_type'] == 'val') {
                        $results = DB::query("update tbl_proposed_val_schedules set val_wf_planned_start_date='" . $_POST[$key] . "' where proposed_sch_row_id='" . substr($key, 4, strlen($key)) . "'");
                    } else {
                        $results = DB::query("update tbl_proposed_routine_test_schedules set routine_test_wf_planned_start_date='" . $_POST[$key] . "' where proposed_sch_row_id='" . substr($key, 4, strlen($key)) . "'");
                    }
                }
            }
            if ($_POST['sch_type'] == 'val') {
                DB::query("update tbl_val_wf_schedule_requests set schedule_request_status='3' where schedule_id=" . $_POST['schedule_id']);
                DB::insert('log', [
                    'change_type' => 'tran_vsch_approve',
                    'table_name' => '',
                    'change_description' => 'Validation Schedule approved by QA Head. SchID:' . $_POST['schedule_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
                echo 'vsch_app_qah';
            } else {
                DB::query("update tbl_routine_test_wf_schedule_requests set schedule_request_status='3' where schedule_id=" . $_POST['schedule_id']);
                DB::insert('log', [
                    'change_type' => 'tran_rsch_approve',
                    'table_name' => '',
                    'change_description' => 'Routine Tests Schedule approved by QA Head. SchID:' . $_POST['schedule_id'],
                    'change_by' => $_SESSION['user_id'],
                    'unit_id' => $_SESSION['unit_id']
                ]);
                echo 'rsch_app_qah';
            }
            
            $pdf = new Fpdi();
            if ($_POST['sch_type'] == 'val') {
                $pdfPath = __DIR__ . '/../../uploads/schedule-report-' . $_SESSION['unit_id'] . '-' . $_POST['schedule_id'] . '.pdf';
                if (!file_exists($pdfPath)) {
                    error_log("PDF file not found: " . $pdfPath);
                    echo 'error:pdf_missing';
                    exit();
                }
                $pageCount = $pdf->setSourceFile($pdfPath);
            } else {
                $pdfPath = __DIR__ . '/../../uploads/rt-schedule-report-' . $_SESSION['unit_id'] . '-' . $_POST['schedule_id'] . '.pdf';
                if (!file_exists($pdfPath)) {
                    error_log("PDF file not found: " . $pdfPath);
                    echo 'error:pdf_missing';
                    exit();
                }
                $pageCount = $pdf->setSourceFile($pdfPath);
            }
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplIdx = $pdf->importPage($pageNo, PdfReader\PageBoundaries::MEDIA_BOX);
                $s = $pdf->getTemplatesize($tplIdx);

                $pdf->addPage($s['orientation'], $s);

                $pdf->useTemplate($tplIdx);
            }

            $pdf->SetFont('Arial', 'B', 10);

            $pdf->SetXY(20, 113);
            $pdf->Cell(50, 30, 'Schedule Approved By:', 1, 0, 'C');
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 10, $_SESSION['user_name'] . "\n" . "Quality Assurance (Cipla Ltd.)" . "\n" . 'Date: ' . date("d.m.Y H:i:s"), 1, 'C');

            if ($_POST['sch_type'] == 'val') {
                $pdf->Output(__DIR__ . '/../../uploads/schedule-report-' . $_SESSION['unit_id'] . '-' . $_POST['schedule_id'] . '.pdf', 'F');

                $query = "select * from tbl_proposed_val_schedules where schedule_id=" . $_POST['schedule_id'];
                $query_results = DB::query($query);

                if (empty($query_results)) {
                } else {
                    foreach ($query_results as $row) {
                        DB::insert('tbl_val_schedules', [
                            'unit_id' => $row['unit_id'],
                            'equip_id' => $row['equip_id'],
                            'val_wf_id' => $row['val_wf_id'],
                            'val_wf_planned_start_date' => $row['val_wf_planned_start_date'],
                            'val_wf_status' => 'Active'
                        ]);
                    }
                    DB::query("DELETE FROM tbl_proposed_val_schedules WHERE schedule_id=%i", intval($_POST['schedule_id']));
                }
            } else {
                $pdf->Output(__DIR__ . '/../../uploads/rt-schedule-report-' . $_SESSION['unit_id'] . '-' . $_POST['schedule_id'] . '.pdf', 'F');
                $query = "select * from tbl_proposed_routine_test_schedules where schedule_id=" . $_POST['schedule_id'];
                $query_results = DB::query($query);

                if (empty($query_results)) {
                } else {
                    foreach ($query_results as $row) {
                        DB::insert('tbl_routine_test_schedules', [
                            'unit_id' => $row['unit_id'],
                            'equip_id' => $row['equip_id'],
                            'test_id' => $row['test_id'],
                            'routine_test_wf_id' => $row['routine_test_wf_id'],
                            'routine_test_wf_planned_start_date' => $row['routine_test_wf_planned_start_date'],
                            'routine_test_wf_status' => 'Active',
                            'routine_test_req_id' => $row['routine_test_req_id']
                        ]);
                    }
                    DB::query("DELETE FROM tbl_proposed_routine_test_schedules WHERE schedule_id=%i", intval($_POST['schedule_id']));
                }
            }
        } else {
            error_log("Invalid permissions for approval - User: " . $_SESSION['user_name']);
            echo 'error:invalid_permissions';
        }
    } else {
        error_log("Invalid action requested: " . $_POST['action']);
        echo 'error:invalid_action';
    }
} catch (Exception $e) {
    error_log("Error in schedule approval: " . $e->getMessage());
    echo 'error:database_error';
}
//header('Location: ..\assignedcases.php');
