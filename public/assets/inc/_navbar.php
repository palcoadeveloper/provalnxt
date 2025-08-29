<nav class="navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
  
  
  
  <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
    <a class="navbar-brand brand-logo" href="index.html"><h1 class="display-3 text-primary">ProVal </h1></a>
    <a class="navbar-brand brand-logo-mini" href="index.html"><h1 class="display-5 text-primary">ProVal </h1></a>
  
  </div>
  <div class="navbar-menu-wrapper d-flex align-items-stretch">
    <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
      <span class="mdi mdi-menu"></span>
    </button>

    <ul class="navbar-nav navbar-nav-right">
      <li class="nav-item nav-profile dropdown">
        <a class="nav-link dropdown-toggle" id="profileDropdown" href="#" data-toggle="dropdown" aria-expanded="false">
     <!--      <div class="nav-profile-img">
            <img src="assets/images/faces/face1.jpg" alt="image">
            <span class="availability-status online"></span>
          </div>  -->
          
          <div class="nav-profile-text">
            <p class="mb-1 text-black">
              <span class="font-weight-bold mb-2"><?php echo $_SESSION['user_name'].(($_SESSION['logged_in_user']=='vendor')?" (".$_SESSION['vendor_name'].")":"") ; ?></span>
              <span class="text-secondary text-small d-none d-md-inline">&nbsp;&nbsp;&nbsp;<?php echo ($_SESSION['logged_in_user']=="vendor") ? "" : $_SESSION['unit_name'].", ".$_SESSION['unit_site']; ?></span>
            </p>
            <?php if (defined('SHOW_SESSION_DEBUG_TIMERS') && SHOW_SESSION_DEBUG_TIMERS): ?>
            <!-- Debug: Session Inactivity Timer -->
            <p class="mb-0 text-small">
              <span class="text-dark">🕒 Inactive: <span id="inactivity-timer">0s</span></span> | 
              <span class="text-info">⏱️ Remaining: <span id="session-remaining"><?php echo (SESSION_TIMEOUT/60); ?>m</span></span> |
              <span id="heartbeat-status" class="text-success">💚 Connected</span>
            </p>
            <?php endif; ?>
          </div>
        </a>
        <div class="dropdown-menu navbar-dropdown" aria-labelledby="profileDropdown">

          <a class="dropdown-item" href="logout.php">
            <i class="mdi mdi-logout mr-2 text-primary"></i> Signout </a>
        </div>
      </li>
    </ul>
    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
      <span class="mdi mdi-menu"></span>
    </button>
  </div>
</nav>
