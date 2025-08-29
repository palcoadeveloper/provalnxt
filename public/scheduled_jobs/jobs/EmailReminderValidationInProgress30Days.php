<?php
/**
 * EmailReminderValidationInProgress30Days
 * 
 * Sends alert emails for validations that have been in progress for more than 30 days.
 * This job helps identify potentially stalled validation processes.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once(__DIR__ . '/../../core/EmailReminderBaseJob.php');

class EmailReminderValidationInProgress30Days extends EmailReminderBaseJob {
    
    /**
     * Process a specific unit
     */
    protected function processUnit($unit) {
        // Get data for this unit
        $data = $this->getEmailData($unit);
        
        // Always send email, even if no data found (to inform users)
        if (empty($data)) {
            $this->logger->logInfo($this->jobName, "No overdue validations found for unit {$unit['unit_id']} - sending notification email");
        } else {
            $this->logger->logInfo($this->jobName, "Found " . count($data) . " overdue validations for unit {$unit['unit_id']}");
        }
        
        // Generate email content (handles both data and no-data cases)
        $emailContent = $this->generateEmailContent($unit, $data);
        
        // Send email
        $result = $this->sendUnitEmail($unit, $emailContent);
        
        return [
            'emails_sent' => $result['emails_sent'],
            'emails_failed' => $result['emails_failed']
        ];
    }
    
    /**
     * Get email subject for this job type
     */
    protected function getEmailSubject($unit) {
        return 'HVACVADMS Alert - Validation Progress Alert - list of equipments for which the validation study is in progress for more than 30 days';
    }
    
    /**
     * Get data for the email content
     */
    protected function getEmailData($unit) {
        try {
			$query = " SELECT  t1.val_wf_id,
						t1.unit_id,
						t4.equipment_code, 
						t1.equipment_id, 
						t4.equipment_category,
						DATE_FORMAT(t2.val_wf_planned_start_date,'%d %M %Y') as val_wf_planned_start_date,
						DATE_FORMAT(t1.actual_wf_start_datetime,'%d %M %Y') as actual_start_date,
						t3.unit_name,
						DATEDIFF(CURRENT_DATE(), t1.actual_wf_start_datetime) as days_in_progress,
						DATEDIFF(CURRENT_DATE(), t2.val_wf_planned_start_date) as days_since_planned_start,
						t1.val_wf_current_stage ,   
						t1.status,
						t5.wf_stage_description as current_stage
                        FROM tbl_val_wf_tracking_details t1 left join tbl_val_schedules t2 on t1.val_wf_id=t2.val_wf_id
                        left join units t3 on t1.unit_id=t3.unit_id
                        left join equipments t4 on t1.equipment_id=t4.equipment_id
						left join workflow_stages t5 on t1.val_wf_current_stage=t5.wf_stage and wf_type='Validation'
                        WHERE DATEDIFF(current_date(),actual_wf_start_datetime) >= 30 and (val_wf_current_stage!=5 and val_wf_current_stage!=99) and t1.unit_id=%i
                        and t1.status='Active' and t2.val_wf_status='Active'
						ORDER BY DATEDIFF(CURRENT_DATE(), t1.actual_wf_start_datetime)";
        /*    $query = "SELECT 
                        t1.val_wf_id,
                        t1.unit_id,
                        t2.equipment_code,
                        t1.equip_id, 
                        t2.equipment_category,
                        DATE_FORMAT(t1.val_wf_planned_start_date,'%d %M %Y') as val_wf_planned_start_date,
                        DATE_FORMAT(t3.actual_wf_start_datetime,'%d %M %Y') as actual_start_date,
                        t4.unit_name,
                        DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) as days_in_progress,
                        DATEDIFF(CURRENT_DATE(), t1.val_wf_planned_start_date) as days_since_planned_start,
                        t3.val_wf_current_stage as current_stage,
                        t3.status as wf_status
                      FROM tbl_val_schedules t1
                      JOIN equipments t2 ON t1.equip_id = t2.equipment_id 
                      JOIN tbl_val_wf_tracking_details t3 ON t1.val_wf_id = t3.val_wf_id
                      JOIN units t4 ON t1.unit_id = t4.unit_id 
                      WHERE DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) > 30
                        AND t3.status IN ('In Progress', 'Pending', 'Review')
                        AND t1.val_wf_status = 'Active' 
                        AND t1.unit_id = %i
                      ORDER BY DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) DESC"; */
            
            // FIX: Use direct string substitution instead of DB parameter binding
            // The DB::query parameter binding seems to have issues in this context
            $fixedQuery = str_replace('%i', intval($unit['unit_id']), $query);
            return DB::query($fixedQuery);
            
        } catch (Exception $e) {
            $this->logger->logError($this->jobName, "Failed to get email data for unit {$unit['unit_id']}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Build custom HTML email content for this job type
     */
    protected function buildEmailHTML($unit, $data, $subject) {
        $html = "<html><head><title>{$subject}</title>";
        $html .= "<style>";
        $html .= "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f9fa; }";
        $html .= ".container { max-width: 800px; margin: 0 auto; background-color: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
        $html .= "table { border-collapse: collapse; width: 100%; margin: 20px 0; }";
        $html .= "th, td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }";
        $html .= "th { background-color: #6c757d; color: white; font-weight: 600; }";
        $html .= "tr:nth-child(even) { background-color: #f8f9fa; }";
        $html .= ".header { color: #495057; font-weight: 600; text-align: center; padding: 20px 0; border-bottom: 2px solid #dee2e6; margin-bottom: 30px; }";
        $html .= ".footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 13px; color: #6c757d; }";
        $html .= ".summary { background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid #6c757d; }";
        $html .= ".actions { background-color: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid #ffc107; }";
        $html .= ".escalation { background-color: #e2e3e5; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid #6c757d; }";
        $html .= "h1 { color: #dc3545; font-size: 24px; margin: 0 0 10px 0; }";
        $html .= "h2 { color: #495057; font-size: 20px; margin: 20px 0 10px 0; }";
        $html .= "h3 { color: #495057; font-size: 16px; margin: 15px 0 10px 0; }";
        $html .= ".priority-high { color: #dc3545; font-weight: 600; }";
        $html .= ".priority-medium { color: #ffc107; font-weight: 600; }";
        $html .= "</style></head><body><div class='container'>";
        
        $html .= "<div class='header'>";
        $html .= "<h1>ProVal System Validation Alert</h1>";
        $html .= "<p style='margin: 0; color: #6c757d;'>Validation studies in progress for more than 30 days</p>";
        $html .= "</div>";
        
        $html .= "<p><strong>Dear User,</strong></p>";
        $html .= "<p>This alert identifies validation studies that have exceeded the 30-day progress threshold and require immediate attention to ensure compliance and operational efficiency.</p>";
        
        if (!empty($data)) {
            // Calculate metrics
            $totalValidations = count($data);
            $severeCount = count(array_filter($data, function($row) { return $row['days_in_progress'] > 40; }));
            $avgDaysInProgress = array_sum(array_column($data, 'days_in_progress')) / $totalValidations;
            $maxDaysInProgress = max(array_column($data, 'days_in_progress'));
            
            $html .= "<div class='summary'>";
            $html .= "<h2>Summary</h2>";
            $html .= "<p><strong>Total Affected Validations:</strong> {$totalValidations}</p>";
            $html .= "<p><strong>Severe Cases (>40 days):</strong> {$severeCount}</p>";
            $html .= "<p><strong>Average Days in Progress:</strong> " . round($avgDaysInProgress, 1) . " days</p>";
            $html .= "<p><strong>Maximum Delay:</strong> {$maxDaysInProgress} days</p>";
            $html .= "<p><strong>Compliance Risk Level:</strong> " . ($severeCount > 0 ? "High" : "Medium") . "</p>";
            $html .= "</div>";
            
            $html .= "<table>";
            $html .= "<thead>";
            $html .= "<tr>";
            $html .= "<th>Priority</th>";
            $html .= "<th>Equipment Code</th>";
            $html .= "<th>Category</th>";
            $html .= "<th>Planned Start</th>";
            $html .= "<th>Actual Start</th>";
            $html .= "<th>Current Stage</th>";
            $html .= "<th>Days in Progress</th>";
            $html .= "</tr>";
            $html .= "</thead>";
            $html .= "<tbody>";
            
            foreach ($data as $row) {
                $cssClass = '';
                $priority = '';
                
                if ($row['days_in_progress'] > 40) {
                    $priority = '<span class="priority-high">High</span>';
                } else {
                    $priority = '<span class="priority-medium">Medium</span>';
                }
                
                $html .= "<tr>";
                $html .= "<td>{$priority}</td>";
                $html .= "<td><strong>" . htmlspecialchars($row['equipment_code']) . "</strong></td>";
                $html .= "<td>" . htmlspecialchars($row['equipment_category']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['val_wf_planned_start_date']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['actual_start_date']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['current_stage'] ?: 'Not specified') . "</td>";
                $html .= "<td><strong>" . $row['days_in_progress'] . " days</strong></td>";
                $html .= "</tr>";
            }
            
            $html .= "</tbody>";
            $html .= "</table>";
            
      /*      $html .= "<div class='actions'>";
            $html .= "<h3>Recommended Actions</h3>";
            $html .= "<ol>";
            $html .= "<li>Review and prioritize all validations exceeding 40 days</li>";
            $html .= "<li>Schedule meetings with responsible validation teams</li>";
            $html .= "<li>Assess resource requirements and availability</li>";
            $html .= "<li>Identify and address process bottlenecks</li>";
            $html .= "<li>Update stakeholders on revised timelines</li>";
            $html .= "<li>Implement corrective action plan within 48 hours</li>";
            $html .= "<li>Review compliance implications and mitigation strategies</li>";
            $html .= "</ol>";
            $html .= "</div>";
            
            $html .= "<div class='escalation'>";
            $html .= "<h3>Escalation Information</h3>";
            $html .= "<p><strong>Next Level:</strong> Senior Management / Quality Head</p>";
            $html .= "<p><strong>Response Time:</strong> Within 24 hours</p>";
            $html .= "<p><strong>Follow-up:</strong> Daily progress updates until resolution</p>";
            $html .= "</div>"; */
            
        } else {
            $html .= "<div class='summary'>";
            $html .= "<p style='color: #28a745; font-weight: 600;'>No validations have been in progress for more than 30 days in this unit.</p>";
            $html .= "<p>All validation processes are within acceptable timeframes.</p>";
            $html .= "</div>";
        }
        
        $html .= "<div class='footer'>";
        $html .= "<p>This is an automated system alert from the ProVal validation management system.</p>";
        $html .= "<p>For questions or concerns, please contact the Quality Assurance team.</p>";
        $html .= "<p><em>Generated on: " . date('d F Y H:i:s') . " (Asia/Kolkata)</em></p>";
        $html .= "</div>";
        $html .= "</div></body></html>";
        
        return $html;
    }
    
    /**
     * Build custom plain text email content for this job type
     */
    protected function buildEmailText($unit, $data, $subject) {
        $text = "{$subject}\n";
        $text .= str_repeat("=", strlen($subject)) . "\n\n";
        $text .= "Dear User,\n\n";
        $text .= "ATTENTION: The following validation studies have been in progress for more than 30 days and require immediate attention.\n\n";
        
        if (!empty($data)) {
            // Calculate summary metrics
            $totalValidations = count($data);
            $criticalCount = count(array_filter($data, function($row) { return $row['days_in_progress'] > 45; }));
            $avgDaysInProgress = array_sum(array_column($data, 'days_in_progress')) / $totalValidations;
            $maxDaysInProgress = max(array_column($data, 'days_in_progress'));
            
            $text .= "VALIDATION PROGRESS ALERT SUMMARY:\n";
            $text .= "===================================\n";
            $text .= "Total Overdue: {$totalValidations} validations\n";
            $text .= "Critical (>45 days): {$criticalCount} validations\n";
            $text .= "Average Days in Progress: " . round($avgDaysInProgress, 1) . " days\n";
            $text .= "Longest Running: {$maxDaysInProgress} days\n\n";
            
            $text .= sprintf("%-15s %-20s %-25s %-15s %-15s %-15s %s\n", 
                "Unit", "Equipment", "Category", "Actual Start", "Current Stage", "Days Progress", "Status");
            $text .= str_repeat("-", 130) . "\n";
            
            foreach ($data as $row) {
                $delayStatus = $row['days_in_progress'] > 45 ? 'CRITICAL' : 'WARNING';
                
                $text .= sprintf("%-15s %-20s %-25s %-15s %-15s %-15s %s\n",
                    substr($row['unit_name'], 0, 14),
                    substr($row['equipment_code'], 0, 19),
                    substr($row['equipment_category'], 0, 24),
                    $row['actual_start_date'],
                    substr($row['current_stage'] ?: 'Not specified', 0, 14),
                    $row['days_in_progress'] . " days",
                    $delayStatus
                );
            }
            
            $text .= str_repeat("-", 130) . "\n\n";
            
            $text .= "RECOMMENDED ACTIONS:\n";
            $text .= "===================\n";
            $text .= "- Review the current status of all validations marked as CRITICAL\n";
            $text .= "- Contact responsible personnel for delayed validations\n";
            $text .= "- Identify and resolve any blockers or resource constraints\n";
            $text .= "- Update validation timelines and communication plans\n";
            $text .= "- Consider escalation for validations >45 days in progress\n\n";
            
        } else {
            $text .= "Good news! No validations have been in progress for more than 30 days in this unit.\n\n";
        }
        
        $text .= "This is an automated alert. Please take immediate action to review and expedite the delayed validation processes.\n\n";
        $text .= "Please note that this is a system generated email.\n\n";
        $text .= "Best regards,\nProVal System\n\n";
        $text .= "Generated on: " . date('d F Y H:i:s') . "\n";
        
        return $text;
    }
    
    /**
     * Validate prerequisites for this job
     */
    protected function validatePrerequisites() {
        try {
            // Check if required tables exist
            $requiredTables = ['tbl_val_schedules', 'equipments', 'tbl_val_wf_tracking_details'];
            
            foreach ($requiredTables as $table) {
                $exists = DB::queryFirstField(
                    "SELECT COUNT(*) FROM information_schema.tables 
                     WHERE table_name = %s AND table_schema = DATABASE()", 
                    $table
                );
                
                if (!$exists) {
                    return [
                        'valid' => false, 
                        'message' => "Required table '$table' does not exist"
                    ];
                }
            }
            
            return ['valid' => true, 'message' => ''];
            
        } catch (Exception $e) {
            return [
                'valid' => false, 
                'message' => 'Prerequisites validation failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get job-specific statistics
     */
    public function getJobSpecificStats($days = 30) {
        try {
            return DB::queryFirstRow(
                "SELECT 
                    COUNT(DISTINCT t1.unit_id) as units_with_critical_delays,
                    COUNT(*) as total_critical_validations,
                    COUNT(CASE WHEN DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) > 50 THEN 1 END) as severe_validations,
                    COUNT(CASE WHEN DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) BETWEEN 30 AND 50 THEN 1 END) as critical_validations,
                    AVG(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as avg_days_in_progress,
                    MAX(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as max_days_in_progress,
                    SUM(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as total_delay_days
                 FROM tbl_val_schedules t1
                 JOIN tbl_val_wf_tracking_details t3 ON t1.val_wf_id = t3.val_wf_id
                 WHERE DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) > 30
              --     AND t3.wf_status IN ('In Progress', 'Pending', 'Review')
                   AND t1.val_wf_status = 'Active'"
            );
        } catch (Exception $e) {
            $this->logger->logError($this->jobName, "Failed to get job stats: " . $e->getMessage());
            return null;
        }
    }
}
?>