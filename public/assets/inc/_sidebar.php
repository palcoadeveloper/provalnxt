<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">
   
    <li class="nav-item">
      <a class="nav-link" href="home.php">
        <span class="menu-title">Dashboard</span>
        <i class="mdi mdi-home menu-icon"></i>
      </a>
    </li>
    
    <li class="nav-item" <?php if((isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 'Yes') || 
    (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === 'Yes')){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?> >
      <a class="nav-link" data-toggle="collapse" href="#user-management" aria-expanded="false" aria-controls="user-management">
        <span class="menu-title">User Management</span>
        <i class="menu-arrow"></i>
         <i class="mdi mdi-account-multiple menu-icon"></i> 
      </a>
      <div class="collapse" id="user-management" >
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"> <a class="nav-link" href="searchuser.php"> Manage Users</a></li>
        </ul>
      </div>
    </li>
    
    <li class="nav-item" <?php if ((isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 'Yes') || 
    (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === 'Yes')) {echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>  >
      <a class="nav-link" data-toggle="collapse" href="#master-management" aria-expanded="false" aria-controls="master-management">
        <span class="menu-title">Master Management</span>
        <i class="menu-arrow"></i>
  <i class="mdi mdi-table-large menu-icon"></i>
      </a>
      <div class="collapse" id="master-management">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"> <a class="nav-link" href="searchdepartments.php"> Departments </a></li>
          <li class="nav-item"> <a class="nav-link" href="searchvendors.php"> Vendors </a></li>
          <li class="nav-item"> <a class="nav-link" href="searchequipments.php"> Equipments </a></li>
          <li class="nav-item"> <a class="nav-link" href="searchinstruments.php"> Instruments Calibration </a></li>
          <li class="nav-item"> <a class="nav-link" href="searchrooms.php"> Room/Location </a></li>
          <li class="nav-item"> <a class="nav-link" href="searchtests.php"> Tests </a></li>
          <li class="nav-item"> <a class="nav-link" href="searchmapping.php"> ETV Mapping </a></li>
          <li class="nav-item"> <a class="nav-link" href="searcherfmapping.php"> ERF Mapping </a></li>
          <li class="nav-item"> <a class="nav-link" href="searchfiltergroups.php"> Filter Groups </a></li>
          <li class="nav-item"> <a class="nav-link" href="searchfilters.php"> Filters </a></li>
          <li class="nav-item" <?php if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === 'Yes') {echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>> <a class="nav-link" href="searchunits.php"> Units </a></li>
        </ul>
      </div>
    </li>
    
    <li class="nav-item" <?php if(isset($_SESSION['department_id']) && (int)$_SESSION['department_id'] === 1){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?> >
      <a class="nav-link" data-toggle="collapse" href="#scheduling" aria-expanded="false" aria-controls="scheduling">
        <span class="menu-title">Scheduling</span>
        <i class="menu-arrow"></i>
    <i class="mdi mdi-timetable menu-icon"></i>
      </a>
      <div class="collapse" id="scheduling">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item"> <a class="nav-link" href="generateschedule.php"> Validation
 </a></li>
          
          <li class="nav-item"> <a class="nav-link" href="generatescheduleroutinetest.php"> Routine Tests
 </a></li>
          
          
        </ul>
      </div>
    </li>
    
    <li class="nav-item">
      <a class="nav-link" data-toggle="collapse" href="#assigned-cases" aria-expanded="false" aria-controls="assigned-cases">
        <span class="menu-title">Need Action</span>
        <i class="menu-arrow"></i>
          <i class="mdi mdi-worker menu-icon"></i>
      </a>
      <div class="collapse" id="assigned-cases">
        <ul class="nav flex-column sub-menu">
          
          <li class="nav-item" <?php if($_SESSION['logged_in_user']=='employee'){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>> <a class="nav-link" href="manageprotocols.php"> Validations</a></li>
          <li class="nav-item" <?php if($_SESSION['logged_in_user']=='employee'){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>> <a class="nav-link" href="manageroutinetests.php"> Routine Tests</a></li>
          <li class="nav-item" <?php if($_SESSION['logged_in_user']=='vendor' || ($_SESSION['logged_in_user']=='employee' && ((int)$_SESSION['department_id']==1 || (int)$_SESSION['department_id']==8 || $_SESSION['is_qa_head']=='Yes' || $_SESSION['is_admin']=='Yes' || $_SESSION['is_super_admin']=='Yes'))){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>> <a class="nav-link" href="assignedcases.php"> Assigned Tasks
 </a></li>
         
          
        </ul>
      </div>
    </li>
    
        <li class="nav-item" <?php if($_SESSION['logged_in_user']=='employee'){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>>
      <a class="nav-link" data-toggle="collapse" href="#view-reports" aria-expanded="false" aria-controls="view-reports">
        <span class="menu-title">Reports</span>
        <i class="menu-arrow"></i>
          <i class="mdi mdi-chart-pie menu-icon"></i>
      </a>
   
      <div class="collapse" id="view-reports">
        <ul class="nav flex-column sub-menu">
          
          <li class="nav-item" <?php if($_SESSION['logged_in_user']=='employee'){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>> <a class="nav-link" href="searchreport.php">Validation </a></li>
          
         <li class="nav-item" <?php if($_SESSION['logged_in_user']=='employee'){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>> <a class="nav-link" href="searchrtreport.php">Routine Tests</a></li>
         
         <li class="nav-item" <?php if($_SESSION['logged_in_user']=='employee'){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>> <a class="nav-link" href="searchschedule.php">Schedules</a></li>
         
         <li class="nav-item" <?php if($_SESSION['is_admin']==='Yes' or $_SESSION['is_super_admin']==='Yes'){echo 'style="display: block;"';}else{echo 'style="display: none;"';} ?>> <a class="nav-link" href="showaudittrail.php">Audit Trail Log</a></li>
          
        </ul>
      </div>
    </li>
  </ul>
</nav>