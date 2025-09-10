# Master Data Collection Format
## ProVal HVAC Validation Management System

Dear Client,

To complete the setup of your ProVal HVAC system, we need you to provide the following master data in the specified formats. Please review each section carefully and provide the data as outlined below.

---

## 1. EQUIPMENT MASTER DATA

### Required Information:
Please provide details for all HVAC equipment that will undergo validation testing.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| equipment_code | Unique identifier for equipment | AHU-01, FCU-205, MAU-001 | Text (50) | Yes |
| unit_id | Unit/facility ID where equipment is located | 1, 2, 3 | Number | Yes |
| department_id | Department responsible for equipment | 1, 2, 3 | Number | Yes |
| equipment_category | Category of equipment | Primary HVAC, Secondary HVAC, Support | Text (100) | Yes |
| validation_frequency | How often validation is required | Annual, Bi-Annual, Quarterly | Text (50) | Yes |
| first_validation_date | Date of first validation | 2024-01-15 | Date | Yes |
| validation_frequencies | JSON of validation frequencies | {"annual": true, "biannual": false} | JSON | No |
| starting_frequency | Initial validation frequency | Annual | Text (50) | No |
| area_served | Area/zone served by equipment | Production Area A, Clean Room 101 | Text (200) | Yes |
| section | Building section/floor | Ground Floor, Second Floor | Text (100) | Yes |
| design_acph | Design air changes per hour | 20, 15, 10 | Number (decimal) | Yes |
| area_classification | Area classification served | ISO 5/Grade 'B', ISO 7/Grade 'C' | Text (200) | Yes |
| area_classification_in_operation | Classification during operation | ISO 5/Grade 'B', ISO 7/Grade 'C' | Text (200) | Yes |
| equipment_type | Type of HVAC equipment | AHU, FCU, MAU, Exhaust Fan | Text (100) | Yes |
| design_cfm | Design CFM capacity | 5000, 15000, 2500 | Number (decimal) | Yes |
| filteration_fresh_air | Fresh air filtration details | G4 Pre + F8 Secondary | Text (200) | No |
| filteration_pre_filter | Pre-filter specifications | G4, F6, F8 | Text (200) | No |
| filteration_intermediate | Intermediate filter specs | F8, F9 | Text (200) | No |
| filteration_final_filter_plenum | Final filter in plenum | H13, H14 | Text (200) | No |
| filteration_exhaust_pre_filter | Exhaust pre-filter specs | G4, F6 | Text (200) | No |
| filteration_exhaust_final_filter | Exhaust final filter specs | H13, H14 | Text (200) | No |
| filteration_terminal_filter | Terminal filter specifications | H14, H13 | Text (200) | No |
| filteration_terminal_filter_on_riser | Terminal filter on riser | H14 at ceiling level | Text (200) | No |
| filteration_bibo_filter | Bag-in-bag-out filter | H14 BIBO, H13 BIBO | Text (200) | No |
| filteration_relief_filter | Relief filter specifications | H13 Relief | Text (200) | No |
| filteration_reativation_filter | Reactivation filter specs | Carbon + HEPA | Text (200) | No |
| equipment_status | Current status of equipment | Active, Inactive, Maintenance | Text (50) | Yes |
| equipment_addition_date | Date equipment was added to system | 2024-01-15 | Date | Yes |

**Sample Data:**
```
equipment_code: AHU-01
unit_id: 1
department_id: 1
equipment_category: Primary HVAC
validation_frequency: Annual
first_validation_date: 2024-01-15
area_served: Production Hall A
section: Ground Floor
design_acph: 20
area_classification: ISO 5/Grade 'B'
area_classification_in_operation: ISO 5/Grade 'B'
equipment_type: AHU
design_cfm: 15000
equipment_status: Active
equipment_addition_date: 2024-01-15
```

---

## 2. ERF MAPPINGS (Equipment-Room-Filter Mappings)

### Required Information:
Mapping between equipment, rooms, and their area classifications.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| equipment_id | Equipment ID from Equipment Master (numeric ID) | 1, 2, 3 | Number | Yes |
| room_loc_id | Room Location ID from Room Master (numeric ID) | 1, 2, 3 | Number | Yes |
| area_classification | Area classification for this mapping | ISO 5/Grade 'B', ISO 7/Grade 'C' | Text (200) | Yes |
| filter_name | Filter name/description (optional) | AHU-01/THF/0.3mu/01/A | Text (200) | No |
| filter_id | Filter ID from Filters Master (if applicable) | 1, 2, 3 | Number | No |
| erf_mapping_status | Status of mapping | Active, Inactive | Enum | Yes |

**Sample Data:**
```
equipment_id: 1
room_loc_id: 1
area_classification: ISO 5/Grade 'B'
filter_name: AHU-01/THF/0.3mu/01/A
filter_id: 1
erf_mapping_status: Active
```

