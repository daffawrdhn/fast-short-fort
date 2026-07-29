<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Response;
use App\Core\Validator;

class ApiService
{
    public function successResponse(mixed $data, ?array $meta = null): Response
    {
        $response = new Response();
        $body = [
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => $meta,
        ];
        $response->json($body);
        return $response;
    }

    public function createdResponse(mixed $data): Response
    {
        $response = new Response();
        $body = [
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => null,
        ];
        $response->json($body, 201);
        return $response;
    }

    public function noContentResponse(): Response
    {
        $response = new Response();
        $response->status(204);
        return $response;
    }

    public function errorResponse(string $message, int $code = 400, ?string $errorCode = null, mixed $errors = null): Response
    {
        $response = new Response();
        $body = [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $errorCode ?? 'ERROR',
                'message' => $message,
                'errors' => $errors,
            ],
            'meta' => null,
        ];
        $response->json($body, $code);
        return $response;
    }

    public function validationErrorResponse(array $errors): Response
    {
        $response = new Response();
        $body = [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ],
            'meta' => null,
        ];
        $response->json($body, 422);
        return $response;
    }

    public function paginatedResponse(array $data, int $total, int $page, int $perPage): Response
    {
        $pages = (int) ceil($total / max($perPage, 1));
        $meta = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages,
        ];
        return $this->successResponse($data, $meta);
    }

    public function validateRequest(array $data, array $rules): ?Response
    {
        $validator = new Validator();
        $errors = $validator->validate($data, $rules);
        if (!empty($errors)) {
            return $this->validationErrorResponse($errors);
        }
        return null;
    }
}
