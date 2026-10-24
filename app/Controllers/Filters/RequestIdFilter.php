<?php

namespace App\Controllers\Filters;

use App\Libraries\RequestContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Adds X-Request-Id to every response so a user-reported reference finds its log lines. */
class RequestIdFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        RequestContext::id();
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response->setHeader('X-Request-Id', RequestContext::id());
    }
}
