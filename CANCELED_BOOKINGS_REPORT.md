# Canceled Bookings Report

This document describes the Canceled Bookings Report feature that provides comprehensive analytics on canceled booking data with various filters and breakdowns.

## Overview

The Canceled Bookings Report is a powerful analytics tool that allows administrators to analyze cancellation patterns, track refund amounts, and understand the reasons behind booking cancellations. It provides detailed metrics and breakdowns to help make informed decisions about resource management and user behavior.

## Purpose

- **Track all bookings that were canceled**: Monitor cancellation patterns and trends
- **Calculate cancellation percentages**: Understand the impact of cancellations relative to total bookings
- **Analyze cancellation reasons**: Identify common reasons for cancellations
- **Track refund amounts**: Monitor financial impact of cancellations
- **Identify problematic patterns**: Find resources or users with high cancellation rates

## Features

### Filters
- **Date Range**: Filter cancellations by start and end dates (based on cancellation date)
- **Resource Type**: Filter by resource category (classrooms, ict_labs, science_labs, sports, cars, auditorium)
- **Specific Resource**: Filter by a specific resource ID
- **User**: Filter by a specific user ID
- **User Type**: Filter by user type (staff, student, admin)

### Metrics
- **Total Number of Cancellations**: Count of all canceled bookings in the filtered period
- **Cancellation Percentage**: Percentage of cancellations relative to total bookings in the period
- **Total Refund Amount**: Sum of all refund amounts for canceled bookings
- **Average Cancellation Time**: Average time between booking creation and cancellation
- **Unique Users Canceled**: Count of distinct users who canceled bookings
- **Unique Resources Canceled**: Count of distinct resources that had cancellations

### Breakdowns
- **By Resource**: Breakdown showing metrics for each individual resource
- **By User Type**: Breakdown showing metrics for each user type (staff, student, admin)
- **By Cancellation Reason**: Breakdown showing metrics for each cancellation reason
- **By Day**: Daily breakdown of cancellation metrics
- **By Week**: Weekly breakdown using ISO-8601 week numbers
- **By Month**: Monthly breakdown of cancellation metrics

## API Endpoint

### GET `/api/reports/canceled-bookings`

**Authentication**: Required (Admin only)

**Query Parameters**:
- `start_date` (optional): Start date in Y-m-d format
- `end_date` (optional): End date in Y-m-d format
- `resource_type` (optional): Resource category filter
- `resource_id` (optional): Specific resource ID filter
- `user_id` (optional): Specific user ID filter
- `user_type` (optional): User type filter (staff, student, admin)

