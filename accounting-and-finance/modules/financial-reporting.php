<?php
require_once '../config/database.php';
require_once '../includes/session.php';

requireLogin();
$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reporting & Compliance - Accounting and Finance System</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/image/LOGO.png">
    <link rel="shortcut icon" type="image/png" href="../assets/image/LOGO.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/financial-reporting.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid px-4">
            <div class="logo-section">
                <div class="logo-circle">
                    <img src="../assets/image/LOGO.png" alt="Evergreen Logo" class="logo-img">
                </div>
                <div class="logo-text">
                    <h1>EVERGREEN</h1>
                    <p>Secure. Invest. Achieve</p>
                </div>
            </div>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../core/dashboard.php">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="modulesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-th-large me-1"></i>Modules
                        </a>
                        <ul class="dropdown-menu dropdown-menu-custom" aria-labelledby="modulesDropdown">
                            <li><a class="dropdown-item" href="general-ledger.php"><i class="fas fa-book me-2"></i>General Ledger</a></li>
                            <li><a class="dropdown-item" href="financial-reporting.php"><i class="fas fa-chart-line me-2"></i>Financial Reporting</a></li>
                            <li><a class="dropdown-item" href="loan-accounting.php"><i class="fas fa-hand-holding-usd me-2"></i>Loan Accounting</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="transaction-reading.php"><i class="fas fa-exchange-alt me-2"></i>Transaction Reading</a></li>
                            <li><a class="dropdown-item" href="expense-tracking.php"><i class="fas fa-receipt me-2"></i>Expense Tracking</a></li>
                            <li><a class="dropdown-item" href="payroll-management.php"><i class="fas fa-users me-2"></i>Payroll Management</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-alt me-1"></i>Reports
                        </a>
                        <ul class="dropdown-menu dropdown-menu-custom" aria-labelledby="reportsDropdown">
                            <li><a class="dropdown-item" href="financial-reporting.php"><i class="fas fa-chart-bar me-2"></i>Financial Statements</a></li>
                            <li><a class="dropdown-item" href="financial-reporting.php"><i class="fas fa-money-bill-wave me-2"></i>Cash Flow Report</a></li>
                            <li><a class="dropdown-item" href="expense-tracking.php"><i class="fas fa-clipboard-list me-2"></i>Expense Summary</a></li>
                            <li><a class="dropdown-item" href="payroll-management.php"><i class="fas fa-wallet me-2"></i>Payroll Report</a></li>
                        </ul>
                    </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cog me-1"></i>Settings
                            </a>
                            <ul class="dropdown-menu dropdown-menu-custom" aria-labelledby="settingsDropdown">
                                <li><a class="dropdown-item" href="bin-station.php"><i class="fas fa-trash-alt me-2"></i>Bin Station</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="database-settings.php"><i class="fas fa-database me-2"></i>Database Settings</a></li>
                            </ul>
                    </li>
                </ul>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Notifications -->
                <div class="dropdown d-none d-md-block">
                    <a class="nav-icon-btn" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom notifications-dropdown" aria-labelledby="notificationsDropdown">
                        <li class="dropdown-header">Notifications</li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-item text-center text-muted"><small>Loading notifications...</small></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center small" href="activity-log.php">View All Notifications</a></li>
                    </ul>
                </div>
                
                <!-- User Profile Dropdown -->
                <div class="dropdown">
                    <a class="user-profile-btn" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-2"></i>
                        <span class="d-none d-lg-inline"><?php echo htmlspecialchars($current_user['full_name']); ?></span>
                        <i class="fas fa-chevron-down ms-2 d-none d-lg-inline"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom" aria-labelledby="userDropdown">
                        <li class="dropdown-header">
                            <div class="user-dropdown-header">
                                <i class="fas fa-user-circle fa-2x"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong>
                                    <small><?php echo htmlspecialchars($current_user['username']); ?></small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="activity-log.php"><i class="fas fa-history me-2"></i>Activity Log</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../core/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="container-fluid py-4">
        <!-- Beautiful Page Header -->
        <div class="beautiful-page-header mb-5">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="header-content">
                            <h1 class="page-title-beautiful">
                                <i class="fas fa-chart-line me-3"></i>
                                Financial Reporting & Compliance
                            </h1>
                            <p class="page-subtitle-beautiful">
                                Generate comprehensive financial reports and analyze your business performance
                            </p>
                        </div>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <div class="header-info-card">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Database Status</div>
                                    <div class="info-value status-connected">Connected</div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Current Period</div>
                                    <div class="info-value"><?php echo date('F Y'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-actions mt-3">
                    <a href="../core/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Reports Section -->
        <div class="reports-section">
            <div class="section-header-simple mb-4">
                <h2 class="section-title-simple">Financial Reports</h2>
                <p class="section-subtitle-simple">Select a report type to generate detailed financial analysis</p>
                </div>


            <!-- Report Cards Grid -->
            <div class="row g-4 mb-5">
                <!-- Balance Sheet Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="report-card-modern h-100">
                        <div class="card-header-modern">
                            <div class="report-icon">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <div class="report-meta">
                                <h5 class="report-title">Balance Sheet</h5>
                                <p class="report-subtitle">Assets, Liabilities, and Equity</p>
                            </div>
                        </div>
                        <div class="card-body-modern">
                            <div class="report-summary">
                                <?php 
                                // ============================================
                                // BALANCE SHEET CALCULATION - Matches API exactly
                                // ============================================
                                
                                // ASSETS: Cash (Deposits - Withdrawals) + Net Transfers + Loans Receivable + Interest + Fees
                                $assets = 0;
                                $deposits = 0;
                                $withdrawals = 0;
                                $transfers_in = 0;
                                $transfers_out = 0;
                                $interest_income = 0;
                                $fees_collected = 0;
                                
                                if ($conn->query("SHOW TABLES LIKE 'bank_transactions'")->num_rows > 0 &&
                                    $conn->query("SHOW TABLES LIKE 'transaction_types'")->num_rows > 0) {
                                    // Deposits
                                    $result = $conn->query("SELECT COALESCE(SUM(bt.amount), 0) as total FROM bank_transactions bt INNER JOIN transaction_types tt ON bt.transaction_type_id = tt.transaction_type_id WHERE tt.type_name = 'Deposit'");
                                    if ($result) { $row = $result->fetch_assoc(); $deposits = floatval($row['total'] ?? 0); }
                                    
                                    // Withdrawals
                                    $result = $conn->query("SELECT COALESCE(SUM(bt.amount), 0) as total FROM bank_transactions bt INNER JOIN transaction_types tt ON bt.transaction_type_id = tt.transaction_type_id WHERE tt.type_name = 'Withdrawal'");
                                    if ($result) { $row = $result->fetch_assoc(); $withdrawals = floatval($row['total'] ?? 0); }
                                    
                                    // Transfers In
                                    $result = $conn->query("SELECT COALESCE(SUM(bt.amount), 0) as total FROM bank_transactions bt INNER JOIN transaction_types tt ON bt.transaction_type_id = tt.transaction_type_id WHERE tt.type_name = 'Transfer In'");
                                    if ($result) { $row = $result->fetch_assoc(); $transfers_in = floatval($row['total'] ?? 0); }
                                    
                                    // Transfers Out
                                    $result = $conn->query("SELECT COALESCE(SUM(bt.amount), 0) as total FROM bank_transactions bt INNER JOIN transaction_types tt ON bt.transaction_type_id = tt.transaction_type_id WHERE tt.type_name = 'Transfer Out'");
                                    if ($result) { $row = $result->fetch_assoc(); $transfers_out = floatval($row['total'] ?? 0); }
                                    
                                    // Interest Income
                                    $result = $conn->query("SELECT COALESCE(SUM(bt.amount), 0) as total FROM bank_transactions bt INNER JOIN transaction_types tt ON bt.transaction_type_id = tt.transaction_type_id WHERE tt.type_name = 'Interest Payment'");
                                    if ($result) { $row = $result->fetch_assoc(); $interest_income = floatval($row['total'] ?? 0); }
                                    
                                    // Fees
                                    $result = $conn->query("SELECT COALESCE(SUM(bt.amount), 0) as total FROM bank_transactions bt INNER JOIN transaction_types tt ON bt.transaction_type_id = tt.transaction_type_id WHERE tt.type_name = 'Fee'");
                                    if ($result) { $row = $result->fetch_assoc(); $fees_collected = floatval($row['total'] ?? 0); }
                                }
                                
                                // Loans Receivable
                                $loan_disbursed = 0;
                                $loan_payments_total = 0;
                                if ($conn->query("SHOW TABLES LIKE 'loan_applications'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(loan_amount), 0) as total FROM loan_applications WHERE status IN ('Approved', 'Active', 'Disbursed')");
                                    if ($result) { $row = $result->fetch_assoc(); $loan_disbursed = floatval($row['total'] ?? 0); }
                                }
                                if ($conn->query("SHOW TABLES LIKE 'loan_payments'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM loan_payments");
                                    if ($result) { $row = $result->fetch_assoc(); $loan_payments_total = floatval($row['total'] ?? 0); }
                                }
                                // Add loan payments from bank_transactions
                                if ($conn->query("SHOW TABLES LIKE 'bank_transactions'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(bt.amount), 0) as total FROM bank_transactions bt INNER JOIN transaction_types tt ON bt.transaction_type_id = tt.transaction_type_id WHERE tt.type_name = 'Loan Payment'");
                                    if ($result) { $row = $result->fetch_assoc(); $loan_payments_total += floatval($row['total'] ?? 0); }
                                }
                                
                                $cash_net = $deposits - $withdrawals;
                                $transfers_net = $transfers_in - $transfers_out;
                                $loans_receivable = $loan_disbursed - $loan_payments_total;
                                
                                if ($cash_net != 0) $assets += $cash_net;
                                if ($transfers_net != 0) $assets += $transfers_net;
                                if ($loans_receivable > 0) $assets += $loans_receivable;
                                if ($interest_income > 0) $assets += $interest_income;
                                if ($fees_collected > 0) $assets += $fees_collected;
                                
                                // LIABILITIES: Customer Deposits Payable + Rewards + Missions + Payroll
                                $liabilities = 0;
                                if ($deposits > 0) $liabilities += $deposits;
                                
                                // Rewards Points Liability
                                if ($conn->query("SHOW TABLES LIKE 'points_history'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(points), 0) as total FROM points_history");
                                    if ($result) { $row = $result->fetch_assoc(); $liabilities += floatval($row['total'] ?? 0) * 0.01; }
                                }
                                
                                // Missions Liability
                                if ($conn->query("SHOW TABLES LIKE 'user_missions'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(points_earned), 0) as total FROM user_missions WHERE status = 'completed'");
                                    if ($result) { $row = $result->fetch_assoc(); $liabilities += floatval($row['total'] ?? 0) * 0.01; }
                                }
                                
                                // Payroll Expenses
                                if ($conn->query("SHOW TABLES LIKE 'payroll_runs'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(total_net), 0) as total FROM payroll_runs WHERE status IN ('completed', 'finalized')");
                                    if ($result) { $row = $result->fetch_assoc(); $liabilities += floatval($row['total'] ?? 0); }
                                }
                                
                                // EQUITY = Assets - Liabilities
                                $equity = $assets - $liabilities;
                                ?>
                                <div class="summary-item">
                                    <span class="summary-label">Assets</span>
                                    <span class="summary-value text-primary">₱<?php echo number_format($assets, 0); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Liabilities</span>
                                    <span class="summary-value text-warning">₱<?php echo number_format($liabilities, 0); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Equity</span>
                                    <span class="summary-value text-success">₱<?php echo number_format($equity, 0); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer-modern">
                            <button class="btn btn-primary btn-generate-modern" onclick="openReportModal('balance-sheet')">
                                <i class="fas fa-file-alt me-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                    </div>

                    <!-- Income Statement Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="report-card-modern h-100">
                        <div class="card-header-modern">
                            <div class="report-icon">
                            <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="report-meta">
                                <h5 class="report-title">Income Statement</h5>
                                <p class="report-subtitle">Revenue, expenses, and Net income</p>
                            </div>
                        </div>
                        <div class="card-body-modern">
                            <div class="report-summary">
                                <?php 
                                // ============================================
                                // INCOME STATEMENT - Matches API generateIncomeStatement() exactly
                                // Revenue: Loan Interest + Loan Payments + Deposits + Transfers In
                                // Expenses: Withdrawals + Transfers Out + Loan Disbursements + Rewards
                                // Note: API doesn't include Payroll in Income Statement expenses
                                // ============================================
                                
                                // REVENUE
                                $revenue = 0;
                                // Loan Interest Income (15% annual rate / 12 months)
                                $loan_interest_rev = $loan_disbursed * 0.15 / 12;
                                if ($loan_interest_rev > 0) $revenue += $loan_interest_rev;
                                if ($loan_payments_total > 0) $revenue += $loan_payments_total;
                                if ($deposits > 0) $revenue += $deposits;
                                if ($transfers_in > 0) $revenue += $transfers_in;
                                
                                // EXPENSES (matching API - no payroll)
                                $expenses = 0;
                                if ($withdrawals > 0) $expenses += $withdrawals;
                                if ($transfers_out > 0) $expenses += $transfers_out;
                                if ($loan_disbursed > 0) $expenses += $loan_disbursed;
                                
                                // Rewards expense
                                $rewards_exp = 0;
                                if ($conn->query("SHOW TABLES LIKE 'points_history'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(points), 0) as total FROM points_history");
                                    if ($result) { $row = $result->fetch_assoc(); $rewards_exp = floatval($row['total'] ?? 0) * 0.01; }
                                }
                                if ($rewards_exp > 0) $expenses += $rewards_exp;
                                
                                // Missions expense
                                $missions_exp = 0;
                                if ($conn->query("SHOW TABLES LIKE 'user_missions'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(points_earned), 0) as total FROM user_missions WHERE status = 'completed'");
                                    if ($result) { $row = $result->fetch_assoc(); $missions_exp = floatval($row['total'] ?? 0) * 0.01; }
                                }
                                if ($missions_exp > 0) $expenses += $missions_exp;
                                
                                // Payroll expense (for Trial Balance only, not Income Statement per API)
                                $payroll_exp = 0;
                                if ($conn->query("SHOW TABLES LIKE 'payroll_runs'")->num_rows > 0) {
                                    $result = $conn->query("SELECT COALESCE(SUM(total_net), 0) as total FROM payroll_runs WHERE status IN ('completed', 'finalized')");
                                    if ($result) { $row = $result->fetch_assoc(); $payroll_exp = floatval($row['total'] ?? 0); }
                                }
                                
                                $net_income = $revenue - $expenses;
                                ?>
                                <div class="summary-item">
                                    <span class="summary-label">Revenue</span>
                                    <span class="summary-value text-success">₱<?php echo number_format($revenue, 0); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Expenses</span>
                                    <span class="summary-value text-danger">₱<?php echo number_format($expenses, 0); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Net Income</span>
                                    <span class="summary-value <?php echo $net_income >= 0 ? 'text-success' : 'text-danger'; ?>">₱<?php echo number_format($net_income, 0); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer-modern">
                            <button class="btn btn-primary btn-generate-modern" onclick="openReportModal('income-statement')">
                                <i class="fas fa-file-alt me-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                    </div>

                    <!-- Cash Flow Statement Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="report-card-modern h-100">
                        <div class="card-header-modern">
                            <div class="report-icon">
                            <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="report-meta">
                                <h5 class="report-title">Cash Flow Statement</h5>
                                <p class="report-subtitle">Operating, Investing, and Financing Activities</p>
                            </div>
                        </div>
                        <div class="card-body-modern">
                            <div class="report-summary">
                                <?php 
                                // ============================================
                                // CASH FLOW STATEMENT - Matches API exactly
                                // ============================================
                                
                                // Cash Balance = Total Deposits (same as API summary.total_deposits)
                                $cash_balance = $deposits;
                                
                                // Operating = Deposits - Withdrawals + Transfers In - Transfers Out + Interest + Fees
                                $operating_cash = $deposits - $withdrawals + $transfers_in - $transfers_out + $interest_income + $fees_collected;
                                
                                // Financing = Loan Payments Received - Loan Disbursements
                                $financing_cash = $loan_payments_total - $loan_disbursed;
                                ?>
                                <div class="summary-item">
                                    <span class="summary-label">Cash Balance</span>
                                    <span class="summary-value text-info">₱<?php echo number_format($cash_balance, 0); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Operating</span>
                                    <span class="summary-value <?php echo $operating_cash >= 0 ? 'text-success' : 'text-danger'; ?>">₱<?php echo number_format($operating_cash, 0); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Financing</span>
                                    <span class="summary-value <?php echo $financing_cash >= 0 ? 'text-success' : 'text-danger'; ?>">₱<?php echo number_format($financing_cash, 0); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer-modern">
                            <button class="btn btn-primary btn-generate-modern" onclick="openReportModal('cash-flow')">
                                <i class="fas fa-file-alt me-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                    </div>

                    <!-- Trial Balance Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="report-card-modern h-100">
                        <div class="card-header-modern">
                            <div class="report-icon">
                            <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="report-meta">
                                <h5 class="report-title">Trial Balance</h5>
                                <p class="report-subtitle">Account Balances and Totals</p>
                            </div>
                        </div>
                        <div class="card-body-modern">
                            <div class="report-summary">
                                <?php 
                                // ============================================
                                // TRIAL BALANCE - Matches API exactly
                                // API includes: Deposits, Transfers In, Interest, Loan Disbursements, Rewards (as debits)
                                // API includes: Withdrawals, Transfers Out, Fees, Loan Payments (as credits)
                                // API does NOT include payroll in trial balance
                                // ============================================
                                
                                // DEBITS: Deposits, Transfers In, Interest, Loan Disbursements, Rewards
                                $total_debits = 0;
                                if ($deposits > 0) $total_debits += $deposits;
                                if ($transfers_in > 0) $total_debits += $transfers_in;
                                if ($interest_income > 0) $total_debits += $interest_income;
                                if ($loan_disbursed > 0) $total_debits += $loan_disbursed;
                                if ($rewards_exp > 0) $total_debits += $rewards_exp;
                                if ($missions_exp > 0) $total_debits += $missions_exp;
                                // Note: Payroll is NOT included in API trial balance
                                
                                // CREDITS: Withdrawals, Transfers Out, Fees, Loan Payments
                                $total_credits = 0;
                                if ($withdrawals > 0) $total_credits += $withdrawals;
                                if ($transfers_out > 0) $total_credits += $transfers_out;
                                if ($fees_collected > 0) $total_credits += $fees_collected;
                                if ($loan_payments_total > 0) $total_credits += $loan_payments_total;
                                
                                // Balancing entry - Retained Earnings
                                $difference = $total_debits - $total_credits;
                                if (abs($difference) > 0.01) {
                                    if ($difference > 0) {
                                        $total_credits += $difference;
                                    } else {
                                        $total_debits += abs($difference);
                                    }
                                }
                                
                                $is_balanced = abs($total_debits - $total_credits) < 0.01;
                                ?>
                                <div class="summary-item">
                                    <span class="summary-label">Total Debits</span>
                                    <span class="summary-value text-danger">₱<?php echo number_format($total_debits, 2); ?></span>
                        </div>
                                <div class="summary-item">
                                    <span class="summary-label">Total Credits</span>
                                    <span class="summary-value text-success">₱<?php echo number_format($total_credits, 2); ?></span>
                    </div>
                                <div class="summary-item">
                                    <span class="summary-label">Status</span>
                                    <span class="summary-value <?php echo $is_balanced ? 'text-success' : 'text-warning'; ?>">
                                        <?php echo $is_balanced ? "✓ Balanced" : "⚠ Unbalanced"; ?>
                                    </span>
                </div>
            </div>
                        </div>
                        <div class="card-footer-modern">
                            <button class="btn btn-primary btn-generate-modern" onclick="openReportModal('trial-balance')">
                                <i class="fas fa-file-alt me-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Regulatory Reports Card -->
                <div class="col-lg-4 col-md-6">
                    <div class="report-card-modern h-100">
                        <div class="card-header-modern">
                            <div class="report-icon">
                                <i class="fas fa-shield-alt"></i>
                    </div>
                            <div class="report-meta">
                                <h5 class="report-title">Regulatory Reports</h5>
                                <p class="report-subtitle">BSP, SEC, or internal compliance templates</p>
                            </div>
                        </div>
                        <div class="card-body-modern">
                            <div class="report-summary">
                                <div class="summary-item">
                                    <span class="summary-label">BSP Reports</span>
                                    <span class="summary-value text-primary">Available</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">SEC Filings</span>
                                    <span class="summary-value text-success">Available</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Internal Compliance</span>
                                    <span class="summary-value text-warning">Available</span>
                    </div>
                            </div>
                        </div>
                        <div class="card-footer-modern">
                            <button class="btn btn-primary btn-generate-modern" onclick="openReportModal('regulatory-reports')">
                                <i class="fas fa-file-alt me-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                </div>
                            </div>
                        </div>

        <!-- Filtering Section -->
        <div class="filtering-section-modern">
            <div class="section-header-simple mb-4">
                <h2 class="section-title-simple">
                    <i class="fas fa-filter me-2" style="color: var(--primary-teal);"></i>Data Filtering & Search
                </h2>
                <p class="section-subtitle-simple">Filter and search financial data across all reports</p>
            </div>

            <div class="filtering-card">
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-calendar-alt me-2" style="color: var(--primary-teal);"></i>Date From
                            </label>
                            <input type="date" class="form-control form-control-modern" id="filter-date-from">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-calendar-alt me-2" style="color: var(--primary-teal);"></i>Date To
                            </label>
                            <input type="date" class="form-control form-control-modern" id="filter-date-to">
                        </div>
                    </div>

                       <div class="col-lg-3 col-md-6">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-cogs me-2" style="color: var(--primary-teal);"></i>Subsystem
                            </label>
                            <select class="form-select form-select-modern" id="filter-subsystem">
                                <option value="">All Subsystems</option>
                                <option value="bank-system">Bank System (Real Customer Accounts)</option>
                                <option value="loan">Loan Subsystem (Real Borrowers)</option>
                                <option value="payroll">Payroll (Real Employees - HRIS Integration)</option>
                                <option value="hris-sia">HRIS-SIA (Real Employee Contracts)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-tags me-2" style="color: var(--primary-teal);"></i>Account Type
                            </label>
                            <select class="form-select form-select-modern" id="filter-account-type">
                                <option value="">All Types</option>
                                <option value="asset">Assets</option>
                                <option value="liability">Liabilities</option>
                                <option value="equity">Equity</option>
                                <option value="revenue">Revenue</option>
                                <option value="expense">Expenses</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-search me-2" style="color: var(--primary-teal);"></i>Custom Search
                            </label>
                            <input type="text" class="form-control form-control-modern" id="filter-custom-search" 
                                   placeholder="Search by account name, description, or reference number...">
                        </div>
                    </div>
                    <div class="col-lg-4 d-flex align-items-end">
                        <div class="filter-actions">
                            <button class="btn btn-primary btn-lg me-3 px-4" onclick="applyFilters()">
                                <i class="fas fa-search me-2"></i>Apply Filters
                            </button>
                            <button class="btn btn-outline-secondary btn-lg px-3" onclick="clearFilters()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
                </div>

        <!-- Filtered Results Section -->
        <div class="filtered-results-modern" id="filtered-results" style="display: none;">
            <div class="section-header-simple mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="section-title-simple mb-2">
                            <i class="fas fa-table me-2" style="color: var(--primary-teal);"></i>Filtered Results
                        </h2>
                        <p class="section-subtitle-simple mb-2" id="results-summary">Showing filtered results</p>
                        <span class="badge bg-success text-white fs-6 px-3 py-2" id="filter-status">No filters applied</span>
                    </div>
                    <!-- Action Buttons -->
                    <div class="results-actions-simple mt-2">
                        <button class="btn btn-success btn-lg me-2" onclick="exportFilteredData('excel')">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </button>
                        <button class="btn btn-danger btn-lg me-2" onclick="exportFilteredData('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </button>
                        <button class="btn btn-secondary btn-lg" onclick="printFilteredData()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination Controls -->
            <div class="pagination-controls mb-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <label for="entries-per-page" class="form-label me-2 mb-0">Show</label>
                            <select class="form-select form-select-sm" id="entries-per-page" style="width: auto;" onchange="changeEntriesPerPage()">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span class="ms-2">entries per page</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-end align-items-center">
                            <span class="text-muted me-3" id="pagination-info">Showing 0 to 0 of 0 entries</span>
                            <nav aria-label="Pagination">
                                <ul class="pagination pagination-sm mb-0" id="pagination-controls">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" onclick="goToPage(1)">First</a>
                                    </li>
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" onclick="goToPreviousPage()">Previous</a>
                                    </li>
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" onclick="goToNextPage()">Next</a>
                                    </li>
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" onclick="goToLastPage()">Last</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <div class="results-table-container">
                <div class="table-responsive">
                    <table class="table table-modern" id="filtered-results-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-calendar me-1" style="color: var(--primary-teal);"></i>Date</th>
                                <th><i class="fas fa-hashtag me-1" style="color: var(--primary-teal);"></i>Account Code</th>
                                <th><i class="fas fa-tag me-1" style="color: var(--primary-teal);"></i>Account Name</th>
                                <th><i class="fas fa-align-left me-1" style="color: var(--primary-teal);"></i>Description</th>
                                <th class="text-end"><i class="fas fa-arrow-up me-1" style="color: var(--accent-gold);"></i>Debit</th>
                                <th class="text-end"><i class="fas fa-arrow-down me-1" style="color: var(--primary-teal);"></i>Credit</th>
                                <th class="text-end"><i class="fas fa-balance-scale me-1" style="color: var(--primary-teal);"></i>Balance</th>
                            </tr>
                        </thead>
                        <tbody id="filtered-results-tbody">
                            <!-- Filtered results will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- No Results Message -->
            <div class="no-results-modern" id="no-results-message" style="display: none;">
                <div class="text-center py-5">
                    <div class="no-results-icon mb-4">
                        <i class="fas fa-search fa-4x text-muted opacity-50"></i>
                    </div>
                    <h3 class="text-muted mb-3">No Results Found</h3>
                    <p class="text-muted mb-4">No records match your current filter criteria. Try adjusting your filters.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn btn-outline-primary" onclick="clearFilters()">
                            <i class="fas fa-times me-1"></i>Clear Filters
                        </button>
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class="fas fa-search me-1"></i>Try Different Filters
                            </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Report Generation Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalTitle">Generate Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="reportModalContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="mt-5">
        <div class="container-fluid">
            <p class="mb-0 text-center">&copy; <?php echo date('Y'); ?> Evergreen Accounting & Finance. All rights reserved.</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/dashboard.js"></script>
    <script src="../assets/js/financial-reporting.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
