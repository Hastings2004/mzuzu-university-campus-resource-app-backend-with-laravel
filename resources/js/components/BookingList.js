import { useContext, useEffect, useState } from "react";
import { useUuidRouting } from "../hooks/useUuidRouting";
import { AppContext } from "../context/appContext";

export default function BookingList() {
    const { navigateToBooking } = useUuidRouting();
    const { user, token } = useContext(AppContext);
    const [bookings, setBookings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchBookings();
    }, []);

    async function fetchBookings() {
        setLoading(true);
        setError(null);

        try {
            const res = await fetch('/api/bookings', {
                method: 'GET',
                headers: {
                    Authorization: `Bearer ${token}`
                }
            });

            const data = await res.json();

            if (res.ok && data.success) {
                setBookings(data.bookings);
            } else {
                setError(data.message || 'Failed to fetch bookings');
            }
        } catch (error) {
            console.error('Error fetching bookings:', error);
            setError('An error occurred while fetching bookings');
        } finally {
            setLoading(false);
        }
    }

    const handleBookingClick = async (booking) => {
        // Use the UUID from the backend for navigation
        await navigateToBooking(booking.uuid);
    };

    if (loading) {
        return (
            <div className="booking-list-container">
                <p>Loading bookings...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="booking-list-container">
                <p className="error-message">{error}</p>
                <button onClick={fetchBookings} className="button">
                    Retry
                </button>
            </div>
        );
    }

    return (
        <div className="booking-list-container">
            <h2>My Bookings</h2>
            
            {bookings.length === 0 ? (
                <p>No bookings found.</p>
            ) : (
                <div className="booking-grid">
                    {bookings.map(booking => (
                        <div 
                            key={booking.id} 
                            className="booking-card"
                            onClick={() => handleBookingClick(booking)}
                        >
                            <h3>{booking.booking_reference}</h3>
                            <p><strong>Resource:</strong> {booking.resource?.name}</p>
                            <p><strong>Status:</strong> 
                                <span className={`status-${booking.status}`}>
                                    {booking.status}
                                </span>
                            </p>
                            <p><strong>Start:</strong> {new Date(booking.start_time).toLocaleDateString()}</p>
                            <p><strong>End:</strong> {new Date(booking.end_time).toLocaleDateString()}</p>
                            
                            {/* Show user info for admins */}
                            {user.user_type === 'admin' && booking.user && (
                                <p><strong>Booked by:</strong> {booking.user.first_name} {booking.user.last_name}</p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
} 