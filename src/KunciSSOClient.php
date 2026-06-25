<?php

namespace Kunci\SSO;

use Exception;

class KunciSSOClient
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $centralUrl;
    private string $portalUrl;

    /**
     * KunciSSOClient constructor.
     *
     * @param array $config
     * @throws Exception
     */
    public function __construct(array $config)
    {
        if (empty($config['client_id'])) {
            throw new Exception('client_id is required');
        }
        if (empty($config['client_secret'])) {
            throw new Exception('client_secret is required');
        }

        $redirect = $config['redirect_uri'] ?? $config['redirect'] ?? null;
        if (empty($redirect)) {
            throw new Exception('redirect or redirect_uri is required');
        }

        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->redirectUri = $redirect;
        $this->centralUrl = rtrim($config['central_url'] ?? 'https://kunci.co.id', '/');
        $this->portalUrl = rtrim($config['portal_url'] ?? 'https://kunci.co.id/portal', '/');
    }

    /**
     * Generate code_verifier and code_challenge for PKCE S256.
     *
     * @return array ['code_verifier' => string, 'code_challenge' => string]
     */
    public function generatePKCE(): array
    {
        // 1. Generate code_verifier (random string, min 43 chars, max 128 chars)
        $randomBytes = random_bytes(64);
        $codeVerifier = bin2hex($randomBytes);

        // 2. Compute PKCE challenge (S256 base64url)
        $hash = hash('sha256', $codeVerifier, true);
        $codeChallenge = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return [
            'code_verifier' => $codeVerifier,
            'code_challenge' => $codeChallenge
        ];
    }

    /**
     * Get authorization URL for redirecting users.
     *
     * @param string $state
     * @param string $codeChallenge
     * @param array $scopes
     * @return string
     */
    public function getAuthorizationUrl(string $state, string $codeChallenge, array $scopes = ['profile', 'email']): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);

        return $this->portalUrl . '/oauth/authorize?' . $query;
    }

    /**
     * Exchange auth code and verifier for central user details.
     *
     * @param string $code
     * @param string $codeVerifier
     * @return array
     * @throws Exception
     */
    public function exchangeCodeForUser(string $code, string $codeVerifier): array
    {
        $url = $this->centralUrl . '/sso/exchange';
        $payload = json_encode([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $this->redirectUri,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $message = $data['message'] ?? 'Failed to exchange authorization code';
            throw new Exception($message, $httpCode);
        }

        if (empty($data['user'])) {
            throw new Exception('Invalid response format: user profile missing');
        }

        return $data['user'];
    }

    /**
     * Validate state to prevent CSRF attacks.
     *
     * @param string|null $sessionState
     * @param string|null $receivedState
     * @return bool
     */
    public function validateState(?string $sessionState, ?string $receivedState): bool
    {
        if (empty($sessionState) || empty($receivedState)) {
            return false;
        }
        return hash_equals($sessionState, $receivedState);
    }
}
