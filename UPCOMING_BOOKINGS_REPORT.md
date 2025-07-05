# Upcoming Bookings Report

## Overview

The Upcoming Bookings Report provides administrators with a comprehensive view of all confirmed bookings scheduled for future periods. This report is essential for resource planning, capacity management, and administrative oversight.

## Purpose

- **Resource Planning**: Identify upcoming resource usage patterns
- **Capacity Management**: Monitor resource utilization in advance
- **Administrative Oversight**: Track booking approvals and user activities
- **Conflict Prevention**: Identify potential scheduling conflicts
- **Department Coordination**: Understand resource needs across different departments

## API Endpoint

```
GET /api/reports/upcoming-bookings
```

**Authentication**: Requires admin authentication via Bearer token

## Request Parameters

### Query Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `start_date` | string | No | Start date filter (Y-m-d format) | `2024-01-15` |
| `end_date` | string | No | End date filter (Y-m-d format) | `2024-02-15` |
| `resource_type` | string | No | Filter by resource category | `classrooms`, `ict_labs` |
| `resource_id` | integer | No | Filter by specific resource ID | `1` |
| `user_id` | integer | No | Filter by specific user ID | `5` |
| `user_type` | string | No | Filter by user type | `staff`, `student`, `admin` |
| `status` | string | No | Filter by booking status | `approved`, `pending`, `in_use` |
| `limit` | integer | No | Maximum number of results (1-1000) | `50` |

### Default Behavior

- **Date Range**: If no dates specified, defaults to next 30 days from today
- **Status Filter**: Only includes `approved`, `pending`, and `in_use` bookings
- **Limit**: Defaults to 100 bookings if not specified
- **Ordering**: Results ordered by start time (earliest first)

## Response Structure

### Success Response (200)

```json
{
  "success": true,
  "message": "Upcoming bookings report generated successfully.",
  "report": {
    "bookings": [
      {
        "id": 1,
        "booking_reference": "BR-ABC123",
        "resource": {
          "id": 1,
          "name": "Computer Lab 1",
          "description": "Main computer laboratory",
          "location": "Building A, Room 101",
          "category": "ict_labs",
          "capacity": 30
        },
        "user": {
          "id": 1,
          "name": "John Doe",
          "email": "john.doe@example.com",
          "user_type": "staff"
        },
        "schedule": {
          "start_time": "2024-01-15T09:00:00.000000Z",
          "end_time": "2024-01-15T11:00:00.000000Z",
          "date": "2024-01-15",
          "start_time_formatted": "09:00",
          "end_time_formatted": "11:00",
          "duration_hours": 2.0,
          "duration_minutes": 120
        },
        "details": {
          "purpose": "Programming class",
          "booking_type": "class",
          "status": "approved",
          "priority": 5
        },
        "approval_info": {
          "approved_by": {
            "id": 2,
            "name": "Admin User"
          },
          "approved_at": "2024-01-14T10:30:00.000000Z",
          "rejected_by": null,
          "rejected_at": null,
          "rejection_reason": null
        },
        "cancellation_info": {
          "cancelled_by": null,
          "cancelled_at": null,
          "cancellation_reason": null
        },
        "document_info": {
          "has_supporting_document": true,
          "document_path": "booking_documents/1234567890_document.pdf"
        },
        "created_at": "2024-01-14T08:00:00.000000Z",
        "updated_at": "2024-01-14T10:30:00.000000Z"
      }
    ],
    "summary": {
      "total_bookings": 25,
      "total_hours": 45.5,
      "average_duration_hours": 1.82,
      "status_breakdown": {
        "approved": 20,
        "pending": 3,
        "in_use": 2
      },
      "resource_type_breakdown": {
        "classrooms": 10,
        "ict_labs": 8,
        "science_labs": 7
      },
      "user_type_breakdown": {
        "staff": 15,
        "student": 10
      },
      "booking_type_breakdown": {
        "class": 12,
        "staff_meeting": 8,
        "student_meeting": 5
      }
    }
  },
  "period": {
    "start_date": "2024-01-15",
    "end_date": "2024-02-14",
    "start_datetime": "2024-01-15T00:00:00.000000Z",
    "end_datetime": "2024-02-14T23:59:59.000000Z"
  },
  "filters_applied": {
    "start_date": "2024-01-15",
    "end_date": "2024-02-14",
    "limit": 10
  },
  "total_bookings": 25
}
```

