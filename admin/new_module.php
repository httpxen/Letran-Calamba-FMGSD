<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "Admin") {
  header("Location: login.php");
  exit();
}

$errors = [];
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $module_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
  $module_description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
  $module_category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

  // Server-side validation
  if (empty($module_title)) {
    $errors['title'] = 'Module title is required';
  } else {
    // Check for duplicate title
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE title = :title");
    $stmt->execute([':title' => $module_title]);
    if ($stmt->fetchColumn() > 0) {
      $errors['title'] = 'A module with this title already exists';
    }
  }

  if (empty($module_description)) {
    $errors['description'] = 'Module description is required';
  }

  if (empty($module_category)) {
    $errors['category'] = 'Module category is required';
  }

  // File upload handling
  if (
    isset($_FILES['thumbnail']) && 
    $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK && 
    is_uploaded_file($_FILES['thumbnail']['tmp_name'])
  ) {
    $file_tmp = $_FILES['thumbnail']['tmp_name'];
    $file_name = basename($_FILES['thumbnail']['name']);
    $file_type = mime_content_type($file_tmp);
    $file_size = $_FILES['thumbnail']['size'];

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB max

    // Verify if it's a valid image
    if (!in_array($file_type, $allowed_types)) {
      $errors['thumbnail'] = 'Only JPEG, PNG, and GIF files are allowed';
    } elseif ($file_size > $max_size) {
      $errors['thumbnail'] = 'File size must be less than 5MB';
    } elseif (!getimagesize($file_tmp)) {
      $errors['thumbnail'] = 'Uploaded file is not a valid image';
    } else {
      // Generate unique file name
      $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
      $unique_name = uniqid('thumb_', true) . '.' . $file_ext;
      $destination = '../Uploads/thumbnails/' . $unique_name;

      if (!is_dir('../Uploads/thumbnails/')) {
        mkdir('../Uploads/thumbnails/', 0755, true);
      }

      if (!move_uploaded_file($file_tmp, $destination)) {
        $errors['thumbnail'] = 'Failed to upload the thumbnail';
      }
    }
  } else {
    $errors['thumbnail'] = 'Module thumbnail is required';
  }

  // If no errors, insert into database
  if (empty($errors)) {
    try {
      $stmt = $pdo->prepare("INSERT INTO modules (title, description, thumbnail, category, created_at)
                            VALUES (:title, :description, :thumbnail, :category, NOW())");
      $stmt->execute([
        ':title' => $module_title,
        ':description' => $module_description,
        ':thumbnail' => $destination,
        ':category' => $module_category
      ]);
      $module_id = $pdo->lastInsertId();

      $_SESSION['success_message'] = 'Module created successfully!';
      header("Location: add_lesson.php?module_id=$module_id");
      exit();
    } catch (PDOException $e) {
      // Log error for debugging (in a production environment)
      error_log("Database error: " . $e->getMessage());
      $errors['database'] = 'An error occurred while saving the module. Please try again.';
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Create New Module</title>
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
              50: "#f5f3ff",
              600: "#4f46e5",
              700: "#4338ca",
            },
            dashboard: "#12234e",
          },
        },
      },
    };
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased min-h-screen">
  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-lg transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none">
      <div class="flex items-center space-x-3 p-6 border-b">
        <img src="../assets/images/favicon.ico" alt="Logo" class="w-10 h-10 rounded-md">
        <h2 class="text-xl font-bold text-dashboard"><span class="text-red-600">Admin</span> Dashboard</h2>
      </div>
      <nav class="mt-6">
        <ul class="space-y-1 px-4">
          <li>
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
              </svg>
              Dashboard
            </a>
          </li>
          <li>
            <a href="user_approvals.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>
              User Approvals
            </a>
          </li>
          <li>
            <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
              </svg>
              Users
            </a>
          </li>
          <li>
            <a href="user_records.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
              </svg>
              User Records
            </a>
          </li>
          <li>
            <a href="module_list.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-600 font-medium transition-colors duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
              </svg>
              Modules
            </a>
          </li>
          <li>
            <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-colors duration-200">
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
          <h1 class="text-xl font-semibold text-dashboard">Create New Module</h1>
        </div>
      </header>

      <!-- Main Content -->
      <main class="flex-1 p-6 overflow-y-auto">
        <div class="max-w-7xl mx-auto">
          <?php if ($success_message): ?>
            <div class="mb-6 p-4 bg-green-50 text-green-600 rounded-lg"><?php echo htmlspecialchars($success_message); ?></div>
          <?php endif; ?>
          <?php if (isset($errors['database'])): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-lg"><?php echo htmlspecialchars($errors['database']); ?></div>
          <?php endif; ?>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Form Section -->
            <div class="bg-white rounded-lg shadow-md p-8">
              <h2 class="text-2xl font-bold text-dashboard mb-6">New Module Details</h2>
              <form id="module-form" method="POST" enctype="multipart/form-data" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Module Title *</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" placeholder="Enter module title" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                    <?php if (isset($errors['title'])): ?>
                      <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['title']); ?></p>
                    <?php endif; ?>
                  </div>
                  <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select id="category" name="category" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200">
                      <option value="" disabled <?php echo empty($_POST['category']) ? 'selected' : ''; ?>>Select a category</option>
                      <option value="Safety" <?php echo ($_POST['category'] ?? '') === 'Safety' ? 'selected' : ''; ?>>Safety</option>
                      <option value="Environment" <?php echo ($_POST['category'] ?? '') === 'Environment' ? 'selected' : ''; ?>>Environment</option>
                      <option value="Health" <?php echo ($_POST['category'] ?? '') === 'Health' ? 'selected' : ''; ?>>Health</option>
                    </select>
                    <?php if (isset($errors['category'])): ?>
                      <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['category']); ?></p>
                    <?php endif; ?>
                  </div>
                </div>
                <div>
                  <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                  <textarea id="description" name="description" placeholder="Describe your module" rows="4" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all duration-200"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                  <?php if (isset($errors['description'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['description']); ?></p>
                  <?php endif; ?>
                </div>
                <div>
                  <label for="thumbnail" class="block text-sm font-medium text-gray-700 mb-1">Thumbnail * (JPEG/PNG/GIF, max 5MB)</label>
                  <div class="relative">
                    <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/gif" class="w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100 transition-all duration-200">
                  </div>
                  <?php if (isset($errors['thumbnail'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['thumbnail']); ?></p>
                  <?php endif; ?>
                </div>
                <div class="flex justify-end space-x-4">
                  <button type="button" onclick="window.location.href='module_list.php'" class="px-6 py-3 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 font-medium transition-all duration-200">Cancel</button>
                  <button type="submit" id="add-new-module-btn" class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-600 font-medium transition-all duration-200" disabled>Create Module</button>
                </div>
              </form>
            </div>
            <!-- Preview Section -->
            <div class="bg-white rounded-lg shadow-md p-8">
              <h2 class="text-2xl font-bold text-dashboard mb-6">Module Preview</h2>
              <div id="module-preview" class="bg-gray-50 rounded-lg p-6 transition-all duration-200">
                <div class="relative aspect-video bg-gray-200 rounded-lg overflow-hidden">
                  <img id="preview-thumbnail" src="../assets/images/placeholder.png" alt="Thumbnail Preview" class="w-full h-full object-cover">
                  <span id="preview-category" class="absolute top-2 right-2 px-2 py-1 text-xs font-medium text-primary-600 bg-primary-50 rounded">Select a category</span>
                </div>
                <h3 id="preview-title" class="mt-4 text-lg font-semibold text-gray-800">Enter module title</h3>
                <p id="preview-description" class="mt-2 text-sm text-gray-600 line-clamp-2">Describe your module</p>
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

    // Close sidebar when clicking outside on mobile
    document.addEventListener("click", (e) => {
      if (
        !sidebar.contains(e.target) &&
        !sidebarToggle.contains(e.target) &&
        !sidebar.classList.contains("-translate-x-full")
      ) {
        sidebar.classList.add("-translate-x-full");
      }
    });

    // Form validation and button state
    const form = document.getElementById('module-form');
    const addModuleBtn = document.getElementById('add-new-module-btn');
    const titleInput = document.getElementById('title');
    const descriptionInput = document.getElementById('description');
    const categorySelect = document.getElementById('category');
    const thumbnailInput = document.getElementById('thumbnail');
    const previewTitle = document.getElementById('preview-title');
    const previewDescription = document.getElementById('preview-description');
    const previewCategory = document.getElementById('preview-category');
    const previewThumbnail = document.getElementById('preview-thumbnail');

    function validateForm() {
      const title = titleInput.value.trim();
      const description = descriptionInput.value.trim();
      const category = categorySelect.value;
      const thumbnail = thumbnailInput.files[0];

      let isValid = true;

      // Clear previous error messages
      document.querySelectorAll('.error-message').forEach(el => el.remove());

      if (!title) {
        isValid = false;
        addErrorMessage('title', 'Module title is required');
      }
      if (!description) {
        isValid = false;
        addErrorMessage('description', 'Module description is required');
      }
      if (!category) {
        isValid = false;
        addErrorMessage('category', 'Module category is required');
      }
      if (!thumbnail) {
        isValid = false;
        addErrorMessage('thumbnail', 'Module thumbnail is required');
      } else if (!['image/jpeg', 'image/png', 'image/gif'].includes(thumbnail.type)) {
        isValid = false;
        addErrorMessage('thumbnail', 'Only JPEG, PNG, and GIF files are allowed');
      } else if (thumbnail.size > 5 * 1024 * 1024) {
        isValid = false;
        addErrorMessage('thumbnail', 'File size must be less than 5MB');
      }

      addModuleBtn.disabled = !isValid;
    }

    function addErrorMessage(fieldId, message) {
      const field = document.getElementById(fieldId);
      const error = document.createElement('p');
      error.className = 'mt-1 text-sm text-red-600 error-message';
      error.innerText = message;
      field.parentElement.appendChild(error);
    }

    // Real-time validation and preview updates
    titleInput.addEventListener('input', () => {
      previewTitle.textContent = titleInput.value.trim() || 'Enter module title';
      validateForm();
    });

    descriptionInput.addEventListener('input', () => {
      previewDescription.textContent = descriptionInput.value.trim() || 'Describe your module';
      validateForm();
    });

    categorySelect.addEventListener('change', () => {
      previewCategory.textContent = categorySelect.value || 'Select a category';
      validateForm();
    });

    thumbnailInput.addEventListener('change', () => {
      const file = thumbnailInput.files[0];
      if (file && ['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
        const reader = new FileReader();
        reader.onload = (e) => {
          previewThumbnail.src = e.target.result;
        };
        reader.readAsDataURL(file);
      } else {
        previewThumbnail.src = '../assets/images/placeholder.png';
      }
      validateForm();
    });

    form.addEventListener('submit', (e) => {
      validateForm();
      if (addModuleBtn.disabled) {
        e.preventDefault();
      } else {
        addModuleBtn.innerText = 'Creating...';
        addModuleBtn.disabled = true;
      }
    });

    // Initial validation
    validateForm();
  </script>
</body>
</html>