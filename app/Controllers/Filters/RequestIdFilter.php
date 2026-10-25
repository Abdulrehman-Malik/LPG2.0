<?php

namespace App\Controllers\Filters;

use App\Libraries\RequestContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Adds X-Request-Id to every response so a user-reported reference finds its log lines, and
 * error.request_id to API error bodies.
 */
class RequestIdFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        RequestContext::id();
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // API error bodies carry the same reference as the header (BL-019).
        if ($response->getStatusCode() >= 400 && str_starts_with(trim(uri_string(), '/'), 'api/')
            && str_contains($response->getHeaderLine('Content-Type'), 'json')) {
            $body = json_decode((string) $response->getBody(), true);
            if (is_array($body) && is_array($body['error'] ?? null) && !isset($body['error']['request_id'])) {
                $body['error']['request_id'] = RequestContext::id();
                $response->setJSON($body);
            }
        }
        return $response->setHeader('X-Request-Id', RequestContext::id());
    }
}
