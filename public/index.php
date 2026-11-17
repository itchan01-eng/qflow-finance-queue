
<?php
require_once '../includes/functions.php';

qflow_start_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#8f1725">
    <meta name="description" content="QueueFlow is the RMMC-MI Finance Office queue management portal. Get your queue ticket and follow your queue status online.">

    <title>QueueFlow | RMMC-MI Finance Office</title>

    <link rel="icon" type="image/png" href="assets/img/icon.png">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>

<body class="landing-page">

<header class="landing-header">
    <div class="landing-container landing-nav">

        <a class="landing-brand" href="index.php" aria-label="QueueFlow home">
    <span class="landing-brand-mark">
        <img src="assets/img/icon.png" alt="">
    </span>

    <span class="landing-brand-text">
        <strong>QueueFlow</strong>
        <small>RMMC-MI · Finance Office</small>
    </span>
</a>

        <nav class="landing-nav-links" aria-label="Main navigation">
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#about">About QueueFlow</a>
        </nav>

        <div class="landing-nav-actions">
            <a class="landing-login-link" href="login.php">Log In</a>
            <a class="landing-button landing-button-primary landing-nav-cta"
               href="register.php">
                Get Started <span aria-hidden="true">↗</span>
            </a>
        </div>

    </div>
</header>

