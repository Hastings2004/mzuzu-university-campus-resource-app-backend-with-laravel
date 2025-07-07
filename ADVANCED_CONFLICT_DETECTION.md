# Advanced Conflict Detection & Prevention System

## Overview

The Advanced Conflict Detection & Prevention system goes beyond simple time slot overlaps to intelligently identify and prevent complex conflicts in resource booking. This system performs real-time checks across various resource schedules, maintenance schedules, and external factors to ensure true availability before confirming a booking.

## Key Features

### 1. Multi-Dimensional Conflict Detection

The system checks for conflicts across multiple dimensions:

- **Time-based conflicts**: Traditional booking overlaps
- **Maintenance conflicts**: Resources under maintenance or scheduled maintenance
- **Shared equipment conflicts**: Resources that share equipment or dependencies
- **Resource dependency conflicts**: Resources that depend on other resources
- **Timetable conflicts**: Fixed schedules and class timetables
- **User schedule conflicts**: User's own booking conflicts
- **Capacity conflicts**: Resource capacity limitations
- **Resource issue conflicts**: Active problems or issues with resources

### 2. Intelligent Conflict Resolution

- **Priority-based preemption**: Higher priority bookings can preempt lower priority ones
- **Alternative resource suggestions**: Automatically suggests similar available resources
- **Conflict-specific recommendations**: Provides targeted suggestions based on conflict type
- **Real-time availability checking**: Instant feedback on resource availability

### 3. Advanced Conflict Types

#### Maintenance Conflicts
```php
// Example: Resource under maintenance
{
    "type": "maintenance",
    "severity": "high",
    "message": "Resource is currently under maintenance.",
    "suggestion": "Please check back later or contact facilities management."
}
```

#### Shared Equipment Conflicts
```php
// Example: Shared equipment conflict
{
    "type": "shared_equipment",
    "severity": "medium",
    "message": "Shared equipment conflict with Computer Lab B",
    "suggestion": "Consider booking Computer Lab A instead, or choose a different time slot."
}
```

#### Resource Dependency Conflicts
```php
// Example: Dependent resource unavailable
{
    "type": "resource_dependency",
    "severity": "high",
    "message": "Dependent resource Audio System is not available",
    "suggestion": "Please ensure Audio System is available before booking this resource."
}
```

## Implementation Details

### Backend Components

#### 1. AdvancedConflictDetectionService

The core service that handles all advanced conflict detection logic:

```php
use App\Services\AdvancedConflictDetectionService;

$service = new AdvancedConflictDetectionService();
$conflicts = $service->detectAdvancedConflicts(
    $resourceId,
    $startTime,
    $endTime,
    $user,
    $bookingData,
    $excludeBookingId
);
```

#### 2. Enhanced BookingService

The BookingService has been enhanced to integrate with advanced conflict detection:

```php
// Check advanced availability
$availability = $bookingService->checkAdvancedAvailability(
    $resourceId,
    $startTime,
    $endTime,
    $user,
    $excludeBookingId
);
```

#### 3. API Endpoints

- `POST /api/bookings/check-advanced-availability` - Check advanced availability with comprehensive conflict detection

### Frontend Components

#### 1. AdvancedConflictDetection.js

A JavaScript component that provides real-time conflict detection in the UI:

```javascript
const advancedConflictDetection = new AdvancedConflictDetection();
```

Features:
- Real-time conflict checking
- Visual conflict display
- Alternative resource suggestions
- Interactive conflict resolution

### Database Schema

The system leverages existing tables and adds new relationships:

#### Resource Issues Table
```sql
CREATE TABLE resource_issues (
    id BIGINT PRIMARY KEY,
    reported_by_user_id BIGINT,
    resource_id BIGINT,
    subject VARCHAR(255),
    description TEXT,
    issue_type VARCHAR(50),
    status ENUM('reported', 'in_progress', 'resolved', 'wont_fix'),
    resolved_at TIMESTAMP NULL,
    resolved_by_user_id BIGINT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Usage Examples

### 1. Basic Conflict Detection

```php
// Check for conflicts before creating a booking
$conflicts = $advancedConflictService->detectAdvancedConflicts(
    $resourceId,
    $startTime,
    $endTime,
    $user
);

if ($conflicts['has_conflicts']) {
    // Handle conflicts
    foreach ($conflicts['conflicts'] as $conflict) {
        echo "Conflict: {$conflict['message']}\n";
        echo "Suggestion: {$conflict['suggestion']}\n";
    }
}
```

### 2. Alternative Resource Suggestions

```php
// Get alternative resources when conflicts are detected
$alternatives = $advancedConflictService->getAlternativeResources(
    $originalResource,
    $startTime,
    $endTime,
    $user
);

