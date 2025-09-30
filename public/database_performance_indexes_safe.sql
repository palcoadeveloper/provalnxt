-- ================================================================
-- ProVal HVAC - Database Performance Optimization Indexes (SAFE)
-- ================================================================
-- This script creates indexes only if they don't already exist
-- Based on actual table schema analysis
-- ================================================================

-- Drop existing custom indexes to avoid conflicts
DROP INDEX IF EXISTS idx_equipments_status_dept ON equipments;
DROP INDEX IF EXISTS idx_equipments_dept_status ON equipments;
DROP INDEX IF EXISTS idx_equipments_dept_id ON equipments;
DROP INDEX IF EXISTS idx_equipments_unit_id ON equipments;
DROP INDEX IF EXISTS idx_equipments_status ON equipments;
DROP INDEX IF EXISTS idx_users_employee_id ON users;
DROP INDEX IF EXISTS idx_users_status_dept ON users;
DROP INDEX IF EXISTS idx_users_dept_unit ON users;
DROP INDEX IF EXISTS idx_users_status ON users;
DROP INDEX IF EXISTS idx_users_email ON users;

-- ================================================================
-- CRITICAL PRIORITY INDEXES (Most Frequent Queries)
-- ================================================================

-- 1. Equipment Status and Department Queries
CREATE INDEX idx_equipments_status_dept ON equipments (equipment_status, department_id);
CREATE INDEX idx_equipments_dept_status ON equipments (department_id, equipment_status);
CREATE INDEX idx_equipments_dept_id ON equipments (department_id);
CREATE INDEX idx_equipments_status ON equipments (equipment_status);

-- 2. User Authentication and Management
CREATE INDEX idx_users_employee_id ON users (employee_id);
CREATE INDEX idx_users_status_dept ON users (user_status, department_id);
CREATE INDEX idx_users_dept_unit ON users (department_id, unit_id);
CREATE INDEX idx_users_status ON users (user_status);
CREATE INDEX idx_users_email ON users (user_email);

-- 3. Equipment Code and Type Queries
CREATE INDEX idx_equipments_type_status ON equipments (equipment_type, equipment_status);
CREATE INDEX idx_equipments_category ON equipments (equipment_category);

-- 4. Date-based Queries (Very Common)
CREATE INDEX idx_equipments_creation_date ON equipments (equipment_creation_datetime);
CREATE INDEX idx_users_created_date ON users (user_created_datetime);
CREATE INDEX idx_equipments_validation_date ON equipments (first_validation_date);

-- 5. Validation Frequency Optimization
CREATE INDEX idx_equipments_frequency ON equipments (validation_frequency, equipment_status);
CREATE INDEX idx_equipments_starting_freq ON equipments (starting_frequency, equipment_status);

-- ================================================================
-- HIGH PRIORITY INDEXES (Frequently Used Tables)
-- ================================================================

-- 6. Audit Trail Optimization (Critical for Compliance)
CREATE INDEX idx_audit_trail_date ON audit_trail (audit_datetime);
CREATE INDEX idx_audit_trail_table_date ON audit_trail (table_name, audit_datetime);
CREATE INDEX idx_audit_trail_user_date ON audit_trail (user_id, audit_datetime);
CREATE INDEX idx_audit_trail_table ON audit_trail (table_name);

-- 7. Instrument Management
CREATE INDEX idx_instruments_equipment ON instruments (equipment_id);
CREATE INDEX idx_instruments_status ON instruments (instrument_status);
CREATE INDEX idx_instruments_calibration ON instruments (next_calibration_date);
CREATE INDEX idx_instruments_creation ON instruments (instrument_creation_datetime);

-- 8. Filter Management
CREATE INDEX idx_filters_equipment ON filters (equipment_id);
CREATE INDEX idx_filters_status ON filters (filter_status);
CREATE INDEX idx_filter_groups_status ON filter_groups (filter_group_status);

-- 9. Department and Unit Management
CREATE INDEX idx_departments_status ON departments (department_status);
CREATE INDEX idx_units_dept ON units (department_id);
CREATE INDEX idx_units_status ON units (unit_status);

-- 10. ERF Mappings
CREATE INDEX idx_erf_mappings_equipment ON erf_mappings (equipment_id);
CREATE INDEX idx_erf_mappings_filter ON erf_mappings (filter_id);
CREATE INDEX idx_erf_mappings_status ON erf_mappings (erf_mapping_status);

-- ================================================================
-- MEDIUM PRIORITY INDEXES (Additional Optimizations)
-- ================================================================

