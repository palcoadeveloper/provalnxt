<?php
/**
 * EmailReminderValidationNotStarted30Days
 * 
 * Sends reminder emails for validations that need to be started within the next 30 days.
 * This job provides early warning for upcoming validation activities.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once(__DIR__ . '/../../core/EmailReminderBaseJob.php');

class EmailReminderValidationNotStarted30Days extends EmailReminderBaseJob {
    
    /**
     * Process a specific unit
     */
    protected function processUnit($unit) {
        // Get data for this unit
        $data = $this->getEmailData($unit);
        
        // Always send email, even if no data found (to inform users)
        if (empty($data)) {
            $this->logger->logInfo($this->jobName, "No validation reminders found for unit {$unit['unit_id']} - sending notification email");
        } else {
            $this->logger->logInfo($this->jobName, "Found " . count($data) . " validation reminders for unit {$unit['unit_id']}");
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
        return 'HVACVADMS Alert - Early Warning - list of equipments for which the validation study needs to be initiated in next 30 days';
    }
    
    /**
     * Get data for the email content
     */
    protected function getEmailData($unit) {
        try {
            $query = "SELECT 
                        val_wf_id,
                        t1.unit_id,
                        equipment_code,
                        t1.equip_id, 
                        equipment_category,
                        DATE_FORMAT(t1.val_wf_planned_start_date,'%d %M %Y') as val_wf_planned_start_date, 
                        t3.unit_name,
                        DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE()) as days_remaining
                      FROM tbl_val_schedules t1
                      JOIN equipments t2 ON t1.equip_id = t2.equipment_id 
                      JOIN units t3 ON t1.unit_id = t3.unit_id 
                      WHERE DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE()) BETWEEN 0 AND 30
                        AND val_wf_id NOT IN (SELECT val_wf_id FROM tbl_val_wf_tracking_details) 
                        AND t1.val_wf_status = 'Active' 
                        AND t1.unit_id = %i
                      ORDER BY t1.val_wf_planned_start_date ASC";
            
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
        $html .= "<p style='margin: 0; color: #6c757d;'>Validations to be initiated within next 30 days</p>";
        $html .= "</div>";
        
        $html .= "<p><strong>Dear User,</strong></p>";
        $html .= "<p>This early warning notification identifies validation studies scheduled to begin within the next 30 days to support effective planning and resource allocation.</p>";
        
        if (!empty($data)) {
            // Categorize by urgency
            $urgent = array_filter($data, function($row) { return $row['days_remaining'] <= 7; });
            $warning = array_filter($data, function($row) { return $row['days_remaining'] > 7 && $row['days_remaining'] <= 14; });
            $info = array_filter($data, function($row) { return $row['days_remaining'] > 14; });
            $totalValidations = count($data);
            $urgentCount = count($urgent);
            
            $html .= "<div class='summary'>";
            $html .= "<h2>Summary</h2>";
            $html .= "<p><strong>Total Upcoming Validations:</strong> {$totalValidations}</p>";
            $html .= "<p><strong>Urgent (7 days or less):</strong> {$urgentCount}</p>";
            $html .= "<p><strong>Warning (8-14 days):</strong> " . count($warning) . "</p>";
            $html .= "<p><strong>Information (15-30 days):</strong> " . count($info) . "</p>";
            $html .= "<p><strong>Planning Priority:</strong> " . ($urgentCount > 0 ? "High" : "Medium") . "</p>";
            $html .= "</div>";
            
            $html .= "<table>";
            $html .= "<thead>";
            $html .= "<tr>";
            $html .= "<th>Priority</th>";
            $html .= "<th>Equipment Code</th>";
            $html .= "<th>Category</th>";
            $html .= "<th>Validation Start Date</th>";
            $html .= "<th>Days Remaining</th>";
            $html .= "</tr>";
            $html .= "</thead>";
            $html .= "<tbody>";
            
            foreach ($data as $row) {
                $cssClass = '';
                $priority = '';
                
                if ($row['days_remaining'] <= 7) {
                    $priority = '<span class="priority-high">High</span>';
                } elseif ($row['days_remaining'] <= 14) {
                    $priority = '<span class="priority-medium">Medium</span>';
                } else {
                    $priority = '<span style="color: #6c757d;">Low</span>';
                }
                
                $html .= "<tr>";
                $html .= "<td>{$priority}</td>";
                $html .= "<td><strong>" . htmlspecialchars($row['equipment_code']) . "</strong></td>";
                $html .= "<td>" . htmlspecialchars($row['equipment_category']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['val_wf_planned_start_date']) . "</td>";
                $html .= "<td><strong>" . $row['days_remaining'] . " days</strong></td>";
                $html .= "</tr>";
            }
            
            $html .= "</tbody>";
            $html .= "</table>";
            
            $html .= "<div class='actions'>";
            $html .= "<h3>Recommended Actions</h3>";
            $html .= "<ol>";
            $html .= "<li>Review and confirm validation schedules for upcoming activities</li>";
            $html .= "<li>Assess team availability and resource requirements</li>";
            $html .= "<li>Prepare validation protocols and documentation</li>";
            $html .= "<li>Coordinate with equipment and facility management</li>";
            $html .= "<li>Plan for any necessary equipment preparation or calibration</li>";
            $html .= "</ol>";
            $html .= "</div>";
            
            $html .= "</tbody>";
            $html .= "</table>";
        } else {
            $html .= "<div class='summary'>";
            $html .= "<p style='color: #28a745; font-weight: 600;'>No equipment validations are scheduled within the next 30 days for this unit.</p>";
            $html .= "<p>All scheduled validations are appropriately planned with adequate lead time.</p>";
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
        $text .= "This is an early warning notification for equipment validations scheduled to begin within the next 30 days.\n\n";
        
        if (!empty($data)) {
            // Categorize by urgency
            $urgent = array_filter($data, function($row) { return $row['days_remaining'] <= 7; });
            $warning = array_filter($data, function($row) { return $row['days_remaining'] > 7 && $row['days_remaining'] <= 14; });
            $info = array_filter($data, function($row) { return $row['days_remaining'] > 14; });
            
            $text .= "SUMMARY:\n";
            $text .= "--------\n";
            $text .= "Urgent (≤7 days): " . count($urgent) . " validations\n";
            $text .= "Warning (8-14 days): " . count($warning) . " validations\n";
            $text .= "Information (15-30 days): " . count($info) . " validations\n";
            $text .= "Total: " . count($data) . " validations\n\n";
            
            $text .= sprintf("%-8s %-15s %-20s %-25s %-20s %s\n", "Priority", "Unit", "Equipment", "Category", "Start Date", "Days Remaining");
            $text .= str_repeat("-", 110) . "\n";
            
            foreach ($data as $row) {
                $priority = '';
                if ($row['days_remaining'] <= 7) {
                    $priority = 'URGENT';
                } elseif ($row['days_remaining'] <= 14) {
                    $priority = 'WARNING';
                } else {
                    $priority = 'INFO';
                }
                
                $text .= sprintf("%-8s %-15s %-20s %-25s %-20s %s days\n",
                    $priority,
                    substr($row['unit_name'], 0, 14),
                    substr($row['equipment_code'], 0, 19),
                    substr($row['equipment_category'], 0, 24),
                    $row['val_wf_planned_start_date'],
                    $row['days_remaining']
                );
            }
            
            $text .= str_repeat("-", 110) . "\n\n";
        } else {
            $text .= "No equipment validations are scheduled within the next 30 days for this unit.\n\n";
        }
        
        $text .= "ACTION REQUIRED: Please review the schedule and ensure proper planning for upcoming validations.\n\n";
        $text .= "Please note that this is a system generated email.\n\n";
        $text .= "Best regards,\nProVal System\n\n";
        $text .= "Generated on: " . date('d F Y H:i:s') . "\n";
        
        return $text;
    }
    
    /**
     * Check if this job should run less frequently than daily
     */
    protected function isJobEnabled() {
        // This job could run weekly instead of daily to reduce email volume
        // Check if today is the configured day for this job
      //  $dayOfWeek = date('w'); // 0 = Sunday, 1 = Monday, etc.
        
        // Run on Mondays (1) and Thursdays (4) for better coverage
      //  return in_array($dayOfWeek, [1, 4]);
		
		return true;
    }
    
    /**
     * Get job-specific statistics
     */
    public function getJobSpecificStats($days = 30) {
        try {
            return DB::queryFirstRow(
                "SELECT 
                    COUNT(DISTINCT t1.unit_id) as units_with_upcoming_validations,
                    COUNT(*) as total_upcoming_validations,
                    COUNT(CASE WHEN DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE()) <= 7 THEN 1 END) as urgent_validations,
                    COUNT(CASE WHEN DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE()) BETWEEN 8 AND 14 THEN 1 END) as warning_validations,
                    COUNT(CASE WHEN DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE()) BETWEEN 15 AND 30 THEN 1 END) as info_validations,
                    AVG(DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE())) as avg_days_until_start
                 FROM tbl_val_schedules t1
                 WHERE DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE()) BETWEEN 0 AND 30
                   AND val_wf_id NOT IN (SELECT val_wf_id FROM tbl_val_wf_tracking_details) 
                   AND t1.val_wf_status = 'Active'"
            );
        } catch (Exception $e) {
            $this->logger->logError($this->jobName, "Failed to get job stats: " . $e->getMessage());
            return null;
        }
    }
}
?>