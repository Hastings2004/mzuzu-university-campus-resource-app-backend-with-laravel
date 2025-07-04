import React, { useState } from 'react';
import api from "../services/api";
import SuggestionsTable from './SuggestionsTable';

export default function BookingForm({ resource, user, token, onSuccess, onError }) {
    const [startDate, setStartDate] = useState("");
    const [startTime, setStartTime] = useState("");
    const [endTime, setEndTime] = useState("");
    const [purpose, setPurpose] = useState("");
    const [bookingType, setBookingType] = useState("");
    const [supportingDocument, setSupportingDocument] = useState(null);
    const [suggestions, setSuggestions] = useState([]);
    const [message, setMessage] = useState("");
    const [loading, setLoading] = useState(false);

    const formatDateTime = (dateString) => {
        return new Date(dateString).toLocaleString();
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setMessage("");
        setSuggestions([]);

        // Validation
        if (!startDate || !startTime || !endTime || !purpose || !bookingType) {
            setMessage("Please fill in all required fields.");
            setLoading(false);
            return;
        }

        const startDateTime = new Date(`${startDate}T${startTime}`);
        const endDateTime = new Date(`${startDate}T${endTime}`);

        if (startDateTime >= endDateTime) {
            setMessage("End time must be after start time.");
            setLoading(false);
            return;
        }

        if (startDateTime < new Date()) {
            setMessage("Booking must be for a future time.");
            setLoading(false);
            return;
        }

        // Create booking data
        const bookingData = {
            resource_id: resource.id,
            start_time: startDateTime.toISOString(),
            end_time: endDateTime.toISOString(),
            purpose: purpose.trim(),
            booking_type: bookingType
        };

        try {
            const response = await api.post('/bookings', bookingData, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            setMessage("Booking created successfully!");
            if (onSuccess) onSuccess(response.data);
            
            // Clear form
            setStartDate("");
            setStartTime("");
            setEndTime("");
            setPurpose("");
            setBookingType("");
            setSupportingDocument(null);
            
        } catch (error) {
            if (error.response?.status === 409 && error.response?.data?.suggestions) {
                setMessage(error.response.data.message || 'Resource is not available. See suggestions below.');
                setSuggestions(error.response.data.suggestions);
            } else {
                setMessage(error.response?.data?.message || error.message || "Failed to create booking.");
                if (onError) onError(error);
            }
        } finally {
            setLoading(false);
        }
    };

    const handleSuggestionBooking = async (suggestion) => {
        setLoading(true);
        setMessage("Submitting suggested booking...");
        setSuggestions([]);

        const data = {
            resource_id: suggestion.resource_id,
            start_time: suggestion.start_time,
            end_time: suggestion.end_time,
            purpose,
            booking_type: bookingType,
        };

        try {
            const response = await api.post('/bookings', data, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            setMessage("Booking created successfully!");
            if (onSuccess) onSuccess(response.data);
            
            // Clear form
            setStartDate("");
            setStartTime("");
            setEndTime("");
            setPurpose("");
            setBookingType("");
            setSupportingDocument(null);
            
        } catch (error) {
            setMessage(error.response?.data?.message || error.message || "Failed to book suggestion.");
            if (onError) onError(error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="booking-form-container">
            <h3>Book {resource?.name || 'Resource'}</h3>
            
            <form onSubmit={handleSubmit} className="booking-form">
                <div className="form-group">
                    <label htmlFor="startDate">Date:</label>
                    <input
                        type="date"
                        id="startDate"
                        value={startDate}
                        onChange={(e) => setStartDate(e.target.value)}
                        required
                        className="form-input"
                    />
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
                </div>

                <div className="form-group">
                    <label htmlFor="endTime">End Time:</label>
                    <input
                        type="time"
                        id="endTime"
                        value={endTime}
                        onChange={(e) => setEndTime(e.target.value)}
                        required
                        className="form-input"
                    />
                </div>

                <div className="form-group">
                    <label htmlFor="bookingType">Booking Type:</label>
                    <select
                        id="bookingType"
                        value={bookingType}
                        onChange={(e) => setBookingType(e.target.value)}
                        required
                        className="form-input"
                    >
                        <option value="">Select booking type</option>
                        {user?.user_type !== 'student' && (
                            <>
                                <option value="university_activity">University Activity</option>
                                <option value="staff_meeting">Staff Meeting</option>
                            </>
                        )}
                        <option value="church_meeting">Church Meeting</option>
                        <option value="class">Student Class</option>
                        <option value="student_meeting">Student Meeting</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div className="form-group">
                    <label htmlFor="purpose">Purpose:</label>
                    <textarea
                        id="purpose"
                        value={purpose}
                        onChange={(e) => setPurpose(e.target.value)}
                        rows="3"
                        required
                        className="form-textarea"
                        placeholder="Describe the purpose of your booking..."
                    />
                </div>

                <button 
                    type="submit" 
                    className="submit-btn"
                    disabled={loading}
                >
                    {loading ? 'Submitting...' : 'Submit Booking'}
                </button>
            </form>

            {message && (
                <div className={`message ${message.includes('successfully') ? 'success' : 'error'}`}>
                    {message}
                </div>
            )}

            <SuggestionsTable 
                suggestions={suggestions}
                onBook={handleSuggestionBooking}
                formatDateTime={formatDateTime}
            />
        </div>
    );
} 