-- Add termination tracking columns to tbl_val_wf_tracking_details
-- These columns help track the original workflow stage and remarks during termination process

ALTER TABLE `tbl_val_wf_tracking_details`
ADD COLUMN `stage_before_termination` VARCHAR(45) DEFAULT NULL
  COMMENT 'Stores the workflow stage before termination request was initiated'
  AFTER `deviation_remark`,
ADD COLUMN `tr_reviewer_remarks` VARCHAR(200) DEFAULT NULL
  COMMENT 'Engineering Department Head remarks during termination review'
  AFTER `stage_before_termination`,
ADD COLUMN `tr_approver_remarks` VARCHAR(200) DEFAULT NULL
  COMMENT 'QA Head remarks during termination approval'
  AFTER `tr_reviewer_remarks`;

-- Add index for termination queries
ALTER TABLE `tbl_val_wf_tracking_details`
ADD INDEX `idx_stage_before_termination` (`stage_before_termination`);

-- Verify the changes
DESCRIBE `tbl_val_wf_tracking_details`;