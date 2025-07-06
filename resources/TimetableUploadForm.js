import React, { useState, useContext } from 'react';
import { AppContext } from "../context/appContext";
import api from "../services/api";

export default function TimetableUploadForm() {
    const { user, token } = useContext(AppContext);
    const [selectedFile, setSelectedFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [message, setMessage] = useState('');
    const [messageType, setMessageType] = useState(''); // 'success' or 'error'
    const [timetableData, setTimetableData] = useState([]);
    const [loadingTimetable, setLoadingTimetable] = useState(false);
    const [studyModeFilter, setStudyModeFilter] = useState('');
    const [deliveryModeFilter, setDeliveryModeFilter] = useState('');

    const handleFileSelect = (event) => {
        const file = event.target.files[0];
        if (file) {
            // Validate file type
            const allowedTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                'application/vnd.ms-excel', // .xls
                'text/csv', // .csv
                'application/csv'
            ];
            
            if (!allowedTypes.includes(file.type)) {
                setMessage('Please select a valid Excel (.xlsx, .xls) or CSV file.');
                setMessageType('error');
                return;
            }

            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                setMessage('File size must be less than 10MB.');
                setMessageType('error');
                return;
            }

            setSelectedFile(file);
            setMessage('');
        }
    };

    const handleUpload = async () => {
        if (!selectedFile) {
            setMessage('Please select a file to upload.');
            setMessageType('error');
            return;
        }

        setUploading(true);
        setMessage('');

        try {
            const formData = new FormData();
            formData.append('file', selectedFile);

            const response = await api.post('/timetable/import', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'Authorization': `Bearer ${token}`
                }
            });

            if (response.data.success) {
                setMessage('Timetable imported successfully!');
                setMessageType('success');
                setSelectedFile(null);
                // Clear file input
                document.getElementById('timetable-file').value = '';
            } else {
                setMessage(response.data.error || 'Upload failed.');
                setMessageType('error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            setMessage(error.response?.data?.error || 'Upload failed. Please try again.');
            setMessageType('error');
        } finally {
            setUploading(false);
        }
    };

    const loadTimetable = async () => {
        setLoadingTimetable(true);
        try {
            const response = await api.get('/timetable', {
                headers: { 'Authorization': `Bearer ${token}` }
            });

            if (response.data.success) {
                setTimetableData(response.data.timetable);
            } else {
                setMessage('Failed to load timetable data.');
                setMessageType('error');
            }
        } catch (error) {
            console.error('Load timetable error:', error);
            setMessage('Failed to load timetable data.');
            setMessageType('error');
        } finally {
            setLoadingTimetable(false);
        }
    };

    const formatTime = (timeString) => {
        if (!timeString) return '';
        return new Date(`2000-01-01T${timeString}`).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    };

    const getDayName = (dayNumber) => {
        const days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        return days[dayNumber] || 'Unknown';
    };

    return (
        <div className="timetable-upload-container">
            <h2>School Timetable Management</h2>
            
            {/* File Upload Section */}
            <div className="upload-section">
                <h3>Upload Timetable</h3>
                <div className="file-upload-area">
                    <input
                        type="file"
                        id="timetable-file"
                        accept=".xlsx,.xls,.csv"
                        onChange={handleFileSelect}
                        className="file-input"
                    />
                    <label htmlFor="timetable-file" className="file-label">
                        {selectedFile ? selectedFile.name : 'Choose Excel/CSV file'}
                    </label>
                </div>
                
                <button 
                    onClick={handleUpload}
                    disabled={!selectedFile || uploading}
                    className="upload-btn"
                >
                    {uploading ? 'Uploading...' : 'Upload Timetable'}
                </button>
            </div>

            {/* Message Display */}
            {message && (
                <div className={`message ${messageType}`}>
                    {message}
                </div>
            )}

            {/* Timetable Display Section */}
            <div className="timetable-display-section">
                <div className="section-header">
                    <h3>Current Timetable</h3>
                    <div className="filter-controls">
                        <select 
                            value={studyModeFilter} 
                            onChange={(e) => setStudyModeFilter(e.target.value)}
                            className="filter-select"
                        >
                            <option value="">All Study Modes</option>
                            <option value="full-time">Full Time</option>
                            <option value="part-time">Part Time</option>
                        </select>
                        <select 
                            value={deliveryModeFilter} 
                            onChange={(e) => setDeliveryModeFilter(e.target.value)}
                            className="filter-select"
                        >
                            <option value="">All Delivery Modes</option>
                            <option value="face-to-face">Face to Face</option>
                            <option value="online">Online</option>
                            <option value="hybrid">Hybrid</option>
                        </select>
                        <button 
                            onClick={loadTimetable}
                            disabled={loadingTimetable}
                            className="load-btn"
                        >
                            {loadingTimetable ? 'Loading...' : 'Load Timetable'}
                        </button>
                    </div>
                </div>

                {timetableData.length > 0 && (
                    <div className="timetable-table-container">
                        <table className="timetable-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Subject</th>
                                    <th>Teacher</th>
                                    <th>Room</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Class Section</th>
                                    <th>Semester</th>
                                    <th>Study Mode</th>
                                    <th>Delivery Mode</th>
                                    <th>Program Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                {timetableData.map((entry, index) => (
                                    <tr key={index}>
                                        <td>{getDayName(entry.day_of_week)}</td>
                                        <td>{entry.subject}</td>
                                        <td>{entry.teacher || 'N/A'}</td>
                                        <td>{entry.room_name}</td>
                                        <td>{formatTime(entry.start_time)}</td>
                                        <td>{formatTime(entry.end_time)}</td>
                                        <td>{entry.class_section}</td>
                                        <td>{entry.semester}</td>
                                        <td>{entry.study_mode || 'N/A'}</td>
                                        <td>{entry.delivery_mode || 'N/A'}</td>
                                        <td>{entry.program_type || 'N/A'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {timetableData.length === 0 && !loadingTimetable && (
                    <p className="no-data">No timetable data available. Upload a file to get started.</p>
                )}
            </div>

            {/* Instructions */}
            <div className="instructions">
                <h3>Excel File Format Instructions</h3>
                <p>Your Excel file should have the following structure:</p>
                <ul>
                    <li><strong>Headers:</strong> Include columns like LEVEL, DAY, TEACHER, SEMESTER, etc.</li>
                    <li><strong>Time Slots:</strong> Use column headers like "8:00", "9:00", "8:00-9:00", etc.</li>
                    <li><strong>Cell Content:</strong> Format as "Course Code - Room Name" (e.g., "BICT 3601 - ICT Lab 1")</li>
                    <li><strong>Supported Formats:</strong> .xlsx, .xls, .csv</li>
                    <li><strong>File Size:</strong> Maximum 10MB</li>
                </ul>
                
                <h4>Example Excel Structure:</h4>
                <div className="example-table">
                    <table>
                        <thead>
                            <tr>
                                <th>LEVEL</th>
                                <th>DAY</th>
                                <th>TEACHER</th>
                                <th>8:00</th>
                                <th>9:00</th>
                                <th>10:00</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Year 1</td>
                                <td>Monday</td>
                                <td>Dr. Smith</td>
                                <td>BICT 3601 - ICT Lab 1</td>
                                <td>Mathematics - Room 101</td>
                                <td>Physics - Lab 2</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
} 