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

            // Define all your time slot column headers EXACTLY as they appear in your Excel sheet.
            // YOU MUST POPULATE THIS ARRAY WITH ALL YOUR TIME SLOTS!
            $timeSlotHeaders = [
                '7:45',
                '8:45',
                '9:45',
                '10:45',
                '11:45',
                '12:45', 
                '1:45',
                '2:45',
                '3:45',
                '4:45',
                '5:45', 
                
            ];


            // Map standard column names to their indices
            $columnMap = [
                'LEVEL'         => array_search('LEVEL', $header)                
            ];

            // Add time slot headers to columnMap
            foreach ($timeSlotHeaders as $tsHeader) {
                $columnMap[$tsHeader] = array_search($tsHeader, $header);
            }

            // Validate that essential columns exist
            // 'COURSE TITLE' and 'TIME' are no longer direct essential columns, as they are derived.
            // 'VENUE' is also optional as it can be derived from the cell content.
            foreach (['LEVEL'] as $requiredCol) {
                if ($columnMap[$requiredCol] === false || $columnMap[$requiredCol] === null) {
                    throw new \Exception("Required column '{$requiredCol}' not found in the file header. Please ensure correct headers.");
                }
            }

            // Validate that at least one time slot column is detected
            $foundTimeSlots = false;
            foreach ($timeSlotHeaders as $tsHeader) {
                if ($columnMap[$tsHeader] !== false && $columnMap[$tsHeader] !== null) {
                    $foundTimeSlots = true;
                    break;
                }            }
            // if (!$foundTimeSlots) {
            //     throw new \Exception("No time slot columns (e.g., '7:45-8:45') found in header. Please ensure your time slot headers are correctly defined in \$timeSlotHeaders array and present in the file.");
            // }


            DB::beginTransaction(); // Start a database transaction

            // Iterate through rows, skipping the header (starting from row 2)
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = $sheet->rangeToArray('A' . $row . ':' . $sheet->getHighestColumn() . $row, NULL, TRUE, FALSE)[0];

                // Skip completely empty rows
                if (empty(array_filter($rowData))) {
                    continue;
                }

                // Extract and trim data for row-level fields
                $level = trim($rowData[$columnMap['LEVEL']] ?? 'N/A'); // Use N/A if LEVEL is not found or empty
                //$mainRowVenue = trim($rowData[$columnMap['VENUE']] ?? ''); // This is the venue column in the main row


                // Validate essential row-level data
                if (empty($day)) {
                    Log::warning("Skipping row {$row} due to missing 'DAY' data: " . implode(', ', $rowData));
                    continue;
                }

                // Iterate through time slot columns
                foreach ($timeSlotHeaders as $tsHeader) {
                    $timeSlotCellContent = trim($rowData[$columnMap[$tsHeader]] ?? '');

                    // Only process if the time slot cell is not empty
                    if (!empty($timeSlotCellContent)) {
                        // Parse cell content (e.g., "BICT 3601 - ICT Lab 1")
                        list($courseCode, $classVenue) = $this->parseCourseAndVenueFromCell($timeSlotCellContent);

                        if (empty($courseCode)) {
                            Log::warning("Skipping time slot '{$tsHeader}' in row {$row} due to unparsable class content: '{$timeSlotCellContent}'");
                            continue;
                        }

                        // Determine start and end time from the time slot header
                        list($startTimeStr, $endTimeStr) = $this->splitTimeRange($tsHeader);

                        $startTime = $this->formatTime($startTimeStr);
                        $endTime = $this->formatTime($endTimeStr);
                        $dayOfWeek = $this->mapDayToNumber($day);

                        // Validate parsed times and day
                        if (is_null($startTime) || is_null($endTime) || is_null($dayOfWeek)) {
                            Log::error("Failed to parse time ('{$startTimeStr}' or '{$endTimeStr}') or day ('{$day}') for row {$row}, time slot '{$tsHeader}'. Raw Data: " . json_encode($rowData));
                            continue; // Skip this specific time slot entry
                        }

                        // Determine the actual venue for this class entry
                        // Prioritize venue from the cell, fallback to main row venue, then a default
                        $actualVenueName = !empty($classVenue) ? $classVenue : "room";
                        if (empty($actualVenueName)) {
                             Log::warning("Skipping time slot '{$tsHeader}' in row {$row} due to no venue identified. Content: '{$timeSlotCellContent}'");
                             continue;
                        }

                        // Find or create resource for the specific class venue
                        $resource = Resource::firstOrCreate(
                            ['name' => $actualVenueName],
                            [
                                'description' => 'Default description for ' . $actualVenueName,
                                'location'    => 'Main Campus', // Default location
                                'capacity'    => 50, // Default capacity
                            ]
                        );

                        if (!$resource) {
                            throw new \Exception("Could not find or create resource for venue: " . $actualVenueName . " for class: " . $courseCode);
                        }

                        // Create Timetable entry
                        Timetable::create([
                            'subject'       => $courseCode, // Using course code as subject
                            'teacher'       => "",
                            'room_id'       => $resource->id,
                            'day_of_week'   => $dayOfWeek,
                            'start_time'    => $startTime,
                            'end_time'      => $endTime,
                            'semester'      => 'December 2024', // Hardcoded or derive dynamically if possible
                            'class_section' => $level, // Using LEVEL for class_section
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
     * @return array An array containing [startTimeStr, endTimeStr]. Defaults to ['00:00AM', '00:00AM'] if parsing fails.
     */
    protected function splitTimeRange(string $timeRange): array
    {
        $parts = explode('-', $timeRange);
        if (count($parts) === 2) {
            return [trim($parts[0]), trim($parts[1])];
        }
        Log::warning("Could not split time range header: '{$timeRange}'. Expected format 'START-END'.");
        return ['00:00', '00:00']; // Fallback in 24-hour format
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