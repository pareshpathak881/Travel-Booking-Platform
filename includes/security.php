<?php
declare(strict_types=1);

/**
 * IndiaYatra — Security Utilities
 *
 * Provides: sanitizeInput, generateCsrfToken, verifyCsrfToken,
 *           verifyCsrfOrForbidden, checkRole, requireLogin
 */

// ── sanitizeInput ─────────────────────────────────────────────────────────
/**
 * Trim whitespace and HTML-encode special characters.
 * Safe to use for both output escaping and input sanitisation.
 *
 * @param  string $data Raw user input
 * @return string       Sanitised string
 */
function sanitizeInput(string $data): string
{
    return trim(htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

// ── generateCsrfToken ─────────────────────────────────────────────────────
/**
 * Return the current session's CSRF token, generating one if absent.
 * Tokens are 64-character hex strings (256 bits of entropy).
 *
 * @return string CSRF token
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

// ── verifyCsrfToken ───────────────────────────────────────────────────────
/**
 * Constant-time comparison of the submitted token against the session token.
 * Returns false (does NOT die) — callers decide how to handle failure.
 *
 * @param  string $token Token submitted by the client
 * @return bool          true if tokens match, false otherwise
 */
function verifyCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], $token);
}

// ── verifyCsrfOrForbidden ────────────────────────────────────────────────
/**
 * Verify CSRF token from POST or X-CSRF-Token header.
 * Aborts with HTTP 403 and a JSON message if verification fails.
 */
function verifyCsrfOrForbidden(): void
{
    $token = '';
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    if (!verifyCsrfToken($token)) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Forbidden: CSRF token verification failed."
        ]);
        exit;
    }
}

// ── checkRole ─────────────────────────────────────────────────────────────
/**
 * Enforce that the currently logged-in user has the required role.
 * If not logged in → redirect to login.php.
 * If logged in but wrong role → redirect to index.php.
 * In either failure case the function calls exit().
 *
 * @param  string $requiredRole 'admin' | 'customer'
 * @return void
 */
function checkRole(string $requiredRole): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . _rootPath() . 'login.php');
        exit;
    }

    if (($_SESSION['role'] ?? '') !== $requiredRole) {
        header('Location: ' . _rootPath() . 'index.php');
        exit;
    }
}

// ── requireLogin ──────────────────────────────────────────────────────────
/**
 * Enforce that any user (regardless of role) is logged in.
 * Redirects to login.php and exits if no active session is found.
 *
 * @return void
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . _rootPath() . 'login.php');
        exit;
    }
}

// ── _rootPath (internal helper) ───────────────────────────────────────────
/**
 * Compute the relative URL path back to the project root.
 * Works whether the calling script is in / or /admin/.
 *
 * @return string e.g. '' or '../'
 */
function _rootPath(): string
{
    $scriptDir  = dirname($_SERVER['SCRIPT_FILENAME'] ?? '');
    $docRoot    = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $depth      = substr_count(str_replace('\\', '/', $scriptDir), '/') -
                  substr_count(str_replace('\\', '/', $docRoot), '/');

    return $depth > 0 ? str_repeat('../', $depth) : '';
}
