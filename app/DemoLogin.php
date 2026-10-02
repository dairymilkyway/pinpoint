<?php

namespace App;

/**
 * The demo account picker shown on the sign-in page.
 *
 * This is a convenience for reviewers, not an authentication path: picking an
 * account fills the ordinary sign-in form and submits it, so the credentials
 * still have to be correct and the login goes through the same guard as any
 * other. Nothing here bypasses Auth.
 *
 * Two things keep it from becoming a liability: it is off unless
 * DEMO_LOGIN_ENABLED is set, and it reports itself disabled in production no
 * matter what that variable says.
 */
final class DemoLogin
{
    public static function enabled(): bool
    {
        return (bool) config('demo.enabled') && ! app()->isProduction();
    }

    /**
     * The accounts to offer, with the blanks filtered out so a half-configured
     * environment shows a shorter list rather than a broken one.
     *
     * @return list<array{role: string, name: string, email: string, password: string}>
     */
    public static function accounts(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $accounts = [];

        foreach (config('demo.accounts', []) as $account) {
            if (blank($account['email'] ?? null) || blank($account['password'] ?? null)) {
                continue;
            }

            $accounts[] = [
                'role' => (string) $account['role'],
                'name' => (string) $account['name'],
                'email' => (string) $account['email'],
                'password' => (string) $account['password'],
            ];
        }

        return $accounts;
    }
}
