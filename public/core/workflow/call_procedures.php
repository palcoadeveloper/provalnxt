<?php
// call_procedure.php

// Include MeekroDB
require_once '../config/db.class.php';



$procedure = $_POST['procedure'];
$equip_id = $_POST['equip_id'];

try {
    if ($procedure === 'kill_pending_validations') {
        $result = DB::query("CALL kill_pending_validations(%i)", $equip_id);
        echo "kill_pending_validations called successfully";
    } elseif ($procedure === 'kill_pending_routine_tests') {
        $result = DB::query("CALL kill_pending_routine_tests(%i)", $equip_id);
        echo "kill_pending_routine_tests called successfully";
    } else {
        throw new Exception("Invalid procedure name");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>