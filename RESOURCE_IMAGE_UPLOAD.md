# Resource Image Upload Functionality

This document explains how to use the image upload functionality for resources in the Campus Resource App.

## Overview

The resource system now supports image uploads for resources. Images are stored in the `storage/app/public/resources` directory and are accessible via the public storage link.

## Features

- ✅ Image upload during resource creation
- ✅ Image update during resource modification
- ✅ Automatic image deletion when resource is deleted
- ✅ Image validation (file type, size limits)
- ✅ Automatic image URL generation
- ✅ Storage cleanup

## API Endpoints

### Create Resource with Image

**POST** `/api/resources`

**Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Form Data:**
```
name: "Resource Name"
description: "Resource Description"
location: "Resource Location"
capacity: 50
category: "classrooms"
status: "available"
image: [file upload]
```

**Response:**
```json
{
    "success": true,
    "message": "Resource created successfully.",
    "resource": {
        "id": 1,
        "name": "Resource Name",
        "description": "Resource Description",
        "location": "Resource Location",
        "capacity": 50,
        "category": "classrooms",
        "status": "available",
        "image": "resources/filename.jpg",
        "image_url": "http://your-domain.com/storage/resources/filename.jpg",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

### Update Resource with Image

**PUT/PATCH** `/api/resources/{id}`

**Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Form Data:**
```
name: "Updated Resource Name"
image: [file upload]
```

**Response:**
```json
{
    "success": true,
    "message": "Resource updated successfully.",
    "resource": {
        "id": 1,
        "name": "Updated Resource Name",
        "image": "resources/new-filename.jpg",
        "image_url": "http://your-domain.com/storage/resources/new-filename.jpg"
    }
}
```

## Image Validation Rules

- **File Types:** jpeg, png, jpg, gif, svg
- **Maximum Size:** 2MB (2048 KB)
- **Required:** No (nullable)

## File Storage

- **Storage Disk:** `public`
- **Directory:** `resources/`
- **Access URL:** `/storage/resources/filename.ext`

## Model Attributes

The `Resource` model includes:

- `image`: Database field storing the file path
- `image_url`: Accessor attribute providing the full URL
- `appends`: Automatically includes `image_url` in JSON responses

## Service Methods

### ResourceService

- `createResource(array $data)`: Handles image upload during creation
- `updateResource(Resource $resource, array $data)`: Handles image upload during updates
- `deleteResource(Resource $resource)`: Deletes associated image file

## Error Handling

The system handles various error scenarios:

- **Invalid file type:** Returns validation error
- **File too large:** Returns validation error
- **Storage errors:** Logs error and returns appropriate message
- **Missing permissions:** Returns 403 Forbidden

## Testing

Use the provided test file `test_resource_image_upload.php` to verify functionality:

```bash
php test_resource_image_upload.php
```

## Security Considerations

- Only admin users can upload images
- File type validation prevents malicious uploads
- File size limits prevent abuse
- Images are stored in public directory with proper permissions

## Frontend Integration

When sending requests from the frontend:

1. Use `FormData` for multipart/form-data requests
2. Include the image file in the form data
3. Set proper `Content-Type` header
4. Handle the `image_url` in responses for display

Example JavaScript:
```javascript
const formData = new FormData();
formData.append('name', 'Resource Name');
formData.append('image', imageFile);

fetch('/api/resources', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
});
``` 