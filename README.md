# Kunci SSO PHP SDK

SDK PHP Resmi untuk integrasi dengan Single Sign-On (SSO) Kunci (OAuth 2.0 PKCE).

## Fitur

- Pembuatan PKCE S256 (`code_verifier` & `code_challenge`) secara otomatis.
- Validasi state parameter untuk proteksi CSRF.
- Penukaran authorization code dengan profil user via backend-to-backend API.
- Support integrasi native PHP maupun Laravel (Auto-discovery, ServiceProvider, & Config).

## Instalasi

Instal package menggunakan Composer:

```bash
composer require kunci/sso-sdk-php
```

## Integrasi Laravel

### 1. Publikasikan Konfigurasi

Jalankan perintah Artisan berikut untuk menyalin file konfigurasi `kunci-sso.php`:

```bash
php artisan vendor:publish --tag=kunci-sso-config
```

### 2. Konfigurasi Environment (`.env`)

Tambahkan variabel berikut ke file `.env` proyek Anda:

```env
KUNCI_SSO_CLIENT_ID=your-client-id
KUNCI_SSO_CLIENT_SECRET=your-client-secret
KUNCI_SSO_REDIRECT_URI=https://your-school.sch.id/oauth/callback
KUNCI_SSO_CENTRAL_URL=https://kunci.co.id
KUNCI_SSO_PORTAL_URL=https://kunci.co.id/portal
```

### 3. Contoh Penggunaan di Controller

`KunciSSOClient` terdaftar secara otomatis di Service Container Laravel sebagai Singleton. Anda dapat langsung menginjeksikannya ke method Controller Anda.

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Kunci\SSO\KunciSSOClient;

class SSOClientController extends Controller
{
    /**
     * Mengarahkan user ke portal otorisasi pusat.
     */
    public function redirect(KunciSSOClient $sso)
    {
        $state = Str::random(40);
        $pkce = $sso->generatePKCE();

        session([
            'sso_state' => $state,
            'sso_code_verifier' => $pkce['code_verifier']
        ]);

        return redirect($sso->getAuthorizationUrl($state, $pkce['code_challenge']));
    }

    /**
     * Menangani callback setelah otorisasi berhasil.
     */
    public function callback(Request $request, KunciSSOClient $sso)
    {
        // 1. Validasi State (CSRF Protection)
        if (!$sso->validateState(session('sso_state'), $request->query('state'))) {
            abort(403, 'State tidak valid.');
        }

        $code = $request->query('code');
        $codeVerifier = session('sso_code_verifier');

        if (!$code || !$codeVerifier) {
            abort(400, 'Parameter tidak lengkap atau kedaluwarsa.');
        }

        // Hapus session key setelah dibaca
        session()->forget(['sso_state', 'sso_code_verifier']);

        try {
            // 2. Tukar Code dengan Profil User Pusat
            $user = $sso->exchangeCodeForUser($code, $codeVerifier);
            
            // $user berisi: ['id' => '...', 'name' => '...', 'email' => '...', 'roles' => [...]]
            // Lakukan login lokal berdasarkan email $user['email']
            
        } catch (\Throwable $e) {
            return redirect('/login')->withErrors(['sso' => 'Gagal login SSO: ' . $e->getMessage()]);
        }
    }
}
```

---

## Penggunaan Native PHP (Non-Laravel)

Jika Anda tidak menggunakan Laravel, Anda dapat menginstansiasi SDK secara manual:

```php
use Kunci\SSO\KunciSSOClient;

$sso = new KunciSSOClient([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://your-school.sch.id/oauth/callback',
    'central_url' => 'https://kunci.co.id',        // opsional
    'portal_url' => 'https://kunci.co.id/portal',  // opsional
]);

// Arahkan ke Halaman Login
$pkce = $sso->generatePKCE();
$_SESSION['sso_state'] = $state = bin2hex(random_bytes(20));
$_SESSION['sso_code_verifier'] = $pkce['code_verifier'];

header('Location: ' . $sso->getAuthorizationUrl($state, $pkce['code_challenge']));
exit;

// Tukar Token pada Callback
if ($sso->validateState($_SESSION['sso_state'], $_GET['state'])) {
    $user = $sso->exchangeCodeForUser($_GET['code'], $_SESSION['sso_code_verifier']);
    // Login berhasil
}
```

## Lisensi

MIT License.