<main>

    <!-- HERO -->
    <section class="landing-hero">
        <div class="landing-hero-decoration landing-hero-decoration-one"></div>
        <div class="landing-hero-decoration landing-hero-decoration-two"></div>

        <div class="landing-container landing-hero-grid">

            <div class="landing-hero-copy">

                <div class="landing-school-label">
                    <span class="landing-label-line"></span>
                    RAMON MAGSAYSAY MEMORIAL COLLEGES – MARBEL, INC.
                </div>

                <p class="landing-eyebrow">
                    FINANCE OFFICE QUEUE MANAGEMENT SYSTEM
                </p>

                <h1>
                    Your time matters.
                    <span>Your queue, simplified.</span>
                </h1>

                <p class="landing-hero-description">
                    Skip the uncertainty of waiting in line. Get your queue
                    number, follow your status, and stay updated through
                    one convenient online portal.
                </p>

                <div class="landing-hero-actions">
                    <a class="landing-button landing-button-primary"
                       href="register.php">
                        Get Started <span aria-hidden="true">→</span>
                    </a>

                    <a class="landing-button landing-button-outline"
                       href="login.php">
                        Log In to Your Account
                    </a>
                </div>

                <div class="landing-hero-note">
                    <span class="landing-check-icon" aria-hidden="true">✓</span>
                    <span>Designed for the RMMC-MI student community</span>
                </div>

            </div>

            <!-- Queue ticket preview -->
            <div class="landing-visual">

                <div class="landing-visual-topline">
                    <span class="landing-visual-dot"></span>
                    QUEUEFLOW STUDENT PORTAL
                    <span class="landing-visual-menu">•••</span>
                </div>

                <div class="landing-queue-card">

                    <div class="landing-queue-card-header">
                        <div>
                            <span class="landing-mini-label">YOUR QUEUE OVERVIEW</span>
                            <h2>Keep your day moving.</h2>
                        </div>

                        <div class="landing-ticket-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M5 5.5h14v3a2.5 2.5 0 0 0 0 5v3H5v-3a2.5 2.5 0 0 0 0-5v-3Z"
                                      stroke="currentColor" stroke-width="1.7"
                                      stroke-linejoin="round"/>
                                <path d="M12 8v1m0 2v1m0 2v1"
                                      stroke="currentColor" stroke-width="1.7"
                                      stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>

                    <div class="landing-ticket">
                        <div class="landing-ticket-top">
                            <span class="landing-mini-label">SAMPLE TICKET</span>
                            <span class="landing-sample-badge">PREVIEW</span>
                        </div>

                        <div class="landing-ticket-number">C-024</div>

                        <div class="landing-ticket-divider">
                            <span></span><span></span>
                        </div>

                        <div class="landing-ticket-info">
                            <div>
                                <span class="landing-mini-label">SERVICE</span>
                                <strong>Finance Office</strong>
                            </div>
                            <div class="landing-ticket-clock">
                                <svg viewBox="0 0 24 24" fill="none"
                                     aria-hidden="true">
                                    <circle cx="12" cy="12" r="9"
                                            stroke="currentColor"
                                            stroke-width="1.7"/>
                                    <path d="M12 7v5l3 2"
                                          stroke="currentColor"
                                          stroke-width="1.7"
                                          stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="landing-queue-progress">
                        <div class="landing-progress-heading">
                            <strong>Queue progress</strong>
                            <span>Stay updated</span>
                        </div>

                        <div class="landing-progress-track">
                            <span></span>
                        </div>

                        <div class="landing-progress-labels">
                            <span>Ticket issued</span>
                            <span>Await your turn</span>
                        </div>
                    </div>

                    <div class="landing-queue-notice">
                        <div class="landing-notice-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"
                                      stroke="currentColor" stroke-width="1.7"
                                      stroke-linejoin="round"/>
                                <path d="M10 21h4"
                                      stroke="currentColor" stroke-width="1.7"
                                      stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div>
                            <strong>Stay in the loop</strong>
                            <p>Check your account for queue updates and notifications.</p>
                        </div>
                    </div>

                </div>

                <div class="landing-floating-note">
                    <span class="landing-floating-check">✓</span>
                    <span>
                        <strong>One convenient portal</strong>
                        <small>Your queue information in one place</small>
                    </span>
                </div>

                <p class="landing-sample-caption">
                    Sample interface · Not a live queue ticket
                </p>

            </div>
        </div>

        <div class="landing-hero-bottom">
            <div class="landing-container landing-hero-bottom-inner">
                <span>BUILT FOR BETTER SERVICE</span>
                <span class="landing-bottom-separator"></span>
                <span>ORGANIZED QUEUES</span>
                <span class="landing-bottom-separator"></span>
                <span>A BETTER STUDENT EXPERIENCE</span>
            </div>
        </div>
    </section>

    <!-- BENEFITS -->
    <section class="landing-benefits" aria-label="QueueFlow benefits">
        <div class="landing-container landing-benefits-grid">

            <article class="landing-benefit">
                <div class="landing-benefit-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2Z"
                              stroke="currentColor" stroke-width="1.7"/>
                        <path d="M9 9h6M9 13h6M9 17h3"
                              stroke="currentColor" stroke-width="1.7"
                              stroke-linecap="round"/>
                    </svg>
                </div>
                <div>
                    <h3>Get Your Ticket</h3>
                    <p>Join an available Finance Office service queue.</p>
                </div>
            </article>

            <article class="landing-benefit">
                <div class="landing-benefit-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"
                                stroke="currentColor" stroke-width="1.7"/>
                        <path d="M12 7v5l3 2"
                              stroke="currentColor" stroke-width="1.7"
                              stroke-linecap="round"/>
                    </svg>
                </div>
                <div>
                    <h3>Follow Your Status</h3>
                    <p>Check your ticket and queue progress online.</p>
                </div>
            </article>

            <article class="landing-benefit">
                <div class="landing-benefit-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m5 12 4 4L19 6"
                              stroke="currentColor" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <h3>Stay Informed</h3>
                    <p>Review important queue changes in your account.</p>
                </div>
            </article>

        </div>
    </section>

    <!-- FEATURES -->
    <section class="landing-section" id="features">
        <div class="landing-container">

            <div class="landing-section-heading">
                <span class="landing-section-kicker">A BETTER WAY TO QUEUE</span>
                <h2>Simple tools. <span>Smoother service.</span></h2>
                <p>
                    QueueFlow helps students access their Finance Office queue
                    information without the extra hassle.
                </p>
            </div>

            <div class="landing-feature-grid">

                <article class="landing-feature-card">
                    <div class="landing-feature-icon landing-icon-red">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 4h10a2 2 0 0 1 2 2v14H5V6a2 2 0 0 1 2-2Z"
                                  stroke="currentColor" stroke-width="1.7"/>
                            <path d="M9 9h6M9 13h6M9 17h3"
                                  stroke="currentColor" stroke-width="1.7"
                                  stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="landing-feature-number">01</span>
                    <h3>Easy Ticketing</h3>
                    <p>
                        Select the Finance Office service you need and obtain
                        your queue ticket through the portal.
                    </p>
                </article>

                <article class="landing-feature-card">
                    <div class="landing-feature-icon landing-icon-gold">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"
                                    stroke="currentColor" stroke-width="1.7"/>
                            <path d="M12 7v5l3 2"
                                  stroke="currentColor" stroke-width="1.7"
                                  stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="landing-feature-number">02</span>
                    <h3>Queue Monitoring</h3>
                    <p>
                        Review your current queue status and follow your
                        ticket's progress from your account.
                    </p>
                </article>

                <article class="landing-feature-card">
                    <div class="landing-feature-icon landing-icon-dark">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"
                                  stroke="currentColor" stroke-width="1.7"
                                  stroke-linejoin="round"/>
                            <path d="M10 21h4"
                                  stroke="currentColor" stroke-width="1.7"
                                  stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="landing-feature-number">03</span>
                    <h3>Queue Notifications</h3>
                    <p>
                        Check important updates, including notifications
                        related to your queue.
                    </p>
                </article>

            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="landing-how" id="how-it-works">
        <div class="landing-container landing-how-grid">

            <div class="landing-how-copy">
                <span class="landing-section-kicker">GET STARTED IN MINUTES</span>
                <h2>Three steps to a <span>simpler queue.</span></h2>
                <p>
                    Use your QueueFlow account to manage your queue information
                    and keep track of your turn.
                </p>

                <a class="landing-text-link" href="register.php">
                    Create your account <span aria-hidden="true">→</span>
                </a>
            </div>

            <div class="landing-steps">

                <article class="landing-step">
                    <div class="landing-step-number">01</div>
                    <div>
                        <h3>Register or Log In</h3>
                        <p>Access QueueFlow using your account credentials.</p>
                    </div>
                </article>

                <article class="landing-step">
                    <div class="landing-step-number">02</div>
                    <div>
                        <h3>Select a Finance Service</h3>
                        <p>Choose the service you need and get your queue ticket.</p>
                    </div>
                </article>

                <article class="landing-step">
                    <div class="landing-step-number">03</div>
                    <div>
                        <h3>Monitor Your Queue</h3>
                        <p>Check your ticket status and review queue updates.</p>
                    </div>
                </article>

            </div>
        </div>
    </section>

    <!-- ABOUT / CTA -->
    <section class="landing-cta" id="about">
        <div class="landing-container">
            <div class="landing-cta-panel">

                <div class="landing-cta-decoration" aria-hidden="true">Q</div>

                <div class="landing-cta-content">
                    <span class="landing-cta-kicker">QUEUEFLOW · RMMC-MI</span>
                    <h2>Make your next visit <span>more organized.</span></h2>
                    <p>
                        Access your Finance Office queue in one place.
                        Sign in or create your account to get started.
                    </p>
                </div>

                <div class="landing-cta-actions">
                    <a class="landing-button landing-button-light"
                       href="register.php">
                        Create an Account <span aria-hidden="true">→</span>
                    </a>
                    <a class="landing-cta-login" href="login.php">
                        Already registered? Log in
                    </a>
                </div>

            </div>
        </div>
    </section>

</main>

<footer class="landing-footer">
    <div class="landing-container">

        <div class="landing-footer-main">

            <a class="landing-brand landing-footer-brand" href="index.php">
                <span class="landing-brand-mark">Q</span>
                <span class="landing-brand-text">
                    <strong>QueueFlow</strong>
                    <small>RMMC-MI Finance Office</small>
                </span>
            </a>

            <p>
                A centralized queue management system designed to support
                a more organized Finance Office experience.
            </p>

            <div class="landing-footer-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="login.php">Log In</a>
                <a href="register.php">Register</a>
            </div>

        </div>

        <div class="landing-footer-bottom">
            <span>© <?= date('Y') ?> QueueFlow · RMMC-MI</span>
            <span>Made for a better student service experience.</span>
        </div>

    </div>
</footer>

</body>
</html>
