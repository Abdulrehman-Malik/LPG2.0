<?php

namespace App\Controllers\Filters;

use App\Libraries\RequestContext;
use App\Libraries\Uuid;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

/**
 * Idempotency-Key for every authenticated API POST (BL-019). Runs after api-auth. The first
 * request under a key is recorded in api_idempotency_keys and a successful (2xx/3xx) response
 * stored; a repeat with the same route and body gets the stored response (header
 * Idempotent-Replay: true), a different request under the same key gets 409. An error response
 * releases the key. Without the header nothing changes.
 *
 * Skipped: auth/* (tokens must never be replayed) and POST customer/orders, which keeps its own
 * order-level idempotency (CustomerOrderService::create).
 */
class ApiIdempotencyFilter implements FilterInterface
{
    /** A request recorded as in progress longer than this is taken over by a retry. */
    private const STALE_SECONDS = 120;

    /** @var array{where:array<string,mixed>}|null the key this request owns */
    private static ?array $claim = null;

    public function before(RequestInterface $request, $arguments = null)
    {
        self::$claim = null;
        if (strtoupper($request->getMethod()) !== 'POST' || self::skipped(uri_string())) {
            return null;
        }
        $key = trim($request->getHeaderLine('Idempotency-Key'));
        if ($key === '') {
            // BL-029: a client-named record (body uuid) is its own idempotency key.
            $uuid = self::bodyUuid($request);
            if ($uuid === null) {
                return null;
            }
            $key = 'uuid:' . $uuid;
        }
        $principal = RequestContext::apiPrincipal();
        if (!$principal) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            return self::error(422, 'INVALID_IDEMPOTENCY_KEY', 'Idempotency-Key must be 8–100 letters, digits or . _ : -');
        }

        $db = Database::connect();
        $route = 'POST ' . uri_string();
        $form = $request instanceof IncomingRequest ? $request->getPost() : [];
        $fingerprint = hash('sha256', $route . "\n" . (string) $request->getBody() . "\n" . json_encode($form));
        $where = ['principal_type' => $principal['audience'], 'principal_id' => $principal['id'], 'idempotency_key' => $key];
        $table = static fn () => $db->table('api_idempotency_keys');

        $existing = $table()->where($where)->get()->getRowArray();
        if ($existing && $existing['status'] !== 'completed'
            && strtotime((string) $existing['created_at']) < time() - self::STALE_SECONDS
            && hash_equals((string) $existing['request_fingerprint'], $fingerprint)) {
            // The earlier attempt died without a response; let this retry run it again.
            $table()->where('id', (int) $existing['id'])->where('status', $existing['status'])->delete();
            $existing = null;
        }
        if (!$existing) {
            $table()->ignore(true)->insert($where + [
                'route' => substr($route, 0, 190), 'request_fingerprint' => $fingerprint, 'status' => 'in_progress',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($db->affectedRows() === 1) {
                self::$claim = ['where' => $where];
                return null;
            }
            $existing = $table()->where($where)->get()->getRowArray();
        }

        if (!$existing || !hash_equals((string) $existing['request_fingerprint'], $fingerprint)) {
            return self::error(409, 'IDEMPOTENCY_KEY_REUSED', 'This Idempotency-Key was already used for a different request.');
        }
        if ($existing['status'] !== 'completed') {
            return self::error(409, 'REQUEST_IN_PROGRESS', 'The same request is still being processed. Retry shortly.');
        }
        return service('response')->setStatusCode((int) $existing['response_status'])
            ->setHeader('Idempotent-Replay', 'true')
            ->setContentType('application/json')
            ->setBody((string) $existing['response_body']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (self::$claim === null) {
            return null;
        }
        $where = self::$claim['where'];
        self::$claim = null;
        $table = Database::connect()->table('api_idempotency_keys')->where($where);
        if ($response->getStatusCode() >= 400) {
            // Postings are atomic, so a rejected or failed request wrote nothing: release the key
            // so the client can correct the problem (e.g. open the cash session) and retry with it.
            $table->delete();
        } else {
            $table->update(['status' => 'completed', 'response_status' => $response->getStatusCode(), 'response_body' => (string) $response->getBody()]);
        }
        return null;
    }

    /** The valid uuid field of a JSON or form body, lower-cased; null when absent or invalid. */
    private static function bodyUuid(RequestInterface $request): ?string
    {
        if (!$request instanceof IncomingRequest) {
            return null;
        }
        try {
            $json = $request->getJSON(true);
        } catch (\Throwable $e) {
            $json = null;
        }
        $value = is_array($json) ? ($json['uuid'] ?? null) : $request->getPost('uuid');
        return is_string($value) && Uuid::isValid($value) ? strtolower($value) : null;
    }

    private static function skipped(string $uri): bool
    {
        $uri = trim($uri, '/');
        return (bool) preg_match('#^api/v1/(customer|staff)/auth/#', $uri) || $uri === 'api/v1/customer/orders';
    }

    private static function error(int $status, string $code, string $message): ResponseInterface
    {
        return service('response')->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }
}