**Response Format**:
```json
{
    "success": true,
    "message": "Canceled bookings report generated successfully.",
    "report": {
        "metrics": {
            "total_cancelations": 25,
            "cancellation_percentage": 8.33,
            "total_refund_amount": 150.00,
            "average_cancellation_time_hours": 12.5,
            "unique_users_canceled": 15,
            "unique_resources_canceled": 8,
            "total_bookings_in_period": 300
        },
        "breakdowns": {
            "by_resource": [
                {
                    "resource_id": 1,
                    "resource_name": "Computer Lab 1",
                    "resource_category": "ict_labs",
                    "total_cancelations": 5,
                    "total_refund_amount": 30.00,
                    "unique_users_canceled": 4,
                    "average_refund_amount": 6.00
                }
            ],
            "by_user_type": [
                {
                    "user_type": "staff",
                    "total_cancelations": 10,
                    "total_refund_amount": 60.00,
                    "unique_users": 8,
                    "average_refund_amount": 6.00
                },
                {
                    "user_type": "student",
                    "total_cancelations": 15,
                    "total_refund_amount": 90.00,
                    "unique_users": 7,
                    "average_refund_amount": 6.00
                }
            ],
            "by_cancellation_reason": [
                {
                    "cancellation_reason": "Schedule conflict",
                    "total_cancelations": 12,
                    "total_refund_amount": 72.00,
                    "unique_users": 10,
                    "average_refund_amount": 6.00
                },
                {
                    "cancellation_reason": "No longer needed",
                    "total_cancelations": 8,
                    "total_refund_amount": 48.00,
                    "unique_users": 6,
                    "average_refund_amount": 6.00
                },
                {
                    "cancellation_reason": "No reason provided",
                    "total_cancelations": 5,
                    "total_refund_amount": 30.00,
                    "unique_users": 4,
                    "average_refund_amount": 6.00
                }
            ],
            "by_day": [
                {
                    "period": "2024-01-15",
                    "period_type": "day",
                    "total_cancelations": 3,
                    "total_refund_amount": 18.00,
                    "unique_users": 3,
                    "average_refund_amount": 6.00
                }
            ],
            "by_week": [
                {
                    "period": "2024-03",
                    "period_type": "week",
                    "total_cancelations": 15,
                    "total_refund_amount": 90.00,
                    "unique_users": 12,
                    "average_refund_amount": 6.00
                }
            ],
            "by_month": [
                {
                    "period": "2024-01",
                    "period_type": "month",
                    "total_cancelations": 25,
                    "total_refund_amount": 150.00,
                    "unique_users": 15,
                    "average_refund_amount": 6.00
                }
            ]
        },
        "canceled_bookings": [
            {
                "id": 123,
                "booking_reference": "BK-2024-001",
                "user": {
                    "id": 1,
                    "name": "John Doe",
                    "email": "john.doe@example.com",
                    "user_type": "staff"
                },
                "resource": {
                    "id": 1,
                    "name": "Computer Lab 1",
                    "description": "Main computer laboratory",
                    "location": "Building A, Room 101",
                    "category": "ict_labs",
                    "capacity": 30
                },
                "original_schedule": {
                    "start_time": "2024-01-15T09:00:00.000000Z",
                    "end_time": "2024-01-15T11:00:00.000000Z",
                    "date": "2024-01-15",
                    "start_time_formatted": "09:00",
                    "end_time_formatted": "11:00",
                    "duration_hours": 2.0
                },
                "cancellation_details": {
                    "cancelled_by": {
                        "id": 1,
                        "name": "John Doe"
                    },
                    "cancelled_at": "2024-01-14T15:30:00.000000Z",
                    "cancellation_reason": "Schedule conflict",
                    "refund_amount": 6.00
                },
                "booking_details": {
                    "purpose": "Programming class",
                    "booking_type": "academic",
                    "priority": "normal"
                },
                "created_at": "2024-01-10T10:00:00.000000Z",
                "updated_at": "2024-01-14T15:30:00.000000Z"
            }
        ]
    },
    "period": {
        "start_date": "2024-01-01",
        "end_date": "2024-01-31",
        "start_datetime": "2024-01-01T00:00:00.000000Z",
        "end_datetime": "2024-01-31T23:59:59.000000Z"
    },
    "filters_applied": {
        "start_date": "2024-01-01",
        "end_date": "2024-01-31",
        "user_type": "staff"
    },
    "total_cancelations": 25
}
```

## Usage Examples

### 1. Basic Report (Current Month)
```bash
GET /api/reports/canceled-bookings
```

### 2. Date Range Filter
```bash
GET /api/reports/canceled-bookings?start_date=2024-01-01&end_date=2024-01-31
```

### 3. Resource Type Filter
```bash
GET /api/reports/canceled-bookings?resource_type=classrooms
```

### 4. User Type Filter
```bash
GET /api/reports/canceled-bookings?user_type=staff
```

### 5. Complex Filter Combination
```bash
GET /api/reports/canceled-bookings?start_date=2024-01-01&end_date=2024-01-31&user_type=student&resource_type=ict_labs
```

## Implementation Details

### Service Layer
The report is implemented in `App\Services\ReportService` with the following key methods:

- `getCanceledBookingsReport(array $filters)`: Main method that orchestrates the report generation
- `getFilteredCanceledBookings(array $filters, array $dateRange)`: Applies filters and retrieves canceled bookings
- `getTotalBookingsInPeriod(array $filters, array $dateRange)`: Gets total bookings for percentage calculation
- `calculateCanceledBookingsMetrics(Collection $canceledBookings, int $totalBookings)`: Calculates the main metrics
- `generateCanceledBookingsBreakdowns(Collection $canceledBookings, array $filters)`: Generates all breakdowns
- `generateCanceledResourceBreakdown(Collection $canceledBookings)`: Resource-specific breakdown
- `generateCanceledUserTypeBreakdown(Collection $canceledBookings)`: User type breakdown
- `generateCancellationReasonBreakdown(Collection $canceledBookings)`: Cancellation reason breakdown
- `generateCanceledTimeBreakdown(Collection $canceledBookings, string $period)`: Time-based breakdowns

