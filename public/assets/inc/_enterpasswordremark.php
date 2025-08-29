<!-- Modal -->
<div class="modal fade" id="enterPasswordRemark" tabindex="-1" role="dialog" aria-labelledby="passwordModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="passwordModalTitle">Enter your password and remarks</h5>
        <button type="button" id="modalbtncross" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="eformmodalvalidation">
        <div class="modal-body">
          <div id="prgmodaladd" class="progress" style="margin-bottom: 10px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
          </div>
          
          <!-- Hidden field to track if this is a send back action -->
          <input type="hidden" id="sendBackAction" value="0">
          <!-- Include CSRF token field -->
          <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') : ''; ?>">
          
          <div class="form-group">
            <label for="user_password">Account Password</label>
            <input type="password" class="form-control" id="user_password" placeholder="Enter the account password.">
          </div>       
          <div class="form-group">
            <label for="user_remark">Remarks</label>
            <input type="text" class="form-control" id="user_remark" placeholder="Enter the remarks.">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" id="emdlbtnclose" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="button" id="emdlbtnsubmit" class="btn btn-primary">Proceed</button>
        </div>
      </form>
    </div>
  </div>
</div>