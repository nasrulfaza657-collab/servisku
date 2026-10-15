<?php
require_once 'config/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['role'] = $user['role'];

            if (!empty($_POST['ingat_saya'])) {
                rememberIssue($pdo, (int) $user['id']);
            }

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Username atau password yang kamu masukkan salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Petugas | ServisKu</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --navy: #0b1220;
            --muted: #64748b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
        }

        /* PANEL KIRI */

        .visual-panel {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 42px 52px;
            color: white;
            background:
                radial-gradient(
                    circle at 75% 35%,
                    rgba(37, 99, 235, .35),
                    transparent 35%
                ),
                linear-gradient(145deg, #0b1220, #111e38 60%, #172d53);
        }

        .register-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            min-height: 51px;
            margin-top: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 13px;
            background: #ffffff;
            color: #334155;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: all .2s ease;
        }

        .register-button:hover {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
            transform: translateY(-1px);
        }

        .visual-panel::before {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            right: -180px;
            bottom: -180px;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 50%;
            box-shadow:
                0 0 0 45px rgba(255,255,255,.025),
                0 0 0 90px rgba(255,255,255,.02);
        }

        .brand {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            display: grid;
            place-items: center;
            width: 47px;
            height: 47px;
            border-radius: 15px;
            color: white;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            box-shadow: 0 8px 25px rgba(37,99,235,.35);
            font-size: 23px;
        }

        .brand-name {
            font-size: 23px;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .brand-subtitle {
            margin-top: 2px;
            color: #94a3b8;
            font-size: 11px;
            letter-spacing: 1.4px;
        }

        .visual-content {
            position: relative;
            z-index: 2;
            max-width: 540px;
            margin: 50px auto;
        }

        .visual-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border: 1px solid rgba(147,197,253,.22);
            border-radius: 50px;
            background: rgba(59,130,246,.12);
            color: #bfdbfe;
            font-size: 12px;
            font-weight: 600;
        }

        .visual-content h1 {
            margin: 24px 0 18px;
            font-size: clamp(35px, 4vw, 53px);
            line-height: 1.15;
            letter-spacing: -2px;
            font-weight: 800;
        }

        .visual-content h1 span {
            color: #60a5fa;
        }

        .visual-content > p {
            max-width: 430px;
            color: #b6c4d9;
            font-size: 15px;
            line-height: 1.9;
        }

        .motorcycle-art {
            width: 100%;
            max-width: 480px;
            margin: 24px auto 0;
            filter: drop-shadow(0 25px 35px rgba(0,0,0,.28));
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 25px;
        }

        .feature-item {
            padding: 15px 12px;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 14px;
            background: rgba(255,255,255,.045);
        }

        .feature-icon {
            display: block;
            margin-bottom: 9px;
            font-size: 19px;
        }

        .feature-item strong {
            display: block;
            font-size: 12px;
        }

        .feature-item small {
            display: block;
            margin-top: 5px;
            color: #94a3b8;
            font-size: 10px;
            line-height: 1.5;
        }

        .visual-footer {
            position: relative;
            z-index: 2;
            color: #8191aa;
            font-size: 11px;
        }

        /* PANEL KANAN */

        .form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background:
                radial-gradient(
                    circle at 100% 0%,
                    rgba(219,234,254,.65),
                    transparent 32%
                ),
                #f8fafc;
        }

        .login-card {
            width: 100%;
            max-width: 410px;
        }

        .mobile-brand {
            display: none;
        }

        .form-label {
            margin-bottom: 9px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .login-heading {
            margin-bottom: 10px;
            font-size: 33px;
            font-weight: 800;
            letter-spacing: -1.2px;
        }

        .login-description {
            margin-bottom: 30px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 17px;
            pointer-events: none;
        }

        .form-control {
            height: 53px;
            padding: 12px 46px;
            border: 1px solid #dbe3ef;
            border-radius: 13px;
            background: white;
            font-size: 13px;
            transition: .2s ease;
        }

        .form-control:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 4px rgba(59,130,246,.12);
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            padding: 4px;
            border: 0;
            background: transparent;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 14px 0 18px;
            color: #5b6b86;
            font-size: 13px;
            cursor: pointer;
            user-select: none;
        }

        .remember-row input {
            width: 17px;
            height: 17px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .login-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 53px;
            margin-top: 25px;
            border: none;
            border-radius: 13px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            box-shadow: 0 8px 18px rgba(37,99,235,.20);
            color: white;
            font-size: 14px;
            font-weight: 700;
            transition: .2s ease;
        }

        .login-button:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #2563eb, #1e40af);
            box-shadow: 0 12px 24px rgba(37,99,235,.25);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .security-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            color: #64748b;
            font-size: 11px;
        }

        .security-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
        }

        .login-alert {
            padding: 13px 15px;
            border: 1px solid #fecaca;
            border-radius: 12px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 12px;
        }

        .form-footer {
            margin-top: 35px;
            color: #94a3b8;
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 900px) {
            .login-page {
                grid-template-columns: 1fr;
            }

            .visual-panel {
                display: none;
            }

            .form-panel {
                min-height: 100vh;
                padding: 28px 22px;
            }

            .mobile-brand {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 42px;
            }

            .login-card {
                max-width: 430px;
            }
        }

        @media (max-width: 480px) {
            .form-panel {
                padding: 25px 20px;
            }

            .login-heading {
                font-size: 29px;
            }

            .login-description {
                margin-bottom: 25px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }
    </style>
</head>
<body>

<div class="login-page">

    <!-- PANEL KIRI -->
    <section class="visual-panel">

        <div class="brand">
            <div class="brand-icon">🔧</div>
            <div>
                <div class="brand-name">ServisKu</div>
                <div class="brand-subtitle">MOTORCYCLE SERVICE SYSTEM</div>
            </div>
        </div>

        <div class="visual-content">

            <div class="visual-badge">
                <span>●</span>
                SISTEM MANAJEMEN BENGKEL
            </div>

            <h1>
                Servis Lebih Mudah,<br>
                <span>Pantau Lebih Cepat.</span>
            </h1>

            <p>
                Kelola antrean, pantau proses perbaikan, dan berikan
                informasi status servis yang jelas kepada pelanggan
                dalam satu sistem.
            </p>

            <div class="motorcycle-art">
                <svg viewBox="0 0 500 245"
                     xmlns="http://www.w3.org/2000/svg"
                     role="img"
                     aria-label="Ilustrasi sepeda motor">

                    <defs>
                        <linearGradient id="bikeBlue" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#60a5fa"/>
                            <stop offset="100%" stop-color="#2563eb"/>
                        </linearGradient>
                        <linearGradient id="metal" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#e2e8f0"/>
                            <stop offset="100%" stop-color="#64748b"/>
                        </linearGradient>
                    </defs>

                    <ellipse cx="255" cy="213" rx="190" ry="13"
                             fill="#020617" opacity=".45"/>

                    <!-- Roda -->
                    <g stroke-linecap="round">
                        <circle cx="125" cy="172" r="53"
                                fill="#0f172a" stroke="#64748b" stroke-width="5"/>
                        <circle cx="125" cy="172" r="39"
                                fill="#1e293b" stroke="#94a3b8" stroke-width="2"/>
                        <circle cx="125" cy="172" r="8"
                                fill="url(#metal)"/>
                        <circle cx="382" cy="172" r="53"
                                fill="#0f172a" stroke="#64748b" stroke-width="5"/>
                        <circle cx="382" cy="172" r="39"
                                fill="#1e293b" stroke="#94a3b8" stroke-width="2"/>
                        <circle cx="382" cy="172" r="8"
                                fill="url(#metal)"/>

                        <g stroke="#64748b" stroke-width="2">
                            <path d="M125 133 V211 M86 172 H164 M98 145 L152 199 M98 199 L152 145"/>
                            <path d="M382 133 V211 M343 172 H421 M355 145 L409 199 M355 199 L409 145"/>
                        </g>
                    </g>

                    <!-- Rangka -->
                    <path d="M125 172 L204 107 L278 172 L190 172 L245 111"
                          fill="none" stroke="url(#metal)" stroke-width="10"
                          stroke-linejoin="round"/>

                    <path d="M278 172 L329 99 L382 172"
                          fill="none" stroke="#94a3b8" stroke-width="9"
                          stroke-linejoin="round"/>

                    <!-- Bodi -->
                    <path d="M184 104
                             Q204 80 244 86
                             L288 105
                             L319 135
                             L278 149
                             L224 133
                             L189 129 Z"
                          fill="url(#bikeBlue)"/>

                    <path d="M206 91 Q226 72 254 82 L277 96 L231 103 Z"
                          fill="#bfdbfe"/>

                    <!-- Tangki -->
                    <path d="M228 83 Q244 69 268 82 L291 103
                             L269 116 L236 106 Z"
                          fill="#3b82f6"/>

                    <!-- Jok -->
                    <path d="M173 81 Q205 67 235 82 L229 95
                             L188 99 L165 93 Z"
                          fill="#111827"/>

                    <!-- Setang -->
                    <path d="M316 103 L335 72 L354 73"
                          stroke="#cbd5e1" stroke-width="7"
                          fill="none" stroke-linecap="round"/>

                    <!-- Lampu -->
                    <path d="M321 123 L343 126 L335 139 L313 136 Z"
                          fill="#fef3c7"/>
                    <path d="M339 128 L369 139 L339 137 Z"
                          fill="#fde68a" opacity=".35"/>

                    <!-- Mesin -->
                    <path d="M216 132 L252 128 L273 159
                             L243 174 L209 160 Z"
                          fill="#334155" stroke="#94a3b8" stroke-width="3"/>

                    <path d="M228 141 L249 141 M226 149 L253 149 M230 157 L255 157"
                          stroke="#94a3b8" stroke-width="3"/>

                    <!-- Knalpot -->
                    <path d="M246 174 L302 183 L326 176"
                          fill="none" stroke="#cbd5e1"
                          stroke-width="9" stroke-linecap="round"/>

                    <!-- Garis dekorasi -->
                    <path d="M239 111 L276 122"
                          stroke="#bfdbfe" stroke-width="3"
                          stroke-linecap="round"/>

                    <circle cx="290" cy="151" r="5" fill="#60a5fa"/>
                </svg>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <span class="feature-icon">📋</span>
                    <strong>Data Servis</strong>
                    <small>Catat keluhan pelanggan</small>
                </div>

                <div class="feature-item">
                    <span class="feature-icon">⚙️</span>
                    <strong>Status Real-Time*</strong>
                    <small>Pantau progres perbaikan</small>
                </div>

                <div class="feature-item">
                    <span class="feature-icon">✓</span>
                    <strong>Lebih Teratur</strong>
                    <small>Kelola antrean bengkel</small>
                </div>
            </div>
        </div>

        <div class="visual-footer">
            © <?= date('Y') ?> ServisKu · Sistem Pelacakan Servis Motor
        </div>
    </section>

    <!-- PANEL KANAN -->
    <main class="form-panel">
        <div class="login-card">

            <div class="mobile-brand">
                <div class="brand-icon">🔧</div>
                <div>
                    <div class="brand-name">ServisKu</div>
                    <div class="brand-subtitle">MOTORCYCLE SERVICE SYSTEM</div>
                </div>
            </div>

            <div class="mb-3">
                <span class="badge rounded-pill text-primary bg-primary-subtle px-3 py-2">
                    AREA ADMIN
                </span>
            </div>

            <h1 class="login-heading">Selamat datang 👋</h1>

            <p class="login-description">
                Silakan masuk ke akun Anda untuk mengelola data
                pelanggan dan memantau status servis motor.
            </p>

            <?php if ($error): ?>
                <div class="login-alert mb-4" role="alert">
                    <strong>Login gagal</strong><br>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" autocomplete="on">

                <div class="mb-4">
                    <label for="username" class="form-label">
                        Username
                    </label>

                    <div class="input-wrap">
                        <span class="input-icon">👤</span>
                        <input
                            id="username"
                            class="form-control"
                            type="text"
                            name="username"
                            placeholder="Masukkan username"
                            value="<?= htmlspecialchars(
                                $_POST['username'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            autocomplete="username"
                            required
                            autofocus>
                    </div>
                </div>

                <div class="mb-2">
                    <label for="password" class="form-label">
                        Password
                    </label>

                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input
                            id="password"
                            class="form-control"
                            type="password"
                            name="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required>

                        <button
                            class="password-toggle"
                            type="button"
                            id="togglePassword"
                            aria-label="Tampilkan password">
                            Lihat
                        </button>
                    </div>
                </div>

                <label class="remember-row" for="ingat_saya">
                    <input type="checkbox" id="ingat_saya" name="ingat_saya" value="1"
                           <?= !empty($_POST['ingat_saya']) ? 'checked' : '' ?>>
                    <span>Ingat saya selama <?= REMEMBER_HARI ?> hari di perangkat ini</span>
                </label>

                <button type="submit" class="login-button">
                    <span>Masuk ke Dashboard</span>
                    <span aria-hidden="true">→</span>
                </button>

                <a href="register.php" class="register-button">
                    <span>＋</span>
                    <span>Daftar Akun Baru</span>
                </a>
            </form>

            <div class="security-note">
                <span class="security-dot"></span>
                Login aman untuk admin dan petugas bengkel
            </div>

            <div class="form-footer">
                © <?= date('Y') ?> ServisKu. Dibuat untuk pelayanan
                bengkel yang lebih baik.
            </div>

        </div>
    </main>

</div>

<script>
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');

    toggleButton.addEventListener('click', function () {
        const isPassword = passwordInput.type === 'password';

        passwordInput.type = isPassword ? 'text' : 'password';
        toggleButton.textContent = isPassword ? 'Sembunyi' : 'Lihat';

        toggleButton.setAttribute(
            'aria-label',
            isPassword ? 'Sembunyikan password' : 'Tampilkan password'
        );
    });
</script>

</body>
</html>