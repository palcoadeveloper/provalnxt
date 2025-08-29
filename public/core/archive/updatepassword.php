<?php 

// Start the session
session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

$query_vendor="select user_password from users u where u.user_id=".$_SESSION['user_id']." and user_status='Active' and is_account_locked is null ";
//echo $query_vendor;
//var_dump($_POST);

$results = DB::queryFirstRow($query_vendor);

if(!empty($results))
{
    if (password_verify($_POST['current_password'], $results['user_password'])) {
        
        $update_result=DB::query("update users set user_password=%s,is_default_password='No' where user_id=%i",password_hash($_POST['confirm_password'], PASSWORD_DEFAULT),intval($_SESSION['user_id']));
        
        $counter = DB::affectedRows();
        
        if($counter>0)
        {
          header('Location: ..\home.php');
          echo "Counter > 0";
        }
        else 
        {
            session_destroy();
        header('Location: ..\login.php?msg=upd_pwd');
           // echo "Counter = 0";
        }
    }
    else 
    {
        session_destroy();
       header('Location: ..\login.php?msg=curr_pwd');
      //  echo "Password not matching";
    }
    
}
else {
    $_SESSION['login_failed']="Yes";
   header('Location: ..\login.php?msg=invld_acct');
    //echo "empty results";
}





?>