foreach ($alternatives as $alternative) {
    echo "Alternative: {$alternative['resource_name']}\n";
    echo "Reason: {$alternative['reason']}\n";
}
```

### 3. Frontend Integration

```javascript
// Check advanced availability from frontend
const checkAvailability = async () => {
    const response = await fetch('/api/bookings/check-advanced-availability', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            resource_id: resourceId,
            start_time: startTime,
            end_time: endTime
        })
    });
    
    const data = await response.json();
    displayAdvancedConflicts(data.data);
};
```

## Conflict Types and Severity Levels

### Severity Levels

- **High**: Critical conflicts that prevent booking (maintenance, resource issues)
- **Medium**: Significant conflicts that may prevent booking (shared equipment, capacity)
- **Low**: Minor conflicts that don't prevent booking (user schedule conflicts)

### Conflict Types

1. **maintenance** - Resource under maintenance
2. **shared_equipment** - Shared equipment conflicts
3. **resource_dependency** - Resource dependency conflicts
4. **timetable** - Fixed schedule conflicts
5. **booking** - Existing booking conflicts
6. **user_schedule** - User's own schedule conflicts
7. **capacity** - Resource capacity conflicts
8. **resource_issue** - Active resource issues

## Testing

### Running the Test

```bash
php test_advanced_conflict_detection.php
```

This test demonstrates:
- Basic conflict detection
- Maintenance conflict detection
- Alternative resource suggestions
- Integration with BookingService

### Test Scenarios

1. **Clean Resource**: No conflicts detected
2. **Maintenance Conflict**: Resource under maintenance
3. **Shared Equipment**: Equipment shared with other resources
4. **Capacity Conflict**: Resource at full capacity
5. **User Schedule**: User has conflicting bookings

## Configuration

### Resource Status Values

- `available` - Resource is available for booking
- `maintenance` - Resource is under maintenance
- `unavailable` - Resource is temporarily unavailable

### Issue Types

- `maintenance` - Maintenance-related issues
- `equipment_malfunction` - Equipment problems
- `cleaning_request` - Cleaning requests
- `booking_conflict` - Booking-related issues

## API Response Format

### Success Response
```json
{
    "success": true,
    "data": {
        "available": false,
        "has_conflicts": true,
        "conflict_types": ["maintenance", "capacity"],
        "conflicts": [
            {
                "type": "maintenance",
                "severity": "high",
                "message": "Resource is currently under maintenance.",
                "suggestion": "Please check back later or contact facilities management."
            }
        ],
        "resource_status": "maintenance",
        "resource_capacity": 25,
        "suggestions": [
            {
                "type": "alternative_resource",
                "message": "Try booking a similar resource in a different location.",
                "priority": "high"
            }
        ],
        "alternative_resources": [
            {
                "resource_id": 2,
                "resource_name": "Computer Lab B",
                "location": "Building A",
                "capacity": 30,
                "category": "computer_lab",
                "reason": "Similar resource with no conflicts"
            }
        ]
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Authentication required"
}
```

## Benefits

1. **Prevents Double Bookings**: Comprehensive conflict detection prevents overlapping bookings
2. **Improves User Experience**: Real-time feedback and suggestions
3. **Reduces Administrative Overhead**: Automated conflict resolution
4. **Increases Resource Utilization**: Better resource allocation through intelligent suggestions
5. **Maintains System Integrity**: Prevents invalid bookings before they occur

## Future Enhancements

1. **External Calendar Integration**: Check against external calendar systems
2. **Machine Learning**: Predict conflicts based on historical data
3. **Advanced Scheduling**: Suggest optimal booking times
4. **Conflict Resolution Workflows**: Automated conflict resolution processes
5. **Real-time Notifications**: Instant conflict notifications to users

## Troubleshooting

### Common Issues

1. **Conflicts not detected**: Ensure resource issues are properly configured
2. **Alternative resources not showing**: Check resource categories and features
3. **API errors**: Verify authentication and request format

### Debug Commands

```bash
# Test conflict detection
php artisan booking:test-conflicts

# Clean up old status values
php artisan booking:test-conflicts --cleanup
```

## Conclusion

The Advanced Conflict Detection & Prevention system provides a comprehensive solution for managing complex booking scenarios. By going beyond simple time overlaps, it ensures that resources are truly available and provides intelligent alternatives when conflicts are detected.

This system significantly improves the booking experience while maintaining system integrity and maximizing resource utilization. 