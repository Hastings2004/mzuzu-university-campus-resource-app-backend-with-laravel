import React from 'react';

export default function SuggestionsTable({ suggestions, onBook, formatDateTime }) {
    if (!suggestions || suggestions.length === 0) return null;

    return (
        <div className="suggestions-section">
            <h4>Suggested Alternatives</h4>
            <table className="suggestions-table">
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
                    {suggestions.map((suggestion, idx) => (
                        <tr key={idx} className="suggestion-row">
                            <td>{suggestion.resource_id}</td>
                            <td>{formatDateTime ? formatDateTime(suggestion.start_time) : new Date(suggestion.start_time).toLocaleString()}</td>
                            <td>{formatDateTime ? formatDateTime(suggestion.end_time) : new Date(suggestion.end_time).toLocaleString()}</td>
                            <td>
                                <span className={`suggestion-type suggestion-type-${suggestion.type}`}>
                                    {suggestion.type}
                                </span>
                            </td>
                            <td>
                                <span className={`preference-score preference-score-${suggestion.preference_score >= 4 ? 'high' : suggestion.preference_score >= 2 ? 'medium' : 'low'}`}>
                                    {suggestion.preference_score ?? 0}
                                </span>
                            </td>
                            <td>
                                <button 
                                    onClick={() => onBook(suggestion)}
                                    className="book-suggestion-btn"
                                >
                                    Book This
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
} 