**Note:** Equipment IDs and Room Location IDs should reference the auto-generated IDs from their respective master tables.

---

## 3. UNITS MASTER DATA

### Required Information:
Units/facilities where equipment is located.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| unit_id | Unique unit identifier (numeric) | 1, 2, 3 | Number | Yes |
| unit_name | Name of unit/facility | Manufacturing Unit 1, Lab Building A | Text (100) | Yes |
| unit_status | Status of unit | Active, Inactive | Enum | Yes |
| primary_test_id | Primary test type for this unit | 1, 2, 3 | Number | No |
| secondary_test_id | Secondary test type for this unit | 1, 2, 3 | Number | No |

**Sample Data:**
```
unit_id: 1
unit_name: Manufacturing Unit 1
unit_status: Active
primary_test_id: 1
secondary_test_id: 2
```

---

## 4. DEPARTMENTS MASTER DATA

### Required Information:
Departments responsible for equipment management.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| department_id | Unique department identifier | 1, 2, 3 | Number | Yes |
| department_name | Name of department | Engineering, Quality Assurance, Production | Text (100) | Yes |
| department_status | Status of department | Active, Inactive | Enum | Yes |

**Sample Data:**
```
department_id: 1
department_name: Engineering
department_status: Active
```

---

## 5. FILTERS MASTER DATA

### Required Information:
Detailed information about all filters in the HVAC system.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| filter_code | Unique filter code identifier | AHU-01/HEPA/H14/001/A | Text (100) | Yes |
| filter_name | Descriptive name of filter | AHU-01 HEPA Filter Primary | Text (255) | No |
| filter_size | Size category of filter | Standard, Large, Small, Custom | Enum | No |
| filter_type | Type/category of filter | HEPA, ULPA, Pre-Filter, Carbon, Vent, Membrane, Other | Enum | Yes |
| manufacturer | Filter manufacturer | Camfil, AAF, Mann+Hummel | Text (100) | No |
| specifications | Detailed technical specifications | 99.97% efficiency at 0.3 microns, G4 Grade Pre-Filter | Text (Large) | No |
| installation_date | Date of installation | 2024-01-15 | Date | Yes |
| planned_due_date | Planned replacement due date | 2025-01-15 | Date | No |
| actual_replacement_date | Actual replacement date | 2025-01-10 | Date | No |
| status | Current status | Active, Inactive | Enum | Yes |
| created_by | ID of user who created record | 1, 2, 3 | Number | No |

**Sample Data:**
```
filter_code: HEPA-AHU01-001
filter_name: AHU-01 HEPA Filter Primary
filter_size: Standard
filter_type: HEPA
manufacturer: Camfil
specifications: 99.97% efficiency at 0.3 microns
installation_date: 2024-01-15
planned_due_date: 2025-01-15
status: Active
created_by: 1
```

---

## 6. ROOM LOCATIONS MASTER DATA

### Required Information:
All room/location details that will be part of the validation scope.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| room_loc_name | Room name/description | Vial Filling Lyo Loading and Unloading Area | Text (500) | Yes |
| room_volume | Room volume in cubic feet | 4354.30, 2400.00, 150.50 | Decimal (10,2) | Yes |

**Sample Data:**
```
room_loc_name: Vial Filling Lyo Loading and Unloading Area
room_volume: 4354.30
```

**Note:** Room volume should be provided in cubic feet. The system will auto-generate room_loc_id as a unique identifier.

---

## 7. VENDORS MASTER DATA

### Required Information:
All vendor/service provider details who will perform validation testing or provide services.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| vendor_name | Name of the vendor/service provider | ABC Validation Services, XYZ Testing Labs | Text (255) | Yes |
| vendor_spoc_name | Single Point of Contact person name | John Smith, Mary Johnson | Text (100) | Yes |
| vendor_spoc_mobile | SPOC mobile number (10 digits) | 9876543210, 8765432109 | Text (10) | Yes |
| vendor_spoc_email | SPOC email address | john.smith@abcvalidation.com | Email | Yes |
| vendor_status | Current status of vendor | Active, Inactive | Enum | Yes |

**Sample Data:**
```
vendor_name: ABC Validation Services
vendor_spoc_name: John Smith
vendor_spoc_mobile: 9876543210
vendor_spoc_email: john.smith@abcvalidation.com
vendor_status: Active
```

**Important Notes:**
- Vendor names should be unique in the system
- SPOC mobile must be exactly 10 digits (no spaces or special characters)
- SPOC email must be a valid email format
- Use 'Inactive' status for vendors no longer providing services but keep for historical data
- The system will auto-generate vendor_id as unique identifier

---

## 8. TESTS MASTER DATA

