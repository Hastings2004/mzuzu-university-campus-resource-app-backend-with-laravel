<?php

namespace App\Services;

use App\Models\Timetable;
use App\Models\Resource;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TimetableImportService
{
    /**
     * Imports timetable data from an Excel/CSV file.
     *
     * @param string $filePath The full path to the Excel/CSV file.
     * @throws \Exception If the file cannot be read or import fails.
     */
    public function importFromExcel(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found at: " . $filePath);
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Assume the first row is the header
            $header = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', NULL, TRUE, FALSE)[0];
            Log::info("DEBUG: Headers detected in file: " . json_encode($header));

            // Define common time slot patterns for school timetables
            $timeSlotPatterns = [
                '7:45', '8:45', '9:45', '10:45', '11:45', '12:45',
                '1:45', '2:45', '3:45', '4:45', '5:45',
                '08:00', '09:00', '10:00', '11:00', '12:00',
                '13:00', '14:00', '15:00', '16:00', '17:00',
                '8:00-9:00', '9:00-10:00', '10:00-11:00', '11:00-12:00',
                '13:00-14:00', '14:00-15:00', '15:00-16:00', '16:00-17:00'
            ];

            // Map standard column names to their indices
            $columnMap = [
                'LEVEL' => array_search('LEVEL', $header),
                'DAY' => array_search('DAY', $header),
                'DAY_OF_WEEK' => array_search('DAY_OF_WEEK', $header),
                'COURSE' => array_search('COURSE', $header),
                'SUBJECT' => array_search('SUBJECT', $header),
                'TEACHER' => array_search('TEACHER', $header),
                'VENUE' => array_search('VENUE', $header),
                'ROOM' => array_search('ROOM', $header),
                'SEMESTER' => array_search('SEMESTER', $header),
                'CLASS' => array_search('CLASS', $header),
                'SECTION' => array_search('SECTION', $header)
            ];

            // Add time slot headers to columnMap
            foreach ($timeSlotPatterns as $pattern) {
                $columnIndex = array_search($pattern, $header);
                if ($columnIndex !== false) {
                    $columnMap[$pattern] = $columnIndex;
                }
            }

            // Also check for time ranges in headers
            foreach ($header as $index => $headerName) {
                if (preg_match('/\d{1,2}:\d{2}-\d{1,2}:\d{2}/', $headerName)) {
                    $columnMap[$headerName] = $index;
                }
            }

            Log::info("DEBUG: Column mapping: " . json_encode($columnMap));

            DB::beginTransaction(); // Start a database transaction

            // Iterate through rows, skipping the header (starting from row 2)
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = $sheet->rangeToArray('A' . $row . ':' . $sheet->getHighestColumn() . $row, NULL, TRUE, FALSE)[0];

                // Skip completely empty rows
                if (empty(array_filter($rowData))) {
                    continue;
                }

                // Extract row-level data
                $level = trim($rowData[$columnMap['LEVEL']] ?? 'N/A');
                $day = trim($rowData[$columnMap['DAY']] ?? $rowData[$columnMap['DAY_OF_WEEK']] ?? '');
                $teacher = trim($rowData[$columnMap['TEACHER']] ?? '');
                $semester = trim($rowData[$columnMap['SEMESTER']] ?? 'Current Semester');
                $classSection = trim($rowData[$columnMap['CLASS']] ?? $rowData[$columnMap['SECTION']] ?? $level);

                // If no day is found in the row, try to determine from the sheet name or other means
                if (empty($day)) {
                    $sheetName = strtolower($sheet->getTitle());
                    if (strpos($sheetName, 'monday') !== false) $day = 'Monday';
                    elseif (strpos($sheetName, 'tuesday') !== false) $day = 'Tuesday';
                    elseif (strpos($sheetName, 'wednesday') !== false) $day = 'Wednesday';
                    elseif (strpos($sheetName, 'thursday') !== false) $day = 'Thursday';
                    elseif (strpos($sheetName, 'friday') !== false) $day = 'Friday';
                    elseif (strpos($sheetName, 'saturday') !== false) $day = 'Saturday';
                    elseif (strpos($sheetName, 'sunday') !== false) $day = 'Sunday';
                }

                // Validate essential row-level data
                if (empty($day)) {
                    Log::warning("Skipping row {$row} due to missing day data: " . implode(', ', $rowData));
                    continue;
                }

                // Iterate through time slot columns
                foreach ($columnMap as $columnName => $columnIndex) {
                    // Skip non-time slot columns
                    if (in_array($columnName, ['LEVEL', 'DAY', 'DAY_OF_WEEK', 'COURSE', 'SUBJECT', 'TEACHER', 'VENUE', 'ROOM', 'SEMESTER', 'CLASS', 'SECTION'])) {
                        continue;
                    }

                    $timeSlotCellContent = trim($rowData[$columnIndex] ?? '');

                    // Only process if the time slot cell is not empty
                    if (!empty($timeSlotCellContent)) {
                        // Parse cell content (e.g., "BICT 3601 - ICT Lab 1" or "Mathematics - Room 101")
                        list($courseCode, $classVenue) = $this->parseCourseAndVenueFromCell($timeSlotCellContent);

                        if (empty($courseCode)) {
                            Log::warning("Skipping time slot '{$columnName}' in row {$row} due to unparsable class content: '{$timeSlotCellContent}'");
                            continue;
                        }

                        // Determine start and end time from the time slot header
                        list($startTimeStr, $endTimeStr) = $this->splitTimeRange($columnName);

                        $startTime = $this->formatTime($startTimeStr);
                        $endTime = $this->formatTime($endTimeStr);
                        $dayOfWeek = $this->mapDayToNumber($day);

                        // Validate parsed times and day
                        if (is_null($startTime) || is_null($endTime) || is_null($dayOfWeek)) {
                            Log::error("Failed to parse time ('{$startTimeStr}' or '{$endTimeStr}') or day ('{$day}') for row {$row}, time slot '{$columnName}'. Raw Data: " . json_encode($rowData));
                            continue; // Skip this specific time slot entry
                        }

                        // Determine the actual venue for this class entry
                        $actualVenueName = !empty($classVenue) ? $classVenue : "Default Room";
                        
                        // Find or create resource for the specific class venue
                        $resource = Resource::firstOrCreate(
                            ['name' => $actualVenueName],
                            [
                                'description' => 'Classroom for ' . $actualVenueName,
                                'location'    => 'Main Campus',
                                'capacity'    => 30, // Default capacity for classrooms
                                'category'    => 'classrooms',
                            ]
                        );

                        if (!$resource) {
                            throw new \Exception("Could not find or create resource for venue: " . $actualVenueName . " for class: " . $courseCode);
                        }

                        // Create Timetable entry
                        Timetable::create([
                            'course_code'   => $courseCode,
                            'subject'       => $courseCode, // Using course code as subject
                            'teacher'       => $teacher,
                            'room_id'       => $resource->id,
                            'day_of_week'   => $dayOfWeek,
                            'start_time'    => $startTime,
                            'end_time'      => $endTime,
                            'semester'      => $semester,
                            'class_section' => $classSection,
                            'course_name'   => $courseCode,
                            'room'          => $actualVenueName,
                            'type'          => 'class'
                        ]);
                    }
                }
            }

            DB::commit(); // Commit the transaction
            Log::info("Timetable import completed successfully from " . $filePath);

        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            DB::rollBack();
            Log::error("Spreadsheet error during import: " . $e->getMessage(), ['file' => $filePath, 'trace' => $e->getTraceAsString()]);
            throw new \Exception("Error reading spreadsheet: " . $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Import failed: " . $e->getMessage(), ['file' => $filePath, 'trace' => $e->getTraceAsString()]);
            throw new \Exception("An error occurred during import: " . $e->getMessage());
        }
    }

    /**
     * Splits a time range string (e.g., "8:45-10:45" or "8:45AM - 10:45AM") into start and end time strings.
     *
     * @param string $timeRange The time range string from the column header.
     * @return array An array containing [startTimeStr, endTimeStr]. Defaults to ['00:00', '00:00'] if parsing fails.
     */
    protected function splitTimeRange(string $timeRange): array
    {
        // Handle time ranges like "8:00-9:00"
        if (strpos($timeRange, '-') !== false) {
            $parts = explode('-', $timeRange);
            if (count($parts) === 2) {
                return [trim($parts[0]), trim($parts[1])];
            }
        }
        
        // Handle single time slots like "8:45" - assume 1 hour duration
        if (preg_match('/^\d{1,2}:\d{2}$/', $timeRange)) {
            $startTime = $timeRange;
            $endTime = $this->addOneHour($startTime);
            return [$startTime, $endTime];
        }
        
        Log::warning("Could not split time range header: '{$timeRange}'. Expected format 'START-END' or single time.");
        return ['00:00', '00:00']; // Fallback in 24-hour format
    }

    /**
     * Adds one hour to a time string.
     *
     * @param string $timeString
     * @return string
     */
    protected function addOneHour(string $timeString): string
    {
        try {
            $carbonTime = Carbon::parse($timeString);
            return $carbonTime->addHour()->format('H:i');
        } catch (\Exception $e) {
            return '00:00';
        }
    }

    /**
     * Converts day name (e.g., "Monday", "MON") to a number (1-7, Monday=1).
     *
     * @param string $dayName
     * @return int|null The numeric day or null if invalid.
     */
    protected function mapDayToNumber(string $dayName): ?int
    {
        $dayMap = [
            'monday'    => 1, 'mon' => 1,
            'tuesday'   => 2, 'tue' => 2,
            'wednesday' => 3, 'wed' => 3,
            'thursday'  => 4, 'thu' => 4,
            'friday'    => 5, 'fri' => 5,
            'saturday'  => 6, 'sat' => 6,
            'sunday'    => 7, 'sun' => 7,
        ];
        return $dayMap[strtolower(trim($dayName))] ?? null;
    }

    /**
     * Formats various time strings into 'H:i:s' format.
     * Handles formats like "8:45AM", "10:45", "13:00".
     *
     * @param string $timeString
     * @return string|null The formatted time string or null if parsing fails.
     */
    protected function formatTime(string $timeString): ?string
    {
        try {
            $carbonTime = Carbon::parse($timeString);
            return $carbonTime->format('H:i:s');
        } catch (\Exception $e) {
            Log::warning("Could not parse time string: '{$timeString}'. Error: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Parses cell content like "COURSE_CODE - VENUE" into course code and venue.
     *
     * @param string $cellContent E.g., "BICT 3601 - ICT Lab 1"
     * @return array An array containing [courseCode, venueName]. VenueName can be null if not found.
     */
    protected function parseCourseAndVenueFromCell(string $cellContent): array
    {
        $parts = explode('-', $cellContent, 2); // Split only on the first hyphen
        $courseCode = trim($parts[0]);
        $venueName = count($parts) > 1 ? trim($parts[1]) : null;

        return [$courseCode, $venueName];
    }
}