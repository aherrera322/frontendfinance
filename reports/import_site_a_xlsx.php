<?php
require_once '../auth/config.php';

// Check if user is authenticated
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($fileExtension === 'xlsx') {
            try {
                require_once 'vendor/autoload.php';
                
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                // Remove header row
                $headers = array_shift($rows);
                
                $pdo = getReservationsDB();
                
                // Get source_id for ZB.com
                $stmt = $pdo->prepare("SELECT id FROM data_sources WHERE source_name = 'zbcom'");
                $stmt->execute();
                $sourceId = $stmt->fetch(PDO::FETCH_ASSOC)['id'];
                
                // Prepare insert statement
                $insertStmt = $pdo->prepare("INSERT INTO zbcom_reservations (
                    source_id, res_date, agency, pay_mode, api_value, credit, cpc, prepay, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $insertedCount = 0;
                
                foreach ($rows as $row) {
                    if (count($row) >= 9) { // Ensure we have enough columns
                        $insertStmt->execute([
                            $sourceId,
                            $row[0] ?? null, // res_date
                            $row[1] ?? null, // agency
                            $row[2] ?? null, // pay_mode
                            $row[3] ?? null, // api_value
                            $row[4] ?? null, // credit
                            $row[5] ?? null, // cpc
                            $row[6] ?? null, // prepay
                            $row[7] ?? null  // status
                        ]);
                        $insertedCount++;
                    }
                }
                
                $message = "Successfully imported $insertedCount records from Site A.";
                $messageType = "success";
                
            } catch (Exception $e) {
                $message = "Error importing file: " . $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "Please upload an XLSX file.";
            $messageType = "error";
        }
    } else {
        $message = "Error uploading file.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Site A Reservations - Zimple Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-primary text-white shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">Zimple Admin</h1>
                    <nav class="flex items-center space-x-6">
                        <a href="../reports/index.html" class="hover:text-yellow-200 transition-colors">Reports</a>
                        <a href="../admin/clients/index.html" class="hover:text-yellow-200 transition-colors">Clients</a>
                        <a href="../admin/partners/index.html" class="hover:text-yellow-200 transition-colors">Partners</a>
                        <a href="../admin/users/index.html" class="hover:text-yellow-200 transition-colors">Users</a>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8">
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Import Site A Reservations</h2>
                    
                    <?php if (isset($message)): ?>
                        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div>
                            <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Site A XLSX File
                            </label>
                            <input type="file" id="file" name="file" accept=".xlsx" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <p class="mt-1 text-sm text-gray-500">Upload an XLSX file containing Site A reservation data.</p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <button type="submit" class="bg-primary hover:bg-primary/90 text-white px-6 py-2 rounded-md font-medium transition-colors">
                                <i class="fas fa-upload mr-2"></i>Import Data
                            </button>
                            <a href="../reports/index.html" class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-6 py-2 rounded-md font-medium transition-colors">
                                Back to Reports
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

