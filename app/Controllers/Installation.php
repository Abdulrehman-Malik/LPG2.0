<?php

namespace App\Controllers;

use App\Services\InstallationSetupService;
use App\Services\PasswordPolicy;
use CodeIgniter\Controller;

/**
 * First-installation wizard: checks → business → first branch → owner account → review →
 * install. The answers are kept in the session (the owner password only as a hash); the install
 * itself runs as separate short requests (prepare, schema, migrations in batches, finalize) so no
 * request hits a host's time limit, and the browser shows progress.
 */
class Installation extends Controller
{
    private const STEPS = ['check' => 'Checks', 'business' => 'Business', 'branch' => 'First branch', 'owner' => 'Owner account', 'review' => 'Review', 'install' => 'Install'];
    private const SESSION = 'install_wizard';

    public function index()
    {
        $service = new InstallationSetupService();
        if (!$service->enabled()) {
            return redirect()->to(site_url('login'))->with('migration_error', 'Fresh database setup is disabled. Enable FRESH_DATABASE_SETUP in .env only during deployment.');
        }
        $wizard = $this->wizard();
        $step = (string) ($this->request->getGet('step') ?: 'check');
        if (!isset(self::STEPS[$step])) {
            $step = 'check';
        }
        // A step is reachable once every step before it has been completed.
        foreach (['business', 'branch', 'owner'] as $needed) {
            if ($this->stepIndex($step) > $this->stepIndex($needed) && empty($wizard[$needed])) {
                return redirect()->to(site_url('installation?step=' . $needed));
            }
        }
        if ($step === 'install' && empty($wizard['token'])) {
            return redirect()->to(site_url('installation?step=review'));
        }

        $state = null;
        $error = session()->getFlashdata('installation_error');
        try {
            $state = $service->inspect();
        } catch (\Throwable $e) {
            log_message('error', 'Installation status check failed: {error}', ['error' => $e->getMessage()]);
            $error = $error ?: 'The database server could not be reached: ' . $e->getMessage();
        }

        return view('installation/setup', [
            'title' => 'Installation',
            'steps' => self::STEPS,
            'step' => $step,
            'state' => $state,
            'checks' => $step === 'check' ? $this->environmentChecks($state) : [],
            'wizard' => $wizard,
            'error' => $error,
            'database' => (array) config('Database')->default,
        ]);
    }

    public function save(string $step)
    {
        $service = new InstallationSetupService();
        if (!$service->enabled()) {
            return redirect()->to(site_url('login'));
        }
        $wizard = $this->wizard();
        $post = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, (array) $this->request->getPost());
        try {
            switch ($step) {
                case 'business':
                    $wizard['business'] = $this->validated($post, [
                        'name' => [true, 150, null, 'Business name'],
                        'code' => [true, 20, '/^[A-Za-z0-9_-]{2,20}$/', 'Business code (2–20 letters, digits, - or _)'],
                        'city' => [true, 80, null, 'City'],
                        'phone' => [false, 30, null, 'Phone'],
                        'email' => [false, 150, 'email', 'Email'],
                        'address' => [false, 255, null, 'Address'],
                    ]);
                    $wizard['business']['code'] = strtoupper($wizard['business']['code']);
                    break;
                case 'branch':
                    $wizard['branch'] = $this->validated($post, [
                        'name' => [true, 120, null, 'Branch name'],
                        'code' => [true, 20, '/^[A-Za-z0-9_-]{2,20}$/', 'Branch code (2–20 letters, digits, - or _)'],
                        'city' => [true, 80, null, 'City'],
                        'phone' => [false, 30, null, 'Phone'],
                        'address' => [false, 255, null, 'Address'],
                    ]);
                    $wizard['branch']['code'] = strtoupper($wizard['branch']['code']);
                    break;
                case 'owner':
                    $owner = $this->validated($post, [
                        'full_name' => [true, 120, null, 'Full name'],
                        'username' => [true, 60, '/^[A-Za-z0-9._-]{3,60}$/', 'Username (3–60 letters, digits, . _ or -)'],
                        'email' => [false, 150, 'email', 'Email'],
                    ]);
                    $password = (string) ($post['password'] ?? '');
                    if ($password !== (string) ($post['password_confirm'] ?? '')) {
                        throw new \RuntimeException('The two passwords do not match.');
                    }
                    PasswordPolicy::assertAcceptable($password);
                    $owner['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $wizard['owner'] = $owner;
                    break;
                case 'review':
                    foreach (['business', 'branch', 'owner'] as $needed) {
                        if (empty($wizard[$needed])) {
                            return redirect()->to(site_url('installation?step=' . $needed));
                        }
                    }
                    if (empty($post['understand'])) {
                        throw new \RuntimeException('Tick the box to confirm you understand what the installation does.');
                    }
                    $confirm = ['confirm' => (string) ($post['confirm'] ?? ''), 'replace_confirm' => (string) ($post['replace_confirm'] ?? '')];
                    $service->authorize($confirm + ['admin_password' => (string) ($post['admin_password'] ?? '')]);
                    $wizard['confirm'] = $confirm;
                    $wizard['token'] = bin2hex(random_bytes(16));
                    break;
                default:
                    return redirect()->to(site_url('installation'));
            }
        } catch (\Throwable $e) {
            return redirect()->to(site_url('installation?step=' . $step))->withInput()->with('installation_error', $e->getMessage());
        }
        session()->set(self::SESSION, $wizard);
        $keys = array_keys(self::STEPS);
        return redirect()->to(site_url('installation?step=' . $keys[$this->stepIndex($step) + 1]));
    }

