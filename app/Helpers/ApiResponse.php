<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a successful JSON response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function error(string $message = 'Error', $errors = null, int $statusCode = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error JSON response.
     *
     * @param mixed $errors
     * @param string $message
     * @return JsonResponse
     */
    public static function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, $errors, 422);
    }

    /**
     * Return a conflict error JSON response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function conflict(string $message = 'Conflict'): JsonResponse
    {
        return self::error($message, null, 409);
    }

    /**
     * Return a not found error JSON response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, null, 404);
    }
}


