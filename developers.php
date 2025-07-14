<?php
    // Start output buffering to prevent headers already sent errors
    ob_start();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Meet the developers behind the EHS Self-Paced Learning System at Colegio de San Juan de Letran Calamba.">
    <meta name="keywords" content="EHS, Letran Calamba, developers, safety education, self-paced learning">
    <meta name="author" content="Colegio de San Juan de Letran Calamba">
    <title>Developers | EHS Letran Calamba</title>
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
        .developer-card {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }
        .developer-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .developer-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
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
                            <img src="https://www.letran-calamba.edu.ph/static/img-content/logoLetran.webp" alt="Colegio de San Juan de Letran Calamba Logo" class="h-16">
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
                        <img src="https://www.svgrepo.com/show/452229/instagram-1.svg" alt="Letran Calamba Instagram" class="h-6 w-6">
                    </a>
                    <a href="https://www.youtube.com/@letrancalambaofficial" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/475700/youtube-color.svg" alt="Letran Calamba YouTube" class="h-6 w-6">
                    </a>
                    <a href="https://x.com/letrancalambaph" target="_blank" class="social-icon">
                        <img src="https://img.icons8.com/?size=50&id=phOKFKYpe00C&format=png" alt="Letran Calamba X" class="h-6 w-6">
                    </a>
                    <a href="https://www.tiktok.com/@letrancalambaph" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/452114/tiktok.svg" alt="Letran Calamba TikTok" class="h-6 w-6">
                    </a>
                    <a href="https://www.facebook.com/LetranCalambaOfficial" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/303113/facebook-icon-logo.svg" alt="Letran Calamba Facebook" class="h-6 w-6">
                    </a>
                    <a href="https://mail.google.com/" target="_blank" class="social-icon">
                        <img src="https://www.svgrepo.com/show/349378/gmail.svg" alt="Letran Calamba Email" class="h-6 w-6">
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
                <a href="index.php#home" class="nav-link text-lg hover:text-white dark:hover:text-white transition">Home</a>
                <a href="index.php#about" class="nav-link text-lg hover:text-white dark:hover:text-white transition">About</a>
                <a href="index.php#features" class="nav-link text-lg hover:text-white dark:hover:text-white transition">Features</a>
                <a href="index.php#contact" class="nav-link text-lg hover:text-white dark:hover:text-white transition">Contact</a>
            </div>
        </nav>
    </header>

    <section id="developers" class="py-20 bg-gradient-to-r from-blue-50 to-gray-100 dark:from-gray-800 dark:to-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-4xl font-bold text-center text-blue-800 dark:text-blue-300 mb-12">Meet Our Developers</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
                <!-- TODO: Replace placeholder images, names, roles, descriptions, and social media links with actual developer information -->
                <div class="developer-card bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-lg text-center">
                    <img src="https://placehold.co/100x100" alt="Profile photo of Lead Developer" class="w-24 h-24 rounded-full mx-auto mb-4">
                    <h3 class="text-2xl font-semibold text-blue-800 dark:text-blue-400 mb-2">John Doe</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-2">Lead Developer</p>
                    <p class="text-gray-600 dark:text-gray-300">Expert in full-stack development with a passion for creating user-friendly interfaces.</p>
                    <div class="flex justify-center space-x-3 mt-4">
                        <a href="https://www.linkedin.com/" target="_blank" class="social-icon">
                            <img src="https://www.svgrepo.com/show/452093/linkedin.svg" alt="LinkedIn profile" class="h-6 w-6">
                        </a>
                        <a href="https://github.com/" target="_blank" class="social-icon">
                            <img src="https://www.svgrepo.com/show/452228/github.svg" alt="GitHub profile" class="h-6 w-6">
                        </a>
                    </div>
                </div>
                <div class="developer-card bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-lg text-center">
                    <img src="https://placehold.co/100x100" alt="Profile photo of UI/UX Designer" class="w-24 h-24 rounded-full mx-auto mb-4">
                    <h3 class="text-2xl font-semibold text-blue-800 dark:text-blue-400 mb-2">Jane Smith</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-2">UI/UX Designer</p>
                    <p class="text-gray-600 dark:text-gray-300">Specializes in crafting intuitive and visually appealing designs for seamless user experiences.</p>
                    <div class="flex justify-center space-x-3 mt-4">
                        <a href="https://www.linkedin.com/" target="_blank" class="social-icon">
                            <img src="https://www.svgrepo.com/show/452093/linkedin.svg" alt="LinkedIn profile" class="h-6 w-6">
                        </a>
                        <a href="https://dribbble.com/" target="_blank" class="social-icon">
                            <img src="https://www.svgrepo.com/show/452095/dribbble.svg" alt="Dribbble portfolio" class="h-6 w-6">
                        </a>
                    </div>
                </div>
                <div class="developer-card bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-lg text-center">
                    <img src="https://placehold.co/100x100" alt="Profile photo of Backend Developer" class="w-24 h-24 rounded-full mx-auto mb-4">
                    <h3 class="text-2xl font-semibold text-blue-800 dark:text-blue-400 mb-2">Alex Reyes</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-2">Backend Developer</p>
                    <p class="text-gray-600 dark:text-gray-300">Focused on building robust and secure server-side solutions for the EHS system.</p>
                    <div class="flex justify-center space-x-3 mt-4">
                        <a href="https://www.linkedin.com/" target="_blank" class="social-icon">
                            <img src="https://www.svgrepo.com/show/452093/linkedin.svg" alt="LinkedIn profile" class="h-6 w-6">
                        </a>
                        <a href="https://github.com/" target="_blank" class="social-icon">
                            <img src="https://www.svgrepo.com/show/452228/github.svg" alt="GitHub profile" class="h-6 w-6">
                        </a>
                    </div>
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
            const developerCards = document.querySelectorAll('.developer-card');
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

            developerCards.forEach(card => observer.observe(card));

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