    /** One install phase as JSON: prepare | schema | migrate | finalize. */
    public function execute(string $phase)
    {
        $wizard = $this->wizard();
        $token = (string) $this->request->getHeaderLine('X-Install-Token');
        if (empty($wizard['token']) || !hash_equals((string) $wizard['token'], $token)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'This installation session has expired. Start the setup again.']);
        }
        @set_time_limit(300);
        ignore_user_abort(true);
        $service = new InstallationSetupService();
        try {
            switch ($phase) {
                case 'prepare':
                    $result = $service->prepare($wizard['confirm'] + ['admin_password' => ''], true);
                    return $this->response->setJSON(['ok' => true, 'message' => $result['backup'] ? 'Safety backup ' . basename($result['backup']) . ' taken; database ready.' : 'Database ready.']);
                case 'schema':
                    $service->createSchema();
                    return $this->response->setJSON(['ok' => true, 'message' => 'Tables and starting data created.']);
                case 'migrate':
                    $result = $service->migrate(5);
                    return $this->response->setJSON(['ok' => true, 'done' => $result['done'], 'applied' => $result['applied'], 'message' => $result['message']]);
                case 'finalize':
                    $service->finalize(['business' => $wizard['business'], 'branch' => $wizard['branch'], 'owner' => $wizard['owner']]);
                    $username = $wizard['owner']['username'];
                    session()->remove(self::SESSION);
                    session()->setFlashdata('migration_success', 'Installation complete. Sign in as ' . $username . '.');
                    return $this->response->setJSON(['ok' => true, 'message' => 'Business, branch and owner account saved.', 'redirect' => site_url('login')]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Installation phase {phase} failed: {error}', ['phase' => $phase, 'error' => $e->getMessage()]);
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => $e->getMessage()]);
        }
        return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Unknown installation phase.']);
    }

    /** Clears the wizard answers (Start over). */
    public function reset()
    {
        session()->remove(self::SESSION);
        return redirect()->to(site_url('installation'));
    }

    private function wizard(): array
    {
        return (array) (session()->get(self::SESSION) ?? []);
    }

    private function stepIndex(string $step): int
    {
        return (int) array_search($step, array_keys(self::STEPS), true);
    }

    /**
     * @param array<string,array{0:bool,1:int,2:?string,3:string}> $rules field => [required, max length, pattern|'email'|null, label]
     * @return array<string,string>
     */
    private function validated(array $post, array $rules): array
    {
        $out = [];
        foreach ($rules as $field => [$required, $max, $pattern, $label]) {
            $value = (string) ($post[$field] ?? '');
            if ($value === '') {
                if ($required) {
                    throw new \RuntimeException($label . ' is required.');
                }
                $out[$field] = '';
                continue;
            }
            if (mb_strlen($value) > $max) {
                throw new \RuntimeException($label . ' can be at most ' . $max . ' characters.');
            }
            if ($pattern === 'email' ? !filter_var($value, FILTER_VALIDATE_EMAIL) : ($pattern !== null && !preg_match($pattern, $value))) {
                throw new \RuntimeException($label . ' is not valid.');
            }
            $out[$field] = $value;
        }
        return $out;
    }

    /** @return list<array{label:string,ok:bool,detail:string,blocking:bool}> */
    private function environmentChecks(?array $state): array
    {
        $writable = WRITEPATH . 'backups';
        if (!is_dir($writable)) {
            @mkdir($writable, 0775, true);
        }
        return [
            ['label' => 'PHP 8.2 or newer', 'ok' => version_compare(PHP_VERSION, '8.2.0', '>='), 'detail' => 'PHP ' . PHP_VERSION, 'blocking' => true],
            ['label' => 'PHP extensions mysqli, intl, mbstring, json', 'ok' => extension_loaded('mysqli') && extension_loaded('intl') && extension_loaded('mbstring') && extension_loaded('json'), 'detail' => implode(', ', array_filter(['mysqli', 'intl', 'mbstring', 'json'], static fn ($e) => !extension_loaded($e))) ?: 'all loaded', 'blocking' => true],
            ['label' => 'writable/ folder is writable (sessions, logs, backups)', 'ok' => is_writable(WRITEPATH) && is_writable($writable), 'detail' => WRITEPATH, 'blocking' => true],
            ['label' => 'Database server reachable', 'ok' => $state !== null, 'detail' => $state !== null ? 'Connected' : 'Check LPG_DB_HOST, LPG_DB_PORT, LPG_DB_USER and LPG_DB_PASS', 'blocking' => true],
            ['label' => 'Database not installed yet', 'ok' => $state !== null && empty($state['complete']), 'detail' => !empty($state['complete']) ? 'Already installed — continuing replaces it (administrator password required)' : 'Ready for installation', 'blocking' => false],
        ];
    }
}
