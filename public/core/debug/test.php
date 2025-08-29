<?php
session_start();

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once '../config/db.class.php';
include '../config/config.php';

date_default_timezone_set("Asia/Kolkata");

if ($_SESSION['logged_in_user'] == "employee") {

    if ($env_identifier == "dev" || $env_identifier == "uat") {

        $query_employee = "select user_id, employee_id, user_name, unit_id, department_id,is_qa_head,is_unit_head,is_admin,is_super_admin,is_account_locked
        from users where user_domain_id='" . $_SESSION['account_name'] . "' and user_password='" . $_POST['user_password'] . "' and user_status='Active' ";
        $results = DB::query($query_employee);

        // echo $query_employee;
        if (empty($results)) {
            //echo $query_employee;
            echo "failed";
            $login_success = false;
        } else {
            $login_success = true;
        }
    } else if ($env_identifier == "prod") {
        // connect to ldap server
        $ldap_connection = ldap_connect($ldap_url) or die("Could not connect to LDAP server.");
        ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap_connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);

        if ($ldap_connection) {
            // binding to ldap server
            $ldapbind = @ldap_bind($ldap_connection, $_POST['username'] . '@cipla.com', $_POST['password']);
            // verify binding
            if (!$ldapbind) {
                //        	echo "LDAP bind failed...".ldap_error($ldap_connection);
                ldap_close($ldap_connection);
                $login_success = false;
                header('Location: ..\login.php?msg=invld_acct');
            } else {
                $login_success = true;
                ldap_close($ldap_connection);
            }
        }
    }

    if ($login_success == true) {
        $query_employee = "select user_id, employee_id, user_name, unit_id, department_id,is_qa_head,is_unit_head,is_admin,is_super_admin,is_account_locked, is_dept_head
    from users where user_domain_id='" . $_SESSION['user_domain_id'] . "' and user_status='Active' ";
        $results = DB::query($query_employee);
        
        if (empty($results)) {
            
            echo "failed";
        } else {
            DB::insert('approver_remarks', [
                'val_wf_id' => (isset($_POST['wf_id'])) ? $_POST['wf_id'] : '',
                'test_wf_id' => (isset($_POST['test_wf_id'])) ? $_POST['test_wf_id'] : '',
                'user_id' => $_SESSION['user_id'],
                'remarks' => $_POST['user_remark'],
                'created_date_time' => DB::sqleval("NOW()"),
                'unit_id' => $_SESSION['unit_id']

            ]);

            echo "success";
        }
    }
} else {
   
    $query_vendor = "select user_id, employee_id, user_name,user_password,vendor_name,is_account_locked,user_status from users u, vendors v where u.vendor_id=v.vendor_id and u.user_type='vendor'
    and employee_id='" . $_SESSION['account_name'] . "' and user_status='Active' and (is_account_locked is null or is_account_locked='')";

    $results = DB::queryFirstRow($query_vendor);

    if (empty($results)) {
        echo "failed";
    } else {

        if (password_verify($_POST['user_password'], $results['user_password'])) {
            if ($results['is_account_locked'] || $results['user_status'] != 'Active') {
                echo "failed";
            } else {
                DB::insert('approver_remarks', [
                    'val_wf_id' => $_POST['wf_id'],
                    'test_wf_id' => $_POST['test_wf_id'],
                    'user_id' => $_SESSION['user_id'],
                    'remarks' => $_POST['user_remark'],
                    'created_date_time' => DB::sqleval("NOW()"),
                    'unit_id' => $_SESSION['unit_id']

                ]);

                echo "success";
            }
        }
    }
}
