<?php
/**
 * Returns the list of departments/programs for RBAC assignment.
 * Used when assigning tellers to departments.
 */
header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/_admin_auth.php';
requireAdminRole(['registrar']); // Only Head Registrar can assign departments to tellers

$departments = [
    ['value' => 'Bachelor of Science in Accountancy (BSA)', 'label' => 'BSA'],
    ['value' => 'Bachelor of Science in Entrepreneurship (BSE)', 'label' => 'BSE'],
    ['value' => 'Bachelor of Science in Marketing (BSM)', 'label' => 'BSM'],
    ['value' => 'Bachelor of Science in Office Administration (BSOA)', 'label' => 'BSOA'],
    ['value' => 'Bachelor of Secondary Education (BSEd) - Mathematics', 'label' => 'BSEd - Mathematics'],
    ['value' => 'Bachelor of Secondary Education (BSEd) - Science', 'label' => 'BSEd - Science'],
    ['value' => 'Bachelor of Secondary Education (BSEd) - Technology Livelihood Education', 'label' => 'BSEd - TLE'],
    ['value' => 'Bachelor of Physical Education (BPEd)', 'label' => 'BPEd'],
    ['value' => 'Diploma in Teaching Secondary (DTS)', 'label' => 'DTS'],
    ['value' => 'BTVTEd - Food and Service Management', 'label' => 'BTVTEd - Food and Service'],
    ['value' => 'BTVTEd - Garments, Fashion, and Design', 'label' => 'BTVTEd - Garments'],
    ['value' => 'BTLEd - Industrial Arts (IA)', 'label' => 'BTLEd - IA'],
    ['value' => 'BTLEd - Home Economics (HE)', 'label' => 'BTLEd - HE'],
    ['value' => 'Bachelor of Science in Civil Engineering (BSCE)', 'label' => 'BSCE'],
    ['value' => 'Bachelor of Science in Information Technology (BSIT)', 'label' => 'BSIT'],
    ['value' => 'Bachelor of Science in Hospitality Management', 'label' => 'BS Hospitality Management'],
    ['value' => 'Bachelor of Science in Hotel and Restaurant Technology', 'label' => 'BS Hotel and Restaurant Tech'],
    ['value' => 'BS in Industrial Technology - Culinary Arts', 'label' => 'BS Industrial Tech - Culinary'],
    ['value' => 'BS in Industrial Technology - Electricity', 'label' => 'BS Industrial Tech - Electricity'],
    ['value' => 'BS in Industrial Technology - Electronics', 'label' => 'BS Industrial Tech - Electronics'],
    ['value' => 'BS in Mechanical Technology - Automotive', 'label' => 'BS Mechanical Tech - Automotive'],
    ['value' => 'BS in Mechanical Technology - Welding and Fabrication', 'label' => 'BS Mechanical Tech - Welding'],
    ['value' => 'Bachelor of Industrial Technology - Electrical Technology', 'label' => 'BIT - Electrical Technology'],
];

echo json_encode(['success' => true, 'departments' => $departments]);