-- 11. Equipment Test Vendor Mapping
CREATE INDEX idx_equipment_test_vendor_equipment ON equipment_test_vendor_mapping (equipment_id);
CREATE INDEX idx_equipment_test_vendor_test ON equipment_test_vendor_mapping (test_id);
CREATE INDEX idx_equipment_test_vendor_vendor ON equipment_test_vendor_mapping (vendor_id);

-- 12. Auto Schedule Configuration
CREATE INDEX idx_auto_schedule_config_equipment ON auto_schedule_config (equipment_id);
CREATE INDEX idx_auto_schedule_config_status ON auto_schedule_config (status);
CREATE INDEX idx_auto_schedule_log_date ON auto_schedule_log (log_datetime);

-- 13. Instrument Approvals and Calibrations
CREATE INDEX idx_instrument_approval_audit_instrument ON instrument_approval_audit (instrument_id);
CREATE INDEX idx_instrument_approval_audit_date ON instrument_approval_audit (approval_datetime);
CREATE INDEX idx_instrument_calibration_approvals_instrument ON instrument_calibration_approvals (instrument_id);
CREATE INDEX idx_instrument_calibration_approvals_date ON instrument_calibration_approvals (calibration_date);

-- 14. Certificate History
CREATE INDEX idx_instrument_certificate_history_instrument ON instrument_certificate_history (instrument_id);
CREATE INDEX idx_instrument_certificate_history_date ON instrument_certificate_history (certificate_date);

-- 15. Error Logging
CREATE INDEX idx_error_log_date ON error_log (error_datetime);
CREATE INDEX idx_error_log_user_date ON error_log (user_id, error_datetime);

-- 16. Log Table Optimization
CREATE INDEX idx_log_date ON log (log_datetime);
CREATE INDEX idx_log_user_date ON log (user_id, log_datetime);
CREATE INDEX idx_log_action_date ON log (action_type, log_datetime);

-- ================================================================
-- COMPOSITE INDEXES FOR COMPLEX QUERIES
-- ================================================================

-- 17. Equipment Dashboard Index
CREATE INDEX idx_equipments_dashboard ON equipments (department_id, equipment_status, equipment_creation_datetime);

-- 18. User Management Index
CREATE INDEX idx_users_management ON users (department_id, user_status, user_created_datetime);

-- 19. Equipment Validation Index
CREATE INDEX idx_equipments_validation ON equipments (equipment_status, validation_frequency, first_validation_date);

-- ================================================================
-- ADMIN AND ROLE-BASED INDEXES
-- ================================================================

-- 20. Admin Role Optimization
CREATE INDEX idx_users_admin ON users (is_admin, user_status);
CREATE INDEX idx_users_super_admin ON users (is_super_admin, user_status);
CREATE INDEX idx_users_qa_head ON users (is_qa_head, department_id);
CREATE INDEX idx_users_unit_head ON users (is_unit_head, unit_id);
CREATE INDEX idx_users_dept_head ON users (is_dept_head, department_id);

-- ================================================================
-- SEARCH OPTIMIZATION INDEXES
-- ================================================================

-- 21. Equipment Search Optimization
CREATE INDEX idx_equipments_search_code_status ON equipments (equipment_code, equipment_status);
CREATE INDEX idx_equipments_search_section_status ON equipments (section, equipment_status);

-- 22. User Search Optimization
CREATE INDEX idx_users_search_name_status ON users (user_name, user_status);
CREATE INDEX idx_users_search_email_status ON users (user_email, user_status);

-- ================================================================
-- EQUIPMENT DETAIL INDEXES
-- ================================================================

-- 23. Equipment Technical Details
CREATE INDEX idx_equipments_type ON equipments (equipment_type);
CREATE INDEX idx_equipments_classification ON equipments (area_classification);

-- ================================================================
-- ANALYZE TABLES AFTER INDEX CREATION
-- ================================================================

ANALYZE TABLE equipments;
ANALYZE TABLE users;
ANALYZE TABLE instruments;
ANALYZE TABLE filters;
ANALYZE TABLE departments;
ANALYZE TABLE units;
ANALYZE TABLE audit_trail;
ANALYZE TABLE erf_mappings;
ANALYZE TABLE filter_groups;
ANALYZE TABLE equipment_test_vendor_mapping;
ANALYZE TABLE auto_schedule_config;
ANALYZE TABLE auto_schedule_log;
ANALYZE TABLE instrument_approval_audit;
ANALYZE TABLE instrument_calibration_approvals;
ANALYZE TABLE instrument_certificate_history;
ANALYZE TABLE error_log;
ANALYZE TABLE log;

-- ================================================================
-- VERIFICATION: Show created indexes
-- ================================================================

SELECT
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'provalnxt_demo'
    AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;

-- ================================================================
-- END OF SAFE INDEX SCRIPT
-- ================================================================