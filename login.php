<?php
// login.php - Professional Login Interface for BroilerGuard (with Poultry Background)
session_start();

// Authentication Check
$isAdminLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle Admin Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Demo credentials - in production, verify against database with hashed password
    if ($username === 'admin' && $password === 'broilerguard2025') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        
        // Remember me functionality
        if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['remember_token'] = $token;
            setcookie('broilerguard_token', $token, time() + (86400 * 30), "/", "", false, true);
        }
        
        header('Location: dashboard.php');
        exit;
    } else {
        $loginError = "Invalid username or password. Please try again.";
        error_log("Failed login attempt for username: " . $username);
    }
}

// If already logged in, redirect to dashboard
if ($isAdminLoggedIn) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>BroilerGuard | IoT-Based Environmental Monitoring and Automation System</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="BroilerGuard - IoT-based environmental monitoring and automation system for broiler chickens in small-scale tunnel-ventilated houses.">
    <meta name="author" content="BroilerGuard">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #FFFCF2 0%, #FFF8E0 30%, #FFF3CC 60%, #FFE699 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
        }

        /* Decorative background elements */
        .bg-decoration {
            position: fixed;
            border-radius: 50%;
            background: rgba(255, 214, 46, 0.08);
            z-index: 0;
        }
        .bg-circle-1 {
            width: 500px;
            height: 500px;
            top: -200px;
            right: -150px;
        }
        .bg-circle-2 {
            width: 400px;
            height: 400px;
            bottom: -150px;
            left: -100px;
        }
        .bg-circle-3 {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 60%;
        }

        /* Floating feathers decoration */
        .floating-feather {
            position: fixed;
            color: rgba(230, 184, 0, 0.15);
            font-size: 2rem;
            animation: floatFeather 8s infinite ease-in-out;
            z-index: 0;
        }
        .feather-1 { top: 10%; left: 10%; animation-delay: 0s; }
        .feather-2 { top: 20%; right: 15%; animation-delay: 2s; font-size: 1.5rem; }
        .feather-3 { bottom: 15%; left: 20%; animation-delay: 4s; font-size: 2.5rem; }
        .feather-4 { bottom: 25%; right: 10%; animation-delay: 6s; font-size: 1.8rem; }

        @keyframes floatFeather {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-20px) rotate(5deg); }
            50% { transform: translateY(-10px) rotate(-3deg); }
            75% { transform: translateY(-25px) rotate(2deg); }
        }

        /* Main container - PERFECTLY CENTERED */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1100px;
            margin: auto;
        }

        /* Split layout card */
        .login-card {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            background: #FFFCF2;
            border-radius: 2rem;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(139, 115, 30, 0.25);
            border: 1px solid rgba(255, 214, 46, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            box-shadow: 0 35px 60px -15px rgba(139, 115, 30, 0.3);
        }

        /* Left side - Branding with Poultry Background Image */
        .login-brand {
            position: relative;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.5));
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-right: 1px solid rgba(255, 214, 46, 0.3);
            overflow: hidden;
            min-height: 500px;
        }
        
        /* Poultry background image */
        .login-brand::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('https://media.gettyimages.com/id/1315387062/photo/photo-by-lauren-a-littlemarch-29-2011chicks-at-raifsnyders-ag-center-raifsnyders-ag-center-in.jpg?s=612x612&w=0&k=20&c=Fq2aDbzeplCnvqrBuQa_J0sFOCwmcGYXkYTAWj8vNrY=');
            background-size: cover;
            background-position: center;
            opacity: 0.85;
            z-index: 0;
        }
        
        /* Overlay gradient for better text readability */
        .login-brand::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.65), rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.6));
            z-index: 1;
        }
        
        /* All content in login-brand should be above the overlay */
        .login-brand > * {
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            margin-bottom: 2rem;
        }

        .brand-logo .logo-icon {
            width: 65px;
            height: 65px;
            background: rgba(255, 214, 46, 0.9);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.2rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(4px);
        }

        .brand-logo .logo-icon i {
            font-size: 2rem;
            color: #3E2C1C;
        }

        .brand-logo h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: -0.5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .brand-tagline h2 {
            color: #FFD62E;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 0.8rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        .brand-tagline p {
            color: #F5F0E0;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        .features-list {
            list-style: none;
            margin-top: 0.5rem;
        }

        .features-list li {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: #F5F0E0;
            font-size: 0.85rem;
            margin-bottom: 0.7rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        .features-list li i {
            color: #FFD62E;
            font-size: 0.9rem;
            width: 20px;
        }

        /* Right side - Login Form */
        .login-form-section {
            padding: 2.5rem;
            background: #FFFCF2;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 1.8rem;
        }

        .form-header h3 {
            color: #3E2C1C;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .form-header p {
            color: #8B7355;
            font-size: 0.85rem;
        }

        /* Back to home button - Smaller and cleaner */
        .back-home-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            border: none;
            color: #B38F00;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-bottom: 1rem;
            width: fit-content;
        }

        .back-home-btn:hover {
            background: rgba(230, 184, 0, 0.08);
            color: #CC9A00;
            transform: translateX(-2px);
        }

        .back-home-btn i {
            font-size: 0.8rem;
        }

        /* Form styles */
        .input-group {
            margin-bottom: 1.3rem;
        }

        .input-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #5C4A1E;
            margin-bottom: 0.5rem;
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i.input-icon {
            position: absolute;
            left: 1rem;
            color: #B38F00;
            font-size: 1rem;
            z-index: 5;
        }

        .input-wrapper input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border: 1.5px solid rgba(255, 214, 46, 0.4);
            border-radius: 0.8rem;
            background: #FFFCF2;
            font-size: 0.9rem;
            color: #3E2C1C;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #FFD62E;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(255, 214, 46, 0.1);
        }

        .input-wrapper input::placeholder {
            color: #C4B5A0;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: #B38F00;
            cursor: pointer;
            font-size: 1rem;
            z-index: 5;
            padding: 0.2rem;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: #CC9A00;
        }

        /* Form options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #FFD62E;
            cursor: pointer;
        }

        .checkbox-wrapper span {
            font-size: 0.8rem;
            color: #5C4A1E;
            cursor: pointer;
            font-weight: 500;
        }

        .forgot-link {
            font-size: 0.8rem;
            color: #B38F00;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        .forgot-link:hover {
            color: #CC9A00;
            text-decoration: underline;
        }

        /* Login button */
        .btn-login {
            width: 100%;
            background: linear-gradient(105deg, #E6B800 0%, #FFD62E 100%);
            border: none;
            color: #3E2C1C;
            font-weight: 700;
            padding: 0.85rem;
            border-radius: 0.8rem;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(105deg, #CC9A00 0%, #E6B800 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(230, 184, 0, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Error message */
        .error-message {
            background: #FDE8E8;
            color: #C0392B;
            padding: 0.8rem 1rem;
            border-radius: 0.8rem;
            font-size: 0.8rem;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border: 1px solid #F5C6C6;
        }

        .error-message i {
            color: #E74C3C;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #8B7355;
            font-size: 0.7rem;
        }

        .login-footer a {
            color: #B38F00;
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .login-card {
                grid-template-columns: 1fr;
                max-width: 500px;
                margin: 0 auto;
            }
            .login-brand {
                padding: 2rem;
                text-align: center;
                border-right: none;
                border-bottom: 1px solid rgba(255, 214, 46, 0.3);
                min-height: auto;
            }
            .brand-logo .logo-icon {
                margin: 0 auto 1rem;
            }
            .features-list {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.8rem;
            }
            .features-list li {
                font-size: 0.75rem;
            }
            .login-form-section {
                padding: 2rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            .login-brand {
                padding: 1.5rem;
            }
            .login-form-section {
                padding: 1.5rem;
            }
            .brand-tagline h2 {
                font-size: 1.2rem;
            }
            .form-header h3 {
                font-size: 1.2rem;
            }
            .features-list li {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>

    <!-- Background decorations -->
    <div class="bg-decoration bg-circle-1"></div>
    <div class="bg-decoration bg-circle-2"></div>
    <div class="bg-decoration bg-circle-3"></div>
    
    <!-- Floating feathers -->
    <div class="floating-feather feather-1"><i class="fas fa-feather-alt"></i></div>
    <div class="floating-feather feather-2"><i class="fas fa-feather-alt"></i></div>
    <div class="floating-feather feather-3"><i class="fas fa-feather-alt"></i></div>
    <div class="floating-feather feather-4"><i class="fas fa-feather-alt"></i></div>

    <!-- Login Container - PERFECTLY CENTERED -->
    <div class="login-container">
        <div class="login-card">
            
            <!-- Left Side - Branding Section with Poultry Background -->
            <div class="login-brand">
                <div class="brand-logo">
                    <div class="logo-icon">
                        <i class="fas fa-feather-alt"></i>
                    </div>
                    <h1>BroilerGuard</h1>
                </div>
                <div class="brand-tagline">
                    <h2>IoT-Based Environmental<br>Monitoring & Automation System</h2>
                    <p>For broiler chickens in small-scale tunnel-ventilated houses with real-time data collection, AI health detection, and automated control.</p>
                </div>
                <ul class="features-list">
                    <li><i class="fas fa-chart-line"></i> Real-Time Environmental Monitoring</li>
                    <li><i class="fas fa-brain"></i> AI-Powered Health Detection</li>
                    <li><i class="fas fa-robot"></i> Automated Feeding & Watering</li>
                    <li><i class="fas fa-tachometer-alt"></i> Smart Ventilation Control</li>
                    <li><i class="fas fa-mobile-alt"></i> Remote Access & Alerts</li>
                </ul>
            </div>

            <!-- Right Side - Login Form -->
            <div class="login-form-section">
                <!-- Back to Homepage Button - Smaller and cleaner -->
                <a href="index.php" class="back-home-btn">
                    <i class="fas fa-arrow-left"></i> Back to Homepage
                </a>

                <div class="form-header">
                    <h3>Welcome Back</h3>
                    <p>Sign in to access your poultry farm dashboard</p>
                </div>

                <?php if (isset($loginError)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($loginError); ?></span>
                    </div>
                <?php endif; ?>

                <form class="login-form" method="POST" action="">
                    <input type="hidden" name="admin_login" value="1">
                    
                    <div class="input-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Enter your username" 
                                   required 
                                   autocomplete="username"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Enter your password" 
                                   required 
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-arrow-right-to-bracket"></i> Sign In
                    </button>
                </form>

                <div class="login-footer">
                    <p>© 2026 BroilerGuard. All rights reserved. | <a href="#">Privacy Policy</a></p>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }

        // Auto-focus on username field
        document.getElementById('username').focus();

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Add loading effect on form submit
        const form = document.querySelector('.login-form');
        const submitBtn = document.querySelector('.btn-login');
        
        if (form) {
            form.addEventListener('submit', function() {
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Signing in...';
                    submitBtn.disabled = true;
                }
            });
        }
    </script>
</body>
</html>