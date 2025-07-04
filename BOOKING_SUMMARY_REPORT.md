# Booking Summary Report

This document describes the Booking Summary Report feature that provides comprehensive analytics on booking data with various filters and breakdowns.

## Overview

The Booking Summary Report is a powerful analytics tool that allows administrators to analyze booking patterns, resource utilization, and user behavior. It provides detailed metrics and breakdowns to help make informed decisions about resource management.

## Features

### Filters
- **Date Range**: Filter bookings by start and end dates
- **Resource Type**: Filter by resource category (classrooms, ict_labs, science_labs, sports, cars, auditorium)
- **Specific Resource**: Filter by a specific resource ID
- **User**: Filter by a specific user ID
- **User Type**: Filter by user type (staff, student, admin)

### Metrics
- **Total Number of Bookings**: Count of all bookings in the filtered period
- **Total Booked Hours**: Sum of all booking durations in hours
- **Average Booking Duration**: Mean duration of bookings in hours
- **Number of Unique Users**: Count of distinct users who made bookings

### Breakdowns
- **By Resource**: Breakdown showing metrics for each individual resource
- **By User Type**: Breakdown showing metrics for each user type (staff, student, admin)
- **By Day**: Daily breakdown of booking metrics
- **By Week**: Weekly breakdown using ISO-8601 week numbers
- **By Month**: Monthly breakdown of booking metrics

## API Endpoint

### GET `/api/reports/booking-summary`

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
    "message": "Booking summary report generated successfully.",
    "report": {
        "metrics": {
            "total_bookings": 150,
            "total_booked_hours": 450.5,
            "average_booking_duration": 3.0,
            "unique_users": 45,
            "valid_bookings_count": 148
        },
        "breakdowns": {
            "by_resource": [
                {
                    "resource_id": 1,
                    "resource_name": "Computer Lab 1",
                    "resource_category": "ict_labs",
                    "total_bookings": 25,
                    "total_booked_hours": 75.5,
                    "average_duration": 3.02,
                    "unique_users": 15
                }
            ],
            "by_user_type": [
                {
                    "user_type": "staff",
                    "total_bookings": 60,
                    "total_booked_hours": 180.0,
                    "average_duration": 3.0,
                    "unique_users": 20
                },
                {
                    "user_type": "student",
                    "total_bookings": 90,
                    "total_booked_hours": 270.5,
                    "average_duration": 3.01,
                    "unique_users": 25
                }
            ],
            "by_day": [
                {
                    "period": "2024-01-15",
                    "period_type": "day",
                    "total_bookings": 10,
                    "total_booked_hours": 30.0,
                    "average_duration": 3.0,
                    "unique_users": 8
                }
            ],
            "by_week": [
                {
                    "period": "2024-03",
                    "period_type": "week",
                    "total_bookings": 50,
                    "total_booked_hours": 150.0,
                    "average_duration": 3.0,
                    "unique_users": 25
                }
            ],
            "by_month": [
                {
                    "period": "2024-01",
                    "period_type": "month",
                    "total_bookings": 200,
                    "total_booked_hours": 600.0,
                    "average_duration": 3.0,
                    "unique_users": 40
                }
            ]
        }
    },
    "period": {
        "start_date": "2024-01-01",
        "end_date": "2024-01-31"
    },
    "filters_applied": {
        "start_date": "2024-01-01",
        "end_date": "2024-01-31",
        "user_type": "staff"
    }
}
```

## Usage Examples

### 1. Basic Report (Current Month)
```bash
GET /api/reports/booking-summary
```

### 2. Date Range Filter
```bash
GET /api/reports/booking-summary?start_date=2024-01-01&end_date=2024-01-31
```

### 3. Resource Type Filter
```bash
GET /api/reports/booking-summary?resource_type=classrooms
```

### 4. User Type Filter
```bash
GET /api/reports/booking-summary?user_type=staff
```

### 5. Complex Filter Combination
```bash
GET /api/reports/booking-summary?start_date=2024-01-01&end_date=2024-01-31&user_type=student&resource_type=ict_labs
```

## Implementation Details

### Service Layer
The report is implemented in `App\Services\ReportService` with the following key methods:

- `getBookingSummaryReport(array $filters)`: Main method that orchestrates the report generation
- `getFilteredBookings(array $filters, array $dateRange)`: Applies filters and retrieves bookings
- `calculateBookingMetrics(Collection $bookings)`: Calculates the main metrics
- `generateBookingBreakdowns(Collection $bookings, array $filters)`: Generates all breakdowns
- `generateResourceBreakdown(Collection $bookings)`: Resource-specific breakdown
- `generateUserTypeBreakdown(Collection $bookings)`: User type breakdown
- `generateTimeBreakdown(Collection $bookings, string $period)`: Time-based breakdowns

### Controller Layer
The API endpoint is handled by `App\Http\Controllers\ReportController::getBookingSummary()` which:

- Validates user authorization (admin only)
- Validates input parameters
- Calls the service layer
- Returns formatted JSON response

### Data Processing
- **Booking Status Filtering**: Only includes bookings with status 'approved', 'in_use', or 'completed'
- **Duration Calculation**: Calculates booking duration in minutes, then converts to hours
- **Invalid Booking Handling**: Skips bookings with invalid start/end times
- **Eager Loading**: Efficiently loads related user and resource data

## Error Handling

The report handles various error scenarios:

- **Invalid Date Range**: Returns error if start date is after end date
- **Invalid Filters**: Validates filter parameters and returns appropriate errors
- **Database Errors**: Catches and logs database errors with detailed stack traces
- **Authorization Errors**: Returns 403 Forbidden for non-admin users

## Performance Considerations

- **Eager Loading**: Uses Laravel's eager loading to minimize database queries
- **Efficient Filtering**: Applies filters at the database level using Eloquent queries
- **Memory Management**: Processes bookings in collections to manage memory usage
- **Indexing**: Relies on existing database indexes for optimal query performance

## Testing

A test script `test_booking_summary_report.php` is provided to verify the functionality:

```bash
php test_booking_summary_report.php
```

The test script covers:
- Basic report generation
- Date range filtering
- User type filtering
- Resource type filtering
- Complex filter combinations
- Breakdown validation

## Security

- **Authorization**: Only admin users can access the report
- **Input Validation**: All query parameters are validated
- **SQL Injection Protection**: Uses Laravel's Eloquent ORM for safe queries
- **Data Exposure**: Only returns aggregated data, not sensitive booking details

## Future Enhancements

Potential improvements for future versions:

1. **Export Functionality**: Add CSV/Excel export capabilities
2. **Caching**: Implement report caching for better performance
3. **Real-time Updates**: WebSocket integration for live report updates
4. **Advanced Filters**: Add more filter options (booking type, priority, etc.)
5. **Visualization**: Integrate with charting libraries for data visualization
6. **Scheduled Reports**: Automated report generation and email delivery
7. **Department Support**: Add department-based filtering and breakdowns

## Troubleshooting

### Common Issues

1. **Empty Results**: Check if the date range contains any bookings
2. **Permission Denied**: Ensure the user has admin privileges
3. **Invalid Date Format**: Use Y-m-d format for dates (e.g., 2024-01-15)
4. **Large Date Ranges**: Very large date ranges may impact performance

### Debug Information

The service logs detailed debug information including:
- Filter parameters received
- Date range processing
- Booking count at each step
- Error details with stack traces

Check the Laravel logs for detailed debugging information:
```bash
tail -f storage/logs/laravel.log
``` 