import { useParams, useNavigate } from 'react-router-dom';
import { useState, useEffect } from 'react';
import uuidRoutingService from '../uuidRoutingService';

/**
 * Custom hook for handling UUID-based routing
 * Provides methods to get data and navigate using UUIDs
 */
export function useUuidRouting() {
    const params = useParams();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    /**
     * Get booking data by parameter name
     * @param {string} paramName - The parameter name (e.g., 'id', 'bookingId')
     * @returns {Promise<Object|null>} - The booking data or null if not found
     */
    const getBookingData = async (paramName = 'id') => {
        const identifier = params[paramName];
        if (!identifier) return null;

        setLoading(true);
        setError(null);

        try {
            const data = await uuidRoutingService.getBookingData(identifier);
            return data;
        } catch (error) {
            console.error('Error fetching booking data:', error);
            setError('Booking not found or you do not have access.');
            return null;
        } finally {
            setLoading(false);
        }
    };

    /**
     * Get resource data by parameter name
     * @param {string} paramName - The parameter name (e.g., 'id', 'resourceId')
     * @returns {Promise<Object|null>} - The resource data or null if not found
     */
    const getResourceData = async (paramName = 'id') => {
        const identifier = params[paramName];
        if (!identifier) return null;

        setLoading(true);
        setError(null);

        try {
            const data = await uuidRoutingService.getResourceData(identifier);
            return data;
        } catch (error) {
            console.error('Error fetching resource data:', error);
            setError('Resource not found or you do not have access.');
            return null;
        } finally {
            setLoading(false);
        }
    };

    /**
     * Navigate to booking detail page
     * @param {string} identifier - UUID or numeric ID
     */
    const navigateToBooking = async (identifier) => {
        await uuidRoutingService.navigateToBooking(identifier, navigate);
    };

    /**
     * Navigate to resource detail page
     * @param {string} identifier - UUID or numeric ID
     */
    const navigateToResource = async (identifier) => {
        await uuidRoutingService.navigateToResource(identifier, navigate);
    };

    /**
     * Navigate to edit booking page
     * @param {string} identifier - UUID or numeric ID
     */
    const navigateToEditBooking = async (identifier) => {
        if (uuidRoutingService.isNumericId(identifier)) {
            try {
                const data = await uuidRoutingService.getBookingData(identifier);
                if (data?.success && data.booking?.uuid) {
                    navigate(`/bookings/${data.booking.uuid}/edit`);
                } else {
                    navigate(`/bookings/${identifier}/edit`);
                }
            } catch (error) {
                navigate(`/bookings/${identifier}/edit`);
            }
        } else if (uuidRoutingService.isValidUuid(identifier)) {
            navigate(`/bookings/${identifier}/edit`);
        } else {
            console.error('Invalid booking identifier:', identifier);
            navigate('/booking');
        }
    };

    /**
     * Navigate to edit resource page
     * @param {string} identifier - UUID or numeric ID
     */
    const navigateToEditResource = async (identifier) => {
        if (uuidRoutingService.isNumericId(identifier)) {
            try {
                const data = await uuidRoutingService.getResourceData(identifier);
                if (data?.success && data.resource?.uuid) {
                    navigate(`/resources/${data.resource.uuid}/edit`);
                } else {
                    navigate(`/resources/${identifier}/edit`);
                }
            } catch (error) {
                navigate(`/resources/${identifier}/edit`);
            }
        } else if (uuidRoutingService.isValidUuid(identifier)) {
            navigate(`/resources/${identifier}/edit`);
        } else {
            console.error('Invalid resource identifier:', identifier);
            navigate('/resources');
        }
    };

    /**
     * Clear cache for specific entity or all
     * @param {string} entity - Optional entity type ('booking', 'resource', or null for all)
     */
    const clearCache = (entity = null) => {
        uuidRoutingService.clearCache(entity);
    };

    return {
        // Data fetching methods
        getBookingData,
        getResourceData,
        
        // Navigation methods
        navigateToBooking,
        navigateToResource,
        navigateToEditBooking,
        navigateToEditResource,
        
        // Utility methods
        clearCache,
        
        // State
        loading,
        error,
        
        // Access to params and service
        params,
        uuidRoutingService
    };
} 