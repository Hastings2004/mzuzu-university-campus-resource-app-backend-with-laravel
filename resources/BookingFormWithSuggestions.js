import { useContext, useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { AppContext } from "../context/appContext";
import api from "../services/api";
import moment from "moment";

export default function BookingFormWithSuggestions() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { user, token } = useContext(AppContext);

    const title = "BOOKING CONFIRMATION";
    
    const [resource, setResource] = useState(null);
    const [resourceBookings, setResourceBookings] = useState({
        pending: [],
        approved: [],
        in_use: []
    });
    const [bookingOption, setBookingOption] = useState("single_day");
    const [startDate, setStartDate] = useState("");
    const [endDate, setEndDate] = useState("");
    const [startTime, setStartTime] = useState("");
    const [endTime, setEndTime] = useState("");
    const [purpose, setPurpose] = useState("");
    const [bookingType, setBookingType] = useState("");
    const [priority, setPriority] = useState("");
    const [bookingMessage, setBookingMessage] = useState("");
    const [validationErrors, setValidationErrors] = useState({});
    const [isResourceAvailable, setIsResourceAvailable] = useState(null);
    const [supportingDocument, setSupportingDocument] = useState(null);
    const [suggestions, setSuggestions] = useState([]); // NEW: For dynamic suggestions
    
    async function getResource() {
        try {
            const response = await api.get(`/resources/${id}`);
            const data = response.data;

            if (data.resource) {
                setResource(data.resource);
            } else if (data.data) {
                setResource(data.data);
            } else {
                setResource(data);
            }
            setBookingMessage("");
        } catch (error) {
            console.error("Failed to fetch resource:", error);
            setResource(null);
            setBookingMessage(`Failed to load resource details: ${error.message || 'Resource not found or unauthorized.'}`);
        }
    }

    async function getResourceBookings() {
        try {
            const response = await api.get(`/resources/${id}/bookings`);
            const data = response.data;
            console.log(data);

            const bookingsByStatus = {
                pending: [],
                approved: [],
                in_use: []
            };

            if (data.bookings && Array.isArray(data.bookings)) {
                data.bookings.forEach(booking => {
                    const currentTime = new Date();
                    const bookingStartTime = new Date(booking.start_time);
                    const bookingEndTime = new Date(booking.end_time);

                    if (booking.status === 'pending') {
                        bookingsByStatus.pending.push(booking);
                    } else if (booking.status === 'approved') {
                        if (currentTime >= bookingStartTime && currentTime <= bookingEndTime) {
                            bookingsByStatus.in_use.push(booking);
                        } else {
                            bookingsByStatus.approved.push(booking);
                        }
                    }
                });
            }

            setResourceBookings(bookingsByStatus);
        } catch (error) {
            console.error("Failed to fetch resource bookings:", error);
        }
    }

    async function handleAvailabilityCheck() {
        setBookingMessage("");
        setIsResourceAvailable(null);

        if (!resource) {
            console.warn("Resource data not loaded yet for availability check.");
            setBookingMessage("Resource data not loaded. Cannot check availability.");
            return;
        }

        let fullStartTime, fullEndTime;

        if (bookingOption === "single_day") {
            if (!startDate || !startTime || !endTime) {
                setIsResourceAvailable(null);
                return;
            }
            fullStartTime = new Date(`${startDate}T${startTime}`);
            fullEndTime = new Date(`${startDate}T${endTime}`);

        } else { // multi_day
            if (!startDate || !startTime || !endDate || !endTime) {
                setIsResourceAvailable(null);
                return;
            }
            fullStartTime = new Date(`${startDate}T${startTime}`);
            fullEndTime = new Date(`${startDate}T${endTime}`);
        }

        if (isNaN(fullStartTime.getTime()) || isNaN(fullEndTime.getTime())) {
            setBookingMessage("Invalid date or time format.");
            setIsResourceAvailable(false);
            return;
        }

        if (fullStartTime >= fullEndTime) {
            setBookingMessage("End time must be after start time for availability check.");
            setIsResourceAvailable(false);
            return;
        }

        // Check if single-day booking truly spans only one day
        if (bookingOption === "single_day") {
            const startDay = fullStartTime.toDateString();
            const endDay = fullEndTime.toDateString();
            if (startDay !== endDay) {
                setBookingMessage("For 'Single Day' booking, start and end times must be on the same day.");
                setIsResourceAvailable(false);
                return;
            }
        }

        // Check if multi-day booking is actually multi-day or at least 2 days
        if (bookingOption === "multi_day") {
            const diffTime = Math.abs(fullEndTime.getTime() - fullStartTime.getTime());
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            if (diffDays < 2) {
                setBookingMessage("For '2 Days and Above' booking, the duration must be at least 2 full days.");
                setIsResourceAvailable(false);
                return;
            }
        }

        const startDateISO = fullStartTime.toISOString();
        const endDateISO = fullEndTime.toISOString();

        try {
            const response = await api.post('/bookings/check-availability', {
                resource_id: resource.id,
                start_time: startDateISO,
                end_time: endDateISO
            });

            setBookingMessage(`Resource is available for the selected time.`);
            setIsResourceAvailable(true);
        } catch (error) {
            console.error("Availability check failed:", error);
            setBookingMessage(error.message || 'Unknown error');
            setIsResourceAvailable(false);
        }
    }

    useEffect(() => {
        // Trigger availability check when relevant time/date inputs change
        if (bookingOption === "single_day" && startDate && startTime && endTime) {
            handleAvailabilityCheck();
        } else if (bookingOption === "multi_day" && startDate && startTime && endDate && endTime) {
            handleAvailabilityCheck();
        } else {
            setIsResourceAvailable(null);
            setBookingMessage("");
        }
    }, [startDate, startTime, endDate, endTime, bookingOption]);

    const requiresDocument = user?.user_type === 'student' &&
        (bookingType === 'student_meeting' || bookingType === 'church_meeting');

    async function handleSubmit(e) {
        e.preventDefault();
        setValidationErrors({});
        setBookingMessage("Submitting...");
        setSuggestions([]); // Clear suggestions on new submit

        // Validate user authentication
        if (!user || !user.id) {
            setBookingMessage("You must be logged in to make a booking.");
            return;
        }

        if (!token) {
            setBookingMessage("Authentication token missing. Please log in again.");
            return;
        }

        // Validate resource
        if (!resource || !resource.id) {
            setBookingMessage("Resource not loaded. Please refresh and try again.");
            return;
        }

        // Validate booking type
        if (!bookingType) {
            setBookingMessage("Please select a booking type.");
            return;
        }

        // Date and time validation
        let startDateTime, endDateTime;
        
        if (bookingOption === 'single_day') {
            if (!startDate || !startTime || !endTime) {
                setBookingMessage("For single day bookings, please provide a start date, start time, and end time.");
                return;
            }
            startDateTime = moment(`${startDate} ${startTime}`, 'YYYY-MM-DD HH:mm');
            endDateTime = moment(`${startDate} ${endTime}`, 'YYYY-MM-DD HH:mm');
        } else { // multi_day
            if (!startDate || !startTime || !endDate || !endTime) {
                setBookingMessage("For multi-day bookings, please provide all start/end dates and times.");
                return;
            }
            startDateTime = moment(`${startDate} ${startTime}`, 'YYYY-MM-DD HH:mm');
            endDateTime = moment(`${endDate} ${endTime}`, 'YYYY-MM-DD HH:mm');
        }

        if (!startDateTime.isValid() || !endDateTime.isValid()) {
            setBookingMessage("The dates or times provided are not valid. Please check them.");
            return;
        }

        if (!endDateTime.isAfter(startDateTime)) {
            setBookingMessage("The booking end time must be after the start time.");
            return;
        }

        if (startDateTime.isBefore(moment())) {
            setBookingMessage("Bookings must be for a future time.");
            return;
        }
        
        // Purpose validation
        const trimmedPurpose = purpose ? purpose.trim() : "";
        if (trimmedPurpose.length < 10 || trimmedPurpose.length > 500) {
            setBookingMessage("Please provide a purpose between 10 and 500 characters.");
            return;
        }
        
        // Document validation
        if (requiresDocument && !supportingDocument) {
            setBookingMessage("A supporting document is required for this booking type.");
            return;
        }

        // Check availability before submitting
        if (isResourceAvailable === false) {
            setBookingMessage("Resource is not available for the selected time. Please choose different times.");
            return;
        }

        // Create FormData
        const formData = new FormData();
        
        // Add required fields with proper conversion
        formData.append('resource_id', resource.id.toString());
        formData.append('user_id', user.id.toString());
        formData.append('start_time', startDateTime.toISOString());
        formData.append('end_time', endDateTime.toISOString());
        formData.append('purpose', trimmedPurpose);
        formData.append('booking_type', bookingType);

        // Add optional fields
        if (user?.user_type === 'admin' && priority) {
            formData.append('priority', priority.toString());
        }
        
        if (supportingDocument) {
            formData.append('supporting_document', supportingDocument);
        }

        // Debug logging
        console.log('=== BOOKING SUBMISSION DEBUG ===');
        console.log('FormData contents:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        console.log('Config:', {
            headers: {
                'Content-Type': supportingDocument ? 'multipart/form-data' : 'application/json',
            }
        });
        console.log('User:', user);
        console.log('Token:', token ? 'Present' : 'Missing');
        console.log('===============================');

        try {
            const config = {
                headers: {
                    'Content-Type': supportingDocument ? 'multipart/form-data' : 'application/json',
                }
            };

            let response;
            if (supportingDocument) {
                response = await api.post('/bookings', formData, config);
            } else {
                const data = {};
                for (let pair of formData.entries()) {
                    data[pair[0]] = pair[1];
                }
                response = await api.post('/bookings', data, config);
            }

            const data = response.data;
            
            console.log('API Response:', {
                status: response.status,
                statusText: response.statusText,
                data: data
            });

            // Success
            setBookingMessage(data.message || "Booking created successfully!");
            setSuggestions([]); // Clear suggestions on success
            alert('Booking created successfully!');

            navigate('/booking');
            // Refresh the bookings list
            getResourceBookings();
            
            // Clear form
            setStartDate('');
            setEndDate('');
            setStartTime('');
            setEndTime('');
            setPurpose('');
            setPriority('');
            setBookingType('');
            setSupportingDocument(null);
            setBookingOption('single_day');
            setIsResourceAvailable(null);
            
            // Reset file input
            const fileInput = document.getElementById('supportingDocument');
            if (fileInput) fileInput.value = '';
            
        } catch (error) {
            // Enhanced error handling with suggestions support
            console.error('=== BOOKING SUBMISSION ERROR ===');
            console.error('Error object:', error);
            console.error('Error response:', error.response);
            console.error('Error message:', error.message);
            console.error('Error status:', error.response?.status);
            console.error('Error data:', error.response?.data);
            console.error('===============================');
            
            if (error.response?.status === 409 && error.response?.data?.suggestions) {
                setBookingMessage(error.response.data.message || 'Resource is not available. See suggestions below.');
                setSuggestions(error.response.data.suggestions);
                setIsResourceAvailable(false);
            } else {
                setSuggestions([]); // Clear suggestions on other errors
                if (error.response?.status === 422) {
                    // Validation errors
                    setBookingMessage(error.response.data.message || 'Please fix the validation errors below.');
                    if (error.response.data.errors) {
                        setValidationErrors(error.response.data.errors);
                    }
                } else if (error.response?.status === 401) {
                    setBookingMessage('Authentication failed. Please log in again.');
                } else if (error.response?.status === 403) {
                    setBookingMessage('You do not have permission to book this resource.');
                } else if (error.response?.status === 500) {
                    setBookingMessage(`Server error occurred. Please try again later. Error details: ${error.response?.data?.message || error.message}`);
                    // Log additional details for debugging
                    console.error('500 Error Details:', {
                        url: error.config?.url,
                        method: error.config?.method,
                        data: error.config?.data,
                        headers: error.config?.headers
                    });
                } else {
                    setBookingMessage(error.message || `Error: ${error.response?.status} - ${error.response?.statusText}`);
                }
            }
        }
    }

    // NEW: Book a suggested slot/resource
    async function handleSuggestionBooking(suggestion) {
        setBookingMessage("Submitting suggested booking...");
        setValidationErrors({});
        setSuggestions([]); // Hide suggestions while submitting

        const data = {
            resource_id: suggestion.resource_id,
            start_time: suggestion.start_time,
            end_time: suggestion.end_time,
            purpose,
            booking_type: bookingType,
        };

        try {
            const response = await api.post('/bookings', data, {
                headers: { 'Content-Type': 'application/json' }
            });
            setBookingMessage(response.data.message || "Booking created successfully!");
            alert('Booking created successfully!');
            navigate('/booking');
            getResourceBookings();
            // Clear form as before...
            setStartDate('');
            setEndDate('');
            setStartTime('');
            setEndTime('');
            setPurpose('');
            setPriority('');
            setBookingType('');
            setSupportingDocument(null);
            setBookingOption('single_day');
            setIsResourceAvailable(null);
            const fileInput = document.getElementById('supportingDocument');
            if (fileInput) fileInput.value = '';
        } catch (error) {
            setBookingMessage(error.response?.data?.message || error.message || "Failed to book suggestion.");
        }
    }

    async function handleDelete(e) {
        e.preventDefault();

        if (!resource) {
            console.warn("Resource data not loaded yet for deletion attempt.");
            setBookingMessage("Resource data not loaded. Cannot delete.");
            return;
        }

        if (!user) {
            setBookingMessage("You must be logged in to delete a resource.");
            return;
        }

        if (!token) {
            setBookingMessage("Authentication required. Please log in again to delete.");
            return;
        }

        if (user && (user.id === resource.user_id || user.user_type === 'admin')) {
            const confirmDelete = window.confirm(`Are you sure you want to delete "${resource.name}"? This action cannot be undone.`);
            if (!confirmDelete) {
                return;
            }

            setBookingMessage("Deleting resource...");
            try {
                const response = await api.delete(`/resources/${id}`);
                const data = response.data;

                setBookingMessage(data.message || "Resource deleted successfully!");
                setTimeout(() => {
                    navigate("/");
                }, 1500);
            } catch (error) {
                console.error("Failed to delete resource:", error);
                setBookingMessage(`Failed to delete resource: ${error.message || 'Unknown error'}`);
            }
        } else {
            console.warn("User not authorized to delete this resource.");
            setBookingMessage("You are not authorized to delete this resource.");
        }
    }

    useEffect(() => {
        if (id && token) {
            getResource();
            getResourceBookings();
        }
    }, [id, token, navigate]);

    const displayError = (field) => {
        if (validationErrors[field]) {
            return <p className="error-message">{validationErrors[field][0]}</p>;
        }
        return null;
    };

    const formatDateTime = (dateString) => {
        return new Date(dateString).toLocaleString();
    };

    const renderBookingList = (bookings, title, status) => {
        if (bookings.length === 0) return null;

        return (
            <div className="booking-status-section">
                <h4>{resource?.name || 'Resource'} Has {bookings.length} Bookings</h4>
                <div className="booking-list">
                    {bookings.map((booking, index) => (
                        <div key={booking.id || index} className="booking-item">                            
                            <div className="booking-info">                                
                                <p><strong>Start Time:</strong> {formatDateTime(booking.start_time)}</p>
                                <p><strong>End time:</strong>{formatDateTime(booking.end_time)}</p>                                                             
                            </div>                            
                        </div>
                    ))}
                </div>
            </div>
        );
    };

    return (
        <>
            <div className="single-resource-container">
                <div className="resource-content">
                    <div className="resource-main">
                        {resource ? (
                            <div key={resource.id} className="single-resource-card">
                                <h2 className="single-resource-title">{resource.name}</h2>
                                
                                {/* Resource Image */}
                                <div className="resource-image-container">
                                    {(() => {
                                        const imageUrl = resource.image_url || resource.image || resource.photo || resource.photo_url || resource.image_path;
                                        
                                        if (imageUrl) {
                                            return (
                                                <img 
                                                    src={imageUrl} 
                                                    alt={resource.name} 
                                                    className="single-resource-image"
                                                    onError={(e) => {
                                                        e.target.style.display = 'none';
                                                        e.target.nextSibling.style.display = 'block';
                                                    }}
                                                />
                                            );
                                        }
                                        return null;
                                    })()}
                                    {(() => {
                                        const imageUrl = resource.image_url || resource.image || resource.photo || resource.photo_url || resource.image_path;
                                        if (!imageUrl || imageUrl === '') {
                                            return (
                                                <div className="resource-image-placeholder">
                                                    <span className="placeholder-icon">📷</span>
                                                    <span className="placeholder-text">No Image Available</span>
                                                </div>
                                            );
                                        }
                                        return null;
                                    })()}
                                </div>
                                
                                <p className="single-resource-detail"><strong>Description:</strong> {resource.description}</p>
                                <p className="single-resource-detail"><strong>Location:</strong> {resource.location}</p>
                                <p className="single-resource-detail"><strong>Capacity:</strong> {resource.capacity}</p>
                                
                                {user ? (
                                    <div className="booking-form-section">
                                        <h3>Book this Resource</h3>
                                        <form onSubmit={handleSubmit} className="booking-form">
                                            <div className="form-group">
                                                <label>Select Booking Duration:</label>
                                                <div className="radio-group">
                                                    <label>
                                                        <input
                                                            type="radio"
                                                            value="single_day"
                                                            checked={bookingOption === "single_day"}
                                                            onChange={() => setBookingOption("single_day")}
                                                        />
                                                        Single Day
                                                    </label>
                                                    <label>
                                                        <input
                                                            type="radio"
                                                            id="multi_day"
                                                            name="booking_option"
                                                            value="multi_day"
                                                            checked={bookingOption === "multi_day"}
                                                            onChange={() => setBookingOption("multi_day")}
                                                        />
                                                        2 Days and Above
                                                    </label>
                                                </div>
                                            </div>

                                            
                                            <div className="form-group">
                                                <label htmlFor="startDate">{bookingOption === 'single_day' ? 'Date:' : 'Start Date:'}</label>
                                                    <input
                                                        type="date"
                                                        id="startDate"
                                                        value={startDate}
                                                        onChange={(e) => setStartDate(e.target.value)}
                                                        required
                                                        className="form-input"
                                                    />
                                                    {displayError('start_date')}
                                            </div>
                                            

                                            <div className="form-group">
                                                <label htmlFor="startTime">Start Time:</label>
                                                <input
                                                    type="time"
                                                    id="startTime"
                                                    value={startTime}
                                                    onChange={(e) => setStartTime(e.target.value)}
                                                    required
                                                    className="form-input"
                                                />
                                                {displayError('start_time')}
                                            </div>

                                            {bookingOption === "multi_day" && (
                                                <div className="form-group">
                                                    <label htmlFor="endDate">End Date:</label>
                                                    <input
                                                        type="date"
                                                        id="endDate"
                                                        value={endDate}
                                                        onChange={(e) => setEndDate(e.target.value)}
                                                        required
                                                        className="form-input"
                                                    />
                                                    {displayError('end_date')}
                                                </div>
                                            )}

                                            <div className="form-group">
                                                <label htmlFor="endTime">End Time:</label>
                                                {/* For single-day, type can still be time, but for multi-day, it's combined with date */}
                                                <input
                                                    type="time"
                                                    id="endTime"
                                                    value={endTime}
                                                    onChange={(e) => setEndTime(e.target.value)}
                                                    required
                                                    className="form-input"
                                                />
                                                {displayError('end_time')}
                                            </div>

                                            {isResourceAvailable === true && (
                                                <p className="availability-message available">Resource is available for these times.</p>
                                            )}
                                            {isResourceAvailable === false && (
                                                <p className="availability-message not-available">Resource is NOT available for these times. Please adjust your selection.</p>
                                            )}
                                            {isResourceAvailable === null && (startTime || (bookingOption === "multi_day" && startDate)) && (endTime || (bookingOption === "multi_day" && endDate)) && (
                                                <p className="availability-message checking">Checking availability...</p>
                                            )}

                                            <div className='form-group'>
                                                <label htmlFor="bookingType">Booking Type:</label>
                                                <select
                                                    name="booking_type"
                                                    id="bookingType"
                                                    className={`form-input ${validationErrors.booking_type ? 'input-error' : ''}`}
                                                    value={bookingType}
                                                    onChange={(e) => setBookingType(e.target.value)}
                                                    required
                                                >
                                                    <option value="">-------------------Booking type---------------------</option>
                                                    { user && user.user_type != 'student' && (
                                                        <>
                                                            <option value="university_activity">University Activity</option>
                                                            <option value="staff_meeting">Staff Meeting</option>
                                                        </>
                                                    )}
                                                    <option value="church_meeting">Church meeting</option>
                                                    <option value="class">Student Class</option>
                                                    <option value="student_meeting">Student Meetings</option>
                                                    <option value="other">Other</option>
                                                </select>
                                                {displayError('booking_type')}
                                            </div>
                                             {user?.user_type === 'student' &&
                                             (bookingType === 'student_meeting' || bookingType === 'other' || bookingType === 'church_meeting') && (
                                                <div className="form-group">
                                                    <label htmlFor="supportingDocument">Supporting Document (PDF, Image):</label>
                                                    <input
                                                        type="file"
                                                        id="supportingDocument"
                                                        onChange={(e) => setSupportingDocument(e.target.files[0])}
                                                        accept=".pdf,.jpg,.jpeg,.png" 
                                                        required
                                                        className="form-input"
                                                    />
                                                    <small className="form-text-muted">Please attach supporting document</small>
                                                    {displayError('supporting_document')}
                                                </div>
                                            )}
                                            <div className="form-group">
                                                <label htmlFor="purpose">Purpose of Booking:</label>
                                                <textarea
                                                    id="purpose"
                                                    value={purpose}
                                                    onChange={(e) => setPurpose(e.target.value)}
                                                    rows="4"
                                                    required
                                                    className="form-textarea"
                                                ></textarea>
                                                {displayError('purpose')}
                                            </div>

                                            {user.user_type === 'admin' && (
                                                <div className="form-group">
                                                    <label htmlFor="priority">Priority (1=Critical, 4=Low):</label>
                                                    <input
                                                        type="number"
                                                        id="priority"
                                                        value={priority}
                                                        onChange={(e) => setPriority(e.target.value)}
                                                        min="1"
                                                        max="4"
                                                        className="form-input"
                                                        placeholder="e.g., 1 for Critical"
                                                    />
                                                    {displayError('priority')}
                                                </div>
                                            )}

                                            <button 
                                                type="submit" 
                                                className="action-button book-button" 
                                                disabled={isResourceAvailable === false}>
                                                Submit Booking
                                            </button>
                                        </form>
                                        {bookingMessage && <p className="booking-message">{bookingMessage}</p>}

                                        {/* ENHANCED SUGGESTIONS TABLE */}
                                        {suggestions.length > 0 && (
                                            <div className="suggestions-section">
                                                <h4>🎯 Suggested Alternative Resources</h4>
                                                <p className="suggestions-intro">
                                                    The requested resource is not available. Here are some alternatives that match your needs:
                                                </p>
                                                <div className="suggestions-grid">
                                                    {suggestions.map((suggestion, idx) => (
                                                        <div key={idx} className="suggestion-card">
                                                            <div className="suggestion-header">
                                                                <h5 className="suggestion-title">
                                                                    {suggestion.resource_name || `Resource #${suggestion.resource_id}`}
                                                                </h5>
                                                                <span className={`suggestion-type ${suggestion.type}`}>
                                                                    {suggestion.type === 'alternative_resource' && '🔄 Alternative'}
                                                                    {suggestion.type === 'feature_based_alternative' && '✨ Feature Match'}
                                                                    {suggestion.type === 'minor_overlap_allowed' && '⏰ Minor Overlap'}
                                                                    {suggestion.type === 'nearby_slot' && '🕐 Nearby Time'}
                                                                </span>
                                                            </div>
                                                            
                                                            <div className="suggestion-details">
                                                                <div className="detail-row">
                                                                    <span className="detail-label">📍 Location:</span>
                                                                    <span className="detail-value">{suggestion.resource_location || 'N/A'}</span>
                                                                </div>
                                                                <div className="detail-row">
                                                                    <span className="detail-label">👥 Capacity:</span>
                                                                    <span className="detail-value">{suggestion.resource_capacity || 'N/A'}</span>
                                                                </div>
                                                                <div className="detail-row">
                                                                    <span className="detail-label">📂 Category:</span>
                                                                    <span className="detail-value">{suggestion.resource_category || 'N/A'}</span>
                                                                </div>
                                                                <div className="detail-row">
                                                                    <span className="detail-label">🕐 Start Time:</span>
                                                                    <span className="detail-value">{formatDateTime(suggestion.start_time)}</span>
                                                                </div>
                                                                <div className="detail-row">
                                                                    <span className="detail-label">🕐 End Time:</span>
                                                                    <span className="detail-value">{formatDateTime(suggestion.end_time)}</span>
                                                                </div>
                                                                
                                                                {/* Feature similarity information */}
                                                                {suggestion.feature_similarity_score !== undefined && (
                                                                    <div className="detail-row">
                                                                        <span className="detail-label">🎯 Feature Match:</span>
                                                                        <span className="detail-value">
                                                                            {suggestion.feature_similarity_score}% 
                                                                            ({suggestion.feature_match_count}/{suggestion.total_requested_features} features)
                                                                        </span>
                                                                    </div>
                                                                )}
                                                                
                                                                {/* Matching features list */}
                                                                {suggestion.matching_features && suggestion.matching_features.length > 0 && (
                                                                    <div className="detail-row">
                                                                        <span className="detail-label">✅ Matching Features:</span>
                                                                        <span className="detail-value">
                                                                            {suggestion.matching_features.join(', ')}
                                                                        </span>
                                                                    </div>
                                                                )}
                                                                
                                                                {/* Suggestion reason */}
                                                                {suggestion.suggestion_reason && (
                                                                    <div className="detail-row">
                                                                        <span className="detail-label">💡 Why Suggested:</span>
                                                                        <span className="detail-value">{suggestion.suggestion_reason}</span>
                                                                    </div>
                                                                )}
                                                                
                                                                {/* Usage statistics */}
                                                                {suggestion.recent_booking_count !== undefined && (
                                                                    <div className="detail-row">
                                                                        <span className="detail-label">📊 Recent Usage:</span>
                                                                        <span className="detail-value">{suggestion.recent_booking_count} bookings (last 30 days)</span>
                                                                    </div>
                                                                )}
                                                                
                                                                {/* Preference score */}
                                                                {suggestion.preference_score !== undefined && (
                                                                    <div className="detail-row">
                                                                        <span className="detail-label">⭐ Preference Score:</span>
                                                                        <span className="detail-value">{suggestion.preference_score}</span>
                                                                    </div>
                                                                )}
                                                            </div>
                                                            
                                                            <div className="suggestion-actions">
                                                                <button 
                                                                    onClick={() => handleSuggestionBooking(suggestion)}
                                                                    className="book-suggestion-btn"
                                                                >
                                                                    📅 Book This Resource
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                                
                                                {/* Summary information */}
                                                <div className="suggestions-summary">
                                                    <p>
                                                        <strong>💡 Tip:</strong> Resources are ranked by feature similarity and your preferences. 
                                                        Higher feature match percentages indicate more similar resources to your original request.
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <p className="login-prompt-message">Please <Link to="/login">log in</Link> to book this resource.</p>
                                )}
                                {(user?.user_type === 'admin' || (user && resource.user_id === user.id)) && (
                                    <div className="resource-actions">
                                        {user?.user_type === 'admin' && (
                                            <Link to={`/resources/edit/${resource.id}`} className="btn btn-secondary">Edit Resource</Link>
                                        )}
                                        <button onClick={handleDelete} className="btn btn-danger">Delete Resource</button>
                                    </div>
                                )}
                                
                            </div>
                        ) : (
                            <p className="">Loading resource or resource not found!</p>
                        )}
                    </div>
                </div>
                <div className="booking-status-sidebar">     
                    <div className="resource-sidebar">
                        <h3>Bookings for {resource?.name || 'this Resource'}</h3>
                        {resourceBookings.in_use.length > 0 && renderBookingList(resourceBookings.in_use, 'Currently In Use', 'in_use')}
                        {resourceBookings.approved.length > 0 && renderBookingList(resourceBookings.approved, 'Approved Bookings', 'approved')}
                        {resourceBookings.pending.length > 0 && renderBookingList(resourceBookings.pending, 'Pending Bookings', 'pending')}

                        {resourceBookings.pending.length === 0 &&
                            resourceBookings.approved.length === 0 &&
                            resourceBookings.in_use.length === 0 &&
                            <p>No bookings found for this resource.</p>}
                    </div>
                </div>
            </div>
        </>
    );
} 