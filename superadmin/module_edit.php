<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "SuperAdmin") {
  header("Location: login.php");
  exit();
}

$module_id = $_GET['module_id'];
$moduleStmt = $pdo->prepare("SELECT * FROM modules WHERE id = :module_id");
$moduleStmt->execute([':module_id' => $module_id]);
$module = $moduleStmt->fetch(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $module_description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $module_category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($module_title)) {
        $errors['title'] = 'Module title is required';
    }

    if (empty($module_description)) {
        $errors['description'] = 'Module description is required';
    }

    if (empty($module_category)) {
        $errors['category'] = 'Module category is required';
    }

    $thumbnail_destination = $module['thumbnail'] ?? '';
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['thumbnail']['tmp_name'])) {
        $file_tmp = $_FILES['thumbnail']['tmp_name'];
        $file_name = $_FILES['thumbnail']['name'];
        $file_type = mime_content_type($file_tmp);

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file_type, $allowed_types)) {
            $errors['thumbnail'] = 'Only JPEG, PNG, and GIF files are allowed';
        } else {
            $uploads_dir = '../Uploads/thumbnails/';
            $unique_name = uniqid('thumbnail_', true) . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
            $thumbnail_destination = $uploads_dir . $unique_name;
        }
    } else {
        // Allow keeping existing thumbnail
        if (empty($module['thumbnail'])) {
            $errors['thumbnail'] = 'Module thumbnail is required';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if (isset($file_tmp) && move_uploaded_file($file_tmp, $thumbnail_destination)) {
                // Delete old thumbnail if it exists and a new one is uploaded
                if ($module['thumbnail'] && file_exists($module['thumbnail']) && $module['thumbnail'] !== $thumbnail_destination) {
                    unlink($module['thumbnail']);
                }
            }

            $stmt = $pdo->prepare("UPDATE modules SET title = :title, description = :description, thumbnail = :thumbnail, category = :category WHERE id = :module_id");
            $stmt->execute([
                ':title' => $module_title,
                ':description' => $module_description,
                ':thumbnail' => $thumbnail_destination,
                ':category' => $module_category,
                ':module_id' => $module_id
            ]);

            $pdo->commit();
            $_SESSION['success_message'] = 'Module updated successfully';
            header("Location: lessons.php?module_id={$module_id}");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            $errors['thumbnail'] = 'Failed to upload thumbnail: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Module - <?= htmlspecialchars($module['title'] ?? '') ?></title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
                    },
                    colors: {
                        primary: {
                            50: "#f0f5ff",
                            500: "#3b82f6",
                            600: "#2563eb",
                            700: "#1d4ed8",
                        },
                        accent: {
                            50: "#ecfdf5",
                            500: "#10b981",
                            600: "#059669",
                        },
                        dashboard: "#1e3a8a",
                    },
                    boxShadow: {
                        'glow': '0 0 15px rgba(59, 130, 246, 0.2)',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'scale-in': 'scaleIn 0.2s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        scaleIn: {
                            '0%': { transform: 'scale(0.95)' },
                            '100%': { transform: 'scale(1)' },
                        },
                    },
                },
            },
        };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50 text-gray-900 font-sans antialiased min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white shadow-xl transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static">
            <div class="flex items-center space-x-3 p-6 border-b border-gray-100">
                <img src="../assets/images/favicon.ico" alt="Logo" class="w-10 h-10 rounded-full">
                <h2 class="text-xl font-bold text-dashboard"><span class="text-red-600">SuperAdmin</span> Dashboard</h2>
            </div>
            <nav class="mt-6">
                <ul class="space-y-2 px-4">
                    <li>
                        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="user_approvals.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0Z" />
                            </svg>
                            User Approvals
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="user_records.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                            </svg>
                            User Scores
                        </a>
                    </li>
                    <li>
                        <a href="module_list.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-primary-50 text-primary-600 font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            Modules
                        </a>
                    </li>
                    <li>
                        <a href="survey_management.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200 ease-in-out transform hover:scale-[1.02] <?php echo $current_page === 'survey_management.php' ? 'bg-primary-50 text-primary-600' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.125 1.125 0 0 1 0 2.25H5.625a1.125 1.125 0 0 1 0-2.25Z" />
                            </svg>
                            Survey Management
                        </a>
                    </li>
                    <li>
                        <a href="account_management.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0012 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 116 0Zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0Zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0Z" />
                            </svg>
                            Account Management
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col">
            <!-- Topbar -->
            <header class="bg-white shadow-sm flex justify-between items-center px-6 py-4">
                <div class="flex items-center space-x-4">
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-semibold text-dashboard">Edit Module - <?= htmlspecialchars($module['title'] ?? '') ?></h1>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <div class="max-w-7xl mx-auto">
                    <?php if (isset($errors['database'])) : ?>
                        <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl animate-fade-in"><?php echo htmlspecialchars($errors['database']); ?></div>
                    <?php elseif (isset($errors['thumbnail'])) : ?>
                        <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl animate-fade-in"><?php echo htmlspecialchars($errors['thumbnail']); ?></div>
                    <?php elseif (isset($_SESSION['success_message'])) : ?>
                        <div class="mb-6 p-4 bg-green-50 text-green-600 rounded-xl animate-fade-in"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Form Section -->
                        <div class="bg-white rounded-2xl shadow-glow p-8 animate-scale-in">
                            <h2 class="text-2xl font-bold text-dashboard mb-6">Edit Module Details</h2>
                            <form id="module-form" method="POST" enctype="multipart/form-data" class="space-y-6">
                                <div>
                                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Module Title *</label>
                                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? $module['title'] ?? ''); ?>" placeholder="Enter module title" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200">
                                    <?php if (isset($errors['title'])) : ?>
                                        <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['title']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                                    <textarea id="description" name="description" placeholder="Enter module description" rows="4" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200"><?php echo htmlspecialchars($_POST['description'] ?? $module['description'] ?? ''); ?></textarea>
                                    <?php if (isset($errors['description'])) : ?>
                                        <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                                    <select id="category" name="category" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all duration-200">
                                        <option value="" disabled <?php echo empty($_POST['category'] ?? $module['category']) ? 'selected' : ''; ?>>Select a category</option>
                                        <option value="Safety" <?php echo ($_POST['category'] ?? $module['category']) === 'Safety' ? 'selected' : ''; ?>>Safety</option>
                                        <option value="Environment" <?php echo ($_POST['category'] ?? $module['category']) === 'Environment' ? 'selected' : ''; ?>>Environment</option>
                                        <option value="Health" <?php echo ($_POST['category'] ?? $module['category']) === 'Health' ? 'selected' : ''; ?>>Health</option>
                                        <option value="Certification" <?php echo ($_POST['category'] ?? $module['category']) === 'Certification' ? 'selected' : ''; ?>>Certification</option>
                                        <option value="Other" <?php echo ($_POST['category'] ?? $module['category']) === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    <?php if (isset($errors['category'])) : ?>
                                        <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['category']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="thumbnail" class="block text-sm font-medium text-gray-700 mb-1">Upload New Thumbnail (JPEG/PNG/GIF)</label>
                                    <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/gif" class="w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100 transition-all duration-200">
                                    <?php if ($module['thumbnail']) : ?>
                                        <img id="thumbnail-preview" src="<?php echo htmlspecialchars($module['thumbnail']); ?>" alt="Current Thumbnail" class="mt-2 w-32 h-32 object-cover rounded-lg">
                                    <?php else : ?>
                                        <img id="thumbnail-preview" src="" alt="Thumbnail Preview" class="mt-2 w-32 h-32 object-cover rounded-lg hidden">
                                    <?php endif; ?>
                                    <?php if (isset($errors['thumbnail'])) : ?>
                                        <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['thumbnail']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex justify-end space-x-4">
                                    <button type="button" onclick="window.location.href='module_list.php'" class="px-6 py-3 bg-gray-100ysics" class="mt-5 mt-5">Cancel</button>
                                    <button type="submit" id="save-module-btn" class="px-6 py-3 bg-primary-600 text-white rounded-xl hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 font-medium transition-all duration-200">Save Module</button>
                                </div>
                            </form>
                        </div>
                        <!-- Preview Section -->
                        <div class="bg-white rounded-2xl shadow-glow p-8 animate-scale-in lg:block hidden">
                            <h2 class="text-2xl font-bold text-dashboard mb-6">Module Preview</h2>
                            <div id="module-preview" class="bg-gray-50 rounded-xl p-6 transition-all duration-200">
                                <img id="preview-thumbnail" src="<?php echo htmlspecialchars($module['thumbnail'] ?? ''); ?>" alt="Module Thumbnail" class="w-full h-48 object-cover rounded-lg mb-4 <?php echo $module['thumbnail'] ? '' : 'hidden'; ?>">
                                <div id="preview-thumbnail-placeholder" class="w-full h-48 bg-gray-200 rounded-lg flex items-center justify-center text-gray-500 mb-4 <?php echo $module['thumbnail'] ? 'hidden' : ''; ?>">No thumbnail selected</div>
                                <h3 id="preview-title" class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($module['title'] ?? 'Enter module title'); ?></h3>
                                <p id="preview-description" class="mt-2 text-sm text-gray-600"><?php echo htmlspecialchars($module['description'] ?? 'Enter module description'); ?></p>
                                <p id="preview-category" class="mt-2 text-sm text-gray-600">Category: <span class="capitalize"><?php echo htmlspecialchars($module['category'] ?? 'Select a category'); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle
        const sidebar = document.getElementById("sidebar");
        const sidebarToggle = document.getElementById("sidebar-toggle");

        sidebarToggle.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
        });

        document.addEventListener("click", (e) => {
            if (
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target) &&
                !sidebar.classList.contains("-translate-x-full")
            ) {
                sidebar.classList.add("-translate-x-full");
            }
        });

        // Real-time preview updates
        const titleInput = document.getElementById('title');
        const descriptionInput = document.getElementById('description');
        const categorySelect = document.getElementById('category');
        const thumbnailInput = document.getElementById('thumbnail');
        const previewTitle = document.getElementById('preview-title');
        const previewDescription = document.getElementById('preview-description');
        const previewCategory = document.getElementById('preview-category');
        const previewThumbnail = document.getElementById('preview-thumbnail');
        const previewThumbnailPlaceholder = document.getElementById('preview-thumbnail-placeholder');
        const thumbnailPreview = document.getElementById('thumbnail-preview');

        titleInput.addEventListener('input', () => {
            previewTitle.textContent = titleInput.value.trim() || 'Enter module title';
        });

        descriptionInput.addEventListener('input', () => {
            previewDescription.textContent = descriptionInput.value.trim() || 'Enter module description';
        });

        categorySelect.addEventListener('change', () => {
            const category = categorySelect.value;
            previewCategory.textContent = `Category: ${category ? category.charAt(0).toUpperCase() + category.slice(1) : 'Select a category'}`;
        });

        thumbnailInput.addEventListener('change', () => {
            const file = thumbnailInput.files[0];
            if (file && ['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                const url = URL.createObjectURL(file);
                previewThumbnail.src = url;
                previewThumbnail.classList.remove('hidden');
                previewThumbnailPlaceholder.classList.add('hidden');
                thumbnailPreview.src = url;
                thumbnailPreview.classList.remove('hidden');
            } else {
                previewThumbnail.src = '<?php echo htmlspecialchars($module['thumbnail'] ?? ''); ?>';
                previewThumbnail.classList.toggle('hidden', !'<?php echo $module['thumbnail'] ? true : false; ?>');
                previewThumbnailPlaceholder.classList.toggle('hidden', '<?php echo $module['thumbnail'] ? true : false; ?>');
                thumbnailPreview.src = '<?php echo htmlspecialchars($module['thumbnail'] ?? ''); ?>';
                thumbnailPreview.classList.toggle('hidden', !'<?php echo $module['thumbnail'] ? true : false; ?>');
            }
        });

        // Form validation and submission
        const form = document.getElementById('module-form');
        const saveModuleBtn = document.getElementById('save-module-btn');

        form.addEventListener('submit', (e) => {
            let hasError = false;
            const title = titleInput.value.trim();
            const description = descriptionInput.value.trim();
            const category = categorySelect.value;
            const thumbnail = thumbnailInput.files[0];
            const existingThumbnail = '<?php echo $module['thumbnail'] ? true : false; ?>';

            document.querySelectorAll('.error-message').forEach(el => el.remove());

            if (!title) {
                hasError = true;
                addErrorMessage('title', 'Module title is required');
            }

            if (!description) {
                hasError = true;
                addErrorMessage('description', 'Module description is required');
            }

            if (!category) {
                hasError = true;
                addErrorMessage('category', 'Module category is required');
            }

            if (!thumbnail && !existingThumbnail) {
                hasError = true;
                addErrorMessage('thumbnail', 'Module thumbnail is required');
            } else if (thumbnail && !['image/jpeg', 'image/png', 'image/gif'].includes(thumbnail.type)) {
                hasError = true;
                addErrorMessage('thumbnail', 'Only JPEG, PNG, and GIF files are allowed');
            }

            if (hasError) {
                e.preventDefault();
                saveModuleBtn.textContent = 'Save Module';
                saveModuleBtn.disabled = false;
            } else {
                const confirmation = confirm('Are you sure you want to update this module?');
                if (!confirmation) {
                    e.preventDefault();
                    saveModuleBtn.textContent = 'Save Module';
                    saveModuleBtn.disabled = false;
                } else {
                    saveModuleBtn.textContent = 'Saving...';
                    saveModuleBtn.disabled = true;
                }
            }
        });

        function addErrorMessage(fieldId, message) {
            const field = document.getElementById(fieldId);
            const error = document.createElement('p');
            error.className = 'mt-1 text-sm text-red-600 error-message';
            error.textContent = message;
            field.parentElement.appendChild(error);
        }
    </script>
</body>
</html>