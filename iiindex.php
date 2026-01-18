<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NimCredit Agent Portal - Multi-Level Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* ... existing CSS styles ... */
        
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .super-admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .agent-badge {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        
        .link-status {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-opened {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .hierarchy-indicator {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .hierarchy-superadmin { background: #667eea; }
        .hierarchy-admin { background: #43e97b; }
        .hierarchy-agent { background: #fa709a; }
        
        .hierarchy-line {
            width: 2px;
            background: #e5e7eb;
            margin-left: 8px;
            margin-right: 8px;
        }
        
        .activity-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .dot-active { background: #10b981; }
        .dot-inactive { background: #ef4444; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="loader"></div>
    </div>

    <!-- Login Container -->
    <div id="loginContainer" class="min-h-screen flex items-center justify-center gradient-bg">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">🔐 NimCredit Portal</h1>
                <p class="text-gray-600 mt-2">Multi-Level Admin System</p>
            </div>
            
            <form id="loginForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                    <input type="text" id="username" required 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none transition">
                </div>
                
                <div class="password-field">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" required 
                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none transition pr-10">
                    <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
                    <p id="loginError" class="text-red-500 text-sm mt-2 hidden">Invalid credentials!</p>
                </div>
                
                <button type="submit" class="w-full gradient-bg text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                    Login
                </button>
            </form>
        </div>
    </div>

    <!-- Main Container (Hidden by default) -->
    <div id="mainContainer" class="hidden">
        
        <!-- Top Navigation -->
        <nav class="gradient-bg text-white shadow-lg">
            <div class="container mx-auto px-6 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold">NimCredit Portal</h1>
                        <div class="flex items-center mt-1">
                            <p class="text-sm opacity-90" id="userWelcome">Welcome, </p>
                            <span id="userRoleBadge" class="role-badge ml-2"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span id="userHierarchy" class="text-sm opacity-90"></span>
                        <button onclick="logout()" class="bg-white text-purple-600 px-6 py-2 rounded-lg font-semibold hover:bg-gray-100 transition">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Super Admin Dashboard -->
        <div id="superAdminDashboard" class="hidden container mx-auto px-6 py-8">
            
            <!-- Tabs -->
            <div class="flex gap-4 mb-8 flex-wrap">
                <button onclick="switchTab('dashboard')" id="tabDashboard" class="tab-active px-6 py-3 rounded-lg font-semibold transition">
                    📊 Dashboard
                </button>
                <button onclick="switchTab('hierarchy')" id="tabHierarchy" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    👥 Hierarchy
                </button>
                <button onclick="switchTab('upi')" id="tabUpi" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    💳 UPI Management
                </button>
                <button onclick="switchTab('analytics')" id="tabAnalytics" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    📈 Analytics
                </button>
                <button onclick="switchTab('admins')" id="tabAdmins" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    🏢 Admin Management
                </button>
                <button onclick="switchTab('agents')" id="tabAgents" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    👤 Agent Management
                </button>
                <button onclick="switchTab('alllinks')" id="tabAlllinks" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    🔗 All Links
                </button>
            </div>

            <!-- Dashboard Tab -->
            <div id="contentDashboard" class="tab-content">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card">
                        <div class="text-3xl font-bold" id="statTotalLinks">0</div>
                        <div class="text-sm opacity-90 mt-2">Total Links</div>
                        <div class="text-xs opacity-70 mt-1" id="statLinksBreakdown"></div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="text-3xl font-bold" id="statTotalPayments">0</div>
                        <div class="text-sm opacity-90 mt-2">Total Payments (₹)</div>
                        <div class="text-xs opacity-70 mt-1" id="statPaymentsBreakdown"></div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="text-3xl font-bold" id="statActiveUsers">0</div>
                        <div class="text-sm opacity-90 mt-2">Active Users</div>
                        <div class="text-xs opacity-70 mt-1" id="statUsersBreakdown"></div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <div class="text-3xl font-bold" id="statConversion">0%</div>
                        <div class="text-sm opacity-90 mt-2">Conversion Rate</div>
                        <div class="text-xs opacity-70 mt-1">Links → Payments</div>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold mb-4">👥 User Distribution</h3>
                        <div id="userDistributionChart" class="space-y-3">
                            <!-- Dynamic content -->
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold mb-4">💰 Revenue Today</h3>
                        <div class="text-4xl font-bold text-green-600 mb-2" id="revenueToday">₹0</div>
                        <div class="text-sm text-gray-600" id="revenueDetails"></div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold mb-4">📈 Performance</h3>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Link Open Rate</span>
                                    <span id="openRate">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div id="openRateBar" class="bg-blue-500 h-2 rounded-full" style="width: 0%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Payment Success Rate</span>
                                    <span id="paymentRate">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div id="paymentRateBar" class="bg-green-500 h-2 rounded-full" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Recent Activities</h2>
                        <div class="flex gap-2">
                            <select id="activityFilter" onchange="loadRecentActivities()" class="border rounded-lg px-3 py-1">
                                <option value="all">All Activities</option>
                                <option value="login">Logins</option>
                                <option value="link">Link Generated</option>
                                <option value="payment">Payments</option>
                            </select>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">User</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Role</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Activity</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Details</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Time</th>
                                </tr>
                            </thead>
                            <tbody id="recentActivities">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Hierarchy Tab -->
            <div id="contentHierarchy" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <h2 class="text-2xl font-bold mb-6">👥 User Hierarchy</h2>
                    
                    <div class="space-y-6">
                        <!-- Super Admin Level -->
                        <div class="p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl">
                            <div class="flex items-center mb-4">
                                <span class="hierarchy-indicator hierarchy-superadmin"></span>
                                <h3 class="text-lg font-bold">Super Admin</h3>
                                <span class="role-badge super-admin-badge ml-2">SUPER ADMIN</span>
                            </div>
                            <div class="pl-6">
                                <div id="superAdminList" class="space-y-2">
                                    <!-- Dynamic content -->
                                </div>
                            </div>
                        </div>

                        <!-- Admin Level -->
                        <div class="p-4 bg-gradient-to-r from-green-50 to-blue-50 rounded-xl ml-8 mt-6">
                            <div class="flex items-center mb-4">
                                <span class="hierarchy-indicator hierarchy-admin"></span>
                                <h3 class="text-lg font-bold">Admins</h3>
                                <span class="role-badge admin-badge ml-2">ADMIN</span>
                            </div>
                            <div id="adminHierarchy" class="pl-6">
                                <!-- Dynamic content will show admins and their agents -->
                            </div>
                        </div>

                        <!-- Agent Level -->
                        <div class="p-4 bg-gradient-to-r from-pink-50 to-yellow-50 rounded-xl ml-16 mt-6">
                            <div class="flex items-center mb-4">
                                <span class="hierarchy-indicator hierarchy-agent"></span>
                                <h3 class="text-lg font-bold">Agents</h3>
                                <span class="role-badge agent-badge ml-2">AGENT</span>
                            </div>
                            <div id="agentList" class="pl-6">
                                <!-- Dynamic content -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-lg">
                        <p class="text-sm text-yellow-800">
                            <strong>📊 Hierarchy Structure:</strong><br>
                            1. Super Admin → Can manage everything<br>
                            2. Admin → Can manage their agents and UPI<br>
                            3. Agent → Can only generate links (No UPI change)
                        </p>
                    </div>
                </div>
            </div>

            <!-- UPI Management Tab -->
            <div id="contentUpi" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <h2 class="text-2xl font-bold mb-6">💳 UPI ID Management</h2>
                    
                    <div class="space-y-6">
                        <!-- Master UPI (For Super Admin Only) -->
                        <div class="p-6 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl">
                            <h3 class="text-lg font-bold mb-4 flex items-center">
                                <span class="hierarchy-indicator hierarchy-superadmin mr-2"></span>
                                Master UPI (Super Admin)
                            </h3>
                            <p class="text-sm text-gray-600 mb-4">This UPI is used when no admin-specific UPI is set</p>
                            <div class="flex gap-3">
                                <input type="text" id="masterUpi" placeholder="master@upi" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                <button onclick="saveUpi('master')" class="gradient-bg text-white px-6 py-3 rounded-lg font-semibold">
                                    Save Master
                                </button>
                            </div>
                        </div>

                        <!-- Admin UPI Assignment -->
                        <div class="p-6 bg-gradient-to-r from-green-50 to-blue-50 rounded-xl">
                            <h3 class="text-lg font-bold mb-4 flex items-center">
                                <span class="hierarchy-indicator hierarchy-admin mr-2"></span>
                                Assign UPI to Admins
                            </h3>
                            <div class="space-y-4">
                                <div id="adminUpiAssignment">
                                    <!-- Dynamic admin UPI assignment fields -->
                                </div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-lg">
                            <p class="text-sm text-yellow-800">
                                ⚠️ <strong>Note:</strong><br>
                                • Master UPI is used as default for all admins<br>
                                • Admin-specific UPI overrides master UPI for that admin's agents<br>
                                • Agents cannot change UPI IDs
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Tab -->
            <div id="contentAnalytics" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-6">📈 Detailed Analytics</h2>
                    
                    <!-- Filter Controls -->
                    <div class="flex gap-4 mb-6 flex-wrap">
                        <select id="analyticsFilterUser" onchange="loadAnalytics()" class="border rounded-lg px-4 py-2">
                            <option value="all">All Users</option>
                            <option value="admin">Admins Only</option>
                            <option value="agent">Agents Only</option>
                        </select>
                        <select id="analyticsFilterDate" onchange="loadAnalytics()" class="border rounded-lg px-4 py-2">
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="all">All Time</option>
                        </select>
                        <button onclick="exportAnalytics()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                            📥 Export CSV
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">User</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Role</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Links Created</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Opened</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Paid</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Total Amount (₹)</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Success Rate</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Last Activity</th>
                                </tr>
                            </thead>
                            <tbody id="analyticsTable">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Admin Management Tab -->
            <div id="contentAdmins" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-8 max-w-4xl">
                    <h2 class="text-2xl font-bold mb-6">🏢 Admin Management</h2>
                    
                    <!-- Add New Admin -->
                    <div class="mb-8 p-6 bg-gradient-to-r from-green-50 to-blue-50 rounded-xl">
                        <h3 class="text-lg font-semibold mb-4 flex items-center">
                            <span class="hierarchy-indicator hierarchy-admin mr-2"></span>
                            Add New Admin
                        </h3>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <input type="text" id="newAdminName" placeholder="Admin Name" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                <input type="text" id="newAdminUsername" placeholder="Username" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                            </div>
                            <div class="flex gap-3">
                                <div class="password-field flex-1">
                                    <input type="password" id="newAdminPassword" placeholder="Password" 
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none pr-10">
                                    <span class="toggle-password" onclick="togglePasswordVisibility('newAdminPassword')">👁️</span>
                                </div>
                                <button onclick="addAdmin()" class="gradient-bg text-white px-6 py-3 rounded-lg font-semibold">
                                    Add Admin
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Admins -->
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Existing Admins</h3>
                        <div id="adminsList" class="space-y-4">
                            <!-- Dynamic content -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agent Management Tab -->
            <div id="contentAgents" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-8 max-w-4xl">
                    <h2 class="text-2xl font-bold mb-6">👤 Agent Management</h2>
                    
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-4">Add New Agent</h3>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <input type="text" id="newAgentName" placeholder="Agent Name" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                <input type="text" id="newAgentUsername" placeholder="Username" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                            </div>
                            <div class="flex gap-3">
                                <div class="password-field flex-1">
                                    <input type="password" id="newAgentPassword" placeholder="Password" 
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none pr-10">
                                    <span class="toggle-password" onclick="togglePasswordVisibility('newAgentPassword')">👁️</span>
                                </div>
                                <select id="newAgentAdmin" class="px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                    <option value="">Select Admin (Optional)</option>
                                </select>
                                <button onclick="addAgent()" class="gradient-bg text-white px-6 py-3 rounded-lg font-semibold">
                                    Add Agent
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-4">Existing Agents</h3>
                        <div id="agentsList" class="space-y-4">
                            <!-- Dynamic content -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Links Tab -->
            <div id="contentAlllinks" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-6">🔗 All Generated Links</h2>
                    
                    <!-- Filter Controls -->
                    <div class="flex gap-4 mb-6 flex-wrap">
                        <select id="linkFilterStatus" onchange="loadAllLinks()" class="border rounded-lg px-4 py-2">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="opened">Opened</option>
                            <option value="paid">Paid</option>
                            <option value="expired">Expired</option>
                        </select>
                        <select id="linkFilterUser" onchange="loadAllLinks()" class="border rounded-lg px-4 py-2">
                            <option value="all">All Users</option>
                        </select>
                        <input type="date" id="linkFilterDate" onchange="loadAllLinks()" class="border rounded-lg px-4 py-2">
                        <button onclick="exportLinks()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
                            📥 Export Links
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">ID</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Customer</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Amount</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Generated By</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">UPI ID</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Created</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="allLinksTable">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex justify-between items-center mt-6">
                        <div class="text-sm text-gray-600">
                            Showing <span id="linksStart">0</span>-<span id="linksEnd">0</span> of <span id="linksTotal">0</span> links
                        </div>
                        <div class="flex gap-2">
                            <button onclick="prevLinksPage()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                                ← Previous
                            </button>
                            <span id="linksPageInfo" class="px-4 py-2">Page 1</span>
                            <button onclick="nextLinksPage()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                                Next →
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Dashboard -->
        <div id="adminDashboard" class="hidden container mx-auto px-6 py-8">
            
            <!-- Admin Tabs -->
            <div class="flex gap-4 mb-8">
                <button onclick="adminSwitchTab('dashboard')" id="adminTabDashboard" class="tab-active px-6 py-3 rounded-lg font-semibold transition">
                    📊 My Dashboard
                </button>
                <button onclick="adminSwitchTab('agents')" id="adminTabAgents" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    👥 My Agents
                </button>
                <button onclick="adminSwitchTab('upi')" id="adminTabUpi" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    💳 My UPI
                </button>
                <button onclick="adminSwitchTab('links')" id="adminTabLinks" class="px-6 py-3 bg-white rounded-lg font-semibold hover:bg-gray-100 transition">
                    🔗 My Links
                </button>
            </div>

            <!-- Admin Dashboard Tab -->
            <div id="adminContentDashboard" class="tab-content">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card">
                        <div class="text-3xl font-bold" id="adminStatTotalLinks">0</div>
                        <div class="text-sm opacity-90 mt-2">My Links Generated</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="text-3xl font-bold" id="adminStatAgents">0</div>
                        <div class="text-sm opacity-90 mt-2">My Agents</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="text-3xl font-bold" id="adminStatPaid">0</div>
                        <div class="text-sm opacity-90 mt-2">Total Payments (₹)</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <div class="text-3xl font-bold" id="adminStatSuccess">0%</div>
                        <div class="text-sm opacity-90 mt-2">Success Rate</div>
                    </div>
                </div>

                <!-- My Agents Performance -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 class="text-xl font-bold mb-4">My Agents Performance</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Agent</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Links Today</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Total Links</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Payments</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Success Rate</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody id="adminAgentsPerformance">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold mb-4">Recent Activities (My Team)</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Agent</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Customer</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Amount</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Time</th>
                                </tr>
                            </thead>
                            <tbody id="adminRecentActivities">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Admin Agents Tab -->
            <div id="adminContentAgents" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-8 max-w-4xl">
                    <h2 class="text-2xl font-bold mb-6">👥 My Agents Management</h2>
                    
                    <!-- Add New Agent -->
                    <div class="mb-8 p-6 bg-gradient-to-r from-green-50 to-blue-50 rounded-xl">
                        <h3 class="text-lg font-semibold mb-4">Add New Agent</h3>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <input type="text" id="adminNewAgentName" placeholder="Agent Name" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                <input type="text" id="adminNewAgentUsername" placeholder="Username" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                            </div>
                            <div class="flex gap-3">
                                <div class="password-field flex-1">
                                    <input type="password" id="adminNewAgentPassword" placeholder="Password" 
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none pr-10">
                                    <span class="toggle-password" onclick="togglePasswordVisibility('adminNewAgentPassword')">👁️</span>
                                </div>
                                <button onclick="adminAddAgent()" class="gradient-bg text-white px-6 py-3 rounded-lg font-semibold">
                                    Add Agent
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- My Existing Agents -->
                    <div>
                        <h3 class="text-lg font-semibold mb-4">My Agents</h3>
                        <div id="adminAgentsList" class="space-y-4">
                            <!-- Dynamic content -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin UPI Tab -->
            <div id="adminContentUpi" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-8 max-w-2xl">
                    <h2 class="text-2xl font-bold mb-6">💳 My UPI Settings</h2>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">My UPI ID</label>
                            <p class="text-sm text-gray-600 mb-4">This UPI will be used for all links generated by you and your agents</p>
                            <div class="flex gap-3">
                                <input type="text" id="adminMyUpi" placeholder="yourname@upi" 
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                <button onclick="adminSaveUpi()" class="gradient-bg text-white px-6 py-3 rounded-lg font-semibold">
                                    Save UPI
                                </button>
                            </div>
                        </div>

                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg">
                            <p class="text-sm text-blue-800">
                                <strong>ℹ️ Information:</strong><br>
                                • Your UPI ID overrides the master UPI for your account<br>
                                • All links generated by you and your agents will use this UPI<br>
                                • Leave empty to use the master UPI
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Links Tab -->
            <div id="adminContentLinks" class="tab-content hidden">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-6">🔗 Links Generated by My Team</h2>
                    
                    <!-- Filter -->
                    <div class="flex gap-4 mb-6">
                        <select id="adminLinkFilter" onchange="loadAdminLinks()" class="border rounded-lg px-4 py-2">
                            <option value="all">All Links</option>
                            <option value="my">My Links Only</option>
                            <option value="agents">Agents Links Only</option>
                            <option value="paid">Paid Only</option>
                            <option value="pending">Pending Only</option>
                        </select>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">ID</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Customer</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Amount</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Generated By</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Created</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold">Link</th>
                                </tr>
                            </thead>
                            <tbody id="adminLinksTable">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agent Portal -->
        <div id="agentPortal" class="hidden container mx-auto px-6 py-8">
            <div class="max-w-6xl mx-auto">
                <!-- Agent Dashboard -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card">
                        <div class="text-3xl font-bold" id="agentStatLinks">0</div>
                        <div class="text-sm opacity-90 mt-2">Links Generated</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="text-3xl font-bold" id="agentStatPaid">0</div>
                        <div class="text-sm opacity-90 mt-2">Payments Received (₹)</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="text-3xl font-bold" id="agentStatSuccess">0%</div>
                        <div class="text-sm opacity-90 mt-2">Success Rate</div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column: Link Generation -->
                    <div class="bg-white rounded-xl shadow-lg p-8">
                        <h2 class="text-2xl font-bold mb-6">🚀 Generate Secure Payment Link</h2>
                        
                        <form id="paymentForm" class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Customer Name *</label>
                                <input type="text" id="customerName" required 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number *</label>
                                <input type="tel" id="customerPhone" required maxlength="10" pattern="[0-9]{10}"
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none"
                                    placeholder="10-digit number">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Number of Loans for this Customer</label>
                                <select id="loanCount" onchange="toggleLoanFields()" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                    <option value="1">1 Loan</option>
                                    <option value="2">2 Loans</option>
                                    <option value="3">3 Loans</option>
                                </select>
                            </div>

                            <!-- Loan 1 -->
                            <div class="loan-field p-4 bg-purple-50 rounded-lg">
                                <h3 class="font-semibold mb-3">Loan 1</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (₹) *</label>
                                        <input type="number" id="amount1" required min="100"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Reference</label>
                                        <input type="text" id="ref1"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                    </div>
                                </div>
                            </div>

                            <!-- Loan 2 -->
                            <div id="loan2Field" class="loan-field p-4 bg-blue-50 rounded-lg hidden">
                                <h3 class="font-semibold mb-3">Loan 2</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (₹)</label>
                                        <input type="number" id="amount2" min="100"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Reference</label>
                                        <input type="text" id="ref2"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                    </div>
                                </div>
                            </div>

                            <!-- Loan 3 -->
                            <div id="loan3Field" class="loan-field p-4 bg-green-50 rounded-lg hidden">
                                <h3 class="font-semibold mb-3">Loan 3</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (₹)</label>
                                        <input type="number" id="amount3" min="100"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Reference</label>
                                        <input type="text" id="ref3"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:outline-none">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="w-full gradient-bg text-white py-4 rounded-lg font-semibold text-lg hover:opacity-90 transition">
                                🚀 Generate Links & Send via WhatsApp
                            </button>
                        </form>

                        <!-- Result Section -->
                        <div id="resultSection" class="hidden mt-8 p-6 bg-green-50 border-2 border-green-200 rounded-lg">
                            <h3 class="text-lg font-bold text-green-800 mb-4">✅ Links Generated Successfully!</h3>
                            <div id="generatedLinks" class="space-y-3">
                                <!-- Dynamic links will appear here -->
                            </div>
                            <p class="text-sm text-gray-600 mt-4">
                                ⏱️ Links will automatically expire 20 minutes after customer opens them.<br>
                                🔒 Each message is randomized to prevent WhatsApp bans.
                            </p>
                        </div>
                    </div>

                    <!-- Right Column: Agent Info & Recent Links -->
                    <div class="space-y-6">
                        <!-- Agent Info -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-bold mb-4">👤 My Information</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Role:</span>
                                    <span class="font-semibold"><span class="role-badge agent-badge">AGENT</span></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Assigned Admin:</span>
                                    <span class="font-semibold" id="agentAssignedAdmin">Loading...</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">UPI ID Used:</span>
                                    <span class="font-semibold" id="agentUpiInfo">Loading...</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Joined On:</span>
                                    <span class="font-semibold" id="agentJoinedDate">Loading...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Links -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-bold mb-4">📋 My Recent Links</h3>
                            <div class="space-y-3" id="agentRecentLinks">
                                <!-- Dynamic content -->
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-bold mb-4">📊 Quick Stats</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Links Today:</span>
                                    <span class="font-semibold" id="agentLinksToday">0</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Pending Payments:</span>
                                    <span class="font-semibold" id="agentPending">0</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Successful Today:</span>
                                    <span class="font-semibold" id="agentSuccessfulToday">0</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Earned:</span>
                                    <span class="font-semibold text-green-600" id="agentTotalEarned">₹0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // ==================== CONFIGURATION ====================
        const DOMAIN = 'https://nimcreditrepay.us.cc';
        const EXPIRY_HOURS = 48;
        const LINKS_PER_PAGE = 20;
        
        // Initialize storage structure
        function initializeStorage() {
            // Users storage with hierarchy
            if (!localStorage.getItem('nimcredit_users')) {
                const defaultUsers = {
                    'superadmin': { 
                        password: 'admin@123', 
                        actualPassword: 'admin@123',
                        role: 'superadmin', 
                        name: 'Super Admin',
                        showPassword: false,
                        created_at: Date.now(),
                        last_login: null,
                        assigned_to: null,
                        status: 'active'
                    },
                    'admin1': { 
                        password: 'admin@123', 
                        actualPassword: 'admin@123',
                        role: 'admin', 
                        name: 'Admin 1',
                        showPassword: false,
                        created_at: Date.now(),
                        last_login: null,
                        assigned_to: null,
                        status: 'active'
                    },
                    'agent1': { 
                        password: 'agent@123', 
                        actualPassword: 'agent@123',
                        role: 'agent', 
                        name: 'Agent 1',
                        showPassword: false,
                        created_at: Date.now(),
                        last_login: null,
                        assigned_to: 'admin1', // Assigned to admin1
                        status: 'active'
                    }
                };
                localStorage.setItem('nimcredit_users', JSON.stringify(defaultUsers));
            }

            // UPI storage with hierarchy
            if (!localStorage.getItem('nimcredit_upi')) {
                const defaultUpi = {
                    master: 'master@nimcredit', // Master UPI for super admin
                    admins: {
                        'admin1': 'admin1@nimcredit' // Admin-specific UPI
                    },
                    agents: {} // Agents use assigned admin's UPI or master
                };
                localStorage.setItem('nimcredit_upi', JSON.stringify(defaultUpi));
            }

            // Links storage with enhanced tracking
            if (!localStorage.getItem('nimcredit_links')) {
                localStorage.setItem('nimcredit_links', JSON.stringify([]));
            }

            // Activities log
            if (!localStorage.getItem('nimcredit_activities')) {
                localStorage.setItem('nimcredit_activities', JSON.stringify([]));
            }
        }

        // ==================== UTILITY FUNCTIONS ====================
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        function showLoader() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
        }

        function hideLoader() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }

        function generateShortId() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            for (let i = 0; i < 6; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        }

        function generateShortUrl(shortId, amount, phone, name, upi, ref = '') {
            const params = new URLSearchParams({
                id: shortId,
                amt: amount,
                phone: phone,
                name: name,
                upi: upi
            });
            if (ref) params.append('sn', ref);
            
            return `${DOMAIN}/pay.html?${params.toString()}`;
        }

        function generateAntiBanMessage(name, amount, link, loanNum = '') {
            const greetings = ["Hi", "Hello", "Dear", "Namaste", "Greetings"];
            const bodies = [
                `Your pending amount of Rs ${amount}${loanNum} needs attention.`,
                `Kindly clear your due payment of Rs ${amount}${loanNum}.`,
                `This is a reminder regarding pending dues: Rs ${amount}${loanNum}.`,
                `Payment request for Rs ${amount}${loanNum} is generated.`,
                `Please complete your transaction of Rs ${amount}${loanNum}.`
            ];
            const footers = ["Pay securely:", "Click to pay:", "Secure payment link:", "Payment Link:", "Link (Valid for 48h):"];
            const closings = ["- NimCredit Team", "- Team NimCredit", "- NC Accounts", "- Automated Alert"];
            
            const r = arr => arr[Math.floor(Math.random() * arr.length)];
            
            return `${r(greetings)} ${name.toUpperCase()},\n\n${r(bodies)}\n\n${r(footers)} ${link}\n\n${r(closings)}`;
        }

        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const isPassword = field.type === 'password';
            field.type = isPassword ? 'text' : 'password';
        }

        function logActivity(username, activity, details = '') {
            const activities = JSON.parse(localStorage.getItem('nimcredit_activities') || '[]');
            activities.push({
                username,
                activity,
                details,
                timestamp: Date.now()
            });
            
            // Keep only last 1000 activities
            if (activities.length > 1000) {
                activities.splice(0, activities.length - 1000);
            }
            
            localStorage.setItem('nimcredit_activities', JSON.stringify(activities));
        }

        // ==================== AUTHENTICATION ====================
        function checkAuth() {
            const currentUser = sessionStorage.getItem('nimcredit_current_user');
            if (currentUser) {
                const userData = JSON.parse(currentUser);
                document.getElementById('loginContainer').classList.add('hidden');
                document.getElementById('mainContainer').classList.remove('hidden');
                
                // Set user welcome message
                const roleBadge = {
                    'superadmin': 'SUPER ADMIN',
                    'admin': 'ADMIN',
                    'agent': 'AGENT'
                }[userData.role];
                
                document.getElementById('userWelcome').textContent = `Welcome, ${userData.name}`;
                document.getElementById('userRoleBadge').textContent = roleBadge;
                document.getElementById('userRoleBadge').className = `role-badge ${userData.role}-badge ml-2`;
                
                // Show appropriate dashboard
                if (userData.role === 'superadmin') {
                    document.getElementById('superAdminDashboard').classList.remove('hidden');
                    loadSuperAdminData();
                } else if (userData.role === 'admin') {
                    document.getElementById('adminDashboard').classList.remove('hidden');
                    loadAdminDashboard();
                } else {
                    document.getElementById('agentPortal').classList.remove('hidden');
                    loadAgentDashboard();
                }
                
                // Log login activity
                logActivity(userData.username, 'login', `Logged in as ${userData.role}`);
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            
            if (users[username] && users[username].password === password) {
                // Update last login
                users[username].last_login = Date.now();
                localStorage.setItem('nimcredit_users', JSON.stringify(users));
                
                sessionStorage.setItem('nimcredit_current_user', JSON.stringify({
                    username: username,
                    role: users[username].role,
                    name: users[username].name,
                    assigned_to: users[username].assigned_to
                }));
                
                checkAuth();
            } else {
                document.getElementById('loginError').classList.remove('hidden');
                setTimeout(() => {
                    document.getElementById('loginError').classList.add('hidden');
                }, 3000);
            }
        });

        function logout() {
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            if (currentUser) {
                logActivity(currentUser.username, 'logout');
            }
            sessionStorage.removeItem('nimcredit_current_user');
            location.reload();
        }

        // ==================== SUPER ADMIN FUNCTIONS ====================
        function switchTab(tabName) {
            // Remove active class from all tabs
            document.querySelectorAll('[id^="tab"]').forEach(tab => {
                tab.classList.remove('tab-active');
                tab.classList.add('bg-white', 'hover:bg-gray-100');
            });
            
            // Hide all content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show selected tab
            const tabId = 'tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1);
            document.getElementById(tabId).classList.add('tab-active');
            document.getElementById(tabId).classList.remove('bg-white', 'hover:bg-gray-100');
            document.getElementById('content' + tabName.charAt(0).toUpperCase() + tabName.slice(1)).classList.remove('hidden');
            
            // Load specific tab data
            if (tabName === 'dashboard') {
                loadSuperAdminData();
            } else if (tabName === 'hierarchy') {
                loadHierarchy();
            } else if (tabName === 'analytics') {
                loadAnalytics();
            } else if (tabName === 'admins') {
                loadAdminsList();
            } else if (tabName === 'agents') {
                loadAgentsList();
            } else if (tabName === 'alllinks') {
                loadAllLinks();
            }
        }

        function loadSuperAdminData() {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const links = JSON.parse(localStorage.getItem('nimcredit_links'));
            const now = Date.now();
            
            // Calculate stats
            const totalLinks = links.length;
            const todayLinks = links.filter(l => {
                const linkDate = new Date(l.created_at);
                const today = new Date();
                return linkDate.toDateString() === today.toDateString();
            });
            
            const paidLinks = links.filter(l => l.paid);
            const totalAmount = paidLinks.reduce((sum, link) => sum + parseFloat(link.amount), 0);
            
            // User counts
            const adminCount = Object.values(users).filter(u => u.role === 'admin').length;
            const agentCount = Object.values(users).filter(u => u.role === 'agent').length;
            const activeUsers = Object.values(users).filter(u => u.status === 'active').length;
            
            // Conversion rate
            const conversionRate = totalLinks > 0 ? ((paidLinks.length / totalLinks) * 100).toFixed(1) : 0;
            
            // Update UI
            document.getElementById('statTotalLinks').textContent = totalLinks;
            document.getElementById('statTotalPayments').textContent = '₹' + totalAmount.toLocaleString('en-IN');
            document.getElementById('statActiveUsers').textContent = activeUsers;
            document.getElementById('statConversion').textContent = conversionRate + '%';
            
            // Breakdowns
            document.getElementById('statLinksBreakdown').innerHTML = `
                <span class="opacity-80">Today: ${todayLinks.length}</span>
            `;
            
            document.getElementById('statPaymentsBreakdown').innerHTML = `
                <span class="opacity-80">Today: ₹${todayLinks.filter(l => l.paid).reduce((sum, l) => sum + parseFloat(l.amount), 0)}</span>
            `;
            
            document.getElementById('statUsersBreakdown').innerHTML = `
                <span class="opacity-80">Admins: ${adminCount} | Agents: ${agentCount}</span>
            `;
            
            // User distribution
            const userDistribution = document.getElementById('userDistributionChart');
            userDistribution.innerHTML = `
                <div class="flex items-center justify-between">
                    <span>Super Admin</span>
                    <span class="font-semibold">1</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Admins</span>
                    <span class="font-semibold">${adminCount}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Agents</span>
                    <span class="font-semibold">${agentCount}</span>
                </div>
            `;
            
            // Revenue today
            const todayRevenue = todayLinks.filter(l => l.paid).reduce((sum, l) => sum + parseFloat(l.amount), 0);
            document.getElementById('revenueToday').textContent = '₹' + todayRevenue.toLocaleString('en-IN');
            
            // Rates
            const openedLinks = links.filter(l => l.opened).length;
            const openRate = totalLinks > 0 ? ((openedLinks / totalLinks) * 100).toFixed(1) : 0;
            const paymentRate = openedLinks > 0 ? ((paidLinks.length / openedLinks) * 100).toFixed(1) : 0;
            
            document.getElementById('openRate').textContent = openRate + '%';
            document.getElementById('paymentRate').textContent = paymentRate + '%';
            document.getElementById('openRateBar').style.width = openRate + '%';
            document.getElementById('paymentRateBar').style.width = paymentRate + '%';
            
            // Load recent activities
            loadRecentActivities();
        }

        function loadRecentActivities() {
            const activities = JSON.parse(localStorage.getItem('nimcredit_activities') || '[]');
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const filter = document.getElementById('activityFilter').value;
            
            // Filter activities
            let filteredActivities = activities;
            if (filter !== 'all') {
                filteredActivities = activities.filter(a => {
                    if (filter === 'login') return a.activity === 'login';
                    if (filter === 'link') return a.activity.includes('link');
                    if (filter === 'payment') return a.activity.includes('payment');
                    return true;
                });
            }
            
            // Get last 20 activities
            const recentActivities = filteredActivities.slice(-20).reverse();
            const tbody = document.getElementById('recentActivities');
            tbody.innerHTML = '';
            
            recentActivities.forEach(activity => {
                const user = users[activity.username];
                if (!user) return;
                
                const tr = document.createElement('tr');
                tr.className = 'table-row';
                
                // Activity icon
                let activityIcon = '📝';
                if (activity.activity === 'login') activityIcon = '🔐';
                else if (activity.activity === 'logout') activityIcon = '🚪';
                else if (activity.activity.includes('link')) activityIcon = '🔗';
                else if (activity.activity.includes('payment')) activityIcon = '💰';
                
                // Role badge
                const roleBadge = {
                    'superadmin': '<span class="role-badge super-admin-badge text-xs">SUPER</span>',
                    'admin': '<span class="role-badge admin-badge text-xs">ADMIN</span>',
                    'agent': '<span class="role-badge agent-badge text-xs">AGENT</span>'
                }[user.role];
                
                tr.innerHTML = `
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="activity-dot ${user.status === 'active' ? 'dot-active' : 'dot-inactive'}"></span>
                            ${user.name}
                        </div>
                    </td>
                    <td class="px-4 py-3">${roleBadge}</td>
                    <td class="px-4 py-3">${activityIcon} ${activity.activity}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${activity.details || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${new Date(activity.timestamp).toLocaleTimeString()}</td>
                `;
                
                tbody.appendChild(tr);
            });
        }

        function loadHierarchy() {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const upiData = JSON.parse(localStorage.getItem('nimcredit_upi'));
            
            // Super Admin List
            const superAdmins = Object.entries(users).filter(([_, u]) => u.role === 'superadmin');
            const superAdminList = document.getElementById('superAdminList');
            superAdminList.innerHTML = '';
            
            superAdmins.forEach(([username, user]) => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-3 bg-white rounded-lg shadow-sm';
                div.innerHTML = `
                    <div>
                        <p class="font-semibold">${user.name}</p>
                        <p class="text-sm text-gray-600">@${username}</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        Last login: ${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}
                    </div>
                `;
                superAdminList.appendChild(div);
            });
            
            // Admin Hierarchy
            const admins = Object.entries(users).filter(([_, u]) => u.role === 'admin');
            const adminHierarchy = document.getElementById('adminHierarchy');
            adminHierarchy.innerHTML = '';
            
            admins.forEach(([adminUsername, admin]) => {
                // Get admin's UPI
                const adminUpi = upiData.admins[adminUsername] || upiData.master || 'Not set';
                
                // Get admin's agents
                const agents = Object.entries(users).filter(([_, u]) => u.role === 'agent' && u.assigned_to === adminUsername);
                
                const adminDiv = document.createElement('div');
                adminDiv.className = 'mb-4 p-4 bg-white rounded-lg shadow-sm';
                adminDiv.innerHTML = `
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <p class="font-semibold flex items-center">
                                <span class="hierarchy-indicator hierarchy-admin mr-2"></span>
                                ${admin.name}
                            </p>
                            <p class="text-sm text-gray-600">@${adminUsername} • UPI: ${adminUpi}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold">${agents.length} agents</p>
                            <p class="text-xs text-gray-500">Status: <span class="${admin.status === 'active' ? 'text-green-600' : 'text-red-600'}">${admin.status}</span></p>
                        </div>
                    </div>
                    <div id="agents-${adminUsername}" class="pl-6 space-y-2">
                        ${agents.length === 0 ? '<p class="text-sm text-gray-500 italic">No agents assigned</p>' : ''}
                    </div>
                `;
                adminHierarchy.appendChild(adminDiv);
                
                // Add agents for this admin
                const agentsContainer = document.getElementById(`agents-${adminUsername}`);
                agents.forEach(([agentUsername, agent]) => {
                    const agentDiv = document.createElement('div');
                    agentDiv.className = 'flex items-center justify-between p-2 bg-gray-50 rounded';
                    agentDiv.innerHTML = `
                        <div class="flex items-center">
                            <span class="hierarchy-indicator hierarchy-agent mr-2"></span>
                            <div>
                                <p class="text-sm font-medium">${agent.name}</p>
                                <p class="text-xs text-gray-500">@${agentUsername}</p>
                            </div>
                        </div>
                        <span class="text-xs ${agent.status === 'active' ? 'text-green-600' : 'text-red-600'}">${agent.status}</span>
                    `;
                    agentsContainer.appendChild(agentDiv);
                });
            });
            
            // All Agents List
            const allAgents = Object.entries(users).filter(([_, u]) => u.role === 'agent');
            const agentList = document.getElementById('agentList');
            agentList.innerHTML = '';
            
            allAgents.forEach(([username, agent]) => {
                const adminName = users[agent.assigned_to] ? users[agent.assigned_to].name : 'Not assigned';
                
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-3 bg-white rounded-lg shadow-sm mb-2';
                div.innerHTML = `
                    <div>
                        <p class="font-semibold flex items-center">
                            <span class="hierarchy-indicator hierarchy-agent mr-2"></span>
                            ${agent.name}
                        </p>
                        <p class="text-sm text-gray-600">Assigned to: ${adminName}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm">@${username}</p>
                        <p class="text-xs text-gray-500">Status: <span class="${agent.status === 'active' ? 'text-green-600' : 'text-red-600'}">${agent.status}</span></p>
                    </div>
                `;
                agentList.appendChild(div);
            });
        }

        function loadUpiData() {
            const upiData = JSON.parse(localStorage.getItem('nimcredit_upi'));
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            
            // Master UPI
            document.getElementById('masterUpi').value = upiData.master || '';
            
            // Admin UPI Assignment
            const adminUpiAssignment = document.getElementById('adminUpiAssignment');
            adminUpiAssignment.innerHTML = '';
            
            const admins = Object.entries(users).filter(([_, u]) => u.role === 'admin');
            
            admins.forEach(([username, admin]) => {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-3 mb-4';
                div.innerHTML = `
                    <div class="w-32">
                        <span class="font-semibold">${admin.name}</span>
                        <p class="text-xs text-gray-500">@${username}</p>
                    </div>
                    <input type="text" id="upi-${username}" placeholder="Leave empty for master UPI" 
                        class="flex-1 px-4 py-2 border rounded-lg" 
                        value="${upiData.admins[username] || ''}">
                    <button onclick="assignAdminUpi('${username}')" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        Assign
                    </button>
                `;
                adminUpiAssignment.appendChild(div);
            });
        }

        function saveUpi(type) {
            const upiData = JSON.parse(localStorage.getItem('nimcredit_upi'));
            const value = document.getElementById(type + 'Upi').value.trim();
            
            if (type === 'master') {
                if (!value) {
                    showNotification('Please enter a valid master UPI ID', 'error');
                    return;
                }
                upiData.master = value;
                showNotification('Master UPI updated successfully!');
            }
            
            localStorage.setItem('nimcredit_upi', JSON.stringify(upiData));
            
            // Log activity
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            logActivity(currentUser.username, 'upi_updated', `${type} UPI changed to ${value}`);
        }

        function assignAdminUpi(adminUsername) {
            const upiData = JSON.parse(localStorage.getItem('nimcredit_upi'));
            const value = document.getElementById(`upi-${adminUsername}`).value.trim();
            
            if (!upiData.admins) upiData.admins = {};
            
            if (value === '') {
                delete upiData.admins[adminUsername];
                showNotification('Admin UPI removed, will use master UPI');
            } else {
                upiData.admins[adminUsername] = value;
                showNotification('Admin UPI assigned successfully!');
            }
            
            localStorage.setItem('nimcredit_upi', JSON.stringify(upiData));
            
            // Log activity
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const adminName = users[adminUsername]?.name || adminUsername;
            logActivity(currentUser.username, 'admin_upi_assigned', `Assigned UPI to ${adminName}: ${value || 'master'}`);
        }

        function loadAnalytics() {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const links = JSON.parse(localStorage.getItem('nimcredit_links'));
            const filterUser = document.getElementById('analyticsFilterUser').value;
            const filterDate = document.getElementById('analyticsFilterDate').value;
            
            const now = Date.now();
            const oneDay = 24 * 60 * 60 * 1000;
            const oneWeek = 7 * oneDay;
            const oneMonth = 30 * oneDay;
            
            // Filter links by date
            let filteredLinks = links;
            if (filterDate !== 'all') {
                const cutoff = now - {
                    'today': oneDay,
                    'week': oneWeek,
                    'month': oneMonth
                }[filterDate];
                
                filteredLinks = links.filter(l => l.created_at >= cutoff);
            }
            
            const tbody = document.getElementById('analyticsTable');
            tbody.innerHTML = '';
            
            // Process each user
            Object.entries(users).forEach(([username, user]) => {
                // Apply user filter
                if (filterUser !== 'all') {
                    if (filterUser === 'admin' && user.role !== 'admin') return;
                    if (filterUser === 'agent' && user.role !== 'agent') return;
                }
                
                // Filter links for this user
                let userLinks = filteredLinks.filter(l => l.agent === username);
                
                // If admin, include their agents' links
                if (user.role === 'admin') {
                    const adminAgents = Object.entries(users)
                        .filter(([_, u]) => u.role === 'agent' && u.assigned_to === username)
                        .map(([agentUsername]) => agentUsername);
                    
                    adminAgents.forEach(agentUsername => {
                        const agentLinks = filteredLinks.filter(l => l.agent === agentUsername);
                        userLinks = [...userLinks, ...agentLinks];
                    });
                }
                
                // Calculate stats
                const totalLinks = userLinks.length;
                const openedLinks = userLinks.filter(l => l.opened).length;
                const paidLinks = userLinks.filter(l => l.paid).length;
                const totalAmount = paidLinks.reduce((sum, link) => sum + parseFloat(link.amount), 0);
                const successRate = totalLinks > 0 ? ((paidLinks.length / totalLinks) * 100).toFixed(1) : 0;
                
                // Last activity
                let lastActivity = 'Never';
                if (user.last_login) {
                    const timeDiff = now - user.last_login;
                    if (timeDiff < oneDay) lastActivity = 'Today';
                    else if (timeDiff < 2 * oneDay) lastActivity = 'Yesterday';
                    else lastActivity = Math.floor(timeDiff / oneDay) + ' days ago';
                }
                
                // Role badge
                const roleBadge = {
                    'superadmin': '<span class="role-badge super-admin-badge">SUPER</span>',
                    'admin': '<span class="role-badge admin-badge">ADMIN</span>',
                    'agent': '<span class="role-badge agent-badge">AGENT</span>'
                }[user.role];
                
                // Add to table
                const tr = document.createElement('tr');
                tr.className = 'table-row';
                tr.innerHTML = `
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="activity-dot ${user.status === 'active' ? 'dot-active' : 'dot-inactive'} mr-2"></span>
                            ${user.name}
                        </div>
                    </td>
                    <td class="px-4 py-3">${roleBadge}</td>
                    <td class="px-4 py-3 font-semibold">${totalLinks}</td>
                    <td class="px-4 py-3">${openedLinks}</td>
                    <td class="px-4 py-3 text-green-600 font-semibold">${paidLinks}</td>
                    <td class="px-4 py-3 font-semibold">₹${totalAmount.toLocaleString('en-IN')}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: ${successRate}%"></div>
                            </div>
                            <span class="text-sm font-semibold">${successRate}%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${lastActivity}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function exportAnalytics() {
            // This would typically be a server-side function
            showNotification('Export feature would connect to server in production', 'success');
        }

        function loadAdminsList() {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const container = document.getElementById('adminsList');
            
            container.innerHTML = '';
            
            Object.entries(users).forEach(([username, user]) => {
                if (user.role === 'admin') {
                    // Get admin's agents
                    const agents = Object.values(users).filter(u => u.role === 'agent' && u.assigned_to === username);
                    
                    const div = document.createElement('div');
                    div.className = 'p-6 bg-gradient-to-r from-green-50 to-blue-50 rounded-xl mb-4';
                    div.innerHTML = `
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <h4 class="text-lg font-bold mr-3">${user.name}</h4>
                                    <span class="role-badge admin-badge">ADMIN</span>
                                </div>
                                <p class="text-sm text-gray-600">Username: @${username}</p>
                                <p class="text-sm text-gray-600 mt-1">Agents: ${agents.length} | Status: <span class="${user.status === 'active' ? 'text-green-600' : 'text-red-600'}">${user.status}</span></p>
                            </div>
                            <button onclick="removeAdmin('${username}')" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
                                Remove Admin
                            </button>
                        </div>
                        
                        <div class="password-section">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm text-gray-600">Password:</span>
                                <span id="password-${username}" class="text-sm font-mono hidden-password">••••••••</span>
                                <button onclick="toggleAgentPassword('${username}')" class="text-xs text-blue-600 hover:text-blue-800">
                                    ${user.showPassword ? 'Hide' : 'Show'}
                                </button>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <button onclick="resetPassword('${username}')" class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600">
                                    Reset Password
                                </button>
                                <button onclick="toggleUserStatus('${username}')" class="bg-${user.status === 'active' ? 'gray' : 'green'}-500 text-white px-3 py-1 rounded text-sm hover:bg-${user.status === 'active' ? 'gray' : 'green'}-600">
                                    ${user.status === 'active' ? 'Deactivate' : 'Activate'}
                                </button>
                            </div>
                        </div>
                    `;
                    container.appendChild(div);
                }
            });
        }

        function loadAgentsList() {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const container = document.getElementById('agentsList');
            
            container.innerHTML = '';
            
            Object.entries(users).forEach(([username, user]) => {
                if (user.role === 'agent') {
                    const assignedAdmin = user.assigned_to ? (users[user.assigned_to]?.name || user.assigned_to) : 'Not assigned';
                    
                    const div = document.createElement('div');
                    div.className = 'p-6 bg-gradient-to-r from-pink-50 to-yellow-50 rounded-xl mb-4';
                    div.innerHTML = `
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <h4 class="text-lg font-bold mr-3">${user.name}</h4>
                                    <span class="role-badge agent-badge">AGENT</span>
                                </div>
                                <p class="text-sm text-gray-600">Username: @${username}</p>
                                <p class="text-sm text-gray-600 mt-1">Assigned to: ${assignedAdmin}</p>
                                <p class="text-sm text-gray-600">Status: <span class="${user.status === 'active' ? 'text-green-600' : 'text-red-600'}">${user.status}</span></p>
                            </div>
                            <div class="flex gap-2">
                                <select id="assign-admin-${username}" class="border rounded-lg px-2 py-1 text-sm">
                                    <option value="">Change Admin...</option>
                                    ${Object.entries(users)
                                        .filter(([_, u]) => u.role === 'admin')
                                        .map(([adminUsername, admin]) => `
                                            <option value="${adminUsername}" ${user.assigned_to === adminUsername ? 'selected' : ''}>${admin.name}</option>
                                        `).join('')}
                                </select>
                                <button onclick="removeAgent('${username}')" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
                                    Remove
                                </button>
                            </div>
                        </div>
                        
                        <div class="password-section">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm text-gray-600">Password:</span>
                                <span id="password-${username}" class="text-sm font-mono hidden-password">••••••••</span>
                                <button onclick="toggleAgentPassword('${username}')" class="text-xs text-blue-600 hover:text-blue-800">
                                    ${user.showPassword ? 'Hide' : 'Show'}
                                </button>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <button onclick="resetPassword('${username}')" class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600">
                                    Reset Password
                                </button>
                                <button onclick="toggleUserStatus('${username}')" class="bg-${user.status === 'active' ? 'gray' : 'green'}-500 text-white px-3 py-1 rounded text-sm hover:bg-${user.status === 'active' ? 'gray' : 'green'}-600">
                                    ${user.status === 'active' ? 'Deactivate' : 'Activate'}
                                </button>
                                <button onclick="assignAgentAdmin('${username}')" class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                                    Reassign
                                </button>
                            </div>
                        </div>
                    `;
                    container.appendChild(div);
                }
            });
        }

        function addAdmin() {
            const name = document.getElementById('newAdminName').value.trim();
            const username = document.getElementById('newAdminUsername').value.trim().toLowerCase();
            const password = document.getElementById('newAdminPassword').value.trim();
            
            if (!name || !username || !password) {
                showNotification('Please fill all fields', 'error');
                return;
            }
            
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            
            if (users[username]) {
                showNotification('Username already exists!', 'error');
                return;
            }
            
            users[username] = {
                password: password,
                actualPassword: password,
                role: 'admin',
                name: name,
                showPassword: false,
                created_at: Date.now(),
                last_login: null,
                assigned_to: null,
                status: 'active'
            };
            
            localStorage.setItem('nimcredit_users', JSON.stringify(users));
            showNotification('Admin added successfully!');
            
            // Clear form
            document.getElementById('newAdminName').value = '';
            document.getElementById('newAdminUsername').value = '';
            document.getElementById('newAdminPassword').value = '';
            
            // Log activity
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            logActivity(currentUser.username, 'admin_added', `Added admin: ${name} (@${username})`);
            
            loadAdminsList();
        }

        function addAgent() {
            const name = document.getElementById('newAgentName').value.trim();
            const username = document.getElementById('newAgentUsername').value.trim().toLowerCase();
            const password = document.getElementById('newAgentPassword').value.trim();
            const assignedAdmin = document.getElementById('newAgentAdmin').value;
            
            if (!name || !username || !password) {
                showNotification('Please fill all fields', 'error');
                return;
            }
            
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            
            if (users[username]) {
                showNotification('Username already exists!', 'error');
                return;
            }
            
            users[username] = {
                password: password,
                actualPassword: password,
                role: 'agent',
                name: name,
                showPassword: false,
                created_at: Date.now(),
                last_login: null,
                assigned_to: assignedAdmin || null,
                status: 'active'
            };
            
            localStorage.setItem('nimcredit_users', JSON.stringify(users));
            showNotification('Agent added successfully!');
            
            // Clear form
            document.getElementById('newAgentName').value = '';
            document.getElementById('newAgentUsername').value = '';
            document.getElementById('newAgentPassword').value = '';
            
            // Log activity
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            logActivity(currentUser.username, 'agent_added', `Added agent: ${name} (@${username}) assigned to: ${assignedAdmin || 'none'}`);
            
            loadAgentsList();
        }

        function removeAdmin(username) {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const admin = users[username];
            
            if (!admin) {
                showNotification('Admin not found!', 'error');
                return;
            }
            
            // Check if admin has agents
            const hasAgents = Object.values(users).some(u => u.role === 'agent' && u.assigned_to === username);
            
            if (hasAgents) {
                if (!confirm(`This admin has ${Object.values(users).filter(u => u.role === 'agent' && u.assigned_to === username).length} agents. Remove admin and unassign all agents?`)) {
                    return;
                }
                
                // Unassign all agents from this admin
                Object.keys(users).forEach(u => {
                    if (users[u].role === 'agent' && users[u].assigned_to === username) {
                        users[u].assigned_to = null;
                    }
                });
            }
            
            if (confirm(`Are you sure you want to remove admin "${admin.name}"?`)) {
                delete users[username];
                localStorage.setItem('nimcredit_users', JSON.stringify(users));
                showNotification('Admin removed successfully!');
                
                // Log activity
                const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
                logActivity(currentUser.username, 'admin_removed', `Removed admin: ${admin.name} (@${username})`);
                
                loadAdminsList();
            }
        }

        function removeAgent(username) {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const agent = users[username];
            
            if (!agent) {
                showNotification('Agent not found!', 'error');
                return;
            }
            
            if (confirm(`Are you sure you want to remove agent "${agent.name}"?`)) {
                delete users[username];
                localStorage.setItem('nimcredit_users', JSON.stringify(users));
                showNotification('Agent removed successfully!');
                
                // Log activity
                const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
                logActivity(currentUser.username, 'agent_removed', `Removed agent: ${agent.name} (@${username})`);
                
                loadAgentsList();
            }
        }

        function assignAgentAdmin(agentUsername) {
            const select = document.getElementById(`assign-admin-${agentUsername}`);
            const adminUsername = select.value;
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            
            if (!users[agentUsername]) {
                showNotification('Agent not found!', 'error');
                return;
            }
            
            users[agentUsername].assigned_to = adminUsername || null;
            localStorage.setItem('nimcredit_users', JSON.stringify(users));
            
            const agentName = users[agentUsername].name;
            const adminName = adminUsername ? (users[adminUsername]?.name || adminUsername) : 'None';
            
            showNotification(`Agent ${agentName} assigned to ${adminName}`);
            
            // Log activity
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            logActivity(currentUser.username, 'agent_reassigned', `Reassigned ${agentName} to ${adminName}`);
            
            loadAgentsList();
        }

        function resetPassword(username) {
            const newPassword = prompt('Enter new password for this user:');
            if (!newPassword || newPassword.length < 3) {
                showNotification('Password must be at least 3 characters', 'error');
                return;
            }
            
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            if (users[username]) {
                users[username].password = newPassword;
                users[username].actualPassword = newPassword;
                localStorage.setItem('nimcredit_users', JSON.stringify(users));
                
                showNotification(`Password reset for ${users[username].name}`);
                
                // Log activity
                const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
                logActivity(currentUser.username, 'password_reset', `Reset password for ${users[username].name} (@${username})`);
                
                if (users[username].role === 'admin') {
                    loadAdminsList();
                } else {
                    loadAgentsList();
                }
            }
        }

        function toggleUserStatus(username) {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            if (users[username]) {
                users[username].status = users[username].status === 'active' ? 'inactive' : 'active';
                localStorage.setItem('nimcredit_users', JSON.stringify(users));
                
                showNotification(`${users[username].name} is now ${users[username].status}`);
                
                // Log activity
                const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
                logActivity(currentUser.username, 'user_status_changed', `Changed ${users[username].name} status to ${users[username].status}`);
                
                if (users[username].role === 'admin') {
                    loadAdminsList();
                } else {
                    loadAgentsList();
                }
            }
        }

        function toggleAgentPassword(username) {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const user = users[username];
            
            if (!user) return;
            
            user.showPassword = !user.showPassword;
            
            const passwordSpan = document.getElementById(`password-${username}`);
            if (passwordSpan) {
                passwordSpan.textContent = user.showPassword ? user.actualPassword : '••••••••';
                passwordSpan.classList.toggle('hidden-password', !user.showPassword);
            }
            
            localStorage.setItem('nimcredit_users', JSON.stringify(users));
            
            if (user.role === 'admin') {
                loadAdminsList();
            } else {
                loadAgentsList();
            }
        }

        // ==================== ADMIN FUNCTIONS ====================
        function adminSwitchTab(tabName) {
            // Remove active class from all admin tabs
            document.querySelectorAll('[id^="adminTab"]').forEach(tab => {
                tab.classList.remove('tab-active');
                tab.classList.add('bg-white', 'hover:bg-gray-100');
            });
            
            // Hide all admin content
            document.querySelectorAll('[id^="adminContent"]').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show selected tab
            const tabId = 'adminTab' + tabName.charAt(0).toUpperCase() + tabName.slice(1);
            document.getElementById(tabId).classList.add('tab-active');
            document.getElementById(tabId).classList.remove('bg-white', 'hover:bg-gray-100');
            document.getElementById('adminContent' + tabName.charAt(0).toUpperCase() + tabName.slice(1)).classList.remove('hidden');
            
            // Load specific tab data
            if (tabName === 'dashboard') {
                loadAdminDashboard();
            } else if (tabName === 'agents') {
                loadAdminAgentsList();
            } else if (tabName === 'links') {
                loadAdminLinks();
            }
        }

        function loadAdminDashboard() {
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const links = JSON.parse(localStorage.getItem('nimcredit_links'));
            
            const adminUsername = currentUser.username;
            
            // Get admin's links
            const adminLinks = links.filter(l => l.agent === adminUsername);
            
            // Get admin's agents
            const adminAgents = Object.entries(users)
                .filter(([_, u]) => u.role === 'agent' && u.assigned_to === adminUsername)
                .map(([agentUsername]) => agentUsername);
            
            // Get agents' links
            let agentsLinks = [];
            adminAgents.forEach(agentUsername => {
                const agentLinks = links.filter(l => l.agent === agentUsername);
                agentsLinks = [...agentsLinks, ...agentLinks];
            });
            
            // All links (admin + agents)
            const allLinks = [...adminLinks, ...agentsLinks];
            
            // Calculate stats
            const totalLinks = allLinks.length;
            const paidLinks = allLinks.filter(l => l.paid);
            const totalPaid = paidLinks.reduce((sum, link) => sum + parseFloat(link.amount), 0);
            const successRate = totalLinks > 0 ? ((paidLinks.length / totalLinks) * 100).toFixed(1) : 0;
            
            // Update UI
            document.getElementById('adminStatTotalLinks').textContent = totalLinks;
            document.getElementById('adminStatAgents').textContent = adminAgents.length;
            document.getElementById('adminStatPaid').textContent = '₹' + totalPaid.toLocaleString('en-IN');
            document.getElementById('adminStatSuccess').textContent = successRate + '%';
            
            // Load agents performance
            loadAdminAgentsPerformance(adminAgents, links);
            
            // Load recent activities
            loadAdminRecentActivities(allLinks);
        }

        function loadAdminAgentsPerformance(adminAgents, allLinks) {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const today = new Date().toDateString();
            
            const tbody = document.getElementById('adminAgentsPerformance');
            tbody.innerHTML = '';
            
            adminAgents.forEach(agentUsername => {
                const agent = users[agentUsername];
                if (!agent) return;
                
                // Get agent's links
                const agentLinks = allLinks.filter(l => l.agent === agentUsername);
                const todayLinks = agentLinks.filter(l => {
                    const linkDate = new Date(l.created_at).toDateString();
                    return linkDate === today;
                });
                
                const paidLinks = agentLinks.filter(l => l.paid);
                const successRate = agentLinks.length > 0 ? ((paidLinks.length / agentLinks.length) * 100).toFixed(1) : 0;
                
                const tr = document.createElement('tr');
                tr.className = 'table-row';
                tr.innerHTML = `
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="activity-dot ${agent.status === 'active' ? 'dot-active' : 'dot-inactive'} mr-2"></span>
                            ${agent.name}
                        </div>
                    </td>
                    <td class="px-4 py-3">${todayLinks.length}</td>
                    <td class="px-4 py-3">${agentLinks.length}</td>
                    <td class="px-4 py-3 text-green-600 font-semibold">${paidLinks.length}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-16 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: ${successRate}%"></div>
                            </div>
                            <span class="text-sm">${successRate}%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs ${agent.status === 'active' ? 'text-green-600' : 'text-red-600'}">${agent.status}</span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            // If no agents
            if (adminAgents.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                            No agents assigned to you yet.
                        </td>
                    </tr>
                `;
            }
        }

        function loadAdminRecentActivities(allLinks) {
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const recentLinks = allLinks.sort((a, b) => b.created_at - a.created_at).slice(0, 10);
            
            const tbody = document.getElementById('adminRecentActivities');
            tbody.innerHTML = '';
            
            recentLinks.forEach(link => {
                const agent = users[link.agent];
                if (!agent) return;
                
                const status = link.paid ? '✅ Paid' : (link.opened ? '👀 Opened' : '⏳ Pending');
                const statusColor = link.paid ? 'text-green-600' : (link.opened ? 'text-blue-600' : 'text-yellow-600');
                
                const tr = document.createElement('tr');
                tr.className = 'table-row';
                tr.innerHTML = `
                    <td class="px-4 py-3">${agent.name}</td>
                    <td class="px-4 py-3">${link.customer_name}</td>
                    <td class="px-4 py-3 font-semibold">₹${link.amount}</td>
                    <td class="px-4 py-3 ${statusColor} font-semibold">${status}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${new Date(link.created_at).toLocaleTimeString()}</td>
                `;
                tbody.appendChild(tr);
            });
            
            // If no links
            if (recentLinks.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                            No links generated yet.
                        </td>
                    </tr>
                `;
            }
        }

        function loadAdminAgentsList() {
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const links = JSON.parse(localStorage.getItem('nimcredit_links'));
            
            // Get admin's agents
            const adminAgents = Object.entries(users)
                .filter(([_, u]) => u.role === 'agent' && u.assigned_to === currentUser.username);
            
            const container = document.getElementById('adminAgentsList');
            container.innerHTML = '';
            
            adminAgents.forEach(([agentUsername, agent]) => {
                // Get agent's stats
                const agentLinks = links.filter(l => l.agent === agentUsername);
                const todayLinks = agentLinks.filter(l => {
                    const linkDate = new Date(l.created_at);
                    const today = new Date();
                    return linkDate.toDateString() === today.toDateString();
                });
                
                const paidLinks = agentLinks.filter(l => l.paid);
                const totalAmount = paidLinks.reduce((sum, link) => sum + parseFloat(link.amount), 0);
                
                const div = document.createElement('div');
                div.className = 'p-6 bg-gradient-to-r from-green-50 to-blue-50 rounded-xl mb-4';
                div.innerHTML = `
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="flex items-center mb-2">
                                <h4 class="text-lg font-bold mr-3">${agent.name}</h4>
                                <span class="role-badge agent-badge">AGENT</span>
                            </div>
                            <p class="text-sm text-gray-600">Username: @${agentUsername}</p>
                            <div class="flex gap-4 mt-2 text-sm">
                                <span>Links: ${agentLinks.length}</span>
                                <span>Today: ${todayLinks.length}</span>
                                <span class="text-green-600">Paid: ₹${totalAmount}</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="adminResetAgentPassword('${agentUsername}')" class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600">
                                Reset Pass
                            </button>
                            <button onclick="adminToggleAgentStatus('${agentUsername}')" class="bg-${agent.status === 'active' ? 'gray' : 'green'}-500 text-white px-3 py-1 rounded text-sm hover:bg-${agent.status === 'active' ? 'gray' : 'green'}-600">
                                ${agent.status === 'active' ? 'Deactivate' : 'Activate'}
                            </button>
                            <button onclick="adminRemoveAgent('${agentUsername}')" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                Remove
                            </button>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            });
            
            // If no agents
            if (adminAgents.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <p class="text-lg mb-2">No agents assigned to you yet.</p>
                        <p class="text-sm">Add agents using the form above.</p>
                    </div>
                `;
            }
        }

        function adminAddAgent() {
            const name = document.getElementById('adminNewAgentName').value.trim();
            const username = document.getElementById('adminNewAgentUsername').value.trim().toLowerCase();
            const password = document.getElementById('adminNewAgentPassword').value.trim();
            
            if (!name || !username || !password) {
                showNotification('Please fill all fields', 'error');
                return;
            }
            
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            
            if (users[username]) {
                showNotification('Username already exists!', 'error');
                return;
            }
            
            users[username] = {
                password: password,
                actualPassword: password,
                role: 'agent',
                name: name,
                showPassword: false,
                created_at: Date.now(),
                last_login: null,
                assigned_to: currentUser.username, // Assign to current admin
                status: 'active'
            };
            
            localStorage.setItem('nimcredit_users', JSON.stringify(users));
            showNotification('Agent added successfully!');
            
            // Clear form
            document.getElementById('adminNewAgentName').value = '';
            document.getElementById('adminNewAgentUsername').value = '';
            document.getElementById('adminNewAgentPassword').value = '';
            
            // Log activity
            logActivity(currentUser.username, 'admin_agent_added', `Added agent: ${name} (@${username})`);
            
            loadAdminAgentsList();
        }

        function adminSaveUpi() {
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            const upiData = JSON.parse(localStorage.getItem('nimcredit_upi'));
            const value = document.getElementById('adminMyUpi').value.trim();
            
            if (!value) {
                showNotification('Please enter a valid UPI ID', 'error');
                return;
            }
            
            if (!upiData.admins) upiData.admins = {};
            upiData.admins[currentUser.username] = value;
            
            localStorage.setItem('nimcredit_upi', JSON.stringify(upiData));
            showNotification('Your UPI ID saved successfully!');
            
            // Log activity
            logActivity(currentUser.username, 'admin_upi_changed', `Changed UPI to: ${value}`);
        }

        function loadAdminLinks() {
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const links = JSON.parse(localStorage.getItem('nimcredit_links'));
            const filter = document.getElementById('adminLinkFilter').value;
            
            // Get links based on filter
            let filteredLinks = links;
            if (filter === 'my') {
                filteredLinks = links.filter(l => l.agent === currentUser.username);
            } else if (filter === 'agents') {
                // Get admin's agents
                const adminAgents = Object.entries(users)
                    .filter(([_, u]) => u.role === 'agent' && u.assigned_to === currentUser.username)
                    .map(([agentUsername]) => agentUsername);
                
                filteredLinks = links.filter(l => adminAgents.includes(l.agent));
            } else if (filter === 'paid') {
                filteredLinks = links.filter(l => l.paid);
            } else if (filter === 'pending') {
                filteredLinks = links.filter(l => !l.paid);
            }
            
            // Filter admin's team links
            filteredLinks = filteredLinks.filter(l => {
                if (l.agent === currentUser.username) return true;
                const agent = users[l.agent];
                return agent && agent.role === 'agent' && agent.assigned_to === currentUser.username;
            });
            
            // Sort by date
            filteredLinks.sort((a, b) => b.created_at - a.created_at);
            
            const tbody = document.getElementById('adminLinksTable');
            tbody.innerHTML = '';
            
            filteredLinks.forEach(link => {
                const agent = users[link.agent];
                if (!agent) return;
                
                const status = link.paid ? '✅ Paid' : (link.opened ? '👀 Opened' : '⏳ Pending');
                const statusClass = link.paid ? 'status-paid' : (link.opened ? 'status-opened' : 'status-pending');
                
                const tr = document.createElement('tr');
                tr.className = 'table-row';
                tr.innerHTML = `
                    <td class="px-4 py-3 text-sm font-mono">${link.id}</td>
                    <td class="px-4 py-3">${link.customer_name}</td>
                    <td class="px-4 py-3 font-semibold">₹${link.amount}</td>
                    <td class="px-4 py-3">
                        ${agent.name}
                        ${agent.username === currentUser.username ? '<span class="text-xs text-blue-600">(You)</span>' : ''}
                    </td>
                    <td class="px-4 py-3">
                        <span class="link-status ${statusClass}">${status}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${new Date(link.created_at).toLocaleDateString()}</td>
                    <td class="px-4 py-3">
                        <button onclick="copyLink('${link.short_url}')" class="text-blue-600 hover:text-blue-800 text-sm">
                            📋 Copy
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            // If no links
            if (filteredLinks.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            No links found.
                        </td>
                    </tr>
                `;
            }
        }

        // ==================== AGENT FUNCTIONS ====================
        function loadAgentDashboard() {
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const links = JSON.parse(localStorage.getItem('nimcredit_links'));
            const upiData = JSON.parse(localStorage.getItem('nimcredit_upi'));
            
            const agentUsername = currentUser.username;
            const agent = users[agentUsername];
            
            // Get agent's links
            const agentLinks = links.filter(l => l.agent === agentUsername);
            const today = new Date().toDateString();
            
            // Today's links
            const todayLinks = agentLinks.filter(l => {
                const linkDate = new Date(l.created_at).toDateString();
                return linkDate === today;
            });
            
            // Paid links
            const paidLinks = agentLinks.filter(l => l.paid);
            const totalPaid = paidLinks.reduce((sum, link) => sum + parseFloat(link.amount), 0);
            
            // Today's successful links
            const todaySuccessful = todayLinks.filter(l => l.paid);
            
            // Success rate
            const successRate = agentLinks.length > 0 ? ((paidLinks.length / agentLinks.length) * 100).toFixed(1) : 0;
            
            // Pending links
            const pendingLinks = agentLinks.filter(l => !l.paid && l.opened);
            
            // Update stats
            document.getElementById('agentStatLinks').textContent = agentLinks.length;
            document.getElementById('agentStatPaid').textContent = '₹' + totalPaid.toLocaleString('en-IN');
            document.getElementById('agentStatSuccess').textContent = successRate + '%';
            
            // Update quick stats
            document.getElementById('agentLinksToday').textContent = todayLinks.length;
            document.getElementById('agentPending').textContent = pendingLinks.length;
            document.getElementById('agentSuccessfulToday').textContent = todaySuccessful.length;
            document.getElementById('agentTotalEarned').textContent = '₹' + totalPaid.toLocaleString('en-IN');
            
            // Update agent info
            const assignedAdmin = agent.assigned_to ? (users[agent.assigned_to]?.name || agent.assigned_to) : 'Not assigned';
            document.getElementById('agentAssignedAdmin').textContent = assignedAdmin;
            document.getElementById('agentJoinedDate').textContent = new Date(agent.created_at).toLocaleDateString();
            
            // Get UPI info
            let upiInfo = upiData.master || 'Not set';
            if (agent.assigned_to && upiData.admins && upiData.admins[agent.assigned_to]) {
                upiInfo = upiData.admins[agent.assigned_to];
            }
            document.getElementById('agentUpiInfo').textContent = upiInfo;
            
            // Load recent links
            loadAgentRecentLinks(agentLinks.slice(-5).reverse());
            
            // Load UPI for link generation
            window.currentUpi = upiInfo;
        }

        function loadAgentRecentLinks(recentLinks) {
            const container = document.getElementById('agentRecentLinks');
            container.innerHTML = '';
            
            if (recentLinks.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-sm">No links generated yet.</p>';
                return;
            }
            
            recentLinks.forEach(link => {
                const status = link.paid ? '✅ Paid' : (link.opened ? '👀 Opened' : '⏳ Pending');
                const statusColor = link.paid ? 'text-green-600' : (link.opened ? 'text-blue-600' : 'text-yellow-600');
                
                const div = document.createElement('div');
                div.className = 'flex justify-between items-center p-3 bg-gray-50 rounded-lg';
                div.innerHTML = `
                    <div>
                        <p class="font-medium">${link.customer_name}</p>
                        <p class="text-sm text-gray-600">₹${link.amount}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm ${statusColor} font-semibold">${status}</p>
                        <p class="text-xs text-gray-500">${new Date(link.created_at).toLocaleDateString()}</p>
                    </div>
                `;
                container.appendChild(div);
            });
        }

        // ==================== ALL LINKS MANAGEMENT ====================
        let currentLinksPage = 1;
        let totalLinksPages = 1;
        let allFilteredLinks = [];

        function loadAllLinks() {
            const links = JSON.parse(localStorage.getItem('nimcredit_links') || '[]');
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const statusFilter = document.getElementById('linkFilterStatus').value;
            const userFilter = document.getElementById('linkFilterUser').value;
            const dateFilter = document.getElementById('linkFilterDate').value;
            
            // Filter links
            allFilteredLinks = links.filter(link => {
                // Status filter
                if (statusFilter !== 'all') {
                    if (statusFilter === 'pending' && (link.paid || !link.opened)) return false;
                    if (statusFilter === 'opened' && (!link.opened || link.paid)) return false;
                    if (statusFilter === 'paid' && !link.paid) return false;
                    if (statusFilter === 'expired') {
                        if (link.first_opened_at) {
                            const expiryTime = link.first_opened_at + (20 * 60 * 1000);
                            if (Date.now() <= expiryTime) return false;
                        } else {
                            return false;
                        }
                    }
                }
                
                // User filter
                if (userFilter !== 'all') {
                    if (link.agent !== userFilter) return false;
                }
                
                // Date filter
                if (dateFilter) {
                    const linkDate = new Date(link.created_at).toISOString().split('T')[0];
                    if (linkDate !== dateFilter) return false;
                }
                
                return true;
            });
            
            // Sort by date (newest first)
            allFilteredLinks.sort((a, b) => b.created_at - a.created_at);
            
            // Update user filter options
            const userFilterSelect = document.getElementById('linkFilterUser');
            const allUsers = ['all'];
            allFilteredLinks.forEach(link => {
                if (!allUsers.includes(link.agent)) {
                    allUsers.push(link.agent);
                }
            });
            
            userFilterSelect.innerHTML = '<option value="all">All Users</option>';
            allUsers.slice(1).forEach(username => {
                const user = users[username];
                if (user) {
                    userFilterSelect.innerHTML += `<option value="${username}">${user.name} (${user.role})</option>`;
                }
            });
            
            // Calculate pagination
            totalLinksPages = Math.ceil(allFilteredLinks.length / LINKS_PER_PAGE);
            currentLinksPage = Math.min(currentLinksPage, totalLinksPages);
            if (currentLinksPage < 1) currentLinksPage = 1;
            
            // Get current page links
            const startIndex = (currentLinksPage - 1) * LINKS_PER_PAGE;
            const endIndex = startIndex + LINKS_PER_PAGE;
            const pageLinks = allFilteredLinks.slice(startIndex, endIndex);
            
            // Update table
            const tbody = document.getElementById('allLinksTable');
            tbody.innerHTML = '';
            
            pageLinks.forEach(link => {
                const agent = users[link.agent];
                if (!agent) return;
                
                let statusText = '⏳ Pending';
                let statusClass = 'status-pending';
                
                if (link.paid) {
                    statusText = '✅ Paid';
                    statusClass = 'status-paid';
                } else if (link.opened) {
                    if (link.first_opened_at) {
                        const expiryTime = link.first_opened_at + (20 * 60 * 1000);
                        if (Date.now() > expiryTime) {
                            statusText = '⌛ Expired';
                            statusClass = 'status-pending';
                        } else {
                            statusText = '👀 Opened';
                            statusClass = 'status-opened';
                        }
                    } else {
                        statusText = '👀 Opened';
                        statusClass = 'status-opened';
                    }
                }
                
                const tr = document.createElement('tr');
                tr.className = 'table-row';
                tr.innerHTML = `
                    <td class="px-4 py-3 text-sm font-mono">${link.id}</td>
                    <td class="px-4 py-3">${link.customer_name}</td>
                    <td class="px-4 py-3 font-semibold">₹${link.amount}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="activity-dot ${agent.status === 'active' ? 'dot-active' : 'dot-inactive'} mr-2"></span>
                            ${agent.name}
                            <span class="role-badge ${agent.role}-badge ml-2">${agent.role.toUpperCase()}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm">${link.upi_used || 'N/A'}</td>
                    <td class="px-4 py-3">
                        <span class="link-status ${statusClass}">${statusText}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${new Date(link.created_at).toLocaleString()}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2">
                            <button onclick="copyLink('${link.short_url}')" class="text-blue-600 hover:text-blue-800 text-sm">
                                📋
                            </button>
                            ${!link.paid ? `
                                <button onclick="markLinkAsPaid('${link.id}')" class="text-green-600 hover:text-green-800 text-sm">
                                    ✅
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            // Update pagination info
            document.getElementById('linksStart').textContent = allFilteredLinks.length === 0 ? 0 : startIndex + 1;
            document.getElementById('linksEnd').textContent = Math.min(endIndex, allFilteredLinks.length);
            document.getElementById('linksTotal').textContent = allFilteredLinks.length;
            document.getElementById('linksPageInfo').textContent = `Page ${currentLinksPage} of ${totalLinksPages}`;
        }

        function prevLinksPage() {
            if (currentLinksPage > 1) {
                currentLinksPage--;
                loadAllLinks();
            }
        }

        function nextLinksPage() {
            if (currentLinksPage < totalLinksPages) {
                currentLinksPage++;
                loadAllLinks();
            }
        }

        function exportLinks() {
            // This would typically be a server-side function
            showNotification('Export feature would connect to server in production', 'success');
        }

        function markLinkAsPaid(linkId) {
            const links = JSON.parse(localStorage.getItem('nimcredit_links'));
            const linkIndex = links.findIndex(l => l.id === linkId);
            
            if (linkIndex !== -1) {
                links[linkIndex].paid = true;
                localStorage.setItem('nimcredit_links', JSON.stringify(links));
                
                // Log activity
                const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
                logActivity(currentUser.username, 'link_marked_paid', `Marked link ${linkId} as paid`);
                
                showNotification('Link marked as paid!');
                loadAllLinks();
            }
        }

        // ==================== COMMON FUNCTIONS ====================
        function toggleLoanFields() {
            const count = parseInt(document.getElementById('loanCount').value);
            
            document.getElementById('loan2Field').classList.toggle('hidden', count < 2);
            document.getElementById('loan3Field').classList.toggle('hidden', count < 3);
            
            // Make required fields based on selection
            document.getElementById('amount2').required = count >= 2;
            document.getElementById('amount3').required = count >= 3;
        }

        document.getElementById('paymentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            showLoader();
            
            const currentUser = JSON.parse(sessionStorage.getItem('nimcredit_current_user'));
            const users = JSON.parse(localStorage.getItem('nimcredit_users'));
            const upiData = JSON.parse(localStorage.getItem('nimcredit_upi'));
            
            const customerName = document.getElementById('customerName').value;
            const customerPhone = document.getElementById('customerPhone').value;
            const loanCount = parseInt(document.getElementById('loanCount').value);
            
            // Validate phone number
            if (!/^\d{10}$/.test(customerPhone)) {
                showNotification('Please enter a valid 10-digit phone number', 'error');
                hideLoader();
                return;
            }
            
            // Get appropriate UPI for this agent
            let upiToUse = upiData.master || 'default@nimcredit';
            if (currentUser.assigned_to && upiData.admins && upiData.admins[currentUser.assigned_to]) {
                upiToUse = upiData.admins[currentUser.assigned_to];
            }
            
            const links = JSON.parse(localStorage.getItem('nimcredit_links'));
            const generatedLinksContainer = document.getElementById('generatedLinks');
            generatedLinksContainer.innerHTML = '';
            
            let whatsappMessages = [];
            
            for (let i = 1; i <= loanCount; i++) {
                const amount = document.getElementById(`amount${i}`).value;
                const ref = document.getElementById(`ref${i}`).value;
                
                if (!amount) continue;
                
                // Generate short ID
                const shortId = generateShortId();
                
                // Generate direct payment URL
                const shortUrl = generateShortUrl(shortId, amount, customerPhone, customerName, upiToUse, ref);
                
                // Store link data with UPI info
                const linkData = {
                    id: shortId,
                    short_url: shortUrl,
                    customer_name: customerName,
                    customer_phone: customerPhone,
                    amount: amount,
                    reference: ref,
                    agent: currentUser.username,
                    upi_used: upiToUse,
                    created_at: Date.now(),
                    opened: false,
                    paid: false,
                    first_opened_at: null
                };
                
                links.push(linkData);
                
                // Create UI for this link
                const linkDiv = document.createElement('div');
                linkDiv.className = 'p-4 bg-white rounded-lg border-2 border-gray-200';
                linkDiv.innerHTML = `
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold">Loan ${i} - ₹${amount}</span>
                        ${ref ? `<span class="text-sm text-gray-600">Ref: ${ref}</span>` : ''}
                    </div>
                    <div class="bg-gray-100 p-2 rounded text-sm font-mono break-all mb-3 short-url">${shortUrl}</div>
                    <div class="text-xs text-gray-500 mb-2">UPI: ${upiToUse}</div>
                    <div class="flex gap-2">
                        <button onclick="copyLink('${shortUrl}')" class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                            📋 Copy
                        </button>
                        <button onclick="openWhatsApp('${customerName}', '${amount}', '${shortUrl}', '${customerPhone}', ${i}, ${loanCount})" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            💬 WhatsApp
                        </button>
                    </div>
                `;
                generatedLinksContainer.appendChild(linkDiv);
                
                // Prepare WhatsApp message
                const loanLabel = loanCount > 1 ? ` (Loan ${i})` : '';
                const message = generateAntiBanMessage(customerName, amount, shortUrl, loanLabel);
                whatsappMessages.push({ phone: customerPhone, message, loanNum: i });
            }
            
            // Save links
            localStorage.setItem('nimcredit_links', JSON.stringify(links));
            
            // Log activity
            logActivity(currentUser.username, 'links_generated', `Generated ${loanCount} links for ${customerName}`);
            
            // Show result section
            document.getElementById('resultSection').classList.remove('hidden');
            
            hideLoader();
            showNotification('Links generated successfully!');
            
            // Auto-open first WhatsApp
            if (whatsappMessages.length > 0) {
                const first = whatsappMessages[0];
                const waUrl = `https://api.whatsapp.com/send/?phone=91${first.phone}&text=${encodeURIComponent(first.message)}&type=phone_number&app_absent=0`;
                setTimeout(() => window.open(waUrl, '_blank'), 500);
            }
            
            // Refresh agent dashboard
            if (currentUser.role === 'agent') {
                loadAgentDashboard();
            } else if (currentUser.role === 'admin') {
                loadAdminDashboard();
            }
        });

        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                showNotification('Link copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                showNotification('Failed to copy link', 'error');
            });
        }

        function openWhatsApp(name, amount, link, phone, loanNum, totalLoans) {
            const loanLabel = totalLoans > 1 ? ` (Loan ${loanNum})` : '';
            const message = generateAntiBanMessage(name, amount, link, loanLabel);
            const encodedMessage = encodeURIComponent(message);
            const waUrl = `https://api.whatsapp.com/send/?phone=91${phone}&text=${encodedMessage}&type=phone_number&app_absent=0`;
            window.open(waUrl, '_blank');
        }

        // ==================== PAYMENT PAGE HANDLER ====================
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('id')) {
            // This is a payment page - show payment interface
            showPaymentPage();
        } else {
            // Normal login flow
            initializeStorage();
            checkAuth();
        }
        
        function showPaymentPage() {
            const shortId = urlParams.get('id');
            const amount = urlParams.get('amt');
            const phone = urlParams.get('phone');
            const name = urlParams.get('name');
            const upi = urlParams.get('upi');
            const ref = urlParams.get('sn');
            
            // Create simple payment page
            document.body.innerHTML = `
                <div class="expired-page">
                    <div>
                        <h1 style="color:#667eea;font-size:40px;margin-bottom:20px;">💳 NimCredit Payment</h1>
                        <div style="background:white;padding:30px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.1);max-width:500px;">
                            <h2 style="color:#333;font-size:24px;margin-bottom:10px;">Payment Details</h2>
                            <div style="margin-bottom:20px;">
                                <p style="color:#666;margin:5px 0;">Customer: <strong>${name || 'N/A'}</strong></p>
                                <p style="color:#666;margin:5px 0;">Amount: <strong>₹${amount || '0'}</strong></p>
                                ${ref ? `<p style="color:#666;margin:5px 0;">Reference: <strong>${ref}</strong></p>` : ''}
                                <p style="color:#666;margin:5px 0;">UPI ID: <strong>${upi || 'N/A'}</strong></p>
                            </div>
                            
                            <div style="background:#f3f4f6;padding:15px;border-radius:8px;margin-bottom:20px;">
                                <p style="color:#666;font-size:14px;margin-bottom:10px;">📱 Payment Instructions:</p>
                                <ol style="color:#666;font-size:14px;margin-left:20px;">
                                    <li>Open your UPI app (Google Pay, PhonePe, Paytm, etc.)</li>
                                    <li>Send payment to: <strong>${upi || 'N/A'}</strong></li>
                                    <li>Enter amount: <strong>₹${amount || '0'}</strong></li>
                                    <li>Add note: "Payment ${ref ? 'Ref: ' + ref : 'for ' + name}"</li>
                                </ol>
                            </div>
                            
                            <button onclick="markPaymentAsPaid('${shortId}')" style="width:100%;background:#667eea;color:white;border:none;padding:15px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;margin-bottom:10px;">
                                ✅ Mark as Paid
                            </button>
                            
                            <p style="color:#999;font-size:12px;text-align:center;margin-top:20px;">
                                This link will expire 20 minutes after opening
                            </p>
                        </div>
                    </div>
                </div>
            `;
            
            // Mark as opened
            const links = JSON.parse(localStorage.getItem('nimcredit_links') || '[]');
            const linkIndex = links.findIndex(l => l.id === shortId);
            
            if (linkIndex !== -1) {
                const now = Date.now();
                
                if (!links[linkIndex].first_opened_at) {
                    // First time opening
                    links[linkIndex].opened = true;
                    links[linkIndex].first_opened_at = now;
                    
                    // Log activity
                    const agent = links[linkIndex].agent;
                    logActivity(agent, 'link_opened', `Customer ${name} opened payment link`);
                    
                    localStorage.setItem('nimcredit_links', JSON.stringify(links));
                } else {
                    // Check expiry (20 minutes)
                    const expiryTime = links[linkIndex].first_opened_at + (20 * 60 * 1000);
                    if (now > expiryTime) {
                        document.body.innerHTML = `
                            <div class="expired-page">
                                <div>
                                    <h1 style="color:#FF5263;font-size:40px;margin-bottom:20px;">⚠️ Link Expired</h1>
                                    <p style="color:#666;font-size:18px;">This payment link expired 20 minutes after opening for security reasons.<br>Please contact the agent for a new link.</p>
                                </div>
                            </div>
                        `;
                    }
                }
            }
        }
        
        function markPaymentAsPaid(shortId) {
            const links = JSON.parse(localStorage.getItem('nimcredit_links') || '[]');
            const linkIndex = links.findIndex(l => l.id === shortId);
            
            if (linkIndex !== -1) {
                links[linkIndex].paid = true;
                localStorage.setItem('nimcredit_links', JSON.stringify(links));
                
                // Log activity
                const link = links[linkIndex];
                logActivity(link.agent, 'payment_received', `Payment received for ${link.customer_name} - ₹${link.amount}`);
                
                document.body.innerHTML = `
                    <div class="expired-page">
                        <div>
                            <h1 style="color:#43e97b;font-size:40px;margin-bottom:20px;">✅ Payment Marked as Paid</h1>
                            <p style="color:#666;font-size:18px;">Thank you for your payment! The agent has been notified.</p>
                            <p style="color:#999;font-size:14px;margin-top:20px;">You can close this window now.</p>
                        </div>
                    </div>
                `;
            }
        }

        // Initialize
        console.log("NimCredit Multi-Level Admin Portal running on:", DOMAIN);
    </script>

</body>
</html>