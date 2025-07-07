import React, { useState, useEffect, useContext } from 'react';
import { AppContext } from "../context/appContext";
import api from "../services/api";

export default function UserPreferencesForm() {
    const { user, token } = useContext(AppContext);
    const [preferences, setPreferences] = useState({
        categories: [],
        times: [],
        features: [],
        capacity: '',
        locations: []
    });
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(false);

    // State for available options
    const [availableCategories, setAvailableCategories] = useState([]);
    const [availableFeatures, setAvailableFeatures] = useState([]);
    const [availableLocations, setAvailableLocations] = useState([]);
    const [availableTimes, setAvailableTimes] = useState([]);
    const [optionsLoading, setOptionsLoading] = useState(true);
    const [optionsError, setOptionsError] = useState('');

    // Fetch available options from API
    useEffect(() => {
        async function fetchOptions() {
            setOptionsLoading(true);
            setOptionsError('');
            try {
                const [catRes, featRes, locRes, timeRes] = await Promise.all([
                    api.get('/preferences/categories'),
                    api.get('/preferences/features'),
                    api.get('/preferences/locations'),
                    api.get('/preferences/times'),
                ]);
                setAvailableCategories(catRes.categories || []);
                setAvailableFeatures(featRes.features || []);
                setAvailableLocations(locRes.locations || []);
                setAvailableTimes(timeRes.times || []);
            } catch (err) {
                setOptionsError('Failed to load preference options.');
            } finally {
                setOptionsLoading(false);
            }
        }
        fetchOptions();
    }, []);

    // Load existing preferences if user has them
    useEffect(() => {
        if (user?.preferences) {
            setPreferences({
                categories: user.preferences.categories || [],
                times: user.preferences.times || [],
                features: user.preferences.features || [],
                capacity: user.preferences.capacity || '',
                locations: user.preferences.locations || []
            });
        }
    }, [user]);

    const handleCategoryChange = (category) => {
        setPreferences(prev => ({
            ...prev,
            categories: prev.categories.includes(category)
                ? prev.categories.filter(c => c !== category)
                : [...prev.categories, category]
        }));
    };

    const handleTimeChange = (time) => {
        setPreferences(prev => ({
            ...prev,
            times: prev.times.includes(time)
                ? prev.times.filter(t => t !== time)
                : [...prev.times, time]
        }));
    };

    const handleFeatureChange = (feature) => {
        setPreferences(prev => ({
            ...prev,
            features: prev.features.includes(feature)
                ? prev.features.filter(f => f !== feature)
                : [...prev.features, feature]
        }));
    };

    const handleLocationChange = (location) => {
        setPreferences(prev => ({
            ...prev,
            locations: prev.locations.includes(location)
                ? prev.locations.filter(l => l !== location)
                : [...prev.locations, location]
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setMessage('');

        try {
            const response = await api.put('/user/preferences', preferences, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            setMessage('Preferences updated successfully!');
        } catch (error) {
            setMessage(error.response?.data?.message || 'Failed to update preferences');
        } finally {
            setLoading(false);
        }
    };

    const handleReset = () => {
        setPreferences({
            categories: [],
            times: [],
            features: [],
            capacity: '',
            locations: []
        });
        setMessage('Preferences reset to default');
    };

    if (optionsLoading) {
        return <div className="preferences-form-container"><p>Loading options...</p></div>;
    }
    if (optionsError) {
        return <div className="preferences-form-container"><p className="error">{optionsError}</p></div>;
    }

    return (
        <div className="preferences-form-container">
            <h2>Booking Preferences</h2>
            <p className="preferences-description">
                Set your preferences to get better booking suggestions and recommendations.
            </p>

            <form onSubmit={handleSubmit} className="preferences-form">
                <div className="preference-section">
                    <h3>Preferred Resource Categories</h3>
                    <div className="checkbox-group">
                        {availableCategories.map(category => (
                            <label key={category} className="checkbox-label">
                                <input
                                    type="checkbox"
                                    checked={preferences.categories.includes(category)}
                                    onChange={() => handleCategoryChange(category)}
                                />
                                <span className="checkbox-text">{category.replace('_', ' ').toUpperCase()}</span>
                            </label>
                        ))}
                    </div>
                </div>

                <div className="preference-section">
                    <h3>Preferred Booking Times</h3>
                    <div className="checkbox-group">
                        {availableTimes.map(time => (
                            <label key={time} className="checkbox-label">
                                <input
                                    type="checkbox"
                                    checked={preferences.times.includes(time)}
                                    onChange={() => handleTimeChange(time)}
                                />
                                <span className="checkbox-text">{time}</span>
                            </label>
                        ))}
                    </div>
                </div>

                <div className="preference-section">
                    <h3>Preferred Features</h3>
                    <div className="checkbox-group">
                        {availableFeatures.map(feature => (
                            <label key={feature} className="checkbox-label">
                                <input
                                    type="checkbox"
                                    checked={preferences.features.includes(feature)}
                                    onChange={() => handleFeatureChange(feature)}
                                />
                                <span className="checkbox-text">{feature.replace('_', ' ').toUpperCase()}</span>
                            </label>
                        ))}
                    </div>
                </div>

                <div className="preference-section">
                    <h3>Preferred Locations</h3>
                    <div className="checkbox-group">
                        {availableLocations.map(location => (
                            <label key={location} className="checkbox-label">
                                <input
                                    type="checkbox"
                                    checked={preferences.locations.includes(location)}
                                    onChange={() => handleLocationChange(location)}
                                />
                                <span className="checkbox-text">{location}</span>
                            </label>
                        ))}
                    </div>
                </div>

                <div className="preference-section">
                    <h3>Minimum Capacity</h3>
                    <input
                        type="number"
                        min="1"
                        max="100"
                        value={preferences.capacity}
                        onChange={(e) => setPreferences(prev => ({ ...prev, capacity: e.target.value }))}
                        placeholder="e.g., 10"
                        className="capacity-input"
                    />
                    <small>Leave empty if no preference</small>
                </div>

                <div className="form-actions">
                    <button 
                        type="submit" 
                        className="save-preferences-btn"
                        disabled={loading}
                    >
                        {loading ? 'Saving...' : 'Save Preferences'}
                    </button>
                    <button 
                        type="button" 
                        onClick={handleReset}
                        className="reset-preferences-btn"
                    >
                        Reset to Default
                    </button>
                </div>

                {message && (
                    <div className={`message ${message.includes('successfully') ? 'success' : 'error'}`}>
                        {message}
                    </div>
                )}
            </form>
        </div>
    );
} 