// Simple API service for making HTTP requests
const API_BASE_URL = '/api';

class ApiService {
    constructor() {
        this.baseURL = API_BASE_URL;
    }

    /**
     * Get auth token from localStorage or context
     */
    getAuthToken() {
        return localStorage.getItem('token') || sessionStorage.getItem('token');
    }

    /**
     * Get default headers with authentication
     */
    getHeaders(contentType = 'application/json') {
        const headers = {
            'Content-Type': contentType,
        };

        const token = this.getAuthToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        return headers;
    }

    /**
     * Make a GET request
     */
    async get(url, options = {}) {
        const response = await fetch(`${this.baseURL}${url}`, {
            method: 'GET',
            headers: this.getHeaders(),
            ...options,
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
    }

    /**
     * Make a POST request
     */
    async post(url, data = {}, options = {}) {
        const response = await fetch(`${this.baseURL}${url}`, {
            method: 'POST',
            headers: this.getHeaders(),
            body: JSON.stringify(data),
            ...options,
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
    }

    /**
     * Make a PUT request
     */
    async put(url, data = {}, options = {}) {
        const response = await fetch(`${this.baseURL}${url}`, {
            method: 'PUT',
            headers: this.getHeaders(),
            body: JSON.stringify(data),
            ...options,
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
    }

    /**
     * Make a DELETE request
     */
    async delete(url, options = {}) {
        const response = await fetch(`${this.baseURL}${url}`, {
            method: 'DELETE',
            headers: this.getHeaders(),
            ...options,
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
    }

    /**
     * Make a PATCH request
     */
    async patch(url, data = {}, options = {}) {
        const response = await fetch(`${this.baseURL}${url}`, {
            method: 'PATCH',
            headers: this.getHeaders(),
            body: JSON.stringify(data),
            ...options,
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
    }
}

// Create and export a singleton instance
const api = new ApiService();
export default api; 