### Controller Layer
The API endpoint is handled by `App\Http\Controllers\ReportController` with the method:

- `getCanceledBookings(Request $request)`: Handles the API request, validates parameters, and returns the response

### Database Queries
The report uses the following key database operations:

1. **Canceled Bookings Query**: Filters bookings with status 'cancelled' and applies date range filters on `cancelled_at`
2. **Total Bookings Query**: Counts all bookings in the period for percentage calculation
3. **Relationship Loading**: Eager loads user, resource, and cancelledBy relationships for efficient data retrieval

### Key Features

#### Date Range Filtering
- Filters canceled bookings based on `cancelled_at` date
- Calculates total bookings in the same period for percentage calculation
- Supports flexible date ranges with proper validation

#### Comprehensive Metrics
- **Total Cancelations**: Raw count of canceled bookings
- **Cancellation Percentage**: Relative to total bookings in the period
- **Financial Impact**: Total and average refund amounts
- **Time Analysis**: Average time between booking creation and cancellation
- **User Analysis**: Unique users who canceled bookings
- **Resource Analysis**: Unique resources that had cancellations

#### Detailed Breakdowns
- **Resource Breakdown**: Per-resource cancellation statistics
- **User Type Breakdown**: Cancellation patterns by user type
- **Reason Breakdown**: Analysis of cancellation reasons
- **Time Breakdowns**: Daily, weekly, and monthly trends

#### Detailed Booking Data
Each canceled booking includes:
- Complete user information
- Complete resource information
- Original schedule details
- Cancellation details (who, when, why, refund)
- Booking purpose and type information

## Business Logic

### Cancellation Percentage Calculation
The cancellation percentage is calculated as:
```
cancellation_percentage = (total_cancelations / total_bookings_in_period) * 100
```

### Refund Amount Tracking
- Tracks total refund amounts across all canceled bookings
- Calculates average refund amounts per booking
- Provides breakdowns by resource, user type, and reason

### Time Analysis
- Calculates average time between booking creation and cancellation
- Helps identify patterns in cancellation timing
- Useful for understanding user behavior

### Data Integrity
- Only includes bookings with status 'cancelled'
- Validates date ranges and filter parameters
- Handles missing or null data gracefully
- Provides meaningful defaults for missing information

## Error Handling

The report includes comprehensive error handling:

1. **Invalid Date Ranges**: Validates that start date is not after end date
2. **Database Errors**: Catches and logs database query errors
3. **Missing Data**: Handles null or missing cancellation reasons
4. **Authorization**: Ensures only admin users can access the report
5. **Parameter Validation**: Validates all input parameters

## Performance Considerations

1. **Efficient Queries**: Uses proper indexing on `status`, `cancelled_at`, and relationship fields
2. **Eager Loading**: Loads relationships in single queries to avoid N+1 problems
3. **Filtered Queries**: Applies filters at the database level for better performance
4. **Pagination Ready**: Structure supports future pagination implementation

## Security

1. **Admin Only Access**: Restricted to users with admin privileges
2. **Input Validation**: All parameters are validated before processing
3. **SQL Injection Protection**: Uses Laravel's query builder for safe queries
4. **Data Sanitization**: Output is properly formatted and sanitized

## Future Enhancements

Potential improvements for the canceled bookings report:

1. **Export Functionality**: Add CSV/Excel export capabilities
2. **Real-time Updates**: Implement WebSocket updates for live data
3. **Advanced Analytics**: Add trend analysis and predictive modeling
4. **Custom Date Ranges**: Support for relative date ranges (last 7 days, last month, etc.)
5. **Comparative Analysis**: Compare cancellation rates across different periods
6. **Alert System**: Notify administrators of unusual cancellation patterns
7. **User Notifications**: Send reports to specific users via email
8. **Dashboard Integration**: Add charts and visualizations to the admin dashboard 