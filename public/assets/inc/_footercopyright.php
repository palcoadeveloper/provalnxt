<?php
// Get the current page name (securely)
$current_page = basename($_SERVER['PHP_SELF']);
// Sanitize the page name to prevent path traversal attacks
$current_page = preg_replace('/[^a-zA-Z0-9._-]/', '', $current_page);
//if ($current_page === 'login.php' || $current_page === "resetpassword.php") {
if ($current_page === 'login.php') {

  echo '<footer class="footer">
  <div class="d-sm-flex justify-content-center ">
   
    <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Hand-crafted & made with <i class="mdi mdi-heart text-danger"></i> by Palcoa Solutions Pvt Ltd.</span>
  </div>
</footer>';
} else {
    echo '<footer class="footer">
  <div class="d-sm-flex justify-content-center justify-content-sm-between">
   
    <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Hand-crafted & made with <i class="mdi mdi-heart text-danger"></i> by Palcoa Solutions Pvt Ltd.</span>
  </div>
</footer>';
}



?>