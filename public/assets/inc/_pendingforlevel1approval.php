<?php


// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

$query=" select val_wf_id,department_id,t.unit_id, equipment_code, equipment_category, actual_wf_start_datetime from tbl_val_wf_tracking_details t, equipments e where
 t.equipment_id=e.equipment_id
 and t.val_wf_current_stage='2' and val_wf_id in
    
(
select val_wf_id from tbl_val_wf_approval_tracking_details where level1_user_dept_approval_by is null or
 level1_eng_approval_by is null or level1_hse_approval_by is null or level1_qc_approval_by is null or level1_qa_approval_by is null
    
    
    
)
and
t.unit_id=".$_SESSION['unit_id'];



// Commented 01-Aug-24
/* $query=" select val_wf_id,t.unit_id, equipment_code, equipment_category, actual_wf_start_datetime from tbl_val_wf_tracking_details t, equipments e where
 t.equipment_id=e.equipment_id
 and t.val_wf_current_stage='2' and val_wf_id in
    
(
select val_wf_id from tbl_val_wf_approval_tracking_details where level1_user_dept_approval_by is null or
 level1_eng_approval_by is null or level1_hse_approval_by is null or level1_qc_approval_by is null or level1_qa_approval_by is null
    
    
    
)
and
t.unit_id=".$_SESSION['unit_id']; */


$countcompletedtasks = DB::query($query);




echo "<div class='table-responsive'><table id='datagrid-level1approval' class='table table-sm table-bordered dataTable no-footer text-center'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Validation Workflow ID </th>
                          <th> Unit ID </th>
                          <th> Equipment Code</th>
                          <th> Equipment Category</th>
                          <th> Actual Start Date</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";

//var_dump($countcompletedtasks);
if(empty($countcompletedtasks))
{
   
   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
}
else
{
    
    
    $count=1;
    foreach ($countcompletedtasks as $row) {
        
        $latest_iteration_details=DB::queryFirstRow("SELECT val_wf_approval_trcking_id,iteration_id 
                            FROM tbl_val_wf_approval_tracking_details
                            WHERE val_wf_id = %s AND iteration_status='Active'
                            AND iteration_id = (
                                SELECT MAX(iteration_id)
                                FROM tbl_val_wf_approval_tracking_details
                                WHERE iteration_status='Active' and val_wf_id = %s
                            )
                            LIMIT 1;", $row['val_wf_id'],$row['val_wf_id']);
        $val_approval_trk_id=$latest_iteration_details['val_wf_approval_trcking_id'];
        $iteration_id=$latest_iteration_details['iteration_id'];
        $approver_details=DB::queryFirstRow("select * from tbl_report_approvers where val_wf_id=%s and iteration_id=%i",$row['val_wf_id'],$iteration_id);
        


        
        if($_SESSION['user_id']==$approver_details['level1_approver_engg'] or $_SESSION['user_id']==$approver_details['level1_approver_hse'] or  $_SESSION['user_id']==$approver_details['level1_approver_qc'] or  $_SESSION['user_id']==$approver_details['level1_approver_qa'] or  $_SESSION['user_id']==$approver_details['level1_approver_user']  )
        {
           

            if($_SESSION['department_id']==1) //Engineering
            {
                

                $query_result=DB::queryFirstField("select level1_eng_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
          

		



                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>". date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                    
                    echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Manage</a> </td>";
                    echo "</tr>";
                    $count=$count+1;
                    
                    
                }
                else
                {
                    
                    //echo "the case is already approved by the engineering team";
                  //  echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                }
            }
            
            else if ($_SESSION['department_id']==8) //QA
            {
                $query_result=DB::queryFirstField("select level1_qa_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                    

                    echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Manage</a> </td>";
                    
                    echo "</tr>";
                    $count=$count+1;
                }
                else
                {
                    //echo "the case is already approved by the QC team";
                   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                }
                
            }
            
            else if ($_SESSION['department_id']==0 || $_SESSION['department_id']==6) //QC
            {
                $query_result=DB::queryFirstField("select level1_qc_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                    echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Manage</a> </td>";
                    
                     echo "</tr>";
                    $count=$count+1;
                }
                else
                {
                    //echo "the case is already approved by the QC team";
                  //  echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                }
                
            }
            
            else if ($_SESSION['department_id']==7) //EHS
            {
                $query_result=DB::queryFirstField("select level1_hse_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                    echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Manage</a> </td>";
                    
                    echo "</tr>";
                    $count=$count+1;
                }
                else
                {
                    //echo "the case is already approved by the EHS team";
                   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                }
                
            }
            else
            {
                $query_result=DB::queryFirstField("select level1_user_dept_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                    echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Manage</a> </td>";
                    echo "</tr>";
                    $count=$count+1;
                }
                else
                {
                    //echo "the case is already approved by the QA/USer team";
                   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                }
                
            }
            
        }
        else {
           // echo $_SESSION['department_id'];
            if($_SESSION['department_id']==1) //Engineering
            {
                $query_result=DB::queryFirstField("select level1_eng_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
           
               
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";

                    
                    echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Self-Assign</a> </td>";
                    echo "</tr>";
                    $count=$count+1;
                    
                }
                else
                {
                   // echo "the case is already approved by the engineering team";
                }
            }
            
            else if ($_SESSION['department_id']==8) //QA
            {
                $query_result=DB::queryFirstField("select level1_qa_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                    
                     echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Self-Assign</a> </td>";
                     echo "</tr>";
                    $count=$count+1;
                }
                else
                {
                   // echo "the case is already approved by the QA team";
                   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                }
                
            }
            
            else if ($_SESSION['department_id']==0) //QC
            {
                $query_result=DB::queryFirstField("select level1_qc_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                     echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Self-Assign</a> </td>";

                    echo "</tr>";
                    $count=$count+1;
                }
                else
                {
                    // echo "the case is already approved by the QC team";
                   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                }
                
            }
            
            else if ($_SESSION['department_id']==7) //EHS
            {
                $query_result=DB::queryFirstField("select level1_hse_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                     echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Self-Assign</a> </td>";

                    echo "</tr>";
                    $count=$count+1;
                }
                else
                {
                  //  echo "the case is already approved by the EHS team";
                   // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                }
                
            }
            else if($_SESSION['department_id']==$row['department_id'])
            {
                $query_result=DB::queryFirstField("select level1_user_dept_approval_by from tbl_val_wf_approval_tracking_details where val_wf_id=%s and val_wf_approval_trcking_id=%d",$row['val_wf_id'],$val_approval_trk_id);
                
                if($query_result=="")
                {
                    echo "<tr>";
                    echo "<td>".$count."</td>";
                    echo "<td>".$row["val_wf_id"]." </td>";
                    echo "<td>".$row["unit_id"]." </td>";
                    echo "<td>".$row["equipment_code"]." </td>";
                    echo "<td>".$row["equipment_category"]." </td>";
                    echo "<td>".date("d.m.Y",strtotime($row["actual_wf_start_datetime"]))." </td>";
                     echo "<td><a href='pendingforlevel1approval.php?approval_stage=1&val_wf_tracking_id=".$val_approval_trk_id."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-primary btn-small' role='button' aria-pressed='true'>Self-Assign</a> </td>";

                    echo "</tr>";
                    $count=$count+1;
                }
                else
                {
                    //echo "the case is already approved by the QA/USer team";
               //     echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
                    
                }
                
            }
            
            
            
            
            
        }
        
        
        
        
        
        
        
        
        
        
        
        
        
    }
    
    
    
    
    
}
echo "  </tbody></table></div>";