### Error Responses

#### Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

#### Forbidden (403)
```json
{
  "success": false,
  "message": "Unauthorized to access reports."
}
```

#### Validation Error (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "end_date": ["The end date must be a date after or equal to start date."]
  }
}
```

#### Server Error (500)
```json
{
  "success": false,
  "message": "An error occurred while generating the upcoming bookings report. Please try again."
}
```

## Usage Examples

### 1. Basic Report (Next 30 Days)
```bash
curl -X GET "http://localhost:8000/api/reports/upcoming-bookings" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### 2. Filter by Date Range
```bash
curl -X GET "http://localhost:8000/api/reports/upcoming-bookings?start_date=2024-01-15&end_date=2024-01-22" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### 3. Filter by Resource Type
```bash
curl -X GET "http://localhost:8000/api/reports/upcoming-bookings?resource_type=classrooms&limit=20" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### 4. Filter by User Type
```bash
curl -X GET "http://localhost:8000/api/reports/upcoming-bookings?user_type=staff&status=approved" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### 5. Complex Filtering
```bash
curl -X GET "http://localhost:8000/api/reports/upcoming-bookings?start_date=2024-01-15&end_date=2024-01-22&user_type=student&status=approved&limit=50" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

## Data Fields Explained

### Booking Details
- **id**: Unique booking identifier
- **booking_reference**: Human-readable booking reference
- **resource**: Complete resource information
- **user**: Complete user information
- **schedule**: Time and date information with formatted displays
- **details**: Booking purpose, type, status, and priority
- **approval_info**: Approval/rejection details
- **cancellation_info**: Cancellation details
- **document_info**: Supporting document information

### Summary Statistics
- **total_bookings**: Total number of bookings in the report
- **total_hours**: Sum of all booking durations
- **average_duration_hours**: Average booking duration
- **status_breakdown**: Count of bookings by status
- **resource_type_breakdown**: Count of bookings by resource category
- **user_type_breakdown**: Count of bookings by user type
- **booking_type_breakdown**: Count of bookings by booking type

## Business Rules

1. **Date Range**: Only future bookings are included (past bookings excluded)
2. **Status Filter**: Only `approved`, `pending`, and `in_use` bookings are included
3. **Authorization**: Only admin users can access this report
4. **Limit**: Maximum 1000 bookings per request
5. **Ordering**: Results ordered by start time (earliest first)

## Integration Examples

### JavaScript/Frontend
```javascript
async function getUpcomingBookings(filters = {}) {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/reports/upcoming-bookings?${params}`, {
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    throw new Error('Failed to fetch upcoming bookings');
  }
  
  return await response.json();
}

// Usage
const report = await getUpcomingBookings({
  start_date: '2024-01-15',
  end_date: '2024-01-22',
  user_type: 'staff',
  limit: 50
});
```

### PHP/Backend
```php
$filters = [
    'start_date' => '2024-01-15',
    'end_date' => '2024-01-22',
    'user_type' => 'staff',
    'limit' => 50
];

$response = Http::withToken($adminToken)
    ->get('/api/reports/upcoming-bookings', $filters);

$report = $response->json();
```

## Performance Considerations

1. **Indexing**: Ensure proper database indexes on:
   - `bookings.start_time`
   - `bookings.status`
   - `bookings.resource_id`
   - `bookings.user_id`

2. **Pagination**: For large datasets, consider implementing pagination
3. **Caching**: Consider caching for frequently accessed reports
4. **Query Optimization**: Use eager loading for related data

## Security Considerations

1. **Authentication**: Admin-only access
2. **Authorization**: Verify user permissions
3. **Input Validation**: All parameters are validated
4. **Rate Limiting**: Consider implementing rate limiting for large reports
5. **Data Privacy**: Ensure sensitive user data is properly handled

## Troubleshooting

### Common Issues

1. **No Results**: Check if date range is in the future
2. **Authorization Error**: Ensure user has admin privileges
3. **Validation Error**: Check parameter formats and values
4. **Performance Issues**: Consider reducing date range or adding indexes

### Debug Information

The API includes debug logging for:
- Request parameters
- Date range calculations
- Query execution
- Error conditions

Check Laravel logs for detailed debugging information. 