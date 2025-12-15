<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    /**
     * Get current user profile.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => auth()->user(),
        ]);
    }

    /**
     * Update profile information (excluding email and password).
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Log all request data for debugging
        \Log::info('Profile update request', [
            'user_id' => $user->id,
            'has_avatar' => $request->hasFile('avatar'),
            'all_data' => $request->all(),
            'input_keys' => array_keys($request->all()),
            'method' => $request->method(),
            'real_method' => $request->input('_method', $request->method()),
            'content_type' => $request->header('Content-Type'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
        ]);

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'avatar' => [
                'nullable',
                'file',
                'mimes:jpeg,jpg,png,gif,webp,jfif', // Explicitly allow these formats
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/jfif', // Also check MIME types
                'max:2048', // 2MB max
            ],
        ], [
            'avatar.mimes' => 'Avatar must be a valid image file (JPEG, JPG, PNG, GIF, WEBP, or JFIF).',
            'avatar.mimetypes' => 'Avatar must be a valid image file.',
            'avatar.max' => 'Avatar file size must not exceed 2MB.',
        ]);

        \Log::info('Profile validation passed', ['validated_data' => $data]);

        if (isset($data['first_name'])) {
            $user->first_name = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $user->last_name = $data['last_name'];
        }
        // Handle phone - allow empty string to clear phone number
        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'] ?: null;
        }
        // Handle address - allow empty string to clear address
        if (array_key_exists('address', $data)) {
            $user->address = $data['address'] ?: null;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            try {
                $file = $request->file('avatar');
                
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
                
                // Check file size manually (in case validation didn't catch it)
                $fileSize = $file->getSize(); // Size in bytes
                $maxSize = 2048 * 1024; // 2MB in bytes
                
                if ($fileSize > $maxSize) {
                    return response()->json([
                        'message' => 'File size (' . round($fileSize / 1024, 2) . ' KB) exceeds the maximum allowed size of 2MB.',
                    ], 422);
                }
                
                // Validate it's actually an image by checking MIME type
                $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/jfif'];
                $mimeType = $file->getMimeType();
                
                if (!in_array(strtolower($mimeType), array_map('strtolower', $allowedMimes))) {
                    return response()->json([
                        'message' => 'Invalid file type. Please upload a JPEG, PNG, GIF, WEBP, or JFIF image. Detected type: ' . $mimeType,
                    ], 422);
                }
                
                // Use custom disk to save to glowbook/storage/avatars
                $avatarsPath = base_path('../storage/avatars');
                if (!file_exists($avatarsPath)) {
                    File::makeDirectory($avatarsPath, 0755, true);
                }
                
                // Ensure directory is writable
                if (!is_writable($avatarsPath)) {
                    \Log::error('Avatars directory is not writable', ['path' => $avatarsPath, 'permissions' => substr(sprintf('%o', fileperms($avatarsPath)), -4)]);
                    return response()->json([
                        'message' => 'Server error: Unable to save file. Please contact support.',
                    ], 500);
                }
                
                // Delete old avatar if exists
                if ($user->avatar) {
                    $oldPath = $avatarsPath . '/' . $user->avatar;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath); // Suppress errors if file doesn't exist
                    }
                }

                // Get file extension - handle jfif and other variations
                $extension = strtolower($file->getClientOriginalExtension());
                // Normalize extensions
                if ($extension === 'jfif' || $extension === 'jpe') {
                    $extension = 'jpg'; // Convert to jpg for consistency
                }
                
                $filename = time() . '_' . $user->id . '.' . $extension;
                
                // Save directly to glowbook/storage/avatars
                // Use move_uploaded_file for better reliability
                $destinationPath = $avatarsPath . '/' . $filename;
                
                if (!move_uploaded_file($file->getRealPath(), $destinationPath)) {
                    \Log::error('Failed to move uploaded file', [
                        'source' => $file->getRealPath(),
                        'destination' => $destinationPath,
                        'error' => error_get_last(),
                    ]);
                    
                    return response()->json([
                        'message' => 'Failed to save file. Please try again or use a different image.',
                    ], 500);
                }
                
                // Verify file was saved
                if (!file_exists($destinationPath)) {
                    return response()->json([
                        'message' => 'File upload failed. Please try again.',
                    ], 500);
                }
                
                $user->avatar = $filename;
                
                \Log::info('Avatar uploaded successfully', [
                    'filename' => $filename,
                    'size' => $fileSize,
                    'mime_type' => $mimeType,
                ]);
                
            } catch (\Exception $e) {
                \Log::error('Avatar upload exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return response()->json([
                    'message' => 'Upload failed: ' . $e->getMessage(),
                ], 500);
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
