/**
 * Advanced Conflict Detection Component
 * Demonstrates intelligent conflict detection beyond simple time overlaps
 */
class AdvancedConflictDetection {
    constructor() {
        this.apiBase = '/api';
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupConflictDetection();
    }

    bindEvents() {
        // Bind to booking form submission
        const bookingForm = document.getElementById('booking-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', (e) => this.handleBookingSubmission(e));
        }

        // Bind to resource selection
        const resourceSelect = document.getElementById('resource_id');
        if (resourceSelect) {
            resourceSelect.addEventListener('change', (e) => this.handleResourceChange(e));
        }

        // Bind to time input changes
        const startTimeInput = document.getElementById('start_time');
        const endTimeInput = document.getElementById('end_time');
        
        if (startTimeInput) {
            startTimeInput.addEventListener('change', () => this.checkAdvancedAvailability());
        }
        if (endTimeInput) {
            endTimeInput.addEventListener('change', () => this.checkAdvancedAvailability());
        }
    }

    setupConflictDetection() {
        // Add conflict detection UI elements
        this.createConflictDetectionUI();
    }

    createConflictDetectionUI() {
        const bookingForm = document.getElementById('booking-form');
        if (!bookingForm) return;

        // Create conflict detection section
        const conflictSection = document.createElement('div');
        conflictSection.id = 'conflict-detection-section';
        conflictSection.className = 'conflict-detection-section';
        conflictSection.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h5>Advanced Conflict Detection</h5>
                </div>
                <div class="card-body">
                    <div id="conflict-status" class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span id="conflict-message">Select a resource and time to check for conflicts</span>
                    </div>
                    <div id="conflict-details" style="display: none;">
                        <h6>Conflict Details:</h6>
                        <div id="conflict-list"></div>
                    </div>
                    <div id="suggestions" style="display: none;">
                        <h6>Suggestions:</h6>
                        <div id="suggestion-list"></div>
                    </div>
                    <div id="alternative-resources" style="display: none;">
                        <h6>Alternative Resources:</h6>
                        <div id="alternative-list"></div>
                    </div>
                </div>
            </div>
        `;

        // Insert after the form
        bookingForm.parentNode.insertBefore(conflictSection, bookingForm.nextSibling);
    }

    async checkAdvancedAvailability() {
        const resourceId = document.getElementById('resource_id')?.value;
        const startTime = document.getElementById('start_time')?.value;
        const endTime = document.getElementById('end_time')?.value;

        if (!resourceId || !startTime || !endTime) {
            this.updateConflictStatus('Please select a resource and time slot', 'info');
            return;
        }

        this.updateConflictStatus('Checking for conflicts...', 'info');

        try {
            const response = await fetch(`${this.apiBase}/bookings/check-advanced-availability`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    resource_id: resourceId,
                    start_time: startTime,
                    end_time: endTime
                })
            });

            const data = await response.json();

            if (data.success) {
                this.displayAdvancedConflicts(data.data);
            } else {
                this.updateConflictStatus('Error checking availability: ' + data.message, 'danger');
            }
        } catch (error) {
            console.error('Error checking advanced availability:', error);
            this.updateConflictStatus('Error checking availability', 'danger');
        }
    }

    displayAdvancedConflicts(availabilityData) {
        const conflictStatus = document.getElementById('conflict-status');
        const conflictDetails = document.getElementById('conflict-details');
        const conflictList = document.getElementById('conflict-list');
        const suggestions = document.getElementById('suggestions');
        const suggestionList = document.getElementById('suggestion-list');
        const alternativeResources = document.getElementById('alternative-resources');
        const alternativeList = document.getElementById('alternative-list');

        if (availabilityData.available) {
            this.updateConflictStatus('✅ No conflicts detected! Resource is available.', 'success');
            conflictDetails.style.display = 'none';
            suggestions.style.display = 'none';
            alternativeResources.style.display = 'none';
        } else {
            this.updateConflictStatus('❌ Conflicts detected! Resource is not available.', 'danger');
            
            // Display conflict details
            this.displayConflictDetails(availabilityData.conflicts, conflictList);
            conflictDetails.style.display = 'block';

            // Display suggestions
            if (availabilityData.suggestions && availabilityData.suggestions.length > 0) {
                this.displaySuggestions(availabilityData.suggestions, suggestionList);
                suggestions.style.display = 'block';
            } else {
                suggestions.style.display = 'none';
            }

            // Display alternative resources
            if (availabilityData.alternative_resources && availabilityData.alternative_resources.length > 0) {
                this.displayAlternativeResources(availabilityData.alternative_resources, alternativeList);
                alternativeResources.style.display = 'block';
            } else {
                alternativeResources.style.display = 'none';
            }
        }
    }

    displayConflictDetails(conflicts, container) {
        if (!conflicts || conflicts.length === 0) {
            container.innerHTML = '<p>No specific conflicts found.</p>';
            return;
        }

        let html = '';
        conflicts.forEach((conflict, index) => {
            const severityClass = this.getSeverityClass(conflict.severity);
            html += `
                <div class="conflict-item ${severityClass} mb-3">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <i class="fas ${this.getConflictIcon(conflict.type)}"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1">${this.getConflictTypeName(conflict.type)}</h6>
                            <p class="mb-1">${conflict.message}</p>
                            ${conflict.suggestion ? `<small class="text-muted">💡 ${conflict.suggestion}</small>` : ''}
                            ${this.getConflictDetails(conflict)}
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    displaySuggestions(suggestions, container) {
        let html = '<ul class="list-unstyled">';
        suggestions.forEach(suggestion => {
            const priorityClass = this.getPriorityClass(suggestion.priority);
            html += `
                <li class="mb-2">
                    <div class="d-flex align-items-start">
                        <span class="badge ${priorityClass} me-2">${suggestion.priority}</span>
                        <span>${suggestion.message}</span>
                    </div>
                </li>
            `;
        });
        html += '</ul>';
        container.innerHTML = html;
    }

    displayAlternativeResources(alternatives, container) {
        let html = '<div class="row">';
        alternatives.forEach(alternative => {
            html += `
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title">${alternative.resource_name}</h6>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt"></i> ${alternative.location}<br>
                                    <i class="fas fa-users"></i> Capacity: ${alternative.capacity}<br>
                                    <i class="fas fa-tag"></i> ${alternative.category}<br>
                                    <i class="fas fa-lightbulb"></i> ${alternative.reason}
                                </small>
                            </p>
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="advancedConflictDetection.selectAlternativeResource(${alternative.resource_id})">
                                Select This Resource
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    getSeverityClass(severity) {
        switch (severity) {
            case 'high': return 'border-danger bg-danger-light';
            case 'medium': return 'border-warning bg-warning-light';
            case 'low': return 'border-info bg-info-light';
            default: return 'border-secondary bg-light';
        }
    }

    getConflictIcon(type) {
        switch (type) {
            case 'maintenance': return 'fa-tools';
            case 'shared_equipment': return 'fa-cogs';
            case 'resource_dependency': return 'fa-link';
            case 'timetable': return 'fa-calendar';
            case 'booking': return 'fa-clock';
            case 'user_schedule': return 'fa-user-clock';
            case 'capacity': return 'fa-users';
            case 'resource_issue': return 'fa-exclamation-triangle';
            default: return 'fa-exclamation-circle';
        }
    }

    getConflictTypeName(type) {
        switch (type) {
            case 'maintenance': return 'Maintenance Conflict';
            case 'shared_equipment': return 'Shared Equipment Conflict';
            case 'resource_dependency': return 'Resource Dependency';
            case 'timetable': return 'Timetable Conflict';
            case 'booking': return 'Booking Conflict';
            case 'user_schedule': return 'User Schedule Conflict';
            case 'capacity': return 'Capacity Conflict';
            case 'resource_issue': return 'Resource Issue';
            default: return 'Unknown Conflict';
        }
    }

    getPriorityClass(priority) {
        switch (priority) {
            case 'high': return 'bg-danger';
            case 'medium': return 'bg-warning';
            case 'low': return 'bg-info';
            default: return 'bg-secondary';
        }
    }

    getConflictDetails(conflict) {
        let details = '';
        
        if (conflict.conflicting_user) {
            details += `<small class="d-block text-muted">Conflicting user: ${conflict.conflicting_user}</small>`;
        }
        
        if (conflict.conflict_start && conflict.conflict_end) {
            details += `<small class="d-block text-muted">Conflict time: ${conflict.conflict_start} to ${conflict.conflict_end}</small>`;
        }
        
        if (conflict.shared_resource_name) {
            details += `<small class="d-block text-muted">Shared resource: ${conflict.shared_resource_name}</small>`;
        }
        
        if (conflict.issue_description) {
            details += `<small class="d-block text-muted">Issue: ${conflict.issue_description}</small>`;
        }
        
        return details;
    }

    updateConflictStatus(message, type) {
        const statusElement = document.getElementById('conflict-status');
        const messageElement = document.getElementById('conflict-message');
        
        if (statusElement && messageElement) {
            statusElement.className = `alert alert-${type}`;
            messageElement.textContent = message;
        }
    }

    selectAlternativeResource(resourceId) {
        const resourceSelect = document.getElementById('resource_id');
        if (resourceSelect) {
            resourceSelect.value = resourceId;
            resourceSelect.dispatchEvent(new Event('change'));
        }
    }

    async handleBookingSubmission(event) {
        // Check for conflicts before submitting
        await this.checkAdvancedAvailability();
        
        // If there are conflicts, prevent submission
        const conflictStatus = document.getElementById('conflict-status');
        if (conflictStatus && conflictStatus.classList.contains('alert-danger')) {
            event.preventDefault();
            alert('Please resolve conflicts before submitting the booking.');
            return false;
        }
    }

    handleResourceChange(event) {
        // Check availability when resource changes
        setTimeout(() => this.checkAdvancedAvailability(), 100);
    }
}

// Initialize the advanced conflict detection
const advancedConflictDetection = new AdvancedConflictDetection();

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdvancedConflictDetection;
} 