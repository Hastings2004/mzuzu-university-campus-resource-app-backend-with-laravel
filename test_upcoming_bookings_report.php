<?php

/**
 * Test script for Upcoming Bookings Report API
 * 
 * This script demonstrates how to use the new upcoming bookings report endpoint
 * with various filtering options.
 */

// Configuration
$baseUrl = 'http://localhost:8000/api'; // Adjust to your Laravel app URL
$adminToken = 'your-admin-token-here'; // Replace with actual admin token

// Helper function to make API requests
function makeApiRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data && $method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

// Test 1: Basic upcoming bookings report (next 30 days)
echo "=== Test 1: Basic Upcoming Bookings Report ===\n";
$url = "$baseUrl/reports/upcoming-bookings";
$result = makeApiRequest($url, 'GET', null, $adminToken);
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Filter by date range (next 7 days)
echo "=== Test 2: Filter by Date Range (Next 7 Days) ===\n";
$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+7 days'));
$url = "$baseUrl/reports/upcoming-bookings?start_date=$startDate&end_date=$endDate";
$result = makeApiRequest($url, 'GET', null, $adminToken);
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Filter by resource type
echo "=== Test 3: Filter by Resource Type (Classrooms) ===\n";
$url = "$baseUrl/reports/upcoming-bookings?resource_type=classrooms&limit=10";
$result = makeApiRequest($url, 'GET', null, $adminToken);
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

// Test 4: Filter by user type
echo "=== Test 4: Filter by User Type (Staff) ===\n";
$url = "$baseUrl/reports/upcoming-bookings?user_type=staff&limit=5";
$result = makeApiRequest($url, 'GET', null, $adminToken);
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

// Test 5: Filter by booking status
echo "=== Test 5: Filter by Booking Status (Approved) ===\n";
$url = "$baseUrl/reports/upcoming-bookings?status=approved&limit=10";
$result = makeApiRequest($url, 'GET', null, $adminToken);
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

// Test 6: Complex filtering (multiple filters)
echo "=== Test 6: Complex Filtering ===\n";
$url = "$baseUrl/reports/upcoming-bookings?start_date=$startDate&end_date=$endDate&user_type=student&status=approved&limit=20";
$result = makeApiRequest($url, 'GET', null, $adminToken);
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

// Test 7: Filter by specific resource ID
echo "=== Test 7: Filter by Specific Resource ID ===\n";
$resourceId = 1; // Replace with actual resource ID
$url = "$baseUrl/reports/upcoming-bookings?resource_id=$resourceId&limit=10";
$result = makeApiRequest($url, 'GET', null, $adminToken);
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

// Test 8: Filter by specific user ID
echo "=== Test 8: Filter by Specific User ID ===\n";
$userId = 1; // Replace with actual user ID
$url = "$baseUrl/reports/upcoming-bookings?user_id=$userId&limit=10";
$result = makeApiRequest($url, 'GET', null, $adminToken);
echo "Status Code: " . $result['status_code'] . "\n";
echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";

echo "=== All Tests Completed ===\n";

/**
 * Example Response Structure:
 * 
 * {
 *   "success": true,
 *   "message": "Upcoming bookings report generated successfully.",
 *   "report": {
 *     "bookings": [
 *       {
 *         "id": 1,
 *         "booking_reference": "BR-ABC123",
 *         "resource": {
 *           "id": 1,
 *           "name": "Computer Lab 1",
 *           "description": "Main computer laboratory",
 *           "location": "Building A, Room 101",
 *           "category": "ict_labs",
 *           "capacity": 30
 *         },
 *         "user": {
 *           "id": 1,
 *           "name": "John Doe",
 *           "email": "john.doe@example.com",
 *           "user_type": "staff"
 *         },
 *         "schedule": {
 *           "start_time": "2024-01-15T09:00:00.000000Z",
 *           "end_time": "2024-01-15T11:00:00.000000Z",
 *           "date": "2024-01-15",
 *           "start_time_formatted": "09:00",
 *           "end_time_formatted": "11:00",
 *           "duration_hours": 2.0,
 *           "duration_minutes": 120
 *         },
 *         "details": {
 *           "purpose": "Programming class",
 *           "booking_type": "class",
 *           "status": "approved",
 *           "priority": 5
 *         },
 *         "approval_info": {
 *           "approved_by": {
 *             "id": 2,
 *             "name": "Admin User"
 *           },
 *           "approved_at": "2024-01-14T10:30:00.000000Z"
 *         },
 *         "document_info": {
 *           "has_supporting_document": true,
 *           "document_path": "booking_documents/1234567890_document.pdf"
 *         }
 *       }
 *     ],
 *     "summary": {
 *       "total_bookings": 25,
 *       "total_hours": 45.5,
 *       "average_duration_hours": 1.82,
 *       "status_breakdown": {
 *         "approved": 20,
 *         "pending": 3,
 *         "in_use": 2
 *       },
 *       "resource_type_breakdown": {
 *         "classrooms": 10,
 *         "ict_labs": 8,
 *         "science_labs": 7
 *       },
 *       "user_type_breakdown": {
 *         "staff": 15,
 *         "student": 10
 *       },
 *       "booking_type_breakdown": {
 *         "class": 12,
 *         "staff_meeting": 8,
 *         "student_meeting": 5
 *       }
 *     }
 *   },
 *   "period": {
 *     "start_date": "2024-01-15",
 *     "end_date": "2024-02-14",
 *     "start_datetime": "2024-01-15T00:00:00.000000Z",
 *     "end_datetime": "2024-02-14T23:59:59.000000Z"
 *   },
 *   "filters_applied": {
 *     "start_date": "2024-01-15",
 *     "end_date": "2024-02-14",
 *     "limit": 10
 *   },
 *   "total_bookings": 25
 * }
 */ 