<?php

namespace App\Controllers;

use App\Services\ApiSessionService;
use App\Services\AuditService;
use App\Services\PasswordPolicy;
use CodeIgniter\Controller;
use Config\Database;

/**
 * The signed-in user's own password (BL-003). Accounts flagged `must_change_password` are kept
 * on this page by AuthFilter until they set a new password.
 */
class Account extends Controller
{
    public function password()
    {
        $userId = (int) session()->get('user_id');
        $user = Database::connect()->table('users')->select('must_change_password')->where('id', $userId)->get()->getRowArray();

        return view('account/password', [
            'title' => 'Change Password',
            'forced' => (int) ($user['must_change_password'] ?? 0) === 1,
            'minLength' => PasswordPolicy::minLength(),
        ]);
    }

    public function savePassword()
    {
        $userId = (int) session()->get('user_id');
        $db = Database::connect();
        $user = $db->table('users')->select('id,password_hash,location_id')->where(['id' => $userId, 'is_active' => 1])->get()->getRowArray();
        if (!$user) {
            return redirect()->to(site_url('login'));
        }

        $current = (string) $this->request->getPost('current_password');
        $new = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if (!password_verify($current, (string) $user['password_hash'])) {
            return redirect()->back()->with('error', 'The current password is incorrect.');
        }
        if ($new !== $confirm) {
            return redirect()->back()->with('error', 'The new password and its confirmation do not match.');
        }
        try {
            PasswordPolicy::assertAcceptable($new, (string) $user['password_hash']);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', user_error_message($e));
        }

        $db->table('users')->where('id', $userId)->update([
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);
        (new ApiSessionService())->revokeAllForStaff($userId);
        AuditService::log('PASSWORD_CHANGED', 'user', $userId, null, ['self_service' => true], $userId, (int) (session()->get('location_id') ?? 0) ?: null);
        session()->regenerate(true);

        return redirect()->to(site_url('dashboard'))->with('success', 'Your password has been changed.');
    }
}
