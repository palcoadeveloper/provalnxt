<?php
/**
 * EmailReminderValidationInProgress38Days
 * 
 * Sends the highest level critical alerts for validations that have been in progress 
 * for more than 38 days. This represents the final escalation level requiring 
 * immediate executive intervention and emergency response protocols.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once(__DIR__ . '/../../core/EmailReminderBaseJob.php');

class EmailReminderValidationInProgress38Days extends EmailReminderBaseJob {
    
    /**
     * Process a specific unit
     */
    protected function processUnit($unit) {
        // Get data for this unit
        $data = $this->getEmailData($unit);
        
        // Always send email, even if no data found (to inform users)
        if (empty($data)) {
            $this->logger->logInfo($this->jobName, "No emergency-level overdue validations found for unit {$unit['unit_id']} - sending notification email");
        } else {
            // This is the highest priority - log as critical system event
            $this->logger->logError($this->jobName, 
                "EMERGENCY: {$unit['unit_name']} has " . count($data) . " validations >38 days - Executive intervention required");
        }
        
        // Generate email content
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
        return 'HVACVADMS ESCALATION ALERT - CRITICAL - Validation studies in progress for more than 38 days - CRITICAL';
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
                        WHERE DATEDIFF(current_date(),actual_wf_start_datetime) >= 38 and (val_wf_current_stage!=5 and val_wf_current_stage!=99) and t1.unit_id=%i
                        and t1.status='Active' and t2.val_wf_status='Active'
						ORDER BY DATEDIFF(CURRENT_DATE(), t1.actual_wf_start_datetime)";
            
            // FIX: Use direct string substitution instead of DB parameter binding
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
        $html .= "th { background-color: #495057; color: white; font-weight: 600; }";
        $html .= "tr:nth-child(even) { background-color: #f8f9fa; }";
        $html .= ".header { color: #495057; font-weight: 600; text-align: center; padding: 20px 0; border-bottom: 3px solid #dc3545; margin-bottom: 30px; }";
        $html .= ".footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 13px; color: #6c757d; }";
        $html .= ".summary { background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid #dc3545; }";
        $html .= ".actions { background-color: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid #ffc107; }";
        $html .= ".escalation { background-color: #f5c6cb; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid #dc3545; }";
        $html .= ".risk-assessment { background-color: #e2e3e5; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid #6c757d; }";
        $html .= "h1 { color: #dc3545; font-size: 28px; margin: 0 0 10px 0; }";
        $html .= "h2 { color: #495057; font-size: 22px; margin: 20px 0 10px 0; }";
        $html .= "h3 { color: #495057; font-size: 18px; margin: 15px 0 10px 0; }";
        $html .= ".priority-emergency { color: #dc3545; font-weight: 700; }";
        $html .= ".priority-critical { color: #ffc107; font-weight: 600; }";
        $html .= ".priority-severe { color: #fd7e14; font-weight: 600; }";
        $html .= "</style></head><body><div class='container'>";
        
        $html .= "<div class='header'>";
        $html .= "<h1>ProVal System Validation Alert</h1>";
        $html .= "<p style='margin: 0; color: #6c757d;'>Validation studies in progress for more than 38 days</p>";
        $html .= "</div>";
        
        $html .= "<p><strong>Dear User,</strong></p>";
        $html .= "<p>This alert identifies validation studies that have exceeded the 38-day progress threshold and require immediate attention to ensure compliance and operational efficiency.</p>";
        
        if (!empty($data)) {
            // Calculate metrics
            $totalValidations = count($data);
            $emergencyCount = count(array_filter($data, function($row) { return $row['days_in_progress'] > 60; }));
            $criticalCount = count(array_filter($data, function($row) { return $row['days_in_progress'] > 45 && $row['days_in_progress'] <= 60; }));
            $avgDaysInProgress = array_sum(array_column($data, 'days_in_progress')) / $totalValidations;
            $maxDaysInProgress = max(array_column($data, 'days_in_progress'));
            $totalDelayDays = array_sum(array_column($data, 'days_in_progress'));
            
            $html .= "<div class='summary'>";
            $html .= "<h2>Summary</h2>";
            $html .= "<p><strong>Total Affected Validations:</strong> {$totalValidations}</p>";
            $html .= "<p><strong>Severe Cases (>60 days):</strong> {$emergencyCount}</p>";
            $html .= "<p><strong>Average Days in Progress:</strong> " . round($avgDaysInProgress, 1) . " days</p>";
            $html .= "<p><strong>Maximum Delay:</strong> {$maxDaysInProgress} days</p>";
            $html .= "<p><strong>Compliance Risk Level:</strong> " . ($emergencyCount > 0 ? "High" : "Medium") . "</p>";
            $html .= "</div>";
            
            $html .= "<table>";
            $html .= "<thead>";
            $html .= "<tr>";
            $html .= "<th>Risk Level</th>";
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
                $riskLevel = '';
                
                if ($row['days_in_progress'] > 50) {
                    $riskLevel = '<span class="priority-high">High</span>';
                } else {
                    $riskLevel = '<span class="priority-medium">Medium</span>';
                }
                
                $html .= "<tr>";
                $html .= "<td>{$riskLevel}</td>";
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
            $html .= "</div>"; */
            
        } else {
            $html .= "<div class='summary'>";
            $html .= "<p style='color: #28a745; font-weight: 600;'>No validations have exceeded the critical 38-day threshold in this unit.</p>";
            $html .= "<p>All validation processes are operating within acceptable executive oversight parameters.</p>";
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
        $text = str_repeat("█", 80) . "\n";
        $text .= "███ EMERGENCY VALIDATION CRISIS ALERT ███\n";
        $text .= str_repeat("█", 80) . "\n\n";
        $text .= "{$subject}\n";
        $text .= str_repeat("=", 80) . "\n\n";
        $text .= "URGENT MESSAGE TO EXECUTIVE LEADERSHIP:\n\n";
        $text .= "VALIDATION PROCESS BREAKDOWN DETECTED: The following validation studies have\n";
        $text .= "exceeded the critical 38-day threshold, indicating severe operational failures\n";
        $text .= "that pose immediate compliance, regulatory, and business continuity risks.\n\n";
        
        if (!empty($data)) {
            // Calculate emergency metrics
            $totalValidations = count($data);
            $emergencyCount = count(array_filter($data, function($row) { return $row['days_in_progress'] > 60; }));
            $criticalCount = count(array_filter($data, function($row) { return $row['days_in_progress'] > 45 && $row['days_in_progress'] <= 60; }));
            $avgDaysInProgress = array_sum(array_column($data, 'days_in_progress')) / $totalValidations;
            $maxDaysInProgress = max(array_column($data, 'days_in_progress'));
            $totalDelayDays = array_sum(array_column($data, 'days_in_progress'));
            
            $text .= "EMERGENCY CRISIS METRICS:\n";
            $text .= "========================\n";
            $text .= "Total Emergency Delays: {$totalValidations} validations\n";
            $text .= "System Failures (>60 days): {$emergencyCount} validations\n";
            $text .= "Critical Delays (45-60 days): {$criticalCount} validations\n";
            $text .= "Average Delay: " . round($avgDaysInProgress, 1) . " days\n";
            $text .= "Maximum Delay: {$maxDaysInProgress} days\n";
            $text .= "Total Lost Days: {$totalDelayDays} days\n\n";
            
            $text .= "REGULATORY & COMPLIANCE RISK ASSESSMENT:\n";
            $text .= "=======================================\n";
            $text .= "Regulatory Compliance Status: " . ($emergencyCount > 0 ? "CRITICAL FAILURE" : "SEVERE RISK") . "\n";
            $text .= "Audit Exposure Level: MAXIMUM\n";
            $text .= "GMP Compliance Impact: SEVERE DEVIATION\n";
            $text .= "Business Continuity Risk: " . ($totalValidations > 5 ? "HIGH" : "MEDIUM") . "\n";
            $text .= "Financial Impact Estimate: " . ($totalDelayDays > 300 ? "SIGNIFICANT" : "MODERATE") . "\n\n";
            
            $text .= sprintf("%-12s %-20s %-25s %-15s %-15s %-15s %s\n", 
                "RISK LEVEL", "Equipment", "Category", "Planned Start", "Actual Start", "Delay Days", "Assigned To");
            $text .= str_repeat("=", 140) . "\n";
            
            foreach ($data as $row) {
                $riskLevel = '';
                if ($row['days_in_progress'] > 60) {
                    $riskLevel = 'EMERGENCY';
                } elseif ($row['days_in_progress'] > 45) {
                    $riskLevel = 'CRITICAL';
                } else {
                    $riskLevel = 'SEVERE';
                }
                
                $text .= sprintf("%-12s %-20s %-25s %-15s %-15s %-15s %s\n",
                    $riskLevel,
                    substr($row['equipment_code'], 0, 19),
                    substr($row['equipment_category'], 0, 24),
                    $row['val_wf_planned_start_date'],
                    $row['actual_start_date'],
                    $row['days_in_progress'] . " DAYS",
                    substr($row['assigned_to'] ?: 'UNASSIGNED', 0, 19)
                );
            }
            
            $text .= str_repeat("=", 140) . "\n\n";
            
            $text .= "EXECUTIVE EMERGENCY RESPONSE PROTOCOL:\n";
            $text .= "=====================================\n";
            $text .= "IMMEDIATE ACTIONS REQUIRED (WITHIN 12 HOURS):\n\n";
            $text .= "1. CONVENE EMERGENCY COMMITTEE: CEO/Quality Head/Plant Head immediate meeting\n";
            $text .= "2. DECLARE OPERATIONAL CRISIS: Activate crisis management protocols\n";
            $text .= "3. RESOURCE MOBILIZATION: Reallocate personnel and resources immediately\n";
            $text .= "4. REGULATORY NOTIFICATION: Assess need for regulatory body communication\n";
            $text .= "5. EXTERNAL EXPERT CONSULTATION: Engage validation consultants if required\n";
            $text .= "6. COMPLETE PROCESS AUDIT: Investigate root causes of systemic delays\n";
            $text .= "7. STAKEHOLDER COMMUNICATION: Prepare crisis communication plan\n";
            $text .= "8. RECOVERY TIMELINE: Establish aggressive completion targets\n\n";
            
            $text .= "EMERGENCY ESCALATION CHAIN:\n";
            $text .= "===========================\n";
            $text .= "IMMEDIATE ESCALATION TO:\n";
            $text .= "Level 1: Chief Executive Officer (CEO)\n";
            $text .= "Level 2: Chief Quality Officer / Quality Head\n";
            $text .= "Level 3: Plant Head / Site Manager\n";
            $text .= "Level 4: Validation Manager / Engineering Head\n\n";
            $text .= "RESPONSE TIME: IMMEDIATE (Within 2 hours)\n";
            $text .= "FOLLOW-UP: Hourly updates until crisis resolution\n";
            $text .= "RESOLUTION TARGET: Emergency action plan within 24 hours\n\n";
            
        } else {
            $text .= "CRISIS AVERTED:\n";
            $text .= "===============\n";
            $text .= "EXCELLENT NEWS: No validations have exceeded the critical 38-day emergency\n";
            $text .= "threshold in this unit. The validation process appears to be functioning\n";
            $text .= "within acceptable parameters.\n\n";
        }
        
        $text .= str_repeat("█", 80) . "\n";
        $text .= "This is the HIGHEST PRIORITY automated emergency alert from the ProVal system.\n";
        $text .= "This alert indicates catastrophic validation process failures requiring\n";
        $text .= "immediate C-level intervention. Delayed response may result in regulatory\n";
        $text .= "sanctions, compliance failures, and business disruption.\n";
        $text .= str_repeat("█", 80) . "\n\n";
        
        $text .= "ProVal System - Emergency Alert Module\n";
        $text .= "Generated on: " . date('d F Y H:i:s') . " (Asia/Kolkata)\n";
        $text .= "Alert Level: EMERGENCY\n";
        
        return $text;
    }
    
    /**
     * This is the highest priority job - additional logging and notifications
     */
    protected function performCleanup() {
        parent::performCleanup();
        
        // Send additional system notification for emergency conditions
        if ($this->jobExecutionId) {
            $this->logger->logError($this->jobName, "Emergency validation alert job completed - Check results immediately");
        }
    }
    
    /**
     * Get job-specific statistics with enhanced emergency metrics
     */
    public function getJobSpecificStats($days = 30) {
        try {
            return DB::queryFirstRow(
                "SELECT 
                    COUNT(DISTINCT t1.unit_id) as units_with_critical_delays,
                    COUNT(*) as total_critical_validations,
                    COUNT(CASE WHEN DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) > 50 THEN 1 END) as severe_validations,
                    COUNT(CASE WHEN DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) BETWEEN 38 AND 50 THEN 1 END) as critical_validations,
                    AVG(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as avg_days_in_progress,
                    MAX(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as max_days_in_progress,
                    SUM(DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime)) as total_delay_days
                 FROM tbl_val_schedules t1
                 JOIN tbl_val_wf_tracking_details t3 ON t1.val_wf_id = t3.val_wf_id
                 WHERE DATEDIFF(CURRENT_DATE(), t3.actual_wf_start_datetime) > 38
              --     AND t3.wf_status IN ('In Progress', 'Pending', 'Review')
                   AND t1.val_wf_status = 'Active'"
            );
        } catch (Exception $e) {
            $this->logger->logError($this->jobName, "Failed to get emergency stats: " . $e->getMessage());
            return null;
        }
    }
}
?>