### Required Information:
All test types that will be performed during HVAC validation processes.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| test_name | Name of the test | Air Change Test, HEPA Filter Integrity Test, Particle Count Test | Text (255) | Yes |
| test_description | Detailed description of test | Verification of air change rate per hour in classified areas | Text (500) | Yes |
| test_purpose | Purpose and objective of test | To ensure adequate air circulation and contamination control | Text (500) | Yes |
| test_performed_by | Who performs the test | Internal, External | Enum | Yes |
| test_status | Current status of test | Active, Inactive | Enum | Yes |
| dependent_tests | Tests that must be completed before this test | NA, 1,2,3 (comma-separated test IDs) | Text | Yes |
| paper_on_glass_enabled | Whether paper-on-glass testing is enabled | Yes, No | Enum | No |

**Sample Data:**
```
test_name: Air Change Test
test_description: Verification of air change rate per hour in classified areas using calibrated instruments
test_purpose: To ensure adequate air circulation and contamination control as per regulatory standards
test_performed_by: Internal
test_status: Active
dependent_tests: NA
paper_on_glass_enabled: No
```

**Important Notes:**
- Test names should be unique and descriptive
- For dependent_tests: Use 'NA' if no dependencies, or comma-separated test IDs (e.g., "1,3,5")
- Circular dependencies are not allowed (Test A cannot depend on Test B if Test B depends on Test A)
- The system will auto-generate test_id as unique identifier

---

## 9. ETV MAPPINGS (Equipment-Test-Vendor Mappings)

### Required Information:
Mapping relationships between Equipment, Tests, and Vendors for validation management.

### Excel Format Template:

| Column Name | Description | Example | Data Type | Required |
|-------------|-------------|---------|-----------|----------|
| equipment_id | Equipment ID from Equipment Master | 1, 2, 3 | Number | Yes |
| test_id | Test ID from Tests Master | 1, 2, 3 | Number | Yes |
| vendor_id | Vendor ID from Vendors Master | 1, 2, 3 | Number | Yes |
| test_type | Classification or type of test | Routine, Validation, Calibration | Text (100) | Yes |
| frequency_label | Frequency identifier | Y, 2Y, Q, M, ALL | Text (3) | No |
| mapping_status | Status of this mapping | Active, Inactive | Enum | Yes |

**Sample Data:**
```
equipment_id: 1
test_id: 1  
vendor_id: 1
test_type: Routine
frequency_label: Y
mapping_status: Active
```

**Frequency Label Options:**
- Y = Yearly
- 2Y = Bi-Yearly  
- Q = Quarterly
- M = Monthly
- ALL = All frequencies (default)

**Important Notes:**
- Equipment, Test, and Vendor IDs must reference existing records in their respective master tables
- Each combination of Equipment + Test + Vendor should be unique
- frequency_label helps categorize which tests apply to which validation schedules
- Use 'ALL' frequency_label for tests that apply to all validation frequencies

---

## SUBMISSION GUIDELINES

### 1. **File Format:**
- Provide data in Microsoft Excel (.xlsx) format
- Use separate sheets for each master data category
- Name sheets clearly: "Equipment", "Units", "Departments", "ERF_Mappings", "Filters", "Room_Locations", "Vendors", "Tests", "ETV_Mappings"

### 2. **Data Quality Requirements:**
- Ensure all "Required" fields are filled
- Use consistent naming conventions
- Avoid special characters in codes/IDs
- Verify all cross-references match between sheets

### 3. **Validation Checks:**
- Equipment codes in mappings must exist in Equipment Master
- Filter IDs in mappings must exist in Filters Master
- Room IDs in mappings must exist in Room Locations Master
- Test IDs in ETV mappings must exist in Tests Master
- Equipment IDs in ETV mappings must exist in Equipment Master
- Vendor IDs in ETV mappings must exist in Vendors Master
- Dependent test IDs must reference existing active tests (no circular dependencies)

### 4. **Additional Notes:**
- If any equipment is not yet installed, mark status as "Planned"
- Include decommissioned equipment with status "Inactive" if historical data is needed
- Provide estimated values for missing technical specifications
- Contact us for clarification on any field requirements

---

## CONTACT INFORMATION

For questions or clarifications regarding this master data collection:

**Technical Support:**
- Email: support@proval-hvac.com
- Phone: +1-XXX-XXX-XXXX

**Project Manager:**
- Name: [Your Name]
- Email: [Your Email]
- Phone: [Your Phone]

---

## DEADLINE

Please provide the completed master data by: **[INSERT DATE]**

This master data is essential for configuring your ProVal HVAC system and ensuring accurate validation workflows. Complete and accurate data will help us deliver a system that meets your specific requirements.

Thank you for your cooperation.

Best regards,  
ProVal HVAC Implementation Team