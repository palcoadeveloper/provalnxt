<?php
/**
 * EmailReminderValidationNotStarted10Days
 * 
 * Sends reminder emails for validations that need to be started within the next 10 days.
 * This job checks for equipment validations with planned start dates between 0-10 days 
 * from today that haven't been initiated yet.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once(__DIR__ . '/../../core/EmailReminderBaseJob.php');

class EmailReminderValidationNotStarted10Days extends EmailReminderBaseJob {
    
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
        return 'HVACVADMS Alert - Reminder Email - list of equipments for which the validation study needs to be initiated in next 10 days';
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
                        t3.unit_name
                      FROM tbl_val_schedules t1
                      JOIN equipments t2 ON t1.equip_id = t2.equipment_id 
                      JOIN units t3 ON t1.unit_id = t3.unit_id 
                      WHERE DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE()) BETWEEN 0 AND 10
                        AND val_wf_id NOT IN (SELECT val_wf_id FROM tbl_val_wf_tracking_details) 
                        AND t1.val_wf_status = 'Active' 
                        AND t1.unit_id = %i
                      ORDER BY t1.val_wf_planned_start_date ASC";
            
            // Debug logging
            $this->logger->logInfo($this->jobName, "getEmailData called for unit {$unit['unit_id']}");
            
            // FIX: Use direct string substitution instead of DB parameter binding
            // The DB::query parameter binding seems to have issues in this context
            $fixedQuery = str_replace('%i', intval($unit['unit_id']), $query);
            $result = DB::query($fixedQuery);
            
            $this->logger->logInfo($this->jobName, "Found " . count($result) . " validation records for unit {$unit['unit_id']}");
            
            return $result;
            
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
        $html .= "<p style='margin: 0; color: #6c757d;'>Validations to be initiated within next 10 days</p>";
        $html .= "</div>";
        
        $html .= "<p><strong>Dear User,</strong></p>";
        $html .= "<p>This reminder identifies upcoming validation studies that need to be initiated within the next 10 days to maintain schedule compliance.</p>";
        
        if (!empty($data)) {
            $totalValidations = count($data);
            $urgentCount = 0;
            foreach ($data as $row) {
                $startDate = DateTime::createFromFormat('d F Y', $row['val_wf_planned_start_date']);
                $today = new DateTime();
                $daysRemaining = $today->diff($startDate)->days;
                if ($daysRemaining <= 3) $urgentCount++;
            }
            
            $html .= "<div class='summary'>";
            $html .= "<h2>Summary</h2>";
            $html .= "<p><strong>Total Upcoming Validations:</strong> {$totalValidations}</p>";
            $html .= "<p><strong>Urgent (3 days or less):</strong> {$urgentCount}</p>";
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
                // Calculate days remaining
                $startDate = DateTime::createFromFormat('d F Y', $row['val_wf_planned_start_date']);
                $today = new DateTime();
                $daysRemaining = $today->diff($startDate)->days;
                
                $cssClass = '';
                $priority = '';
                
                if ($daysRemaining <= 3) {
                    $priority = '<span class="priority-high">High</span>';
                } else {
                    $priority = '<span class="priority-medium">Medium</span>';
                }
                
                $html .= "<tr>";
                $html .= "<td>{$priority}</td>";
                $html .= "<td>" . htmlspecialchars($row['equipment_code']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['equipment_category']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row['val_wf_planned_start_date']) . "</td>";
                $html .= "<td>" . $daysRemaining . " days</td>";
                $html .= "</tr>";
            }
            
            $html .= "</tbody>";
            $html .= "</table>";
        } else {
            $html .= "<div class='summary'>";
            $html .= "<p style='color: #28a745; font-weight: 600;'>No equipment validations are due within the next 10 days for this unit.</p>";
            $html .= "<p>All scheduled validations are on track with adequate lead time.</p>";
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
        $text .= "Please find below the list of equipments for which the validation study needs to be initiated within next 10 days.\n\n";
        
        if (!empty($data)) {
            $text .= sprintf("%-20s %-20s %-25s %-20s %s\n", "Unit", "Equipment", "Category", "Start Date", "Days Remaining");
            $text .= str_repeat("-", 100) . "\n";
            
            foreach ($data as $row) {
                // Calculate days remaining
                $startDate = DateTime::createFromFormat('d F Y', $row['val_wf_planned_start_date']);
                $today = new DateTime();
                $daysRemaining = $today->diff($startDate)->days;
                
                $text .= sprintf("%-20s %-20s %-25s %-20s %s days\n",
                    substr($row['unit_name'], 0, 19),
                    substr($row['equipment_code'], 0, 19),
                    substr($row['equipment_category'], 0, 24),
                    $row['val_wf_planned_start_date'],
                    $daysRemaining
                );
            }
            
            $text .= str_repeat("-", 100) . "\n";
            $text .= "Total Equipment: " . count($data) . "\n\n";
        } else {
            $text .= "No equipment validations are due within the next 10 days for this unit.\n\n";
        }
        
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
                    COUNT(DISTINCT t1.unit_id) as units_with_upcoming_validations,
                    COUNT(*) as total_upcoming_validations,
                    AVG(DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE())) as avg_days_until_start,
                    MIN(t1.val_wf_planned_start_date) as earliest_validation_date,
                    MAX(t1.val_wf_planned_start_date) as latest_validation_date
                 FROM tbl_val_schedules t1
                 WHERE DATEDIFF(t1.val_wf_planned_start_date, CURRENT_DATE()) BETWEEN 0 AND 10
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