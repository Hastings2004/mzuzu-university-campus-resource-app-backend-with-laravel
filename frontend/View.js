import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { formatDateTime } from '../utils/dateUtils';

const View = () => {
    const [suggestions, setSuggestions] = useState([]);
    const navigate = useNavigate();

    async function handleSubmit(e) {
        e.preventDefault();
        setValidationErrors({});
        setBookingMessage("Submitting...");
        setSuggestions([]);
        // ... (all your validation logic, unchanged) ...
        // Create FormData
        const formData = new FormData();
        formData.append('resource_id', resource.id.toString());
        formData.append('user_id', user.id.toString());
        formData.append('start_time', startDateTime.toISOString());
        formData.append('end_time', endDateTime.toISOString());
        formData.append('purpose', trimmedPurpose);
        formData.append('booking_type', bookingType);
        if (user?.user_type === 'admin' && priority) {
            formData.append('priority', priority.toString());
        }
        if (supportingDocument) {
            formData.append('supporting_document', supportingDocument);
        }
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
            setBookingMessage(data.message || "Booking created successfully!");
            setSuggestions([]);
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
            if (error.response?.status === 409 && error.response?.data?.suggestions) {
                setBookingMessage(error.response.data.message || 'Resource is not available. See suggestions below.');
                setSuggestions(error.response.data.suggestions);
                setIsResourceAvailable(false);
            } else {
                setSuggestions([]);
                // ... (rest of your error handling, unchanged) ...
                if (error.response?.status === 422) {
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
                } else {
                    setBookingMessage(error.message || `Error: ${error.response?.status} - ${error.response?.statusText}`);
                }
            }
        }
    }

    async function handleSuggestionBooking(suggestion) {
        setBookingMessage("Submitting suggested booking...");
        setValidationErrors({});
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

    return (
        <div>
            {/* In your render, after bookingMessage and before admin actions, add: */}
            {suggestions.length > 0 && (
                <div className="suggestions-section">
                    <h4>Suggested Alternatives</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Resource ID</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Type</th>
                                <th>Preference Score</th>
                                <th>Book</th>
                            </tr>
                        </thead>
                        <tbody>
                            {suggestions.map((s, idx) => (
                                <tr key={idx}>
                                    <td>{s.resource_id}</td>
                                    <td>{formatDateTime(s.start_time)}</td>
                                    <td>{formatDateTime(s.end_time)}</td>
                                    <td>{s.type}</td>
                                    <td>{s.preference_score ?? 0}</td>
                                    <td>
                                        <button onClick={() => handleSuggestionBooking(s)}>
                                            Book This
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

export default View; 