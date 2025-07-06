import api from './api';

class UuidRoutingService {
    constructor() {
        this.uuidToIdMap = new Map();
        this.idToUuidMap = new Map();
        this.cache = new Map(); // Optional caching for performance
    }

    /**
     * Generate a UUID for a numeric ID and store the mapping
     * @param {number} id - The numeric ID
     * @returns {string} - The generated UUID
     */
    generateUuidForId(id) {
        if (this.idToUuidMap.has(id)) {
            return this.idToUuidMap.get(id);
        }

        // Generate a deterministic UUID based on the ID
        const uuid = this.generateDeterministicUuid(id);
        
        // Store the mapping
        this.uuidToIdMap.set(uuid, id);
        this.idToUuidMap.set(id, uuid);
        
        return uuid;
    }

    /**
     * Get the numeric ID from a UUID
     * @param {string} uuid - The UUID
     * @returns {number|null} - The numeric ID or null if not found
     */
    getNumericIdFromUuid(uuid) {
        return this.uuidToIdMap.get(uuid) || null;
    }

    /**
     * Generate a deterministic UUID based on a numeric ID
     * @param {number} id - The numeric ID
     * @returns {string} - The generated UUID
     */
    generateDeterministicUuid(id) {
        // Create a deterministic UUID using the ID as a seed
        const seed = id.toString();
        const hash = this.simpleHash(seed);
        
        // Format as UUID v4
        const uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = (hash + Math.random() * 16) % 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
        
        return uuid;
    }

    /**
     * Simple hash function for deterministic UUID generation
     * @param {string} str - The string to hash
     * @returns {number} - The hash value
     */
    simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        return Math.abs(hash);
    }

    /**
     * Check if a string is a valid UUID
     * @param {string} uuid - The string to check
     * @returns {boolean} - True if it's a valid UUID
     */
    isValidUuid(uuid) {
        const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
        return uuidRegex.test(uuid);
    }

    /**
     * Check if a string is a numeric ID
     * @param {string} id - The string to check
     * @returns {boolean} - True if it's a numeric ID
     */
    isNumericId(id) {
        return /^\d+$/.test(id);
    }

    /**
     * Get booking data by identifier (UUID or numeric ID)
     * @param {string} identifier - The booking identifier
     * @returns {Promise<Object>} - The booking data
     */
    async getBookingData(identifier) {
        // Check cache first
        if (this.cache.has(`booking_${identifier}`)) {
            return this.cache.get(`booking_${identifier}`);
        }

        try {
            let response;
            if (this.isNumericId(identifier)) {
                // Use numeric ID directly
                response = await api.get(`/bookings/${identifier}`);
            } else if (this.isValidUuid(identifier)) {
                // Use UUID lookup endpoint
                response = await api.get(`/bookings/lookup-uuid/${identifier}`);
            } else {
                throw new Error('Invalid booking identifier');
            }

            // Cache the result
            this.cache.set(`booking_${identifier}`, response.data);
            return response.data;
        } catch (error) {
            console.error('Error fetching booking data:', error);
            throw error;
        }
    }

    /**
     * Get resource data by identifier (UUID or numeric ID)
     * @param {string} identifier - The resource identifier
     * @returns {Promise<Object>} - The resource data
     */
    async getResourceData(identifier) {
        // Check cache first
        if (this.cache.has(`resource_${identifier}`)) {
            return this.cache.get(`resource_${identifier}`);
        }

        try {
            let response;
            if (this.isNumericId(identifier)) {
                // Use numeric ID directly
                response = await api.get(`/resources/${identifier}`);
            } else if (this.isValidUuid(identifier)) {
                // Use UUID lookup endpoint
                response = await api.get(`/resources/lookup-uuid/${identifier}`);
            } else {
                throw new Error('Invalid resource identifier');
            }

            // Cache the result
            this.cache.set(`resource_${identifier}`, response.data);
            return response.data;
        } catch (error) {
            console.error('Error fetching resource data:', error);
            throw error;
        }
    }

    /**
     * Clear cache for a specific entity or all cache
     * @param {string} entity - Optional entity type ('booking', 'resource', or null for all)
     */
    clearCache(entity = null) {
        if (entity) {
            // Clear cache for specific entity
            for (const key of this.cache.keys()) {
                if (key.startsWith(`${entity}_`)) {
                    this.cache.delete(key);
                }
            }
        } else {
            // Clear all cache
            this.cache.clear();
        }
    }

    /**
     * Handle navigation to booking detail page
     * @param {string} identifier - UUID or numeric ID
     * @param {Function} navigate - React Router navigate function
     */
    async navigateToBooking(identifier, navigate) {
        if (this.isNumericId(identifier)) {
            // For numeric ID, try to get the UUID from backend
            try {
                const data = await this.getBookingData(identifier);
                if (data?.success && data.booking?.uuid) {
                    navigate(`/booking/${data.booking.uuid}`);
                } else {
                    navigate(`/booking/${identifier}`);
                }
            } catch (error) {
                // Fallback to numeric ID if lookup fails
                navigate(`/booking/${identifier}`);
            }
        } else if (this.isValidUuid(identifier)) {
            // For UUID, navigate directly
            navigate(`/booking/${identifier}`);
        } else {
            console.error('Invalid booking identifier:', identifier);
            navigate('/booking');
        }
    }

    /**
     * Handle navigation to resource detail page
     * @param {string} identifier - UUID or numeric ID
     * @param {Function} navigate - React Router navigate function
     */
    async navigateToResource(identifier, navigate) {
        if (this.isNumericId(identifier)) {
            // For numeric ID, try to get the UUID from backend
            try {
                const data = await this.getResourceData(identifier);
                if (data?.success && data.resource?.uuid) {
                    navigate(`/resource/${data.resource.uuid}`);
                } else {
                    navigate(`/resource/${identifier}`);
                }
            } catch (error) {
                // Fallback to numeric ID if lookup fails
                navigate(`/resource/${identifier}`);
            }
        } else if (this.isValidUuid(identifier)) {
            // For UUID, navigate directly
            navigate(`/resource/${identifier}`);
        } else {
            console.error('Invalid resource identifier:', identifier);
            navigate('/resources');
        }
    }
}

// Create and export a singleton instance
const uuidRoutingService = new UuidRoutingService();
export default uuidRoutingService; 