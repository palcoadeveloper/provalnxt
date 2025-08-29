<?php
if(!isset($_SESSION))
{
    session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
} 

include_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");
/*use setasign\Fpdi\Fpdi;
 use setasign\Fpdi\PdfReader;
 require_once ('fpdf\fpdf.php');
 require_once('fpdf\fpdi\autoload.php');*/


    DB::query("update tbl_training_details set record_status='Inactive' where id=%i",intval($_POST['record_id']));
     $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
            DB::insert('log', [

                'change_type' => 'tran_traindtls_removed',
                'table_name' => '',
                'change_description' => 'Training details removed. Record ID:'.$_POST['record_id'].' Val WF ID: '.$_POST['val_wf_id'],
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id 

            ]);
    


