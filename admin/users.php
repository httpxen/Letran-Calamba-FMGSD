<?php
session_start();
require_once '../db/db.php';

// Check if user is logged in and has Admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

// Fetch all users from the database (initial load)
$query = "SELECT id, fullname, email, profile_picture, last_login, is_online, current_device FROM users";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}

// Function to format the last login date
function formatLastLogin($last_login) {
    if (!$last_login) {
        return 'Never';
    }
    return date('Y-m-d h:i A', strtotime($last_login));
}

// Function to determine device icon and name based on User-Agent
function getDeviceInfo($user_agent) {
    if ($user_agent && preg_match('/Windows|Macintosh|Linux/i', $user_agent)) {
        return ['icon' => 'desktop', 'name' => 'Desktop'];
    }
    if ($user_agent && preg_match('/iPhone|iPad|Android/i', $user_agent)) {
        return ['icon' => 'phone', 'name' => 'Mobile'];
    }
    return ['icon' => 'question-mark-circle', 'name' => 'Unknown'];
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin - Manage Users</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@heroicons/react@2.0.18/24/outline/index.js"></script>
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
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
  </head>
  <body class="bg-gray-50 text-gray-900 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
      <!-- Sidebar -->
      <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 w-64 bg-white border-r shadow-lg transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:static md:shadow-none"
      >
        <div class="flex items-center space-x-3 p-6 border-b">
          <img
            src="../assets/images/favicon.ico"
            alt="Logo"
            class="w-10 h-10 rounded-md"
          />
          <h2 class="text-xl font-bold text-dashboard">
            <span class="text-red-600">Admin</span> Dashboard
          </h2>
        </div>

        <nav class="mt-6">
          <ul class="space-y-1 px-4">
            <li>
              <a
                href="dashboard.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-5 h-5 text-primary-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                  />
                </svg>
                Dashboard
              </a>
            </li>
            <li>
              <a
                href="user_approvals.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-5 h-5 text-primary-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                User Approvals
              </a>
            </li>
            <li>
              <a
                href="users.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 text-primary-600 font-medium transition-all duration-200"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-5 h-5 text-primary-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                  />
                </svg>
                Users
              </a>
            </li>
            <li>
              <a
                href="user_records.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-5 h-5 text-primary-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"
                  />
                </svg>
                User Records
              </a>
            </li>
            <li>
              <a
                href="module_list.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-primary-50 hover:text-primary-600 font-medium transition-all duration-200"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-5 h-5 text-primary-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"
                  />
                </svg>
                Modules
              </a>
            </li>
            <li>
              <a
                href="../logout.php"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-600 font-medium transition-all duration-200"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-5 h-5 text-red-600"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"
                  />
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
        <header
          class="bg-white shadow-sm flex justify-between items-center px-6 py-4"
        >
          <div class="flex items-center space-x-4">
            <button
              id="sidebar-toggle"
              class="md:hidden text-gray-600 hover:text-primary-600 focus:outline-none"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="w-6 h-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                />
              </svg>
            </button>
            <h1 class="text-xl font-semibold text-dashboard">
              Manage <span class="text-red-600">Users</span>
            </h1>
          </div>
        </header>

        <!-- Main content area -->
        <main class="flex-1 p-6 overflow-y-auto">
          <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Manage Users</h2>
            <div class="overflow-x-auto">
              <table class="w-full table-auto border-collapse">
                <thead>
                  <tr class="bg-gray-100">
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Profile</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Full Name</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Email</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Last Login</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Current Device</th>
                  </tr>
                </thead>
                <tbody id="users-table-body">
                  <?php while ($row = mysqli_fetch_assoc($result)) { 
                    $status_class = $row['is_online'] ? 'bg-green-500' : 'bg-red-500';
                    $status_text = $row['is_online'] ? 'Online' : 'Offline';
                    $profile_pic = $row['profile_picture'] ?: '../assets/images/profile-placeholder.png';
                    $device_info = getDeviceInfo($row['current_device']);
                    $device_icon = $device_info['icon'];
                    $device_name = $device_info['name'];
                  ?>
                    <tr data-user-id="<?php echo $row['id']; ?>" class="border-t">
                      <td class="px-4 py-3">
                        <img
                          src="<?php echo htmlspecialchars($profile_pic); ?>"
                          alt="Profile Picture"
                          class="w-10 h-10 rounded-full object-cover"
                        />
                      </td>
                      <td class="px-4 py-3 text-gray-700"><?php echo htmlspecialchars($row['fullname']); ?></td>
                      <td class="px-4 py-3 text-gray-700"><?php echo htmlspecialchars($row['email']); ?></td>
                      <td class="px-4 py-3 flex items-center space-x-2">
                        <span class="w-3 h-3 rounded-full <?php echo $status_class; ?>"></span>
                        <span class="text-gray-700"><?php echo $status_text; ?></span>
                      </td>
                      <td class="px-4 py-3 text-gray-700 last-login"><?php echo formatLastLogin($row['last_login']); ?></td>
                      <td class="px-4 py-3 text-gray-700 flex items-center space-x-2">
                        <?php if ($device_icon === 'desktop'): ?>
                          <svg class="w-6 h-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                          </svg>
                        <?php elseif ($device_icon === 'phone'): ?>
                          <svg class="w-6 h-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                          </svg>
                        <?php else: ?>
                          <svg class="w-6 h-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                          </svg>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($device_name); ?></span>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>
    </div>

    <script>
      // Function to format the last login date on the client side
      function formatLastLogin(last_login) {
        if (!last_login) {
          return 'Never';
        }
        const date = new Date(last_login);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        let hours = date.getHours();
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${year}-${month}-${day} ${hours}:${minutes} ${ampm}`;
      }

      // Function to determine device icon and name
      function getDeviceInfo(user_agent) {
        if (user_agent && user_agent.match(/Windows|Macintosh|Linux/i)) {
          return { icon: 'desktop', name: 'Desktop' };
        }
        if (user_agent && user_agent.match(/iPhone|iPad|Android/i)) {
          return { icon: 'phone', name: 'Mobile' };
        }
        return { icon: 'question-mark-circle', name: 'Unknown' };
      }

      // Function to fetch and update user data
      function updateUserTable() {
        fetch('fetch_users.php')
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(users => {
            const tableBody = document.getElementById('users-table-body');
            users.forEach(user => {
              const row = tableBody.querySelector(`tr[data-user-id="${user.id}"]`);
              if (row) {
                // Update status
                const statusCell = row.cells[3];
                statusCell.innerHTML = `
                  <span class="w-3 h-3 rounded-full ${user.status_class}"></span>
                  <span class="text-gray-700">${user.status}</span>
                `;
                // Update last login
                const lastLoginCell = row.cells[4];
                lastLoginCell.textContent = formatLastLogin(user.last_login);
                // Update current device
                const deviceCell = row.cells[5];
                const deviceInfo = getDeviceInfo(user.current_device);
                deviceCell.innerHTML = `
                  ${deviceInfo.icon === 'desktop' ? '<svg class="w-6 h-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" /></svg>' : ''}
                  ${deviceInfo.icon === 'phone' ? '<svg class="w-6 h-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>' : ''}
                  ${deviceInfo.icon === 'question-mark-circle' ? '<svg class="w-6 h-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>' : ''}
                  <span>${deviceInfo.name}</span>
                `;
              }
            });
          })
          .catch(error => console.error('Error fetching user data:', error));
      }

      // Initial call to update the table
      updateUserTable();

      // Set interval to update the table every 5 seconds
      setInterval(updateUserTable, 5000);

      // Sidebar toggle functionality
      const sidebar = document.getElementById('sidebar');
      const sidebarToggle = document.getElementById('sidebar-toggle');

      sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
      });

      // Close sidebar when clicking outside on mobile
      document.addEventListener('click', (e) => {
        if (
          !sidebar.contains(e.target) &&
          !sidebarToggle.contains(e.target) &&
          !sidebar.classList.contains('-translate-x-full')
        ) {
          sidebar.classList.add('-translate-x-full');
        }
      });
    </script>
  </body>
</html>
<?php
if ($result) {
    mysqli_free_result($result);
}
mysqli_close($conn);
?>