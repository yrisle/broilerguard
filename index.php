<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>BroilerGuard · IoT Poultry System</title>
  <meta name="description" content="IoT monitoring & automation for broiler chickens. Real-time sensing, AI health detection, automated control." />
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <!-- Swiper -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: #FFFCF2;
      color: #2E241A;
      overflow-x: hidden;
      scroll-behavior: smooth;
    }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #FFF8E0; }
    ::-webkit-scrollbar-thumb { background: #E6B800; border-radius: 20px; }

    /* Navbar – with Login button */
    .navbar {
      position: fixed;
      top: 0; width: 100%; z-index: 1000;
      background: rgba(255, 252, 242, 0.92);
      backdrop-filter: blur(12px);
      padding: 0.6rem 2rem;
      border-bottom: 1px solid rgba(230, 184, 0, 0.15);
      transition: all 0.2s;
    }
    .navbar.scrolled { padding: 0.4rem 2rem; background: rgba(255, 252, 242, 0.97); }
    .nav-container {
      display: flex; justify-content: space-between; align-items: center;
      max-width: 1300px; margin: 0 auto;
    }
    .logo h2 {
      font-size: 1.5rem; font-weight: 800;
      background: linear-gradient(135deg, #B38F00, #FFD62E);
      -webkit-background-clip: text; background-clip: text; color: transparent;
      letter-spacing: -0.3px;
    }
    .logo h2 i { color: #FFD62E; margin-right: 4px; }
    .nav-links {
      display: flex; gap: 2rem; align-items: center;
    }
    .nav-links a {
      text-decoration: none; font-weight: 500; color: #2E241A;
      font-size: 0.9rem; transition: 0.2s;
    }
    .nav-links a:hover { color: #B38F00; }
    .nav-links .login-btn {
      background: linear-gradient(105deg, #E6B800, #FFD62E);
      color: #2E241A !important;
      padding: 0.5rem 1.5rem;
      border-radius: 60px;
      font-weight: 700;
      font-size: 0.9rem;
      transition: 0.25s;
      box-shadow: 0 4px 12px rgba(230,184,0,0.25);
    }
    .nav-links .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(230,184,0,0.35);
      background: #CC9A00;
      color: #FFFCF2 !important;
    }
    .mobile-menu { display: none; font-size: 1.5rem; cursor: pointer; }

    /* Hero */
    .hero-carousel {
      position: relative; width: 100%; height: 100vh; min-height: 680px;
      overflow: hidden; margin-top: 0;
    }
    .swiper-hero { width: 100%; height: 100%; }
    .hero-slide {
      position: relative; width: 100%; height: 100%;
      background-size: cover; background-position: center;
      display: flex; align-items: center;
    }
    .hero-slide::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(135deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.3) 100%);
      z-index: 1;
    }
    .hero-content {
      position: relative; z-index: 2; max-width: 700px; padding: 2rem;
    }
    .hero-tag {
      display: inline-block; background: rgba(255, 214, 46, 0.95);
      color: #2E241A; font-size: 0.7rem; font-weight: 700;
      padding: 0.3rem 1rem; border-radius: 30px; letter-spacing: 1px;
      text-transform: uppercase; margin-bottom: 1rem;
    }
    .hero-content h1 {
      font-size: clamp(2.2rem, 4.8vw, 4rem); font-weight: 800;
      color: white; line-height: 1.2; margin-bottom: 1rem;
    }
    .hero-content h1 span { color: #FFD62E; border-bottom: 3px solid #FFD62E; }
    .hero-subtitle {
      font-size: 1rem; color: rgba(255,255,255,0.9);
      margin-bottom: 1.8rem; line-height: 1.5; max-width: 550px;
    }
    .hero-buttons { display: flex; gap: 0.8rem; flex-wrap: wrap; }
    .btn-primary {
      background: linear-gradient(105deg, #E6B800, #FFD62E);
      border: none; color: #2E241A; font-weight: 700;
      padding: 0.8rem 2rem; border-radius: 60px;
      box-shadow: 0 8px 18px -6px rgba(230,184,0,0.4);
      transition: all 0.25s; cursor: pointer; font-size: 0.95rem;
      display: inline-flex; align-items: center; gap: 0.5rem;
      text-decoration: none;
    }
    .btn-primary:hover { transform: translateY(-3px); background: #CC9A00; color: #FFFCF2; }
    .btn-outline {
      background: transparent; border: 2px solid #FFD62E;
      color: white; font-weight: 600; padding: 0.75rem 1.6rem;
      border-radius: 60px; transition: 0.25s; text-decoration: none;
      display: inline-flex; align-items: center; gap: 0.5rem;
    }
    .btn-outline:hover { background: rgba(255,214,46,0.15); border-color: #FFD62E; transform: translateY(-3px); }

    .hero-carousel .swiper-button-next,
    .hero-carousel .swiper-button-prev {
      color: #FFD62E; background: rgba(0,0,0,0.3); width: 44px; height: 44px;
      border-radius: 50%; backdrop-filter: blur(4px);
    }
    .hero-carousel .swiper-button-next:hover,
    .hero-carousel .swiper-button-prev:hover { background: rgba(255,214,46,0.3); }
    .hero-carousel .swiper-pagination-bullet { background: white; opacity: 0.5; width: 10px; height: 10px; }
    .hero-carousel .swiper-pagination-bullet-active { background: #FFD62E; opacity: 1; }

    /* Sections */
    .section-padding { padding: 4rem 2rem; }
    .container-custom { max-width: 1300px; margin: 0 auto; padding: 0 20px; }
    .section-header { text-align: center; margin-bottom: 2.5rem; }
    .section-badge {
      display: inline-block; background: rgba(255, 214, 46, 0.15);
      color: #B38F00; font-size: 0.65rem; font-weight: 700;
      padding: 0.2rem 1rem; border-radius: 30px; letter-spacing: 1px;
      text-transform: uppercase; margin-bottom: 0.6rem;
    }
    .section-header h2 {
      font-size: 2.2rem; font-weight: 800; color: #2E241A;
      margin-bottom: 0.5rem;
    }
    .section-header p {
      font-size: 1rem; color: #5A4A3A; max-width: 620px; margin: 0 auto;
    }

    /* Features – refined, balanced grid, cleaner cards */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.8rem;
    }
    .feature-card {
      background: #FFFCF2;
      border-radius: 28px;
      padding: 1.8rem 1.6rem 1.6rem;
      border: 1px solid rgba(255, 214, 46, 0.15);
      box-shadow: 0 8px 24px -10px rgba(139, 115, 30, 0.06);
      transition: all 0.25s ease;
      display: flex;
      flex-direction: column;
    }
    .feature-card:hover {
      transform: translateY(-6px);
      border-color: rgba(255, 214, 46, 0.5);
      box-shadow: 0 16px 32px -12px rgba(139, 115, 30, 0.12);
    }
    .feature-icon {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #FFF3CC, #FFE699);
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.2rem;
      flex-shrink: 0;
    }
    .feature-icon i {
      font-size: 1.6rem;
      color: #B38F00;
    }
    .feature-card h3 {
      font-size: 1.2rem;
      font-weight: 700;
      color: #2E241A;
      margin-bottom: 0.4rem;
      letter-spacing: -0.2px;
    }
    .feature-card p {
      font-size: 0.95rem;
      line-height: 1.5;
      color: #4F3F2F;
      margin-bottom: 0;
      flex: 1;
    }
    .feature-tag {
      margin-top: 0.8rem;
      font-size: 0.7rem;
      font-weight: 600;
      color: #B38F00;
      background: rgba(255, 214, 46, 0.08);
      display: inline-block;
      padding: 0.15rem 0.9rem;
      border-radius: 30px;
      letter-spacing: 0.3px;
      align-self: flex-start;
    }

    /* Dashboard carousel */
    .dashboard-swiper-container { position: relative; overflow: hidden; padding: 10px 0 30px; }
    .dashboard-swiper { overflow: visible; padding: 10px 0 40px; }
    .dashboard-card-slide { padding: 0.4rem; }
    .dashboard-card {
      background: #FFFCF2; border-radius: 28px; overflow: hidden;
      border: 1px solid rgba(255,214,46,0.2);
      box-shadow: 0 12px 30px -12px rgba(139,115,30,0.1);
      transition: 0.35s;
    }
    .dashboard-card:hover { transform: translateY(-6px); border-color: #FFD62E; }
    .dashboard-img-wrapper {
      background: #1a1a2e; padding: 1rem; border-radius: 24px 24px 0 0;
    }
    .dashboard-img-wrapper img {
      width: 100%; border-radius: 16px; display: block;
      transition: transform 0.4s;
    }
    .dashboard-card:hover .dashboard-img-wrapper img { transform: scale(1.01); }
    .dashboard-card-header {
      padding: 1rem 1.4rem; background: #FFFCF2;
      border-bottom: 1px solid rgba(255,214,46,0.1);
    }
    .dashboard-card-header h3 {
      font-size: 1.1rem; font-weight: 700; color: #2E241A;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .dashboard-card-header h3 i { color: #E6B800; }
    .dashboard-card-footer {
      padding: 0.6rem 1.4rem 1rem; background: #FFFCF2;
      text-align: center;
    }
    .dashboard-card-footer span {
      color: #6B5744; font-size: 0.75rem;
      background: rgba(255,214,46,0.08); padding: 0.2rem 1rem;
      border-radius: 30px; display: inline-block;
    }
    .dashboard-swiper .swiper-button-next,
    .dashboard-swiper .swiper-button-prev {
      color: #E6B800; background: #FFFCF2; width: 40px; height: 40px;
      border-radius: 50%; box-shadow: 0 4px 12px rgba(139,115,30,0.1);
      border: 1px solid rgba(255,214,46,0.2);
    }
    .dashboard-swiper .swiper-button-next:hover,
    .dashboard-swiper .swiper-button-prev:hover { background: #FFD62E; color: #2E241A; }

    /* Steps */
    .steps-container {
      display: flex; flex-wrap: wrap; justify-content: center;
      gap: 1.5rem; margin: 1.5rem 0;
    }
    .step-card {
      background: #FFFCF2; border-radius: 24px; text-align: center;
      padding: 1.6rem 1.2rem; flex: 1; min-width: 150px;
      border: 1px solid rgba(255,214,46,0.12); transition: 0.25s;
    }
    .step-card:hover { transform: translateY(-4px); }
    .step-number {
      width: 48px; height: 48px; background: linear-gradient(135deg, #E6B800, #FFD62E);
      color: #2E241A; border-radius: 60px; display: flex; align-items: center;
      justify-content: center; font-weight: 800; font-size: 1.2rem;
      margin: 0 auto 0.8rem;
    }
    .step-card h4 { font-size: 1rem; font-weight: 700; margin-bottom: 0.2rem; }
    .step-card p { font-size: 0.85rem; color: #5A4A3A; }

    /* Tech */
    .tech-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 1.2rem;
    }
    .tech-card {
      background: #FFFCF2; border-radius: 20px; text-align: center;
      padding: 1.2rem 0.8rem; border: 1px solid rgba(255,214,46,0.1);
      transition: 0.2s;
    }
    .tech-card:hover { border-color: #FFD62E; transform: translateY(-4px); }
    .tech-card i { font-size: 1.8rem; color: #E6B800; display: block; margin-bottom: 0.3rem; }
    .tech-card p { font-size: 0.8rem; font-weight: 500; }

    /* Stats */
    .stats-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 1.8rem; text-align: center;
    }
    .stat-item i { font-size: 2rem; color: #E6B800; }
    .stat-item h3 { font-size: 2rem; font-weight: 800; margin: 0.3rem 0 0; }
    .stat-item p { color: #5A4A3A; font-weight: 500; }
    .stat-item small { color: #8B7A6A; }

    .bg-alt { background: #FFF8E0; }

    /* Footer */
    footer { background: #2E241A; color: #E6DCC8; }
    footer a { color: #FFE699; text-decoration: none; }
    footer a:hover { color: #FFD62E; }
    .footer-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 2rem;
    }
    .footer-brand p { margin-top: 0.5rem; color: #C4B8A2; line-height: 1.5; font-size: 0.9rem; }
    .footer-links ul { list-style: none; padding: 0; margin-top: 0.4rem; }
    .footer-links ul li { margin-bottom: 0.3rem; }
    .footer-bottom {
      margin-top: 2rem; padding-top: 1rem;
      border-top: 1px solid rgba(255,243,204,0.1);
      text-align: center; font-size: 0.8rem; color: #A89880;
    }

    .scroll-reveal { opacity: 0; transform: translateY(25px); transition: all 0.7s ease; }
    .scroll-reveal.revealed { opacity: 1; transform: translateY(0); }

    @media (max-width: 1024px) {
      .features-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 900px) {
      .nav-links { display: none; flex-direction: column; width: 100%; background: #FFFCF2; padding: 0.8rem; border-radius: 20px; margin-top: 0.5rem; border: 1px solid rgba(255,214,46,0.2); }
      .nav-links.show { display: flex; }
      .nav-links .login-btn { align-self: flex-start; }
      .mobile-menu { display: block; }
      .hero-carousel { height: 75vh; min-height: 520px; }
      .hero-content h1 { font-size: 1.8rem; }
      .section-padding { padding: 2.5rem 1rem; }
      .section-header h2 { font-size: 1.8rem; }
      .dashboard-swiper .swiper-button-next,
      .dashboard-swiper .swiper-button-prev { display: none; }
    }
    @media (max-width: 680px) {
      .features-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<!-- NAVBAR – with Login button -->
<nav class="navbar" id="navbar">
  <div class="nav-container">
    <div class="logo"><h2><i class="fas fa-feather-alt"></i> BroilerGuard</h2></div>
    <div class="mobile-menu" id="mobileMenuBtn"><i class="fas fa-bars"></i></div>
    <div class="nav-links" id="navLinks">
      <a href="#home">Home</a>
      <a href="#features">Features</a>
      <a href="#howitworks">How It Works</a>
      <a href="#dashboard">Dashboard</a>
      <a href="#contact">Contact</a>
      <a href="login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
    </div>
  </div>
</nav>

<!-- HERO CAROUSEL -->
<section id="home" class="hero-carousel">
  <div class="swiper-container swiper-hero">
    <div class="swiper-wrapper">
      <div class="swiper-slide hero-slide" style="background-image: url('https://media.gettyimages.com/id/487197908/photo/chicks-feed-in-a-broiler-house-at-the-chelny-broiler-poultry-farm-operated-by-zao-agrosila.jpg?s=612x612&w=0&k=20&c=FG7aklYEg-9s2j9oPYl5-URKadDPKAKeLtcSdFZGw1Q=');">
        <div class="container-custom hero-content">
          <span class="hero-tag"><i class="fas fa-microchip"></i> IoT Monitoring</span>
          <h1>Real-Time <span>Climate & Health</span> for Broilers</h1>
          <div class="hero-subtitle">DHT11 sensors, AI vision, and automated controls in a single platform for tunnel-ventilated poultry houses.</div>
          <div class="hero-buttons">
            <a href="#dashboard" class="btn-primary">Explore Dashboard <i class="fas fa-arrow-right"></i></a>
            <button class="btn-outline" onclick="document.getElementById('features').scrollIntoView({behavior:'smooth'})">Learn More</button>
          </div>
        </div>
      </div>
      <div class="swiper-slide hero-slide" style="background-image: url('https://smartaviculture.com/wp-content/uploads/2018/12/2-5.jpg');">
        <div class="container-custom hero-content">
          <span class="hero-tag"><i class="fas fa-brain"></i> AI Health</span>
          <h1>Early Detection <span>with TensorFlow.js</span></h1>
          <div class="hero-subtitle">ESP32-CAM + Teachable Machine models detect respiratory issues, posture anomalies, and swelling.</div>
          <div class="hero-buttons">
            <a href="#dashboard" class="btn-primary">View AI Analysis <i class="fas fa-arrow-right"></i></a>
            <button class="btn-outline" onclick="document.getElementById('howitworks').scrollIntoView({behavior:'smooth'})">How It Works</button>
          </div>
        </div>
      </div>
      <div class="swiper-slide hero-slide" style="background-image: url('https://zootecnicainternational.com/wp-content/uploads/2017/07/Poultry-Welfare-VDL-mang-pulcini-broiler-Fittra-696x464.jpg');">
        <div class="container-custom hero-content">
          <span class="hero-tag"><i class="fas fa-robot"></i> Smart Automation</span>
          <h1>Feed, Water & <span>Ventilation</span> Controlled</h1>
          <div class="hero-subtitle">Relay modules automate feeders, pumps, and fans – reducing manual work by up to 70%.</div>
          <div class="hero-buttons">
            <a href="#dashboard" class="btn-primary">Start Automation <i class="fas fa-arrow-right"></i></a>
            <button class="btn-outline" onclick="document.getElementById('features').scrollIntoView({behavior:'smooth'})">Features</button>
          </div>
        </div>
      </div>
    </div>
    <div class="swiper-button-next"></div><div class="swiper-button-prev"></div>
    <div class="swiper-pagination"></div>
  </div>
</section>

<!-- FEATURES – refined cards -->
<section id="features" class="section-padding">
  <div class="container-custom">
    <div class="section-header scroll-reveal">
      <span class="section-badge">Why BroilerGuard</span>
      <h2>Smart Poultry Farming, Simplified</h2>
      <p>Integrated sensors, AI, and automation – designed for small-scale tunnel-ventilated houses.</p>
    </div>
    <div class="features-grid">
      <div class="feature-card scroll-reveal">
        <div class="feature-icon"><i class="fas fa-temperature-low"></i></div>
        <h3>Environmental Intelligence</h3>
        <p>DHT11 sensors monitor temp &amp; humidity 24/7. Automated ventilation triggers based on thresholds.</p>
        <span class="feature-tag"><i class="fas fa-arrow-right" style="margin-right:4px;"></i> real-time</span>
      </div>
      <div class="feature-card scroll-reveal">
        <div class="feature-icon"><i class="fas fa-brain"></i></div>
        <h3>AI Health Surveillance</h3>
        <p>ESP32-CAM + TensorFlow.js detect early signs of respiratory distress, posture issues, and swelling.</p>
        <span class="feature-tag"><i class="fas fa-arrow-right" style="margin-right:4px;"></i> early warning</span>
      </div>
      <div class="feature-card scroll-reveal">
        <div class="feature-icon"><i class="fas fa-robot"></i></div>
        <h3>Precision Automation</h3>
        <p>Scheduled feeding, water refill, and tunnel ventilation – all automated to reduce manual labor.</p>
        <span class="feature-tag"><i class="fas fa-arrow-right" style="margin-right:4px;"></i> 70% less labor</span>
      </div>
      <div class="feature-card scroll-reveal">
        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
        <h3>Data-Driven Insights</h3>
        <p>Real-time dashboard with historical trends, exportable reports, and smart alerts.</p>
        <span class="feature-tag"><i class="fas fa-arrow-right" style="margin-right:4px;"></i> analytics</span>
      </div>
      <div class="feature-card scroll-reveal">
        <div class="feature-icon"><i class="fas fa-bell"></i></div>
        <h3>Instant Alerts</h3>
        <p>Email and in-app notifications when environmental parameters exceed safe ranges.</p>
        <span class="feature-tag"><i class="fas fa-arrow-right" style="margin-right:4px;"></i> proactive</span>
      </div>
      <div class="feature-card scroll-reveal">
        <div class="feature-icon"><i class="fas fa-cloud-upload-alt"></i></div>
        <h3>Cloud-Ready Platform</h3>
        <p>Secure MySQL + PHP backend. Access your farm data from anywhere, any device.</p>
        <span class="feature-tag"><i class="fas fa-arrow-right" style="margin-right:4px;"></i> remote access</span>
      </div>
    </div>
  </div>
</section>


<section id="dashboard" class="section-padding">
  <div class="container-custom">
    <div class="section-header scroll-reveal">
      <span class="section-badge">Live Previews</span>
      <h2>Dashboard at a Glance</h2>
      <p>Real-time environmental data, AI health monitoring, and automation controls.</p>
    </div>
    <div class="dashboard-swiper-container">
      <div class="swiper-container dashboard-swiper">
        <div class="swiper-wrapper">
          <?php
          $slides = [
            ['img' => 'dashboard-analytics.png', 'title' => 'Environmental Monitoring', 'icon' => 'fa-chart-line', 'desc' => 'Temp & Humidity · DHT11 · Trends'],
            ['img' => 'dashboard-monitoring.png', 'title' => 'Live Camera Feed', 'icon' => 'fa-tachometer-alt', 'desc' => 'ESP32-CAM · Real-time video'],
            ['img' => 'dashboard-ai-detection.png', 'title' => 'AI Health Detection', 'icon' => 'fa-brain', 'desc' => 'TensorFlow.js · Teachable Machine'],
            ['img' => 'dashboard-automation.png', 'title' => 'Automation Control', 'icon' => 'fa-sliders-h', 'desc' => 'Feeding · Water · Ventilation'],
          ];
          foreach ($slides as $s):
          ?>
          <div class="swiper-slide dashboard-card-slide">
            <div class="dashboard-card">
              <div class="dashboard-img-wrapper">
                <?php if (file_exists('images/' . $s['img'])): ?>
                  <img src="images/<?php echo $s['img']; ?>" alt="<?php echo $s['title']; ?>">
                <?php else: ?>
                  <div style="background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:20px;padding:50px 15px;text-align:center;min-height:200px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <i class="fas <?php echo $s['icon']; ?>" style="font-size:40px;color:#FFD62E;margin-bottom:10px;"></i>
                    <p style="color:#8B9BB4;"><?php echo $s['title']; ?></p>
                    <p style="color:#5C4A1E;font-size:10px;margin-top:6px;">images/<?php echo $s['img']; ?></p>
                  </div>
                <?php endif; ?>
              </div>
              <div class="dashboard-card-header">
                <h3><i class="fas <?php echo $s['icon']; ?>"></i> <?php echo $s['title']; ?></h3>
              </div>
              <div class="dashboard-card-footer">
                <span><i class="fas <?php echo $s['icon']; ?>"></i> <?php echo $s['desc']; ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="swiper-button-next"></div><div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="howitworks" class="section-padding bg-alt">
  <div class="container-custom">
    <div class="section-header scroll-reveal">
      <span class="section-badge">Workflow</span>
      <h2>From Sensor to Action</h2>
      <p>Five steps from data collection to intelligent response.</p>
    </div>
    <div class="steps-container">
      <div class="step-card scroll-reveal"><div class="step-number">01</div><h4>Data Collection</h4><p>DHT11 sensors + ESP32-CAM capture environment & visual data.</p></div>
      <div class="step-card scroll-reveal"><div class="step-number">02</div><h4>Cloud Transmission</h4><p>ESP32 sends encrypted data via Wi-Fi to the web server.</p></div>
      <div class="step-card scroll-reveal"><div class="step-number">03</div><h4>AI Analysis</h4><p>TensorFlow.js models analyze images for health signs.</p></div>
      <div class="step-card scroll-reveal"><div class="step-number">04</div><h4>Smart Response</h4><p>Relays trigger fans, feeders, or pumps based on thresholds.</p></div>
      <div class="step-card scroll-reveal"><div class="step-number">05</div><h4>Actionable Insights</h4><p>Dashboard shows alerts, trends, and control options.</p></div>
    </div>
  </div>
</section>

<!-- TECH STACK -->
<section class="section-padding">
  <div class="container-custom">
    <div class="section-header scroll-reveal">
      <span class="section-badge">Tech Stack</span>
      <h2>Built with Purpose</h2>
      <p>Reliable hardware and software for poultry automation.</p>
    </div>
    <div class="tech-grid">
      <div class="tech-card scroll-reveal"><i class="fas fa-microchip"></i><p>ESP32</p><small style="color:#A0B0C8;">Dual-core MCU</small></div>
      <div class="tech-card scroll-reveal"><i class="fas fa-camera"></i><p>ESP32-CAM</p><small style="color:#A0B0C8;">OV2640</small></div>
      <div class="tech-card scroll-reveal"><i class="fas fa-thermometer-half"></i><p>DHT11</p><small style="color:#A0B0C8;">Temp / Humidity</small></div>
      <div class="tech-card scroll-reveal"><i class="fas fa-brain"></i><p>TensorFlow.js</p><small style="color:#A0B0C8;">ML models</small></div>
      <div class="tech-card scroll-reveal"><i class="fas fa-robot"></i><p>Teachable Machine</p><small style="color:#A0B0C8;">Image training</small></div>
      <div class="tech-card scroll-reveal"><i class="fas fa-database"></i><p>MySQL</p><small style="color:#A0B0C8;">Data storage</small></div>
      <div class="tech-card scroll-reveal"><i class="fas fa-code"></i><p>PHP 8</p><small style="color:#A0B0C8;">Backend API</small></div>
      <div class="tech-card scroll-reveal"><i class="fab fa-js"></i><p>JavaScript</p><small style="color:#A0B0C8;">Dashboard UI</small></div>
    </div>
  </div>
</section>

<!-- STATS -->
<section class="section-padding bg-alt">
  <div class="container-custom">
    <div class="stats-grid">
      <div class="stat-item scroll-reveal"><i class="fas fa-chart-line"></i><h3>70%</h3><p>Less Manual Labor</p><small>Automated systems</small></div>
      <div class="stat-item scroll-reveal"><i class="fas fa-heartbeat"></i><h3>95%</h3><p>Detection Rate</p><small>AI health monitoring</small></div>
      <div class="stat-item scroll-reveal"><i class="fas fa-tachometer-alt"></i><h3>24/7</h3><p>Real-Time Monitoring</p><small>Continuous sensing</small></div>
      <div class="stat-item scroll-reveal"><i class="fas fa-leaf"></i><h3>40%</h3><p>Feed Waste Reduction</p><small>Scheduled feeding</small></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section-padding" style="background: linear-gradient(120deg, #FFF8E0, #FFF3CC);">
  <div class="container-custom" style="max-width:700px;text-align:center;">
    <div class="scroll-reveal">
      <i class="fas fa-shield-alt fa-3x" style="color:#E6B800;margin-bottom:0.8rem;"></i>
      <h2 style="font-size:1.8rem;font-weight:800;">Ready to Modernize Your Farm?</h2>
      <p style="color:#5A4A3A;margin-bottom:1.5rem;">BroilerGuard combines IoT, AI, and automation for healthier birds and higher productivity.</p>
      <a href="#dashboard" class="btn-primary">Explore Dashboard <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer id="contact" class="section-padding" style="padding-bottom:1.5rem;">
  <div class="container-custom">
    <div class="footer-grid">
      <div class="footer-brand"><h3><i class="fas fa-feather-alt"></i> BroilerGuard</h3><p>IoT-based monitoring & automation for broiler chickens in tunnel-ventilated houses.</p></div>
      <div class="footer-links"><h4>Quick</h4><ul><li><a href="#home">Home</a></li><li><a href="#features">Features</a></li><li><a href="#howitworks">How It Works</a></li><li><a href="#dashboard">Dashboard</a></li></ul></div>
      <div class="footer-links"><h4>Resources</h4><ul><li><a href="#">Docs</a></li><li><a href="#">API</a></li><li><a href="#">Support</a></li></ul></div>
      <div class="footer-contact"><h4>Connect</h4><p><i class="fas fa-envelope"></i> support@broilerguard.com</p><p><i class="fas fa-phone"></i> +63 (XXX) XXX-XXXX</p><div style="display:flex;gap:1rem;margin-top:0.6rem;"><a href="#"><i class="fab fa-facebook"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-github"></i></a></div></div>
    </div>
    <div class="footer-bottom"><p>© 2026 BroilerGuard · Batangas State University - ARASOF · BSIT</p></div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
  window.addEventListener('scroll', function() {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50);
  });
  document.getElementById('mobileMenuBtn').addEventListener('click', function() {
    document.getElementById('navLinks').classList.toggle('show');
  });
  document.querySelectorAll('.nav-links a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
      const t = document.querySelector(this.getAttribute('href'));
      if(t) { e.preventDefault(); t.scrollIntoView({behavior:'smooth'}); document.getElementById('navLinks').classList.remove('show'); }
    });
  });
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => { if(e.isIntersecting) e.target.classList.add('revealed'); });
  }, { threshold: 0.1 });
  document.querySelectorAll('.scroll-reveal').forEach(el => revealObserver.observe(el));

  new Swiper('.swiper-hero', {
    slidesPerView: 1, loop: true, autoplay: { delay: 6000 }, effect: 'fade', fadeEffect: { crossFade: true },
    pagination: { el: '.swiper-pagination', clickable: true },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' }
  });
  new Swiper('.dashboard-swiper', {
    slidesPerView: 1, spaceBetween: 24, loop: true, autoplay: { delay: 5000, disableOnInteraction: false },
    pagination: { el: '.swiper-pagination', clickable: true },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    breakpoints: { 768: { slidesPerView: 2 }, 1024: { slidesPerView: 3 } }
  });
</script>
</body>
</html>
