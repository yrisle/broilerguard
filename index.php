<?php
// index.php - Homepage with New Color Palette (No Footer, Hidden Scrollbar)
session_start();

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'broilerguard');

// --- Authentication Check ---
$isAdminLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle Admin Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get current page parameter
$page = $_GET['page'] ?? 'home';

// Include admin dashboard if requested and logged in
if ($page === 'admin') {
    if (!$isAdminLoggedIn) {
        header('Location: login.php');
        exit;
    }
    $dashboardFile = __DIR__ . '/dashboard.php';
    if (file_exists($dashboardFile)) {
        include $dashboardFile;
    } else {
        echo '<div style="padding: 2rem; font-family: Arial, sans-serif;">Admin dashboard is currently unavailable.</div>';
    }
    exit;
}

// Array of dashboard images with their details
$dashboardSlides = [
    ['img' => 'dashboard-analytics.png', 'title' => 'Analytics Dashboard', 'icon' => 'fa-chart-line', 'desc' => 'Analytics & Reports · Export · Real-time'],
    ['img' => 'dashboard-monitoring.png', 'title' => 'Live Monitoring', 'icon' => 'fa-tachometer-alt', 'desc' => 'Real-Time Monitoring · Live Sensors'],
    ['img' => 'dashboard-ai-detection.png', 'title' => 'AI Detection', 'icon' => 'fa-brain', 'desc' => 'AI Health Detection · Predictive Analytics'],
    ['img' => 'dashboard-automation.png', 'title' => 'Automation Controls', 'icon' => 'fa-sliders-h', 'desc' => 'Automation Controls · Remote Management'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>BroilerGuard | Smart Poultry Management System</title>
    <meta name="description" content="Revolutionize your poultry farm with AI-powered monitoring, automated feeding systems, and real-time analytics.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ============================================
           DIRECT CSS VARIABLES (FALLBACK)
           ============================================ */
        :root {
            --bg-primary: #F5F5F5;
            --bg-secondary: #E8F0E8;
            --bg-card: #FFFFFF;
            --text-primary: #2C3E2C;
            --text-secondary: #4D724D;
            --text-muted: #6B8A6B;
            --accent: #8DB48E;
            --accent-dark: #4D724D;
            --accent-light: #D4E8D4;
            --sidebar-bg: #3A5C3A;
            --sidebar-text: #F5F5F5;
            --sidebar-muted: #A8C8A8;
            --green: #4D724D;
            --green-light: #D4E8D4;
            --yellow: #C8A24A;
            --yellow-light: #F4EEDC;
            --red: #A44A3F;
            --red-light: #F6E9E7;
            --blue: #4F6C7A;
            --blue-light: #EAF0F3;
            --orange: #B9772A;
            --orange-light: #F9EFE5;
            --purple: #8E44AD;
            --shadow-sm: 0 2px 8px rgba(77, 114, 77, 0.08);
            --shadow-md: 0 10px 24px rgba(77, 114, 77, 0.12);
            --shadow-soft: 0 16px 34px -18px rgba(36, 60, 45, 0.2);
        }

        /* ============================================
           HIDE SCROLLBAR - KEEP SCROLLING
           ============================================ */
        ::-webkit-scrollbar {
            width: 0;
            height: 0;
            background: transparent;
        }
        * {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-secondary);
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* Navbar - Updated */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(245, 245, 245, 0.95);
            backdrop-filter: blur(16px);
            box-shadow: 0 2px 12px rgba(77, 114, 77, 0.08);
            padding: 0.8rem 2rem;
            border-bottom: 1px solid rgba(141, 180, 142, 0.15);
            transition: all 0.3s ease;
        }
        .navbar.scrolled {
            padding: 0.5rem 2rem;
            background: rgba(245, 245, 245, 0.98);
        }
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            max-width: 1300px;
            margin: 0 auto;
        }
        .logo h2 {
            font-size: 1.6rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
        }
        .logo h2 i { color: var(--accent); }
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-links a {
            text-decoration: none;
            font-weight: 500;
            color: var(--text-secondary);
            transition: 0.2s;
            font-size: 0.95rem;
        }
        .nav-links a:hover { color: var(--accent-dark); }
        .nav-links .admin-login-btn {
            background: linear-gradient(100deg, var(--accent-dark), #3A5C3A);
            color: #FFFFFF !important;
            padding: 0.5rem 1.3rem;
            border-radius: 30px;
            font-weight: 700;
            box-shadow: 0 8px 20px -10px rgba(77, 114, 77, 0.35);
        }
        .mobile-menu {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--accent-dark);
        }

        /* Hero Carousel */
        .hero-carousel {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 700px;
            overflow: hidden;
            margin-top: 0;
        }
        .swiper-hero { width: 100%; height: 100%; }
        .hero-slide {
            position: relative;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
        }
        .hero-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.4) 100%);
            z-index: 1;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 750px;
            padding: 2rem;
        }
        .hero-tag {
            display: inline-block;
            background: rgba(141, 180, 142, 0.92);
            color: #14261D;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            margin-bottom: 1.2rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .hero-content h1 {
            font-size: clamp(2.5rem, 5vw, 4.5rem);
            font-weight: 800;
            color: white;
            margin-bottom: 1.2rem;
            line-height: 1.2;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
        }
        .hero-content h1 span {
            color: var(--accent);
            border-bottom: 3px solid var(--accent);
            display: inline-block;
        }
        .hero-subtitle {
            font-size: 1.15rem;
            color: rgba(255,255,255,0.92);
            margin-bottom: 2rem;
            line-height: 1.6;
            max-width: 600px;
        }
        .hero-buttons { display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn-primary {
            background: linear-gradient(105deg, var(--accent-dark) 0%, #3A5C3A 100%);
            border: none;
            color: #FFFFFF;
            font-weight: 700;
            padding: 0.9rem 2rem;
            border-radius: 50px;
            box-shadow: 0 10px 20px -8px rgba(77, 114, 77, 0.25);
            transition: all 0.3s;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            background: linear-gradient(105deg, #3A5C3A 0%, var(--accent-dark) 100%);
            box-shadow: 0 15px 25px -10px rgba(77, 114, 77, 0.35);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--accent);
            color: white;
            font-weight: 600;
            padding: 0.85rem 1.8rem;
            border-radius: 50px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-outline:hover {
            background: rgba(141, 180, 142, 0.2);
            border-color: var(--accent-light);
            transform: translateY(-3px);
        }

        .hero-carousel .swiper-button-next,
        .hero-carousel .swiper-button-prev {
            color: var(--accent);
            background: rgba(77, 114, 77, 0.42);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            backdrop-filter: blur(4px);
            transition: all 0.3s;
        }
        .hero-carousel .swiper-button-next:hover,
        .hero-carousel .swiper-button-prev:hover {
            background: rgba(141, 180, 142, 0.2);
        }
        .hero-carousel .swiper-button-next:after,
        .hero-carousel .swiper-button-prev:after { font-size: 20px; }
        .hero-carousel .swiper-pagination-bullet {
            background: white;
            opacity: 0.6;
            width: 10px;
            height: 10px;
        }
        .hero-carousel .swiper-pagination-bullet-active {
            background: var(--accent);
            opacity: 1;
            width: 12px;
            height: 12px;
        }

        .swiper-slide-active .hero-content {
            animation: fadeInUp 0.9s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Section Styles */
        .section-padding { padding: 6rem 2rem; }
        .container-custom {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 24px;
        }
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .section-badge {
            display: inline-block;
            background: rgba(141, 180, 142, 0.16);
            color: var(--accent-dark);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.35rem 0.95rem;
            border-radius: 30px;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        .section-header h2 {
            font-size: 2.35rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.8rem;
        }
        .section-header p {
            font-size: 1.02rem;
            color: var(--text-muted);
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* Feature Cards - Updated */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 2rem;
        }
        .feature-card {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 2rem 1.8rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.15);
        }
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-md);
            border-color: var(--accent);
        }
        .feature-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--accent-light), #E8F0E8);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.35rem;
        }
        .feature-icon i {
            font-size: 1.8rem;
            color: var(--accent-dark);
        }
        .feature-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
        }
        .feature-card p {
            color: var(--text-secondary);
            line-height: 1.65;
        }

        /* Steps Section */
        .steps-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
        }
        .step-card {
            background: var(--bg-card);
            border-radius: 24px;
            text-align: center;
            padding: 2rem 1.5rem;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.15);
            flex: 1;
            min-width: 180px;
        }
        .step-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
        .step-number {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--accent-dark), #3A5C3A);
            color: #FFFFFF;
            border-radius: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.4rem;
            margin: 0 auto 1.2rem auto;
        }
        .step-card h4 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        .step-card p {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Dashboard Carousel */
        .dashboard-swiper-container {
            position: relative;
            overflow: hidden;
            padding: 20px 0;
        }
        .dashboard-swiper {
            overflow: visible;
            padding: 10px 0 40px;
        }
        .dashboard-card-slide { padding: 0.5rem; transition: all 0.3s ease; }
        .dashboard-card {
            background: var(--bg-card);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            border: 1px solid rgba(141, 180, 142, 0.15);
            box-shadow: var(--shadow-sm);
        }
        .dashboard-card:hover {
            transform: translateY(-10px);
            border-color: var(--accent);
            box-shadow: var(--shadow-md);
        }
        .dashboard-img-wrapper {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #3A5C3A, var(--accent-dark));
            border-radius: 20px 20px 0 0;
            padding: 1rem;
        }
        .dashboard-img-wrapper img {
            width: 100%;
            border-radius: 16px;
            display: block;
            transition: transform 0.5s ease;
        }
        .dashboard-card:hover .dashboard-img-wrapper img { transform: scale(1.02); }
        .dashboard-card-header {
            padding: 1.2rem 1.5rem;
            background: var(--bg-card);
            border-bottom: 1px solid rgba(141, 180, 142, 0.10);
        }
        .dashboard-card-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0;
        }
        .dashboard-card-header h3 i { color: var(--accent-dark); font-size: 1rem; }
        .dashboard-card-footer {
            padding: 0.8rem 1.5rem 1.2rem;
            background: var(--bg-card);
            text-align: center;
        }
        .dashboard-card-footer span {
            color: var(--text-muted);
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(141, 180, 142, 0.12);
            padding: 0.35rem 0.9rem;
            border-radius: 30px;
        }

        .dashboard-swiper .swiper-button-next,
        .dashboard-swiper .swiper-button-prev {
            color: var(--accent-dark);
            background: var(--bg-card);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.15);
        }
        .dashboard-swiper .swiper-button-next:hover,
        .dashboard-swiper .swiper-button-prev:hover {
            background: var(--accent-light);
            color: var(--accent-dark);
        }
        .dashboard-swiper .swiper-button-next:after,
        .dashboard-swiper .swiper-button-prev:after { font-size: 18px; }
        .dashboard-swiper .swiper-pagination-bullet {
            background: var(--accent-dark);
            opacity: 0.4;
        }
        .dashboard-swiper .swiper-pagination-bullet-active {
            background: var(--accent);
            opacity: 1;
        }

        /* Tech Stack */
        .tech-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1.5rem;
        }
        .tech-card {
            background: var(--bg-card);
            border-radius: 20px;
            text-align: center;
            padding: 1.5rem 1rem;
            transition: all 0.25s;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(141, 180, 142, 0.15);
        }
        .tech-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
            box-shadow: var(--shadow-md);
        }
        .tech-card i {
            font-size: 2rem;
            color: var(--accent-dark);
            margin-bottom: 0.6rem;
            display: block;
        }
        .tech-card p {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Stats Section - Updated */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }
        .stat-item i {
            font-size: 2.5rem;
            color: var(--accent-dark);
        }
        .stat-item h3 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-top: 0.5rem;
            margin-bottom: 0.2rem;
            color: var(--text-primary);
        }
        .stat-item p { color: var(--text-muted); font-weight: 500; }

        /* Utilities */
        .bg-alt { background: var(--bg-secondary); }
        .scroll-reveal {
            opacity: 0;
            transform: translateY(35px);
            transition: all 0.8s ease;
        }
        .scroll-reveal.revealed {
            opacity: 1;
            transform: translateY(0);
        }
        .admin-badge {
            background: var(--accent);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        @media (max-width: 900px) {
            .nav-links {
                display: none;
                flex-direction: column;
                width: 100%;
                background: var(--bg-card);
                padding: 1rem;
                border-radius: 20px;
                margin-top: 1rem;
                border: 1px solid rgba(141,180,142,0.15);
            }
            .nav-links.show { display: flex; }
            .mobile-menu { display: block; }
            .hero-carousel { height: 80vh; min-height: 600px; }
            .hero-content h1 { font-size: 1.8rem; }
            .section-padding { padding: 3rem 1.2rem; }
            .section-header h2 { font-size: 1.8rem; }
            .dashboard-swiper .swiper-button-next,
            .dashboard-swiper .swiper-button-prev { display: none; }
        }
    </style>
</head>
<body>

<!-- NAVIGATION BAR -->
<nav class="navbar" id="navbar">
    <div class="nav-container">
        <div class="logo">
            <h2><i class="fas fa-feather-alt"></i> BroilerGuard</h2>
        </div>
        <div class="mobile-menu" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </div>
        <div class="nav-links" id="navLinks">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#howitworks">How It Works</a>
            <a href="#dashboard">Dashboard</a>
            <a href="#contact">Contact</a>
            <?php if ($isAdminLoggedIn): ?>
                <span class="admin-badge"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="?page=admin" class="admin-login-btn">Dashboard</a>
                <a href="?logout=1" class="admin-login-btn" style="background:#6B8A6B;">Logout</a>
            <?php else: ?>
                <a href="login.php" class="admin-login-btn">Admin Portal</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- HERO CAROUSEL -->
<section id="home" class="hero-carousel">
    <div class="swiper-container swiper-hero">
        <div class="swiper-wrapper">
            <div class="swiper-slide hero-slide" style="background-image: url('https://media.gettyimages.com/id/487197908/photo/chicks-feed-in-a-broiler-house-at-the-chelny-broiler-poultry-farm-operated-by-zao-agrosila.jpg?s=612x612&w=0&k=20&c=FG7aklYEg-9s2j9oPYl5-URKadDPKAKeLtcSdFZGw1Q=');">
                <div class="container-custom hero-content">
                    <span class="hero-tag"><i class="fas fa-microchip"></i> NEXT-GENERATION IOT</span>
                    <h1>Intelligent Environmental <span>Monitoring</span> for Poultry Excellence</h1>
                    <div class="hero-subtitle">Real-time tracking of temperature, humidity, and air quality with AI-driven analytics. Know your farm's condition at a glance, anytime, anywhere.</div>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn-primary">Launch Dashboard <i class="fas fa-arrow-right"></i></a>
                        <button class="btn-outline" onclick="document.getElementById('features').scrollIntoView({behavior:'smooth'})">Discover More</button>
                    </div>
                </div>
            </div>
            <div class="swiper-slide hero-slide" style="background-image: url('https://smartaviculture.com/wp-content/uploads/2018/12/2-5.jpg');">
                <div class="container-custom hero-content">
                    <span class="hero-tag"><i class="fas fa-brain"></i> ARTIFICIAL INTELLIGENCE</span>
                    <h1>Early Disease Detection <span>Powered by AI</span> & Computer Vision</h1>
                    <div class="hero-subtitle">Advanced deep learning algorithms identify health anomalies before symptoms appear. Reduce mortality rates with predictive intelligence.</div>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn-primary">Try AI Demo <i class="fas fa-arrow-right"></i></a>
                        <button class="btn-outline" onclick="document.getElementById('howitworks').scrollIntoView({behavior:'smooth'})">See Technology</button>
                    </div>
                </div>
            </div>
            <div class="swiper-slide hero-slide" style="background-image: url('https://zootecnicainternational.com/wp-content/uploads/2017/07/Poultry-Welfare-VDL-mang-pulcini-broiler-Fittra-696x464.jpg');">
                <div class="container-custom hero-content">
                    <span class="hero-tag"><i class="fas fa-robot"></i> SMART AUTOMATION</span>
                    <h1>Fully Automated <span>Feeding, Watering</span> & Ventilation Systems</h1>
                    <div class="hero-subtitle">Eliminate manual labor by up to 70%. Set schedules, monitor consumption, and let our smart system handle the rest — precision farming at its finest.</div>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn-primary">Start Automation <i class="fas fa-arrow-right"></i></a>
                        <button class="btn-outline" onclick="document.getElementById('features').scrollIntoView({behavior:'smooth'})">Learn More</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
    </div>
</section>

<!-- FEATURES SECTION -->
<section id="features" class="section-padding">
    <div class="container-custom">
        <div class="section-header scroll-reveal">
            <span class="section-badge">Why Choose BroilerGuard</span>
            <h2>Everything You Need for Modern Poultry Farming</h2>
            <p>Engineered for efficiency, designed for results — experience the future of poultry management</p>
        </div>
        <div class="features-grid">
            <div class="feature-card scroll-reveal">
                <div class="feature-icon"><i class="fas fa-temperature-low"></i></div>
                <h3>Environmental Intelligence</h3>
                <p>24/7 monitoring of temperature, humidity, and air quality with instant threshold alerts and historical trend analysis.</p>
            </div>
            <div class="feature-card scroll-reveal">
                <div class="feature-icon"><i class="fas fa-brain"></i></div>
                <h3>AI Health Surveillance</h3>
                <p>Proactive health monitoring using computer vision — detect respiratory issues, abnormal behavior, and early disease markers.</p>
            </div>
            <div class="feature-card scroll-reveal">
                <div class="feature-icon"><i class="fas fa-robot"></i></div>
                <h3>Precision Automation</h3>
                <p>Smart feeding schedules, automated water replenishment, and climate-controlled ventilation — all on autopilot.</p>
            </div>
            <div class="feature-card scroll-reveal">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Data-Driven Insights</h3>
                <p>Comprehensive analytics, exportable reports, and predictive models to optimize your farm's performance and profitability.</p>
            </div>
            <div class="feature-card scroll-reveal">
                <div class="feature-icon"><i class="fas fa-bell"></i></div>
                <h3>Real-Time Alerts</h3>
                <p>Notifications when critical parameters deviate from optimal ranges.</p>
            </div>
            <div class="feature-card scroll-reveal">
                <div class="feature-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                <h3>Cloud-Based Platform</h3>
                <p>Secure, reliable, and accessible from anywhere.</p>
            </div>
        </div>
    </div>
</section>

<!-- DASHBOARD CAROUSEL -->
<section id="dashboard" class="section-padding">
    <div class="container-custom">
        <div class="section-header scroll-reveal">
            <span class="section-badge">Live Previews</span>
            <h2>Powerful Admin Dashboard Interface</h2>
            <p>Everything you need to monitor, analyze, and control your poultry farm — all in one place</p>
        </div>
        <div class="dashboard-swiper-container">
            <div class="swiper-container dashboard-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($dashboardSlides as $slide): ?>
                    <div class="swiper-slide dashboard-card-slide">
                        <div class="dashboard-card">
                            <div class="dashboard-img-wrapper">
                                <?php if (file_exists('images/' . $slide['img'])): ?>
                                    <img src="images/<?php echo $slide['img']; ?>" alt="<?php echo $slide['title']; ?>">
                                <?php else: ?>
                                    <div style="background:linear-gradient(135deg,#3A5C3A,var(--accent-dark));border-radius:20px;padding:60px 20px;text-align:center;min-height:250px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                                        <i class="fas <?php echo $slide['icon']; ?>" style="font-size:48px;color:var(--accent);margin-bottom:15px;"></i>
                                        <p style="color:#E8EDE6;"><?php echo $slide['title']; ?> Preview</p>
                                        <p style="color:#D9E4D7;font-size:11px;margin-top:8px;">Place: images/<?php echo $slide['img']; ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="dashboard-card-header">
                                <h3><i class="fas <?php echo $slide['icon']; ?>"></i> <?php echo $slide['title']; ?></h3>
                            </div>
                            <div class="dashboard-card-footer">
                                <span><i class="fas <?php echo $slide['icon']; ?>"></i> <?php echo $slide['desc']; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS SECTION -->
<section id="howitworks" class="section-padding bg-alt">
    <div class="container-custom">
        <div class="section-header scroll-reveal">
            <span class="section-badge">Seamless Workflow</span>
            <h2>From Sensor to Action in 5 Simple Steps</h2>
            <p>Integrated technology that works together seamlessly to give you complete control</p>
        </div>
        <div class="steps-container">
            <div class="step-card scroll-reveal"><div class="step-number">01</div><h4>Data Collection</h4><p>IoT sensors continuously monitor environmental conditions and chicken behavior</p></div>
            <div class="step-card scroll-reveal"><div class="step-number">02</div><h4>Cloud Transmission</h4><p>ESP32 microcontrollers securely transmit data to the cloud</p></div>
            <div class="step-card scroll-reveal"><div class="step-number">03</div><h4>AI Analysis</h4><p>Machine learning algorithms process data and detect anomalies in real-time</p></div>
            <div class="step-card scroll-reveal"><div class="step-number">04</div><h4>Smart Response</h4><p>Automation triggers activate fans, feeders, and water pumps as needed</p></div>
            <div class="step-card scroll-reveal"><div class="step-number">05</div><h4>Actionable Insights</h4><p>View real-time metrics, receive alerts, and control systems from your dashboard</p></div>
        </div>
    </div>
</section>

<!-- TECHNOLOGY STACK SECTION -->
<section class="section-padding">
    <div class="container-custom">
        <div class="section-header scroll-reveal">
            <span class="section-badge">Built With Excellence</span>
            <h2>Cutting-Edge Technology Stack</h2>
            <p>Industry-leading hardware and software powering the BroilerGuard ecosystem</p>
        </div>
        <div class="tech-grid">
            <div class="tech-card scroll-reveal"><i class="fas fa-microchip"></i><p>ESP32</p><small style="color:#A0B0C8;">Dual-Core MCU</small></div>
            <div class="tech-card scroll-reveal"><i class="fas fa-camera"></i><p>ESP32-CAM</p><small style="color:#A0B0C8;">OV2640 Sensor</small></div>
            <div class="tech-card scroll-reveal"><i class="fas fa-thermometer-half"></i><p>DHT11</p><small style="color:#A0B0C8;">Temp & Humidity</small></div>
            <div class="tech-card scroll-reveal"><i class="fas fa-brain"></i><p>TensorFlow.js</p><small style="color:#A0B0C8;">Machine Learning</small></div>
            <div class="tech-card scroll-reveal"><i class="fas fa-database"></i><p>MySQL</p><small style="color:#A0B0C8;">Data Storage</small></div>
            <div class="tech-card scroll-reveal"><i class="fas fa-code"></i><p>PHP 8.x</p><small style="color:#A0B0C8;">Backend API</small></div>
            <div class="tech-card scroll-reveal"><i class="fab fa-js"></i><p>JavaScript</p><small style="color:#A0B0C8;">Interactive UI</small></div>
            <div class="tech-card scroll-reveal"><i class="fas fa-cloud"></i><p>AWS Cloud</p><small style="color:#A0B0C8;">Scalable Hosting</small></div>
        </div>
    </div>
</section>

<!-- STATS SECTION -->
<section class="section-padding bg-alt">
    <div class="container-custom">
        <div class="stats-grid">
            <div class="stat-item scroll-reveal"><i class="fas fa-chart-line"></i><h3>70%</h3><p>Reduction in Manual Labor</p></div>
            <div class="stat-item scroll-reveal"><i class="fas fa-heartbeat"></i><h3>95%</h3><p>Early Disease Detection Rate</p></div>
            <div class="stat-item scroll-reveal"><i class="fas fa-tachometer-alt"></i><h3>24/7</h3><p>Real-Time Monitoring</p></div>
            <div class="stat-item scroll-reveal"><i class="fas fa-leaf"></i><h3>40%</h3><p>Feed Waste Reduction</p></div>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="section-padding" style="background: linear-gradient(120deg, #E8F0E8, #F5F5F5);">
    <div class="container-custom" style="max-width: 800px; margin: 0 auto; text-align: center;">
        <div class="scroll-reveal">
            <i class="fas fa-shield-alt fa-3x" style="color:var(--accent-dark); margin-bottom: 1rem;"></i>
            <h2 style="font-size: 2rem; margin-bottom: 0.8rem; color:var(--text-primary);">Ready to Transform Your Poultry Farm?</h2>
            <p style="color: var(--text-muted); margin-bottom: 1.8rem;">Join hundreds of farmers who have already upgraded to smart poultry management with BroilerGuard.</p>
            <?php if ($isAdminLoggedIn): ?>
                <a href="?page=admin" class="btn-primary">Go to Dashboard <i class="fas fa-arrow-right"></i></a>
            <?php else: ?>
                <a href="login.php" class="btn-primary">Access Admin Portal <i class="fas fa-arrow-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
    });

    // Mobile menu toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const navLinks = document.getElementById('navLinks');
    if(mobileBtn) mobileBtn.addEventListener('click', () => navLinks.classList.toggle('show'));

    // Smooth scroll for anchor links
    document.querySelectorAll('.nav-links a[href^="#"], .btn-outline, .btn-primary[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if(targetId && targetId !== "#" && targetId !== "javascript:void(0)" && targetId.startsWith('#')) {
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    if(navLinks.classList.contains('show')) navLinks.classList.remove('show');
                }
            }
        });
    });

    // Scroll reveal observer
    const revealElements = document.querySelectorAll('.scroll-reveal');
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if(entry.isIntersecting) entry.target.classList.add('revealed');
        });
    }, { threshold: 0.1 });
    revealElements.forEach(el => revealObserver.observe(el));

    // Initialize Hero Swiper Carousel
    new Swiper('.swiper-hero', {
        slidesPerView: 1, spaceBetween: 0, loop: true, autoplay: { delay: 6000, disableOnInteraction: false },
        pagination: { el: '.swiper-pagination', clickable: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        effect: 'fade', fadeEffect: { crossFade: true }, speed: 1000,
    });

    // Initialize Dashboard Swiper Carousel
    new Swiper('.dashboard-swiper', {
        slidesPerView: 1,
        spaceBetween: 24,
        loop: true,
        autoplay: { delay: 5000, disableOnInteraction: false },
        pagination: { el: '.swiper-pagination', clickable: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        breakpoints: { 768: { slidesPerView: 2 }, 1024: { slidesPerView: 3 } },
        speed: 800,
    });
</script>
</body>
</html>