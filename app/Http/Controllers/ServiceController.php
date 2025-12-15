<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    /**
     * PUBLIC LIST for any logged-in user (client, aesthetician, admin).
     * GET /api/services
     * Optional: ?search=glow
     */
    public function publicIndex(Request $request)
    {
        $search = trim($request->query('search', ''));

        $query = Service::where('is_active', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $services = $query->orderBy('id', 'asc')->get();

        return response()->json([
            'items' => $services,
        ]);
    }

    /**
     * PUBLIC SHOW (for booking detail)
     * GET /api/services/{service}
     */
    public function show(Service $service)
    {
        if (! $service->is_active) {
            return response()->json(['error' => 'Service not available'], 404);
        }

        return response()->json([
            'item' => $service,
        ]);
    }

    /**
     * ADMIN LIST (with filters)
     * GET /api/admin/services?search=...&status=active|inactive|all
     */
    public function index(Request $request)
    {
        $search = trim($request->query('search', ''));
        $status = $request->query('status', 'all'); // active, inactive, all

        $query = Service::query();

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $services = $query->orderBy('id', 'asc')->get();

        return response()->json([
            'items' => $services,
        ]);
    }

    /**
     * ADMIN CREATE
     * POST /api/admin/services
     */
    public function store(Request $request)
    {
        // Convert string booleans to actual booleans before validation
        // FormData sends all values as strings, so "true" becomes string "true"
        if ($request->has('is_active')) {
            $isActive = $request->input('is_active');
            // Convert string representations to boolean
            if (is_string($isActive)) {
                $isActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
            }
            // Merge back into request so validation sees a boolean
            $request->merge(['is_active' => (bool) $isActive]);
        }

        try {
            $data = $request->validate([
                'name'             => 'required|string|max:255',
                'description'      => 'nullable|string',
                'price'            => 'required|numeric|min:0',
                'duration_minutes' => 'required|integer|min:1|max:600',
                'is_active'        => 'sometimes|boolean',
                'image'            => [
                    'nullable',
                    'file',
                    'mimes:jpeg,jpg,png,gif,webp,jfif',
                    'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/jfif',
                    'max:2048', // 2MB max
                ],
            ], [
                'image.mimes' => 'Image must be a valid image file (JPEG, JPG, PNG, GIF, WEBP, or JFIF).',
                'image.mimetypes' => 'Image must be a valid image file.',
                'image.max' => 'Image file size must not exceed 2MB.',
            ]);

            // Ensure is_active has a default value
            if (!isset($data['is_active'])) {
                $data['is_active'] = true; // Default to active
            }

            $data['created_by'] = auth()->id();

            // Handle image upload
            if ($request->hasFile('image')) {
                try {
                    $file = $request->file('image');
                    
                    // Validate file was uploaded successfully
                    if (!$file->isValid()) {
                        $errorCode = $file->getError();
                        $errorMessages = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
                        ];
                        
                        return response()->json([
                            'message' => 'File upload error: ' . ($errorMessages[$errorCode] ?? 'Unknown error (' . $errorCode . ')'),
                        ], 422);
                    }
                    
                    // Check file size manually
                    $fileSize = $file->getSize();
                    $maxSize = 2048 * 1024; // 2MB
                    
                    if ($fileSize > $maxSize) {
                        return response()->json([
                            'message' => 'File size (' . round($fileSize / 1024, 2) . ' KB) exceeds the maximum allowed size of 2MB.',
                        ], 422);
                    }
                    
                    // Validate MIME type
                    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/jfif'];
                    $mimeType = $file->getMimeType();
                    
                    if (!in_array(strtolower($mimeType), array_map('strtolower', $allowedMimes))) {
                        return response()->json([
                            'message' => 'Invalid file type. Please upload a JPEG, PNG, GIF, WEBP, or JFIF image. Detected type: ' . $mimeType,
                        ], 422);
                    }
                    
                    $servicesPath = base_path('../storage/services');
                    if (!file_exists($servicesPath)) {
                        \Illuminate\Support\Facades\File::makeDirectory($servicesPath, 0755, true);
                    }
                    
                    // Ensure directory is writable
                    if (!is_writable($servicesPath)) {
                        \Log::error('Services directory is not writable', ['path' => $servicesPath]);
                        return response()->json([
                            'message' => 'Server error: Unable to save file. Please contact support.',
                        ], 500);
                    }
                    
                    // Get file extension - normalize jfif
                    $extension = strtolower($file->getClientOriginalExtension());
                    if ($extension === 'jfif' || $extension === 'jpe') {
                        $extension = 'jpg';
                    }
                    
                    $filename = time() . '_' . uniqid() . '.' . $extension;
                    
                    // Save using move_uploaded_file for better reliability
                    $destinationPath = $servicesPath . '/' . $filename;
                    
                    if (!move_uploaded_file($file->getRealPath(), $destinationPath)) {
                        \Log::error('Failed to move uploaded service image', [
                            'source' => $file->getRealPath(),
                            'destination' => $destinationPath,
                            'error' => error_get_last(),
                        ]);
                        
                        return response()->json([
                            'message' => 'Failed to save image. Please try again or use a different image.',
                        ], 500);
                    }
                    
                    // Verify file was saved
                    if (!file_exists($destinationPath)) {
                        return response()->json([
                            'message' => 'File upload failed. Please try again.',
                        ], 500);
                    }
                    
                    $data['image'] = $filename;
                    
                    \Log::info('Service image uploaded successfully', [
                        'filename' => $filename,
                        'size' => $fileSize,
                        'mime_type' => $mimeType,
                    ]);
                    
                } catch (\Exception $e) {
                    \Log::error('Service image upload exception', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    return response()->json([
                        'message' => 'Upload failed: ' . $e->getMessage(),
                    ], 500);
                }
            } else {
                // Set default service image if no image is provided
                $data['image'] = 'default_service.png';
            }

            \Log::info('About to create service', ['final_data' => $data]);

            $service = Service::create($data);
            
            \Log::info('Service created successfully', ['service_id' => $service->id]);
            
            return response()->json([
                'message' => 'Service created',
                'item'    => $service,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Service validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Service creation failed', [
                'error' => $e->getMessage(),
                'data' => $data ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to create service: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ADMIN UPDATE
     * PUT /api/admin/services/{service}
     */
    public function update(Request $request, Service $service)
    {
        // Log all request data for debugging
        // For FormData, use all() to get all inputs
        $allInputs = $request->all();
        \Log::info('Service update request', [
            'service_id' => $service->id,
            'has_image' => $request->hasFile('image'),
            'all_data' => $allInputs,
            'input_keys' => array_keys($allInputs),
            'method' => $request->method(),
            'real_method' => $request->input('_method', $request->method()),
            'content_type' => $request->header('Content-Type'),
            'name' => $request->input('name'),
            'price' => $request->input('price'),
            'duration_minutes' => $request->input('duration_minutes'),
        ]);

        // Convert string booleans to actual booleans before validation
        // FormData sends all values as strings, so "true" becomes string "true"
        if ($request->has('is_active')) {
            $isActive = $request->input('is_active');
            // Convert string representations to boolean
            if (is_string($isActive)) {
                $isActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
            }
            // Merge back into request so validation sees a boolean
            $request->merge(['is_active' => (bool) $isActive]);
        }

        try {
            $data = $request->validate([
                'name'             => 'required|string|max:255',
                'description'      => 'nullable|string',
                'price'            => 'required|numeric|min:0',
                'duration_minutes' => 'required|integer|min:1|max:600',
                'is_active'        => 'sometimes|boolean',
                'image'            => [
                    'nullable',
                    'file',
                    'mimes:jpeg,jpg,png,gif,webp,jfif',
                    'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/jfif',
                    'max:2048', // 2MB max
                ],
            ], [
                'image.mimes' => 'Image must be a valid image file (JPEG, JPG, PNG, GIF, WEBP, or JFIF).',
                'image.mimetypes' => 'Image must be a valid image file.',
                'image.max' => 'Image file size must not exceed 2MB.',
            ]);

            \Log::info('Service validation passed', ['validated_data' => $data]);

            // Handle image upload
            if ($request->hasFile('image')) {
                try {
                    $file = $request->file('image');
                    
                    // Validate file was uploaded successfully
                    if (!$file->isValid()) {
                        $errorCode = $file->getError();
                        $errorMessages = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
                        ];
                        
                        return response()->json([
                            'message' => 'File upload error: ' . ($errorMessages[$errorCode] ?? 'Unknown error (' . $errorCode . ')'),
                        ], 422);
                    }
                    
                    // Check file size manually
                    $fileSize = $file->getSize();
                    $maxSize = 2048 * 1024; // 2MB
                    
                    if ($fileSize > $maxSize) {
                        return response()->json([
                            'message' => 'File size (' . round($fileSize / 1024, 2) . ' KB) exceeds the maximum allowed size of 2MB.',
                        ], 422);
                    }
                    
                    // Validate MIME type
                    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/jfif'];
                    $mimeType = $file->getMimeType();
                    
                    if (!in_array(strtolower($mimeType), array_map('strtolower', $allowedMimes))) {
                        return response()->json([
                            'message' => 'Invalid file type. Please upload a JPEG, PNG, GIF, WEBP, or JFIF image. Detected type: ' . $mimeType,
                        ], 422);
                    }
                    
                    $servicesPath = base_path('../storage/services');
                    if (!file_exists($servicesPath)) {
                        \Illuminate\Support\Facades\File::makeDirectory($servicesPath, 0755, true);
                    }
                    
                    // Ensure directory is writable
                    if (!is_writable($servicesPath)) {
                        \Log::error('Services directory is not writable', ['path' => $servicesPath]);
                        return response()->json([
                            'message' => 'Server error: Unable to save file. Please contact support.',
                        ], 500);
                    }
                    
                    // Delete old image if exists (and it's not the default)
                    if ($service->image && $service->image !== 'default_service.png') {
                        $oldPath = $servicesPath . '/' . $service->image;
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }

                    // Get file extension - normalize jfif
                    $extension = strtolower($file->getClientOriginalExtension());
                    if ($extension === 'jfif' || $extension === 'jpe') {
                        $extension = 'jpg';
                    }
                    
                    $filename = time() . '_' . uniqid() . '.' . $extension;
                    
                    // Save using move_uploaded_file for better reliability
                    $destinationPath = $servicesPath . '/' . $filename;
                    
                    if (!move_uploaded_file($file->getRealPath(), $destinationPath)) {
                        \Log::error('Failed to move uploaded service image', [
                            'source' => $file->getRealPath(),
                            'destination' => $destinationPath,
                            'error' => error_get_last(),
                        ]);
                        
                        return response()->json([
                            'message' => 'Failed to save image. Please try again or use a different image.',
                        ], 500);
                    }
                    
                    // Verify file was saved
                    if (!file_exists($destinationPath)) {
                        return response()->json([
                            'message' => 'File upload failed. Please try again.',
                        ], 500);
                    }
                    
                    $data['image'] = $filename;
                    \Log::info('Service image uploaded successfully', [
                        'filename' => $filename,
                        'size' => $fileSize,
                        'mime_type' => $mimeType,
                    ]);
                    
                } catch (\Exception $e) {
                    \Log::error('Service image upload exception', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    return response()->json([
                        'message' => 'Upload failed: ' . $e->getMessage(),
                    ], 500);
                }
            }
            // If no new image, keep existing image (don't update image field)

            $service->update($data);
            
            \Log::info('Service updated successfully', ['service_id' => $service->id]);

            return response()->json([
                'message' => 'Service updated',
                'item'    => $service->fresh(), // Reload to get image_url
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Service update validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Service update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to update service: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ADMIN DELETE
     * DELETE /api/admin/services/{service}
     *
     * Later, when bookings exist, we can switch this to "soft delete"
     * or just set is_active = false if you prefer.
     */
    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json([
            'message' => 'Service deleted',
        ]);
    }
}
