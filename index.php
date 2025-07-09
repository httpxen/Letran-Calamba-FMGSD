<?php
    // Start output buffering to prevent headers already sent errors
    ob_start();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHS | Letran Calamba</title>
    <link rel="stylesheet" href="index.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="icon" type="image/x-icon" href="https://www.letran-calamba.edu.ph/static/img-content/logoLetran.webp">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <!-- Google Fonts for Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .sticky {
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .social-icon:hover {
            transform: scale(1.2) rotate(10deg);
            transition: transform 0.3s ease;
        }
        .nav-link {
            position: relative;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: rgb(255, 255, 255);
            transition: width 0.3s ease;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .feature-card {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }
        .feature-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        .parallax {
            background-attachment: fixed;
            background-position: center;
            background-size: cover;
        }
        .animate-hero {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }
        .animate-hero.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .pogi {
            font-family: 'Old English Text MT', 'Garamond', serif; 
            font-size: 22px;
            font-weight: 550;
        }
        .pogi2 {
            font-family: sans-serif;
            font-size: 14px;
            font-weight: 400;
            color: #000000;
            letter-spacing: 0.5px;
        }
        .pogi2-dark {
            color: #ffffff;
        }
        .nartel {
            padding-top: 0.8%;
        }
        .logo-text-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        html {
            scroll-behavior: smooth;
        }
        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        .notification.success {
            background-color: #10b981;
            color: white;
        }
        .notification.error {
            background-color: #ef4444;
            color: white;
        }
        .notification button {
            margin-left: 16px;
            background: none;
            border: none;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        /* FAQ styles */
        .faq-item {
            background-color: #ffffff;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .dark .faq-item {
            background-color: #1f2937;
        }
        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            cursor: pointer;
            font-weight: 600;
            color: #1e40af;
        }
        .dark .faq-question {
            color: #93c5fd;
        }
        .faq-answer {
            padding: 0 1rem 1rem 1rem;
            display: none;
            color: #4b5563;
        }
        .dark .faq-answer {
            color: #d1d5db;
        }
        .faq-answer.active {
            display: block;
        }
        .faq-toggle {
            transition: transform 0.3s ease;
        }
        .faq-toggle.active {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Notification Container -->
    <div id="notification" class="notification hidden"></div>

    <header class="bg-white dark:bg-gray-800 shadow-lg sticky z-50">
        <div class="container mx-auto px-4 py-2 flex flex-col md:flex-row items-center justify-between">
            <div class="flex items-center mb-4 md:mb-0">
                <div class="logo-text-container">
                    <div class="logo">
                        <a href="https://www.letran-calamba.edu.ph/" target="_blank" rel="noopener noreferrer">
                            <img src="https://www.letran-calamba.edu.ph/static/img-content/logoLetran.webp" alt="College Logo" class="h-16">
                        </a>
                    </div>
                    <div class="nartel">
                        <span class="block text-xl font-bold text-gray-800 dark:text-white pogi">Colegio de San Juan de Letran Calamba</span>
                        <span class="block text-sm text-gray-600 dark:text-white pogi2">Bucal, Calamba City, Laguna, Philippines ● 4027</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
                <div class="flex space-x-3">
                    <a href="https://www.instagram.com/letrancalambaph/" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/452229/instagram-1.svg" alt="Instagram" class="h-6 w-6">
                    </a>
                    <a href="https://www.youtube.com/@letrancalambaofficial" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/475700/youtube-color.svg" alt="YouTube" class="h-6 w-6">
                    </a>
                    <a href="https://x.com/letrancalambaph" target="_blank" class="social-icon">
                        <img src="https://img.icons8.com/?size=50&id=phOKFKYpe00C&format=png" alt="X" class="h-6 w-6">
                    </a>
                    <a href="https://www.tiktok.com/@letrancalambaph" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/452114/tiktok.svg" alt="TikTok" class="h-6 w-6">
                    </a>
                    <a href="https://www.facebook.com/LetranCalambaOfficial" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/303113/facebook-icon-logo.svg" alt="Facebook" class="h-6 w-6">
                    </a>
                    <a href="https://mail.google.com/" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/349378/gmail.svg" alt="Email" class="h-6 w-6">
                    </a>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="login.php" class="w-full bg-blue-900 text-white px-4 py-2 rounded-full font-semibold hover:bg-blue-800 dark:bg-gray-700 dark:hover:bg-gray-600 transition duration-300">LOGIN</a>
                    <button id="theme-toggle" class="bg-gray-200 dark:bg-gray-700 p-2 rounded-full focus:outline-none">
                        <svg id="theme-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <nav class="bg-blue-900 dark:bg-gray-700 text-white py-3">
            <div class="container mx-auto px-4 flex justify-center space-x-12">
                <a href="#home" class="nav-link text-lg hover:text-white dark:hover:text-white transition">Home</a>
                <a href="#about" class="nav-link text-lg hover:text-white dark:hover:text-white transition">About</a>
                <a href="#features" class="nav-link text-lg hover:text-white dark:hover:text-white transition">Features</a>
                <a href="#contact" class="nav-link text-lg hover:text-white dark:hover:text-white transition">Contact</a>
            </div>
        </nav>
    </header>

    <section id="home" class="relative h-[700px] text-white overflow-hidden">
        <video autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover z-0">
            <source src="https://www.letran-calamba.edu.ph/static/img-content/letrantourvid.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
        <div class="absolute inset-0 bg-gray-900 dark:bg-gray-900 opacity-60 z-10"></div>
        <div class="container mx-auto px-4 h-full flex items-center justify-center text-center relative z-20">
            <div>
                <h1 class="text-5xl md:text-7xl font-bold mb-4 animate-hero">EHS Self-Paced Learning System</h1>
                <p class="text-xl md:text-3xl mb-8 max-w-3xl mx-auto animate-hero" style="transition-delay: 0.2s;">
                    Transforming safety education with flexible, interactive, and comprehensive training for Letran Calamba.
                </p>
                <a href="#about" class="bg-transparent border border-white text-white px-8 py-4 rounded-full font-semibold text-lg hover:bg-white hover:text-black dark:bg-transparent dark:border-white dark:text-white dark:hover:bg-white dark:hover:text-black transition animate-hero" style="transition-delay: 0.4s;">Explore Now</a>
            </div>
        </div>
    </section>

    <section id="about" class="py-20 bg-gradient-to-r from-blue-50 to-gray-100 dark:from-gray-800 dark:to-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center text-blue-800 dark:text-blue-300 mb-12">About Our EHS System</h2>
            <div class="flex flex-col md:flex-row items-center gap-12">
                <div class="md:w-1/2">
                    <video class="rounded-3xl shadow-2xl w-full" autoplay muted loop playsinline>
                        <source src="../assets/video/EHS System Demo.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <div class="md:w-1/2">
                    <p class="text-gray-700 dark:text-gray-300 text-lg mb-4">
                        The Self-Paced Seminar and Assessment System for Environmental Health and Safety (EHS) at Letran Calamba is a state-of-the-art platform designed to deliver critical safety education. Employees can engage with interactive assessments at their own pace, ensuring mastery of EHS principles.
                    </p>
                    <p class="text-gray-700 dark:text-gray-300 text-lg">
                        With a user-friendly interface and secure technologies, our system promotes a culture of safety and responsibility, aligning with Letran Calamba's mission of excellence.
                    </p>
                </div>
            </div>
        </div>


            <div class="mt-12">
                <h3 class="text-2xl font-semibold text-center text-blue-800 dark:text-blue-300 mb-6">What Our Users Say</h3>
                <div id="carousel" class="relative overflow-hidden">
                    <div id="carousel-items" class="flex transition-transform duration-500">
                        <div class="min-w-full text-center p-8 bg-white dark:bg-gray-800 rounded-2xl">
                            <img src="../assets/images/Mr. Andrei Infante.jpg" alt="Mr. Andrei Infante" class="w-12 h-12 rounded-full mx-auto mb-4">
                            <p class="text-gray-600 dark:text-gray-300 italic mb-4">"The EHS system made learning safety protocols so flexible and engaging!"</p>
                            <p class="text-blue-800 dark:text-blue-400 font-semibold">– Mr. Andrei Infante</p>
                        </div>
                        <div class="min-w-full text-center p-8 bg-white dark:bg-gray-800 rounded-2xl">
                            <img src="https://placehold.co/50x50" alt="Maria Santos" class="w-12 h-12 rounded-full mx-auto mb-4">
                            <p class="text-gray-600 dark:text-gray-300 italic mb-4">"I love how I can track my progress and revisit modules anytime."</p>
                            <p class="text-blue-800 dark:text-blue-400 font-semibold">– Maria Santos, Student</p>
                        </div>
                        <div class="min-w-full text-center p-8 bg-white dark:bg-gray-800 rounded-2xl">
                            <img src="https://placehold.co/50x50" alt="Pedro Reyes" class="w-12 h-12 rounded-full mx-auto mb-4">
                            <p class="text-gray-600 dark:text-gray-300 italic mb-4">"A game-changer for safety education at Letran!"</p>
                            <p class="text-blue-800 dark:text-blue-400 font-semibold">– Pedro Reyes, Faculty</p>
                        </div>
                    </div>
                    <button id="prev" class="absolute left-0 top-1/2 transform -translate-y-1/2 bg-transparent dark:bg-transparent text-blue-900 dark:text-white p-2 hover:text-blue-700 dark:hover:text-blue-400">❮</button>
                    <button id="next" class="absolute right-0 top-1/2 transform -translate-y-1/2 bg-transparent dark:bg-transparent text-blue-900 dark:text-white p-2 hover:text-blue-700 dark:hover:text-blue-400">❯</button>
                </div>
            </div>

            <!-- FAQ Accordion -->
            <div class="mt-12 max-w-3xl mx-auto">
                <h3 class="text-2xl font-semibold text-center text-blue-800 dark:text-blue-300 mb-6">Frequently Asked Questions</h3>
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How do I access the EHS modules?</span>
                        <svg class="faq-toggle w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div class="faq-answer">
                        <p>Log in with your Letran Calamba credentials on the platform. Once logged in, you can access all available modules from the dashboard.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Is the EHS system free to use?</span>
                        <svg class="faq-toggle w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, the system is free for all Letran Calamba employees as part of our commitment to safety education.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Can I track my progress?</span>
                        <svg class="faq-toggle w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div class="faq-answer">
                        <p>Absolutely! The system provides detailed progress reports, allowing you to monitor your learning journey and completed assessments.</p>
                    </div>
                </div>
            </div>
        </div>  
    </section>

    <!-- Quick Stats Section -->
    <section class="py-16 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center text-blue-800 dark:text-blue-300 mb-12">Our Impact</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="text-4xl font-bold text-blue-900 dark:text-blue-400 mb-2">1,000+</div>
                    <p class="text-lg text-gray-600 dark:text-gray-300">Active Users</p>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-blue-900 dark:text-blue-400 mb-2">5,000+</div>
                    <p class="text-lg text-gray-600 dark:text-gray-300">Modules Completed</p>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-blue-900 dark:text-blue-400 mb-2">100%</div>
                    <p class="text-lg text-gray-600 dark:text-gray-300">Safety Commitment</p>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="py-20 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center text-blue-800 dark:text-blue-300 mb-12">System Features</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="feature-card bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-lg text-center">
                    <img src="../assets/images/Self-Paced Learning.png" alt="Self-Paced Learning" class="mx-auto mb-4 rounded-l-lg rounded-r-lg">
                    <h3 class="text-2xl font-semibold text-blue-800 dark:text-blue-400 mb-2">Self-Paced Learning</h3>
                    <p class="text-gray-600 dark:text-gray-300">Access seminars and modules anytime, anywhere, tailored to your schedule.</p>
                </div>
                <div class="feature-card bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-lg text-center">
                    <img src="../assets/images/Interactive-Assessments.png" alt="Interactive Assessments" class="mx-auto mb-4 rounded-l-lg rounded-r-lg">
                    <h3 class="text-2xl font-semibold text-blue-800 dark:text-blue-400 mb-2">Interactive Assessments</h3>
                    <p class="text-gray-600 dark:text-gray-300">Test your knowledge with engaging quizzes and real-time feedback.</p>
                </div>
                <div class="feature-card bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-lg text-center">
                    <img src="../assets/images/Progress-Tracking.png" alt="Progress Tracking" class="mx-auto mb-4 rounded-l-lg rounded-r-lg">
                    <h3 class="text-2xl font-semibold text-blue-800 dark:text-blue-400 mb-2">Progress Tracking</h3>
                    <p class="text-gray-600 dark:text-gray-300">Monitor your learning journey with detailed reports and insights.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="py-20 bg-blue-50 dark:bg-gray-800">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center text-blue-800 dark:text-blue-300 mb-12">Get in Touch</h2>
            <div class="max-w-lg mx-auto bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-2xl">
                <form action="contact.php" method="POST">
                    <div class="mb-6">
                        <label for="name" class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">Name</label>
                        <input type="text" id="name" name="name" class="w-full border border-gray-300 dark:border-gray-600 rounded-full py-3 px-4 focus:outline-none focus:ring-2 focus:ring-blue-800 dark:focus:ring-blue-600 transition" required>
                    </div>
                    <div class="mb-6">
                        <label for="email" class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">Email</label>
                        <input type="email" id="email" name="email" class="w-full border border-gray-300 dark:border-gray-600 rounded-full py-3 px-4 focus:outline-none focus:ring-2 focus:ring-blue-800 dark:focus:ring-blue-600 transition" required>
                    </div>
                    <div class="mb-6">
                        <label for="message" class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">Message</label>
                        <textarea id="message" name="message" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg py-3 px-4 focus:outline-none focus:ring-2 focus:ring-blue-800 dark:focus:ring-blue-600 transition" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-900 dark:bg-gray-700 text-white py-3 rounded-full font-semibold hover:bg-blue-800 dark:hover:bg-gray-600 transition">Send Message</button>
                </form>
            </div>
            <p class="text-center text-gray-700 dark:text-gray-300 mt-6">Or email us at <a href="mailto:ehs@letran-calamba.edu.ph" class="text-blue-800 dark:text-blue-400 hover:underline">ehs@letran-calamba.edu.ph</a>.</p>
            <div class="max-w-2xl mx-auto mt-8">
                <h3 class="text-2xl font-semibold text-center text-blue-800 dark:text-blue-300 mb-4">Our Location</h3>
                <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl overflow-hidden">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.103273541313!2d121.16283107456591!3d14.188729087028788!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd63e1282e62d9%3A0x9d6626d9f156784d!2sColegio%20de%20San%20Juan%20de%20Letran%20Calamba!5e0!3m2!1sen!2sph!4v1749647634585!5m2!1sen!2sph" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="bg-blue-900 dark:bg-gray-700 text-white py-4">
            <div class="container mx-auto px-30 max-w-6xl flex flex-col md:flex-row justify-between items-center space-y-6 md:space-y-0">
                <div class="flex items-center space-x-4">
                    <img src="https://www.letran-calamba.edu.ph/static/img-content/logoLetran.webp" alt="College Logo" class="h-10">
                    <div>
                        <span class="block text-lg font-semibold">Colegio de San Juan de Letran Calamba</span>
                        <span class="block text-sm text-gray-300 dark:text-white">Bucal, Calamba City, Laguna</span>
                    </div>
                </div>
                <div class="flex space-x-6 mt-4 md:mt-0">
                    <a href="https://www.instagram.com/letrancalambaph/" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/452229/instagram-1.svg" alt="Instagram" class="h-6 w-6">
                    </a>
                    <a href="https://www.youtube.com/@letrancalambaofficial" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/475700/youtube-color.svg" alt="YouTube" class="h-6 w-6">
                    </a>
                    <a href="https://x.com/letrancalambaph" target="_blank" class="social-icon">
                        <img src="https://img.icons8.com/?size=50&id=phOKFKYpe00C&format=png" alt="X" class="h-6 w-6">
                    </a>
                    <a href="https://www.tiktok.com/@letrancalambaph" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/452114/tiktok.svg" alt="TikTok" class="h-6 w-6">
                    </a>
                    <a href="https://www.facebook.com/LetranCalambaOfficial" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/303113/facebook-icon-logo.svg" alt="Facebook" class="h-6 w-6">
                    </a>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-b from-red-500 to-blue-500 dark:from-red-600 dark:to-blue-600 text-white py-8">
            <div class="container mx-auto px-4 max-w-6xl">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
                    <div class="flex flex-col self-start">
                        <div>
                            <h3 class="text-lg font-semibold mb-4">DOMINICAN LINKS</h3>
                            <ul class="space-y-4 pt-2">
                                <li class="flex items-center space-x-3">
                                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTSeq9yZZzddnK1te4Xei0tG7C7suQ7BR6XIA&s" alt="Letran Manila Logo" class="h-10 w-10 rounded-full">
                                    <span>Letran Manila</span>
                                </li>
                                <li class="flex items-center space-x-3">
                                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTIv6UaL7PRbJ1c8OiepwHv9a9evRCr_WpRUA&s" alt="Letran Manaoag Logo" class="h-10 w-10 rounded-full">
                                    <span>Letran Manaoag</span>
                                </li>
                                <li class="flex items-center space-x-3">
                                    <img src="https://admission.letranbataan.edu.ph/Images/LetranBataanLogo.png" alt="Letran Bataan Logo" class="h-10 w-10 rounded-full">
                                    <span>Letran Bataan</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold mb-4">UST INSTITUTIONS</h3>
                        <ul class="space-y-4 pt-2">
                            <li class="flex items-center space-x-3">
                                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQQToOOcxRKOwhNt29IkB6e4npYiAM9sh90Og&s" alt="UST Logo" class="h-10 w-10 rounded-full">
                                <span>University of Santo Tomas</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://upload.wikimedia.org/wikipedia/en/thumb/6/6d/Angelicum_College.png/250px-Angelicum_College.png" alt="UST Angelicum Logo" class="h-10 w-10 rounded-full">
                                <span>University of Santo Tomas Angelicum College</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://upload.wikimedia.org/wikipedia/en/0/01/UST-Legazpi_Seal.png" alt="UST Legazpi Logo" class="h-10 w-10 rounded-full">
                                <span>University of Santo Tomas Legazpi</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRjdE8lNdi_Ua8vIEp8LzNztmasX3r0mA5TPQ&s" alt="Dominican Province Logo" class="h-10 w-10 rounded-full">
                                <span>Dominican Province of the Philippines</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQXXL6v_Jezsx6AZhEz_3FLj-FGM65_VCz4pw&s" alt="Angelicum Iloilo Logo" class="h-10 w-10 rounded-full">
                                <span>Angelicum School Iloilo</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold mb-4">INTERNATIONAL LINKAGES</h3>
                        <ul class="space-y-4 pt-2">
                            <li class="flex items-center space-x-3">
                                <img src="https://media.licdn.com/dms/image/v2/C510BAQHS3xc4bqqLJw/company-logo_200_200/company-logo_200_200/0/1630594052808/hfse_international_school_logo?e=2147483647&v=beta&t=3oA-aBSfDI67A0Jd0GIrFyXWyKAhdjQvfqsDxmbeesI" alt="HFSE Logo" class="h-10 w-10 rounded-full">
                                <span>HFSE International School Singapore</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQWyenzEa6YnvkQEFRYaEAsB_j-eYh1NazNtA&s" alt="Hungkuang Logo" class="h-10 w-10 rounded-full">
                                <span>Hungkuang University</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://www.letran-calamba.edu.ph/static/img-content/footer-icons/INTI.png" alt="INTI Logo" class="h-10 w-10 rounded-full">
                                <span>INTI International University</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://upload.wikimedia.org/wikipedia/en/a/a7/Ming_chuan_university_logo.png" alt="Ming Chuan Logo" class="h-10 w-10 rounded-full">
                                <span>Ming Chuan University</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://upload.wikimedia.org/wikipedia/id/4/48/Stiki.jpg" alt="STIKI Logo" class="h-10 w-10 rounded-full">
                                <span>Sekolah Tinggi Informatika & Komputer Indonesia (STIKI)</span>
                            </li>
                            <li class="flex items-center space-x-3">
                                <img src="https://assets.siakadcloud.com/uploads/sanagustin/logoaplikasi/1462.jpg" alt="Agustinus Hippo Logo" class="h-10 w-10 rounded-full">
                                <span>Universitas Katolik Santo Agustinus Hippo Indonesia</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-red-500 dark:bg-red-500 mt-0 pt-4 pb-4">
            <p class="text-center text-gray-100 dark:text-gray-200 text-sm">© <?php echo date("Y"); ?> Colegio de San Juan de Letran Calamba. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const featureCards = document.querySelectorAll('.feature-card');
            const heroElements = document.querySelectorAll('.animate-hero');
            const themeToggle = document.getElementById('theme-toggle');
            const themeIcon = document.getElementById('theme-icon');
            const notification = document.getElementById('notification');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });

            featureCards.forEach(card => observer.observe(card));
            heroElements.forEach(el => observer.observe(el));

            // Load theme from localStorage
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            updateThemeIcon(savedTheme);

            // Toggle theme
            themeToggle.addEventListener('click', () => {
                const isDark = document.documentElement.classList.toggle('dark');
                const theme = isDark ? 'dark' : 'light';
                localStorage.setItem('theme', theme);
                updateThemeIcon(theme);
            });

            function updateThemeIcon(theme) {
                themeIcon.innerHTML = theme === 'dark' ?
                    `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.645"></path>` :
                    `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>`;
            }

            const carousel = document.getElementById('carousel-items');
            const prevBtn = document.getElementById('prev');
            const nextBtn = document.getElementById('next');
            let currentIndex = 0;
            const slides = carousel.children.length;

            function updateCarousel() {
                carousel.style.transform = `translateX(-${currentIndex * 100}%)`;
            }

            nextBtn.addEventListener('click', () => {
                currentIndex = (currentIndex + 1) % slides;
                updateCarousel();
            });

            prevBtn.addEventListener('click', () => {
                currentIndex = (currentIndex - 1 + slides) % slides;
                updateCarousel();
            });

            setInterval(() => {
                currentIndex = (currentIndex + 1) % slides;
                updateCarousel();
            }, 5000);

            // Handle notification display
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            if (status && message) {
                notification.innerHTML = `<span>${message}</span> <button onclick="closeNotification()">×</button>`;
                notification.classList.remove('hidden');
                notification.classList.add(status === 'success' ? 'success' : 'error', 'show');
                // Auto-hide after 5 seconds
                setTimeout(closeNotification, 5000);
                // Clear URL parameters
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // FAQ accordion functionality
            const faqQuestions = document.querySelectorAll('.faq-question');
            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const answer = question.nextElementSibling;
                    const toggle = question.querySelector('.faq-toggle');
                    const isActive = answer.classList.contains('active');

                    // Close all answers
                    document.querySelectorAll('.faq-answer').forEach(ans => ans.classList.remove('active'));
                    document.querySelectorAll('.faq-toggle').forEach(tog => tog.classList.remove('active'));

                    // Toggle current answer
                    if (!isActive) {
                        answer.classList.add('active');
                        toggle.classList.add('active');
                    }
                });
            });
        });

        function closeNotification() {
            const notification = document.getElementById('notification');
            notification.classList.remove('show');
            setTimeout(() => notification.classList.add('hidden'), 300);
        }
    </script>
</body>
</html>
<?php
    // End output buffering
    ob_end_flush();
?>