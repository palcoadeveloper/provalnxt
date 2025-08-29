<?php
/**
 * EmailReminderValidationInProgress35Days
 * 
 * Sends escalation emails for validations that have been in progress for more than 35 days.
 * This is a higher-level alert for critical delays that require management attention.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once(__DIR__ . '/../../core/EmailReminderBaseJob.php');

class EmailReminderValidationInProgress35Days extends EmailReminderBaseJob {
    
    /**
     * Process a specific unit
     */
    protected function processUnit($unit) {
        // Get data for this unit
        $data = $this->getEmailData($unit);
        
        // Always send email, even if no data found (to inform users)
        if (empty($data)) {
            $this->logger->logInfo($this->jobName, "No critically overdue validations found for unit {$unit['unit_id']} - sending notification email");
        } else {
            $this->logger->logWarning($this->jobName, "Critical validation delays detected for unit {$unit['unit_id']}: " . count($data) . " validations >35 days");
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
        return 'HVACVADMS ESCALATION ALERT - CRITICAL - Validation studies in progress for more than 35 days - IMMEDIATE ACTION REQUIRED';
    }
    
    /**
     * Get data for the email content
     */
    protected function getEmailData($unit) {
        try {
            $query = "SELECT  t1.val_wf_id,
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
                        WHERE DATEDIFF(current_date(),actual_wf_start_datetime) >= 35 and (val_wf_current_stage!=5 and val_wf_current_stage!=99) and t1.unit_id=%i
                        and t1.status='Active' and t2.val_wf_status='Active'
						ORDER BY DATEDIFF(CURRENT_DATE(), t1.actual_wf_start_datetime)";
            
            // FIX: Use direct string substitution instead of DB parameter binding
            $fixedQuery = str_replace('%i', intval($unit['unit_id']), $query);
            $data = DB::query($fixedQuery);
            
            // Log critical alert for audit trail
            if (!empty($data)) {
                $this->logger->logWarning($this->jobName, 
                    "Critical validation delays detected for unit {$unit['unit_id']}: " . count($data) . " validations >35 days");
            }
            
            return $data;
            
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
        $html .= "<p style='margin: 0; color: #6c757d;'>Validation studies in progress for more than 35 days</p>";
        $html .= "</div>";
        
        $html .= "<p><strong>Dear User,</strong></p>";
        $html .= "<p>This alert identifies validation studies that have exceeded the 35-day progress threshold and require immediate attention to ensure compliance and operational efficiency.</p>";
        
        if (!empty($data)) {
            // Calculate metrics
            $totalValidations = count($data);
            $severeCount = count(array_filter($data, function($row) { return $row['days_in_progress'] > 50; }));
            $avgDaysInProgress = array_sum(array_column($data, 'days_in_progress')) / $totalValidations;
            $maxDaysInProgress = max(array_column($data, 'days_in_progress'));
            $totalDelayDays = array_sum(array_column($data, 'days_in_progress'));
            
            $html .= "<div class='summary'>";
            $html .= "<h2>Summary</h2>";
            $html .= "<p><strong>Total Affected Validations:</strong> {$totalValidations}</p>";
            $html .= "<p><strong>Severe Cases (>50 days):</strong> {$severeCount}</p>";
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
                
                if ($row['days_in_progress'] > 50) {
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
            
     /*       $html .= "<div class='actions'>";
            $html .= "<h3>Recommended Actions</h3>";
            $html .= "<ol>";
            $html .= "<li>Review and prioritize all validations exceeding 50 days</li>";
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
            $html .= "</div>";
     */       
        } else {
            $html .= "<div class='summary'>";
            $html .= "<p style='color: #28a745; font-weight: 600;'>No validations have been in progress for more than 35 days in this unit.</p>";
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
        $text = str_repeat("*", 80) . "\n";
        $text .= "*** CRITICAL ESCALATION ALERT ***\n";
        $text .= str_repeat("*", 80) . "\n\n";
        $text .= "{$subject}\n";
        $text .= str_repeat("=", strlen($subject)) . "\n\n";
        $text .= "Dear Management Team,\n\n";
        $text .= "URGENT ATTENTION REQUIRED: The following validation studies have been in progress\n";
        $text .= "for more than 35 days, indicating critical delays that require immediate\n";
        $text .= "management intervention and escalation.\n\n";
        
        if (!empty($data)) {
            // Calculate critical metrics
            $totalValidations = count($data);
            $severeCount = count(array_filter($data, function($row) { return $row['days_in_progress'] > 50; }));
            $avgDaysInProgress = array_sum(array_column($data, 'days_in_progress')) / $totalValidations;
            $maxDaysInProgress = max(array_column($data, 'days_in_progress'));
            $totalDelayDays = array_sum(array_column($data, 'days_in_progress'));
            
            $text .= "CRITICAL METRICS:\n";
            $text .= "================\n";
            $text .= "Total Critical Delays: {$totalValidations} validations\n";
            $text .= "Severe Delays (>50 days): {$severeCount} validations\n";
            $text .= "Average Days Delayed: " . round($avgDaysInProgress, 1) . " days\n";
            $text .= "Longest Delay: {$maxDaysInProgress} days\n";
            $text .= "Total Validation Days Delayed: {$totalDelayDays} days\n\n";
            
            $text .= "BUSINESS IMPACT ANALYSIS:\n";
            $text .= "========================\n";
            $text .= "Compliance Risk Level: " . ($severeCount > 0 ? "HIGH" : "MEDIUM") . "\n";
            $text .= "Resource Efficiency Impact: " . ($avgDaysInProgress > 40 ? "SIGNIFICANT" : "MODERATE") . "\n";
            $text .= "Timeline Recovery Required: IMMEDIATE\n\n";
            
            $text .= sprintf("%-8s %-20s %-25s %-15s %-15s %-15s %s\n", 
                "Priority", "Equipment", "Category", "Planned Start", "Actual Start", "Days Delayed", "Assigned To");
            $text .= str_repeat("-", 140) . "\n";
            
            foreach ($data as $row) {
                $priority = $row['days_in_progress'] > 50 ? 'SEVERE' : 'CRITICAL';
                
                $text .= sprintf("%-8s %-20s %-25s %-15s %-15s %-15s %s\n",
                    $priority,
                    substr($row['equipment_code'], 0, 19),
                    substr($row['equipment_category'], 0, 24),
                    $row['val_wf_planned_start_date'],
                    $row['actual_start_date'],
                    $row['days_in_progress'] . " DAYS",
                    substr($row['assigned_to'] ?: 'Unassigned', 0, 19)
                );
            }
            
            $text .= str_repeat("-", 140) . "\n\n";
            
            $text .= "IMMEDIATE ACTIONS REQUIRED:\n";
            $text .= "==========================\n";
            $text .= "1. ESCALATE TO SENIOR MANAGEMENT: All validations >50 days require C-level attention\n";
            $text .= "2. CONDUCT EMERGENCY REVIEW: Schedule immediate meeting with validation teams\n";
            $text .= "3. RESOURCE REALLOCATION: Assess and provide additional resources if needed\n";
            $text .= "4. PROCESS INTERVENTION: Identify and eliminate blockers immediately\n";
            $text .= "5. STAKEHOLDER COMMUNICATION: Inform all affected parties of revised timelines\n";
            $text .= "6. CORRECTIVE ACTION PLAN: Develop and implement recovery plan within 48 hours\n";
            $text .= "7. COMPLIANCE ASSESSMENT: Review regulatory impact and mitigation strategies\n\n";
            
            $text .= "ESCALATION MATRIX:\n";
            $text .= "=================\n";
            $text .= "Next Escalation Level: Senior Management / Quality Head\n";
            $text .= "Response Time Required: Within 24 hours\n";
            $text .= "Follow-up Schedule: Daily progress updates until resolution\n\n";
            
        } else {
            $text .= "GOOD NEWS: No validations have been in progress for more than 35 days in this unit.\n\n";
        }
        
        $text .= str_repeat("*", 80) . "\n";
        $text .= "This is a CRITICAL automated escalation alert.\n";
        $text .= "Immediate management intervention is required to address these validation delays.\n";
        $text .= str_repeat("*", 80) . "\n\n";
        
        $text .= "Please note that this is a system generated email with high priority.\n\n";
        $text .= "ProVal System - Critical Alert Module\n";
        $text .= "Generated on: " . date('d F Y H:i:s') . " (Asia/Kolkata)\n";
        
        return $text;
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
                    COUNT(CASE WHEN DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) BETWEEN 35 AND 50 THEN 1 END) as critical_validations,
                    AVG(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as avg_days_in_progress,
                    MAX(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as max_days_in_progress,
                    SUM(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as total_delay_days
                 FROM tbl_val_schedules t1
                 JOIN tbl_val_wf_tracking_details t3 ON t1.val_wf_id = t3.val_wf_id
                 WHERE DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) > 35
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