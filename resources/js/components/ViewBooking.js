import { useContext, useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { AppContext } from "../context/appContext";
import { useUuidRouting } from "../hooks/useUuidRouting";

export default function ViewBooking() {
    const { uuid } = useParams(); // This 'uuid' should be the booking UUID or numeric ID
    const { getBookingData, loading, error } = useUuidRouting();
    const navigate = useNavigate();
    const { user, token } = useContext(AppContext);

    const [booking, setBooking] = useState(null);

    async function getBookingDetails() {
        const data = await getBookingData('uuid');
        
        if (!data || !data.success) {
            return; // Error is handled by the hook
        }

        const receivedBooking = data.booking;

        // Check if the user is authorized to view this booking
        if (user.user_type !== 'admin' && receivedBooking && receivedBooking.user_id !== user.id) {
            alert("You are not authorized to view this booking.");
            navigate("/booking");
            return;
        }

        setBooking(receivedBooking);
    }

    async function handleDelete() {
        if (!booking) {
            console.warn("Booking data not loaded yet for deletion attempt.");
            return;
        }

        if (!window.confirm("Are you sure you want to delete this booking? This action cannot be undone.")) {
            return; // User cancelled
        }

        try {
            // Use the numeric ID from the booking object for API calls
            const res = await fetch(`/api/bookings/${booking.id}`, {
                method: "DELETE",
                headers: {
                    Authorization: `Bearer ${token}`,
                },
            });

            const data = await res.json();

            if (res.ok) {
                alert(data.message || "Booking deleted successfully!");
                navigate("/booking"); // Redirect to a suitable page after deletion
            } else {
                alert(data.message || "Failed to delete booking.");
                console.error("Failed to delete booking:", data);
            }
        } catch (error) {
            console.error("Network or unexpected error during deletion:", error);
            alert("An unexpected error occurred while deleting the booking. Please try again.");
        }
    }

    useEffect(() => {
        if (uuid) {
            getBookingDetails();
        }
    }, [uuid, user]); // Removed token and navigate from dependencies

    if (loading) {
        return (
            <div className="single-resource-container">
                <p>Loading booking details...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="single-resource-container">
                <p className="booking-not-found-message">{error}</p>
                <button onClick={() => navigate("/booking")} className="button">
                    Back to Bookings
                </button>
            </div>
        );
    }

    if (!booking) {
        return (
            <div className="single-resource-container">
                <p className="booking-not-found-message">Booking not found or you are not authorized to view it.</p>
                <button onClick={() => navigate("/booking")} className="button">
                    Back to Bookings
                </button>
            </div>
        );
    }

    const isOwner = user && booking.user_id && user.id === booking.user_id;
    const isAdmin = user && user.user_type === 'admin';
    const canModify = isOwner || isAdmin;

    return (
        <>
            <div className="single-resource-container">
                <div key={booking.id} className="single-resource-card">
                    <center><h2 className="single-resource-title">{booking.resource?.name}</h2></center>
                    <p className="booking-detail"><strong>Reference number:</strong> {booking.booking_reference}</p>
                    <p className="booking-detail"><strong>Description:</strong>{booking.resource?.description}</p>
                    <p className="booking-detail"><strong>Location:</strong> {booking.resource?.location}</p>
                    <p className="booking-detail"><strong>Capacity:</strong> {booking.resource?.capacity}</p>
                    <p className="booking-detail"><strong>Purpose:</strong> {booking.purpose}</p>
                    <p className="booking-detail"><strong>Start Time:</strong> {new Date(booking.start_time).toLocaleString()}</p>
                    <p className="booking-detail"><strong>End Time:</strong> {new Date(booking.end_time).toLocaleString()}</p>
                    <p className="booking-detail"><strong>Booking Status:</strong>
                        <span className={
                            booking.status === 'approved'
                                ? 'status-approved'
                                : booking.status === 'pending'
                                    ? 'status-pending'
                                    : 'status-rejected'
                        }>
                            {booking.status}
                        </span>
                    </p>

                    {user.user_type === 'admin' && booking.user && (
                        <div>
                            <p className="booking-detail"><strong>Booked by:</strong> {booking.user.first_name + " " + booking.user.last_name}</p>
                            <p className="booking-detail"><strong>Email:</strong> {booking.user.email}</p>
                        </div>
                    )}

                    {canModify && (
                        <div className="booking-actions">
                            <Link to={`/bookings/${booking.uuid}/edit`} className="button update-button">
                                Update Booking
                            </Link>
                            <button onClick={handleDelete} className="button delete-button">
                                Delete Booking
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
} 