<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/MenuManager.php';

$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

$auth->requireAuth('/tls/login.php');

if (!$auth->hasMenuAccess('mnuLoadEntry')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$menuManager = new MenuManager($auth);
$user = $auth->getCurrentUser();

$db = new Database(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);
$db->connect($user['customer_db']);

$loadKey = $_GET['loadkey'] ?? null;
$loadData = null;
$errorMessage = '';
$successMessage = '';

if ($_POST['action'] ?? '' === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle tender saving first if manual entry
        $tenderKey = saveTender($db, $_POST);
        
        $params = [
            'Key' => (int)($_POST['load_key'] ?? 0),
            'OriginKey' => (int)($_POST['origin_key'] ?? 0),
            'DestinationKey' => (int)($_POST['destination_key'] ?? 0),
            'ShipmentID' => $_POST['shipment_id'] ?? '',
            'PaymentMeth' => $_POST['payment_method'] ?? 'COD',
            'TenderKey' => $tenderKey,
            'Priority' => (int)($_POST['priority'] ?? 1),
            'Height' => (int)($_POST['height'] ?? 0),
            'Width' => (int)($_POST['width'] ?? 0),
            'Length' => (int)($_POST['length'] ?? 0),
            'MinWeight' => (int)($_POST['min_weight'] ?? 0),
            'Weight' => (int)($_POST['weight'] ?? 0),
            'Miles' => (int)($_POST['miles'] ?? 0),
            'Rate' => (float)($_POST['rate'] ?? 0.00),
            'Commodity' => $_POST['commodity'] ?? '',
            'RateCode' => $_POST['rate_code'] ?? 'F',
            'Booked' => isset($_POST['booked']) ? 1 : 0,
            'Brokerable' => isset($_POST['brokerable']) ? 1 : 0,
            'Dispatched' => isset($_POST['dispatched']) ? 1 : 0,
            'EDIAck' => isset($_POST['edi_ack']) ? 1 : 0,
            'Offer' => (int)($_POST['offer'] ?? 0),
            'EDIStatus' => $_POST['edi_status'] ?? '',
            'EnteredBy' => $user['user_id'],
            'Billed' => isset($_POST['billed']) ? 1 : 0,
            'Notes' => $_POST['notes'] ?? '',
            'CustomerKey' => (int)($_POST['customer_key'] ?? 0),
            'LocationCode' => (int)($_POST['location_code'] ?? 0),
            'BillingFlag' => isset($_POST['billing_flag']) ? 1 : 0,
            'ReloadZone' => (int)($_POST['reload_zone'] ?? 0),
            'Hold' => isset($_POST['hold']) ? 1 : 0,
            'AutoOffer' => isset($_POST['auto_offer']) ? 1 : 0,
            'LoadDateKey' => (int)($_POST['load_date_key'] ?? 0),
            'DelDateKey' => (int)($_POST['del_date_key'] ?? 0),
            'HomeTime' => (int)($_POST['home_time'] ?? 0),
            'PTACity' => $_POST['pta_city'] ?? '',
            'PTAState' => $_POST['pta_state'] ?? '',
            'PTAZip' => $_POST['pta_zip'] ?? '',
            'LoadType' => $_POST['load_type'] ?? 'NET',
            'TrailerType' => $_POST['trailer_type'] ?? 'FLT',
            'SplitLoad' => isset($_POST['split_load']) ? 1 : 0,
            'ExternalPostingStopped' => isset($_POST['external_posting_stopped']) ? 1 : 0,
            'CompanyID' => (int)($_POST['company_id'] ?? 1),
            'TrailerKey' => (int)($_POST['trailer_key'] ?? 0),
            'Preloaded' => isset($_POST['preloaded']) ? 1 : 0,
            'OriginStopReason' => $_POST['origin_stop_reason'] ?? 'CL',
            'DestStopReason' => $_POST['dest_stop_reason'] ?? 'CU',
            'ST_ID' => (int)($_POST['st_id'] ?? 0),
            'HighValue' => isset($_POST['high_value']) ? 1 : 0
        ];

        $result = $db->executeStoredProcedure('spLoadHeader_Save', $params);
        
        if ($result === 0) {
            $loadKey = $params['Key'];
            
            // Handle stops data if present
            if (!empty($_POST['stops_data'])) {
                try {
                    $stopsData = json_decode($_POST['stops_data'], true);
                    if (is_array($stopsData)) {
                        saveLoadStops($db, $loadKey, $stopsData);
                    }
                } catch (Exception $e) {
                    error_log('Error saving stops data: ' . $e->getMessage());
                    $errorMessage = 'Load saved but stops data failed to save.';
                }
            }
            
            if (empty($errorMessage)) {
                $successMessage = 'Load saved successfully.';
            }
        } else {
            $errorMessage = 'Failed to save load. Error code: ' . $result;
        }
    } catch (Exception $e) {
        $errorMessage = 'Error saving load: ' . $e->getMessage();
        error_log('Load save error: ' . $e->getMessage());
    }
}

function saveLoadStops(Database $db, int $loadKey, array $stopsData): void
{
    // Use the spLoadStops_Save stored procedure which should handle the stops for a load
    foreach ($stopsData as $stop) {
        try {
            // Determine if this is a QuickKey address or manual entry
            if (!empty($stop['key'])) {
                // Using existing QuickKey address - use spStop_Save
                $stopParams = [
                    'LoadKey' => $loadKey,
                    'StopSeq' => $stop['sequence'],
                    'ReasonCode' => trim($stop['reason']), // Trim to handle any extra spaces
                    'NameKey' => (int)$stop['key']
                ];
                
                $result = $db->executeStoredProcedure('spStop_Save', $stopParams);
                
            } elseif (!empty($stop['manual'])) {
                // Manual address entry - create new NameAddress with QK qualifier first
                $manual = $stop['manual'];
                
                // Create new name/address entry with QK qualifier for QuickKey addresses
                $addressParams = [
                    'QuickKey' => !empty($manual['quick_key']) ? strtoupper($manual['quick_key']) : '',
                    'Name1' => $manual['company_name'],
                    'Address1' => $manual['address1'],
                    'Address2' => $manual['address2'] ?? '',
                    'City' => $manual['city'],
                    'State' => strtoupper($manual['state']),
                    'Zip' => $manual['zip'] ?? '',
                    'NameQual' => 'QK'  // QuickKey qualifier for new addresses
                ];
                
                // Save the address first to get a NameKey
                $nameKey = $db->executeStoredProcedure('spNameAddress_Save', $addressParams);
                
                if ($nameKey > 0) {
                    // Now save the stop with the new NameKey
                    $stopParams = [
                        'LoadKey' => $loadKey,
                        'StopSeq' => $stop['sequence'],
                        'ReasonCode' => trim($stop['reason']),
                        'NameKey' => $nameKey
                    ];
                    
                    $result = $db->executeStoredProcedure('spStop_Save', $stopParams);
                }
            }
        } catch (Exception $e) {
            error_log("Error saving stop {$stop['sequence']}: " . $e->getMessage());
            throw $e;
        }
    }
}

function saveTender(Database $db, array $postData): int
{
    // Handle tender saving if tender information is provided
    if (!empty($postData['tender_manual'])) {
        $manual = $postData['tender_manual'];
        
        // Create or update tender
        $tenderParams = [
            'QuickKey' => !empty($manual['quick_key']) ? strtoupper($manual['quick_key']) : '',
            'TenderName' => $manual['tender_name'] ?? '',
            'City' => $manual['city'] ?? '',
            'State' => strtoupper($manual['state'] ?? ''),
            'Phone' => $manual['phone'] ?? ''
        ];
        
        // Save the tender and return the TenderKey
        $tenderKey = $db->executeStoredProcedure('spTender_Save', $tenderParams);
        return $tenderKey;
    }
    
    return (int)($postData['tender_key'] ?? 0);
}

if ($loadKey) {
    try {
        $loadData = $db->executeStoredProcedureWithReturn('spLoadHeader_Get', ['Key' => (int)$loadKey]);
        if (empty($loadData)) {
            $errorMessage = 'Load not found.';
            $loadKey = null;
        } else {
            $loadData = $loadData[0];
        }
    } catch (Exception $e) {
        $errorMessage = 'Error loading data: ' . $e->getMessage();
        error_log('Load retrieval error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Load Entry - TLS Operations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/tls/css/app.css">
    <style>
        .sortable-container {
            min-height: 200px;
        }
        
        .stop-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 12px;
            transition: all 0.2s ease;
            cursor: move;
        }
        
        .stop-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .stop-item.dragging {
            opacity: 0.5;
            transform: rotate(2deg);
        }
        
        .stop-item.drag-over {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        
        .stop-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .drag-handle {
            margin-right: 10px;
            color: #6c757d;
            cursor: grab;
            font-size: 18px;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }
        
        .stop-label {
            flex-grow: 1;
            display: flex;
            align-items: center;
        }
        
        .stop-role-badge {
            font-size: 0.85em;
            padding: 0.35em 0.65em;
        }
        
        .stop-content {
            padding-left: 28px; /* Align with the drag handle */
        }
        
        .remove-stop {
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .stop-item:hover .remove-stop {
            opacity: 1;
        }
        
        /* Role-specific styling */
        .stop-item[data-stop-type="origin"] {
            border-left: 4px solid #28a745;
        }
        
        .stop-item[data-stop-type="destination"] {
            border-left: 4px solid #dc3545;
        }
        
        .stop-item[data-stop-type="intermediate"] {
            border-left: 4px solid #ffc107;
        }
        
        .alert-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php echo $menuManager->generateMainMenu(); ?>
        
        <div class="main-content">
            <div class="row mb-4">
                <div class="col">
                    <h2><i class="bi bi-truck"></i> Load Entry</h2>
                </div>
                <div class="col-auto">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="newLoad()">
                            <i class="bi bi-plus-circle"></i> New
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="searchLoad()">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="deleteLoad()" <?php echo $loadKey ? '' : 'disabled'; ?>>
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loadForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="load_key" value="<?php echo $loadData['LoadKey'] ?? 0; ?>">
                
                <!-- Independent Column Layout -->
                <div class="d-flex flex-wrap">
                    <!-- Left Column - Independent Cards -->
                    <div class="flex-shrink-0" style="width: 48%; margin-right: 2%;">
                        <!-- Tender Information - First Priority -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5><i class="bi bi-building"></i> Tender Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="tender_search" class="form-label fw-bold">Tender:</label>
                                        <input type="text" class="form-control" id="tender_search" placeholder="Search tender companies...">
                                        <input type="hidden" id="tender_key" name="tender_key" value="<?php echo $loadData['TenderKey'] ?? ''; ?>">
                                        
                                        <!-- Manual Tender Entry -->
                                        <div class="manual-tender-entry mt-2" id="manual_tender_entry" style="display: none;">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <input type="text" class="form-control form-control-sm" 
                                                           id="tender_quick_key" name="tender_manual[quick_key]" 
                                                           placeholder="Quick Key (6 characters)" maxlength="6">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" class="form-control form-control-sm" 
                                                           name="tender_manual[tender_name]" 
                                                           placeholder="Tender Company Name">
                                                </div>
                                                <div class="col-6">
                                                    <input type="text" class="form-control form-control-sm" 
                                                           name="tender_manual[city]" 
                                                           placeholder="City">
                                                </div>
                                                <div class="col-3">
                                                    <input type="text" class="form-control form-control-sm" 
                                                           name="tender_manual[state]" 
                                                           placeholder="State" maxlength="2">
                                                </div>
                                                <div class="col-3">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" 
                                                            onclick="toggleManualTender(false)">
                                                        <i class="bi bi-search"></i>
                                                    </button>
                                                </div>
                                                <div class="col-12">
                                                    <input type="text" class="form-control form-control-sm" 
                                                           name="tender_manual[phone]" 
                                                           placeholder="Phone Number">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="tender-info mt-1" id="tender_info" style="display: none;">
                                            <small class="text-muted"><span id="tender_details"></span></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleManualTender(true)">
                                        <i class="bi bi-pencil"></i> Enter New Tender
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Information -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5><i class="bi bi-person-lines-fill"></i> Customer Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="customer_search" class="form-label">Customer:</label>
                                        <input type="text" class="form-control" id="customer_search" placeholder="Search customers...">
                                        <input type="hidden" id="customer_key" name="customer_key" value="<?php echo $loadData['BillToCust'] ?? ''; ?>">
                                        <div class="customer-info mt-2" id="customer_info" style="display: none;">
                                            <small class="text-muted">
                                                <div><strong>Terms:</strong> <span id="customer_terms"></span></div>
                                                <div><strong>Credit Limit:</strong> $<span id="customer_credit_limit"></span></div>
                                                <div><strong>AR Balance:</strong> $<span id="customer_ar_balance"></span></div>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="location_search" class="form-label">Location:</label>
                                        <input type="text" class="form-control" id="location_search" placeholder="Search locations..." disabled>
                                        <input type="hidden" id="location_code" name="location_code" value="<?php echo $loadData['BillToLoc'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Load Information -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5><i class="bi bi-info-circle"></i> Load Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="load_number" class="form-label">Load #:</label>
                                        <input type="text" class="form-control" id="load_number" 
                                               value="<?php echo htmlspecialchars($loadData['LoadKey'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="shipment_id" class="form-label">Shipment ID:</label>
                                        <input type="text" class="form-control" id="shipment_id" name="shipment_id" 
                                               value="<?php echo htmlspecialchars($loadData['ShipmentID'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="commodity" class="form-label">Commodity:</label>
                                        <input type="text" class="form-control" id="commodity" name="commodity" 
                                               placeholder="Search commodities..."
                                               value="<?php echo htmlspecialchars($loadData['Commodity'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="weight" class="form-label">Weight:</label>
                                        <input type="number" class="form-control" id="weight" name="weight" 
                                               value="<?php echo $loadData['Weight'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="miles" class="form-label">Miles:</label>
                                        <input type="number" class="form-control" id="miles" name="miles" 
                                               value="<?php echo $loadData['Miles'] ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="rate" class="form-label">Rate:</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="rate" name="rate" step="0.01" 
                                                   value="<?php echo $loadData['Rate'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="rate_code" class="form-label">Rate Code:</label>
                                        <select class="form-select" id="rate_code" name="rate_code">
                                            <option value="F" <?php echo ($loadData['RateCode'] ?? 'F') === 'F' ? 'selected' : ''; ?>>Flat</option>
                                            <option value="M" <?php echo ($loadData['RateCode'] ?? '') === 'M' ? 'selected' : ''; ?>>Per Mile</option>
                                            <option value="C" <?php echo ($loadData['RateCode'] ?? '') === 'C' ? 'selected' : ''; ?>>Per CWT</option>
                                            <option value="NT" <?php echo ($loadData['RateCode'] ?? '') === 'NT' ? 'selected' : ''; ?>>Per Ton</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="payment_method" class="form-label">Payment:</label>
                                        <select class="form-select" id="payment_method" name="payment_method">
                                            <option value="COD" <?php echo ($loadData['PaymentMeth'] ?? 'COD') === 'COD' ? 'selected' : ''; ?>>COD</option>
                                            <option value="NET" <?php echo ($loadData['PaymentMeth'] ?? '') === 'NET' ? 'selected' : ''; ?>>Net</option>
                                            <option value="FAC" <?php echo ($loadData['PaymentMeth'] ?? '') === 'FAC' ? 'selected' : ''; ?>>Factor</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="booked" name="booked" 
                                                   <?php echo ($loadData['Booked'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="booked">Booked</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="brokerable" name="brokerable" 
                                                   <?php echo ($loadData['Brokerable'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="brokerable">Brokerable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="dispatched" name="dispatched" 
                                                   <?php echo ($loadData['Dispatched'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="dispatched">Dispatched</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="hold" name="hold" 
                                                   <?php echo ($loadData['Hold'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="hold">Hold</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column - Independent Cards -->
                    <div class="flex-shrink-0" style="width: 50%;">
                        <!-- Stops -->
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="bi bi-geo-alt"></i> Stops</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addStop()">
                                        <i class="bi bi-plus"></i> Add Stop
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info alert-sm">
                                    <i class="bi bi-info-circle"></i> <strong>Drag to reorder stops.</strong> 
                                    First stop = Shipper (Origin), Last stop = Consignee (Destination), Middle stops = Intermediate stops.
                                </div>
                                
                                <!-- Sortable Stops Container -->
                                <div id="sortable_stops_container" class="sortable-container">
                                    <!-- Origin (initially) -->
                                    <div class="stop-item" data-stop-type="origin" data-stop-id="origin" draggable="true">
                                        <div class="stop-header">
                                            <div class="drag-handle">
                                                <i class="bi bi-grip-vertical"></i>
                                            </div>
                                            <div class="stop-label">
                                                <span class="stop-role-badge badge bg-success">Shipper (Origin)</span>
                                                <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-stop" onclick="removeStopItem('origin')" style="display: none;">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="stop-content">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control stop-address-search" id="origin_search" placeholder="Search shipper address...">
                                                    <input type="hidden" id="origin_key" name="origin_key" value="<?php echo $loadData['OriginKey'] ?? ''; ?>">
                                                    
                                                    <!-- Manual Address Entry -->
                                                    <div class="manual-address-entry mt-2" id="manual_address_origin" style="display: none;">
                                                        <div class="row g-2">
                                                            <div class="col-6">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="origin_manual[quick_key]" 
                                                                       placeholder="Quick Key (6 chars)" maxlength="6">
                                                            </div>
                                                            <div class="col-6">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="origin_manual[company_name]" 
                                                                       placeholder="Company Name">
                                                            </div>
                                                            <div class="col-12">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="origin_manual[address1]" 
                                                                       placeholder="Address Line 1">
                                                            </div>
                                                            <div class="col-12">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="origin_manual[address2]" 
                                                                       placeholder="Address Line 2 (Optional)">
                                                            </div>
                                                            <div class="col-4">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="origin_manual[city]" 
                                                                       placeholder="City">
                                                            </div>
                                                            <div class="col-3">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="origin_manual[state]" 
                                                                       placeholder="State" maxlength="2">
                                                            </div>
                                                            <div class="col-3">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="origin_manual[zip]" 
                                                                       placeholder="ZIP" maxlength="10">
                                                            </div>
                                                            <div class="col-2">
                                                                <button type="button" class="btn btn-sm btn-outline-secondary w-100" 
                                                                        onclick="toggleManualAddress('origin', false)">
                                                                    <i class="bi bi-search"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <select class="form-select stop-reason-select" id="origin_stop_reason" name="origin_stop_reason">
                                                        <option value="">Select reason...</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="stop-info mt-1" id="origin_info" style="display: none;">
                                                <small class="text-muted"><span id="origin_address"></span></small>
                                            </div>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleManualAddress('origin', true)">
                                                    <i class="bi bi-pencil"></i> Enter New Address
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Destination (initially) -->
                                    <div class="stop-item" data-stop-type="destination" data-stop-id="destination" draggable="true">
                                        <div class="stop-header">
                                            <div class="drag-handle">
                                                <i class="bi bi-grip-vertical"></i>
                                            </div>
                                            <div class="stop-label">
                                                <span class="stop-role-badge badge bg-danger">Consignee (Destination)</span>
                                                <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-stop" onclick="removeStopItem('destination')" style="display: none;">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="stop-content">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control stop-address-search" id="destination_search" placeholder="Search consignee address...">
                                                    <input type="hidden" id="destination_key" name="destination_key" value="<?php echo $loadData['DestinationKey'] ?? ''; ?>">
                                                    
                                                    <!-- Manual Address Entry -->
                                                    <div class="manual-address-entry mt-2" id="manual_address_destination" style="display: none;">
                                                        <div class="row g-2">
                                                            <div class="col-6">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="destination_manual[quick_key]" 
                                                                       placeholder="Quick Key (6 chars)" maxlength="6">
                                                            </div>
                                                            <div class="col-6">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="destination_manual[company_name]" 
                                                                       placeholder="Company Name">
                                                            </div>
                                                            <div class="col-12">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="destination_manual[address1]" 
                                                                       placeholder="Address Line 1">
                                                            </div>
                                                            <div class="col-12">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="destination_manual[address2]" 
                                                                       placeholder="Address Line 2 (Optional)">
                                                            </div>
                                                            <div class="col-4">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="destination_manual[city]" 
                                                                       placeholder="City">
                                                            </div>
                                                            <div class="col-3">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="destination_manual[state]" 
                                                                       placeholder="State" maxlength="2">
                                                            </div>
                                                            <div class="col-3">
                                                                <input type="text" class="form-control form-control-sm" 
                                                                       name="destination_manual[zip]" 
                                                                       placeholder="ZIP" maxlength="10">
                                                            </div>
                                                            <div class="col-2">
                                                                <button type="button" class="btn btn-sm btn-outline-secondary w-100" 
                                                                        onclick="toggleManualAddress('destination', false)">
                                                                    <i class="bi bi-search"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <select class="form-select stop-reason-select" id="dest_stop_reason" name="dest_stop_reason">
                                                        <option value="">Select reason...</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="stop-info mt-1" id="destination_info" style="display: none;">
                                                <small class="text-muted"><span id="destination_address"></span></small>
                                            </div>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleManualAddress('destination', true)">
                                                    <i class="bi bi-pencil"></i> Enter New Address
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Load Details -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5><i class="bi bi-geo-alt"></i> Load Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="height" class="form-label">Height (in):</label>
                                        <input type="number" class="form-control" id="height" name="height" 
                                               value="<?php echo $loadData['Height'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="width" class="form-label">Width (in):</label>
                                        <input type="number" class="form-control" id="width" name="width" 
                                               value="<?php echo $loadData['Width'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="length" class="form-label">Length (ft):</label>
                                        <input type="number" class="form-control" id="length" name="length" 
                                               value="<?php echo $loadData['Length'] ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="load_type" class="form-label">Load Type:</label>
                                        <select class="form-select" id="load_type" name="load_type">
                                            <option value="NET" <?php echo ($loadData['LoadType'] ?? 'NET') === 'NET' ? 'selected' : ''; ?>>Net</option>
                                            <option value="GRS" <?php echo ($loadData['LoadType'] ?? '') === 'GRS' ? 'selected' : ''; ?>>Gross</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="trailer_type" class="form-label">Trailer Type:</label>
                                        <select class="form-select" id="trailer_type" name="trailer_type">
                                            <option value="FLT" <?php echo ($loadData['TrailerType'] ?? 'FLT') === 'FLT' ? 'selected' : ''; ?>>Flatbed</option>
                                            <option value="VAN" <?php echo ($loadData['TrailerType'] ?? '') === 'VAN' ? 'selected' : ''; ?>>Van</option>
                                            <option value="REF" <?php echo ($loadData['TrailerType'] ?? '') === 'REF' ? 'selected' : ''; ?>>Reefer</option>
                                            <option value="STB" <?php echo ($loadData['TrailerType'] ?? '') === 'STB' ? 'selected' : ''; ?>>Step Deck</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="priority" class="form-label">Priority:</label>
                                        <select class="form-select" id="priority" name="priority">
                                            <option value="1" <?php echo ($loadData['Priority'] ?? 1) == 1 ? 'selected' : ''; ?>>Normal</option>
                                            <option value="2" <?php echo ($loadData['Priority'] ?? 1) == 2 ? 'selected' : ''; ?>>High</option>
                                            <option value="3" <?php echo ($loadData['Priority'] ?? 1) == 3 ? 'selected' : ''; ?>>Rush</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="min_weight" class="form-label">Min Weight:</label>
                                        <input type="number" class="form-control" id="min_weight" name="min_weight" 
                                               value="<?php echo $loadData['MinWeight'] ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="high_value" name="high_value" 
                                                   <?php echo ($loadData['HighValue'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="high_value">High Value</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="split_load" name="split_load" 
                                                   <?php echo ($loadData['SplitLoad'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="split_load">Split Load</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="preloaded" name="preloaded" 
                                                   <?php echo ($loadData['Preloaded'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="preloaded">Preloaded</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="billed" name="billed" 
                                                   <?php echo ($loadData['Billed'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="billed">Billed</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Sections -->
                <div class="row mt-3">
                    <div class="col">
                        <div class="accordion" id="advancedSections">
                            <!-- Advanced Rate Details -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingRates">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#collapseRates" aria-expanded="false" aria-controls="collapseRates">
                                        <i class="bi bi-calculator me-2"></i> Advanced Rate Details
                                    </button>
                                </h2>
                                <div id="collapseRates" class="accordion-collapse collapse" 
                                     aria-labelledby="headingRates" data-bs-parent="#advancedSections">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="offer" class="form-label">Offer:</label>
                                                <input type="number" class="form-control" id="offer" name="offer" 
                                                       value="<?php echo $loadData['Offer'] ?? ''; ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="home_time" class="form-label">Home Time:</label>
                                                <input type="number" class="form-control" id="home_time" name="home_time" 
                                                       value="<?php echo $loadData['HomeTime'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-4">
                                                <label for="pta_city" class="form-label">PTA City:</label>
                                                <input type="text" class="form-control" id="pta_city" name="pta_city" 
                                                       value="<?php echo htmlspecialchars($loadData['PTACity'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="pta_state" class="form-label">PTA State:</label>
                                                <input type="text" class="form-control" id="pta_state" name="pta_state" maxlength="2"
                                                       value="<?php echo htmlspecialchars($loadData['PTAState'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="pta_zip" class="form-label">PTA Zip:</label>
                                                <input type="text" class="form-control" id="pta_zip" name="pta_zip" maxlength="5"
                                                       value="<?php echo htmlspecialchars($loadData['PTAZip'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Special Handling -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingSpecial">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#collapseSpecial" aria-expanded="false" aria-controls="collapseSpecial">
                                        <i class="bi bi-exclamation-triangle me-2"></i> Special Handling
                                    </button>
                                </h2>
                                <div id="collapseSpecial" class="accordion-collapse collapse" 
                                     aria-labelledby="headingSpecial" data-bs-parent="#advancedSections">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label for="reload_zone" class="form-label">Reload Zone:</label>
                                                <input type="number" class="form-control" id="reload_zone" name="reload_zone" 
                                                       value="<?php echo $loadData['ReloadZone'] ?? ''; ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="company_id" class="form-label">Company ID:</label>
                                                <input type="number" class="form-control" id="company_id" name="company_id" 
                                                       value="<?php echo $loadData['CompanyID'] ?? 1; ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="st_id" class="form-label">ST ID:</label>
                                                <input type="number" class="form-control" id="st_id" name="st_id" 
                                                       value="<?php echo $loadData['ST_ID'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="auto_offer" name="auto_offer" 
                                                           <?php echo ($loadData['AutoOffer'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="auto_offer">Auto Offer</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="billing_flag" name="billing_flag" 
                                                           <?php echo ($loadData['BillingFlag'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="billing_flag">Billing Flag</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="external_posting_stopped" name="external_posting_stopped" 
                                                           <?php echo ($loadData['ExternalPostingStopped'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="external_posting_stopped">Stop External Posting</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- EDI Information -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingEDI">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#collapseEDI" aria-expanded="false" aria-controls="collapseEDI">
                                        <i class="bi bi-arrow-left-right me-2"></i> EDI Information
                                    </button>
                                </h2>
                                <div id="collapseEDI" class="accordion-collapse collapse" 
                                     aria-labelledby="headingEDI" data-bs-parent="#advancedSections">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="edi_status" class="form-label">EDI Status:</label>
                                                <input type="text" class="form-control" id="edi_status" name="edi_status" maxlength="3"
                                                       value="<?php echo htmlspecialchars($loadData['EDIStatus'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" id="edi_ack" name="edi_ack" 
                                                           <?php echo ($loadData['EDIAck'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="edi_ack">EDI Acknowledged</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-chat-text"></i> Notes</h5>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" id="notes" name="notes" rows="4" 
                                          placeholder="Enter load notes..."><?php echo htmlspecialchars($loadData['Notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Load
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="clearForm()">
                            <i class="bi bi-arrow-clockwise"></i> Clear
                        </button>
                        <?php if ($loadKey): ?>
                            <button type="button" class="btn btn-info" onclick="viewLoadDetails(<?php echo $loadKey; ?>)">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Autocomplete functionality
        class TLSAutocomplete {
            constructor(inputElement, hiddenElement, apiType, onSelect = null) {
                this.input = inputElement;
                this.hidden = hiddenElement;
                this.apiType = apiType;
                this.onSelect = onSelect;
                this.resultsContainer = null;
                this.currentIndex = -1;
                this.searchTimeout = null;
                
                this.init();
            }
            
            init() {
                console.log('Initializing autocomplete for:', this.input.id, 'API type:', this.apiType);
                this.createResultsContainer();
                this.input.addEventListener('input', (e) => this.handleInput(e));
                this.input.addEventListener('keydown', (e) => this.handleKeydown(e));
                this.input.addEventListener('blur', (e) => this.handleBlur(e));
                this.input.addEventListener('focus', (e) => this.handleFocus(e));
            }
            
            createResultsContainer() {
                this.resultsContainer = document.createElement('div');
                this.resultsContainer.className = 'autocomplete-results position-absolute bg-white border rounded shadow-sm w-100';
                this.resultsContainer.style.zIndex = '1050';
                this.resultsContainer.style.maxHeight = '300px';
                this.resultsContainer.style.overflowY = 'auto';
                this.resultsContainer.style.display = 'none';
                this.input.parentNode.style.position = 'relative';
                this.input.parentNode.appendChild(this.resultsContainer);
            }
            
            handleInput(e) {
                const query = e.target.value.trim();
                console.log('Input event for:', this.input.id, 'Query:', query);
                clearTimeout(this.searchTimeout);
                
                // Special handling for customer_locations - allow empty query to show all
                if (this.apiType === 'customer_locations') {
                    if (query.length === 0) {
                        console.log('Empty query for locations, showing all');
                        this.search('*'); // Use * as wildcard to show all locations
                        return;
                    }
                } else if (query.length < 2) {
                    console.log('Query too short, hiding results');
                    this.hideResults();
                    return;
                }
                
                console.log('Setting timeout for search...');
                this.searchTimeout = setTimeout(() => {
                    console.log('Executing search for:', query);
                    this.search(query);
                }, 300);
            }
            
            handleKeydown(e) {
                const items = this.resultsContainer.querySelectorAll('.autocomplete-item');
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.currentIndex = Math.min(this.currentIndex + 1, items.length - 1);
                        this.updateSelection(items);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        this.currentIndex = Math.max(this.currentIndex - 1, -1);
                        this.updateSelection(items);
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (this.currentIndex >= 0 && items[this.currentIndex]) {
                            this.selectItem(JSON.parse(items[this.currentIndex].dataset.item));
                        }
                        break;
                    case 'Escape':
                        this.hideResults();
                        break;
                }
            }
            
            handleBlur(e) {
                setTimeout(() => this.hideResults(), 150);
            }
            
            handleFocus(e) {
                if (this.apiType === 'customer_locations') {
                    // For locations, always show all on focus (click)
                    console.log('Location field focused, showing all locations');
                    this.search('*');
                } else if (e.target.value.trim().length >= 2) {
                    this.search(e.target.value.trim());
                }
            }
            
            updateSelection(items) {
                items.forEach((item, index) => {
                    item.classList.toggle('active', index === this.currentIndex);
                });
            }
            
            search(query) {
                let url = `/tls/api/lookup.php?type=${this.apiType}&q=${encodeURIComponent(query)}`;
                
                // Add customer_id for customer_locations lookup
                if (this.apiType === 'customer_locations') {
                    const customerId = document.getElementById('customer_key').value;
                    if (!customerId) {
                        console.log('No customer selected for location search');
                        this.hideResults();
                        return;
                    }
                    url += `&customer_id=${customerId}`;
                }
                
                console.log('Fetching:', url);
                fetch(url)
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Search results:', data);
                        this.displayResults(data);
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        this.hideResults();
                    });
            }
            
            displayResults(results) {
                this.resultsContainer.innerHTML = '';
                this.currentIndex = -1;
                
                if (results.length === 0) {
                    this.hideResults();
                    return;
                }
                
                results.forEach((item, index) => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item p-2 border-bottom';
                    div.style.cursor = 'pointer';
                    div.innerHTML = item.label;
                    div.dataset.item = JSON.stringify(item);
                    
                    div.addEventListener('click', () => {
                        this.selectItem(item);
                    });
                    
                    div.addEventListener('mouseenter', () => {
                        this.currentIndex = index;
                        this.updateSelection(this.resultsContainer.querySelectorAll('.autocomplete-item'));
                    });
                    
                    this.resultsContainer.appendChild(div);
                });
                
                this.showResults();
            }
            
            selectItem(item) {
                this.input.value = item.label;
                this.hidden.value = item.id;
                this.hideResults();
                
                if (this.onSelect) {
                    this.onSelect(item);
                }
            }
            
            showResults() {
                this.resultsContainer.style.display = 'block';
            }
            
            hideResults() {
                this.resultsContainer.style.display = 'none';
            }
        }
        
        // Initialize autocomplete fields
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing autocomplete fields...');
            
            // Customer autocomplete
            console.log('Setting up customer autocomplete...');
            new TLSAutocomplete(
                document.getElementById('customer_search'),
                document.getElementById('customer_key'),
                'customers',
                function(customer) {
                    document.getElementById('customer_terms').textContent = customer.terms || 'N/A';
                    document.getElementById('customer_credit_limit').textContent = customer.credit_limit || '0.00';
                    document.getElementById('customer_ar_balance').textContent = customer.ar_balance || '0.00';
                    document.getElementById('customer_info').style.display = 'block';
                    
                    // Enable location search
                    const locationSearch = document.getElementById('location_search');
                    locationSearch.disabled = false;
                    locationSearch.placeholder = 'Search locations for ' + customer.company_name + '...';
                    
                    // Initialize location autocomplete
                    if (!locationSearch.autocompleteInitialized) {
                        new TLSAutocomplete(
                            locationSearch,
                            document.getElementById('location_code'),
                            'customer_locations',
                            function(location) {
                                // Location selected callback if needed
                            }
                        );
                        locationSearch.autocompleteInitialized = true;
                    }
                }
            );
            
            // Origin autocomplete
            new TLSAutocomplete(
                document.getElementById('origin_search'),
                document.getElementById('origin_key'),
                'addresses',
                function(address) {
                    let addressDisplay = '';
                    if (address.addr1) addressDisplay += address.addr1;
                    if (address.addr2) addressDisplay += (addressDisplay ? ', ' : '') + address.addr2;
                    if (address.city || address.state || address.zip) {
                        addressDisplay += (addressDisplay ? ', ' : '') + 
                                        address.city + 
                                        (address.state ? ', ' + address.state : '') + 
                                        (address.zip ? ' ' + address.zip : '');
                    }
                    document.getElementById('origin_address').textContent = addressDisplay;
                    document.getElementById('origin_info').style.display = 'block';
                }
            );
            
            // Destination autocomplete
            new TLSAutocomplete(
                document.getElementById('destination_search'),
                document.getElementById('destination_key'),
                'addresses',
                function(address) {
                    let addressDisplay = '';
                    if (address.addr1) addressDisplay += address.addr1;
                    if (address.addr2) addressDisplay += (addressDisplay ? ', ' : '') + address.addr2;
                    if (address.city || address.state || address.zip) {
                        addressDisplay += (addressDisplay ? ', ' : '') + 
                                        address.city + 
                                        (address.state ? ', ' + address.state : '') + 
                                        (address.zip ? ' ' + address.zip : '');
                    }
                    document.getElementById('destination_address').textContent = addressDisplay;
                    document.getElementById('destination_info').style.display = 'block';
                }
            );
            
            // Commodity autocomplete
            new TLSAutocomplete(
                document.getElementById('commodity'),
                null, // No hidden field needed for commodity
                'commodities'
            );

            // Initialize stop reasons for origin and destination
            loadStopReasons('origin_stop_reason', '<?php echo $loadData['OriginStopReason'] ?? 'CL'; ?>');
            loadStopReasons('dest_stop_reason', '<?php echo $loadData['DestStopReason'] ?? 'CU'; ?>');

            // Tender autocomplete
            new TLSAutocomplete(
                document.getElementById('tender_search'),
                document.getElementById('tender_key'),
                'tenders',
                function(tender) {
                    document.getElementById('tender_details').textContent = 
                        tender.tender_name + ' - ' + tender.city + ', ' + tender.state;
                    document.getElementById('tender_info').style.display = 'block';
                }
            );
        });
        
        // Additional utility functions
        function newLoad() {
            window.location.href = '/tls/dispatch/load-entry.php';
        }

        function searchLoad() {
            const loadNumber = prompt('Enter Load Number:');
            if (loadNumber) {
                window.location.href = '/tls/dispatch/load-entry.php?loadkey=' + encodeURIComponent(loadNumber);
            }
        }

        function deleteLoad() {
            if (confirm('Are you sure you want to delete this load?')) {
                // TODO: Implement delete functionality
                alert('Delete functionality not yet implemented');
            }
        }

        function clearForm() {
            if (confirm('Clear all form data?')) {
                document.getElementById('loadForm').reset();
            }
        }

        function viewLoadDetails(loadKey) {
            // TODO: Implement load details view
            alert('Load details view not yet implemented. Load Key: ' + loadKey);
        }

        // Auto-calculate freight charge based on rate and rate code
        document.getElementById('rate').addEventListener('change', calculateFreightCharge);
        document.getElementById('rate_code').addEventListener('change', calculateFreightCharge);
        document.getElementById('miles').addEventListener('change', calculateFreightCharge);
        document.getElementById('weight').addEventListener('change', calculateFreightCharge);
        document.getElementById('min_weight').addEventListener('change', calculateFreightCharge);

        function calculateFreightCharge() {
            const rate = parseFloat(document.getElementById('rate').value) || 0;
            const rateCode = document.getElementById('rate_code').value;
            const miles = parseInt(document.getElementById('miles').value) || 0;
            const weight = parseInt(document.getElementById('weight').value) || 0;
            const minWeight = parseInt(document.getElementById('min_weight').value) || 0;
            
            let freightCharge = 0;
            const effectiveWeight = Math.max(weight, minWeight);
            
            switch(rateCode) {
                case 'M':
                case 'PM':
                    freightCharge = rate * miles;
                    break;
                case 'C':
                    freightCharge = rate * (effectiveWeight / 100);
                    break;
                case 'NT':
                    freightCharge = rate * (effectiveWeight / 2000);
                    break;
                default: // 'F'
                    freightCharge = rate;
            }
            
            // Display calculated freight charge (you could add a readonly field for this)
            console.log('Calculated Freight Charge: $' + freightCharge.toFixed(2));
        }

        // Stops Management with Drag & Drop
        let stopCounter = 0;
        let draggedElement = null;

        function loadStopReasons(selectId, selectedValue = '') {
            fetch('/tls/api/lookup.php?type=stop_reasons&q=')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById(selectId);
                    select.innerHTML = '<option value="">Select reason...</option>';
                    
                    data.forEach(reason => {
                        const option = document.createElement('option');
                        option.value = reason.code;
                        option.textContent = reason.code + ' - ' + reason.description;
                        if (reason.code === selectedValue) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading stop reasons:', error);
                });
        }

        function addStop() {
            stopCounter++;
            const container = document.getElementById('sortable_stops_container');
            const destinationElement = container.querySelector('[data-stop-type="destination"]');
            
            const stopDiv = document.createElement('div');
            stopDiv.className = 'stop-item';
            stopDiv.setAttribute('data-stop-type', 'intermediate');
            stopDiv.setAttribute('data-stop-id', `stop_${stopCounter}`);
            stopDiv.setAttribute('draggable', 'true');
            
            stopDiv.innerHTML = `
                <div class="stop-header">
                    <div class="drag-handle">
                        <i class="bi bi-grip-vertical"></i>
                    </div>
                    <div class="stop-label">
                        <span class="stop-role-badge badge bg-warning">Intermediate Stop</span>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-stop" onclick="removeStopItem('stop_${stopCounter}')">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                <div class="stop-content">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="text" class="form-control stop-address-search" 
                                   id="stop_search_${stopCounter}" 
                                   placeholder="Search intermediate stop address...">
                            <input type="hidden" id="stop_key_${stopCounter}">
                            
                            <!-- Manual Address Entry -->
                            <div class="manual-address-entry mt-2" id="manual_address_stop_${stopCounter}" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" 
                                               placeholder="Quick Key (6 chars)" maxlength="6">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm" 
                                               placeholder="Company Name">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control form-control-sm" 
                                               placeholder="Address Line 1">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control form-control-sm" 
                                               placeholder="Address Line 2 (Optional)">
                                    </div>
                                    <div class="col-4">
                                        <input type="text" class="form-control form-control-sm" 
                                               placeholder="City">
                                    </div>
                                    <div class="col-3">
                                        <input type="text" class="form-control form-control-sm" 
                                               placeholder="State" maxlength="2">
                                    </div>
                                    <div class="col-3">
                                        <input type="text" class="form-control form-control-sm" 
                                               placeholder="ZIP" maxlength="10">
                                    </div>
                                    <div class="col-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" 
                                                onclick="toggleManualAddress('stop_${stopCounter}', false)">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select stop-reason-select" 
                                    id="stop_reason_${stopCounter}">
                                <option value="">Select reason...</option>
                            </select>
                        </div>
                    </div>
                    <div class="stop-info mt-1" id="stop_info_${stopCounter}" style="display: none;">
                        <small class="text-muted"><span id="stop_address_${stopCounter}"></span></small>
                    </div>
                    <div class="mt-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleManualAddress('stop_${stopCounter}', true)">
                            <i class="bi bi-pencil"></i> Enter New Address
                        </button>
                    </div>
                </div>
            `;
            
            // Insert before destination
            container.insertBefore(stopDiv, destinationElement);
            
            // Initialize drag events
            initializeDragEvents(stopDiv);
            
            // Initialize autocomplete for the new stop
            initializeStopAutocomplete(stopCounter);
            
            // Load stop reasons for the new stop
            loadStopReasons(`stop_reason_${stopCounter}`);
            
            // Update all stop roles after adding
            updateStopRoles();
        }

        function removeStopItem(stopId) {
            const stopElement = document.querySelector(`[data-stop-id="${stopId}"]`);
            if (stopElement) {
                stopElement.remove();
                updateStopRoles();
            }
        }

        function toggleManualAddress(stopId, showManual) {
            const searchField = document.getElementById(`${stopId.replace('stop_', '')}_search`) || 
                               document.getElementById(`stop_search_${stopId.replace('stop_', '')}`);
            const manualDiv = document.getElementById(`manual_address_${stopId}`);
            const stopInfoDiv = document.getElementById(`stop_info_${stopId.replace('stop_', '')}`);
            
            if (showManual) {
                // Store current search value to allow undo
                searchField.dataset.previousValue = searchField.value;
                
                searchField.style.display = 'none';
                manualDiv.style.display = 'block';
                if (stopInfoDiv) stopInfoDiv.style.display = 'none';
                // Clear the search field and hidden key
                searchField.value = '';
                const hiddenKey = document.getElementById(`${stopId.replace('stop_', '')}_key`) || 
                                 document.getElementById(`stop_key_${stopId.replace('stop_', '')}`);
                if (hiddenKey) hiddenKey.value = '';
            } else {
                searchField.style.display = 'block';
                manualDiv.style.display = 'none';
                // Clear manual entry fields
                const manualInputs = manualDiv.querySelectorAll('input');
                manualInputs.forEach(input => input.value = '');
                
                // Restore previous search value (undo capability)
                if (searchField.dataset.previousValue) {
                    searchField.value = searchField.dataset.previousValue;
                    searchField.dispatchEvent(new Event('input')); // Trigger autocomplete
                }
            }
        }

        function toggleManualTender(showManual) {
            const searchField = document.getElementById('tender_search');
            const manualDiv = document.getElementById('manual_tender_entry');
            const tenderInfoDiv = document.getElementById('tender_info');
            
            if (showManual) {
                // Store current search value to allow undo
                searchField.dataset.previousValue = searchField.value;
                
                searchField.style.display = 'none';
                manualDiv.style.display = 'block';
                tenderInfoDiv.style.display = 'none';
                // Clear the search field and hidden key
                searchField.value = '';
                document.getElementById('tender_key').value = '';
            } else {
                searchField.style.display = 'block';
                manualDiv.style.display = 'none';
                // Clear manual entry fields
                const manualInputs = manualDiv.querySelectorAll('input');
                manualInputs.forEach(input => input.value = '');
                
                // Restore previous search value (undo capability)
                if (searchField.dataset.previousValue) {
                    searchField.value = searchField.dataset.previousValue;
                    searchField.dispatchEvent(new Event('input')); // Trigger autocomplete
                }
            }
        }

        function initializeStopAutocomplete(stopNumber) {
            const searchField = document.getElementById(`stop_search_${stopNumber}`);
            const hiddenField = document.getElementById(`stop_key_${stopNumber}`);
            
            new TLSAutocomplete(
                searchField,
                hiddenField,
                'addresses',
                function(address) {
                    let addressDisplay = '';
                    if (address.addr1) addressDisplay += address.addr1;
                    if (address.addr2) addressDisplay += (addressDisplay ? ', ' : '') + address.addr2;
                    if (address.city || address.state || address.zip) {
                        addressDisplay += (addressDisplay ? ', ' : '') + 
                                        address.city + 
                                        (address.state ? ', ' + address.state : '') + 
                                        (address.zip ? ' ' + address.zip : '');
                    }
                    document.getElementById(`stop_address_${stopNumber}`).textContent = addressDisplay;
                    document.getElementById(`stop_info_${stopNumber}`).style.display = 'block';
                }
            );
        }

        // Drag and Drop Functionality
        function initializeDragEvents(element) {
            element.addEventListener('dragstart', handleDragStart);
            element.addEventListener('dragover', handleDragOver);
            element.addEventListener('drop', handleDrop);
            element.addEventListener('dragend', handleDragEnd);
        }

        function handleDragStart(e) {
            draggedElement = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.outerHTML);
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            if (draggedElement !== this) {
                const container = document.getElementById('sortable_stops_container');
                const draggedIndex = Array.from(container.children).indexOf(draggedElement);
                const targetIndex = Array.from(container.children).indexOf(this);
                
                if (draggedIndex < targetIndex) {
                    container.insertBefore(draggedElement, this.nextSibling);
                } else {
                    container.insertBefore(draggedElement, this);
                }
                
                updateStopRoles();
            }
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
            // Remove drag-over class from all elements
            document.querySelectorAll('.stop-item').forEach(item => {
                item.classList.remove('drag-over');
            });
        }

        function updateStopRoles() {
            const container = document.getElementById('sortable_stops_container');
            const stops = Array.from(container.children);
            
            stops.forEach((stop, index) => {
                const badge = stop.querySelector('.stop-role-badge');
                const removeBtn = stop.querySelector('.remove-stop');
                
                if (index === 0) {
                    // First stop is always shipper (origin)
                    stop.setAttribute('data-stop-type', 'origin');
                    badge.className = 'stop-role-badge badge bg-success';
                    badge.textContent = 'Shipper (Origin)';
                    removeBtn.style.display = 'none'; // Can't remove origin
                } else if (index === stops.length - 1) {
                    // Last stop is always consignee (destination)  
                    stop.setAttribute('data-stop-type', 'destination');
                    badge.className = 'stop-role-badge badge bg-danger';
                    badge.textContent = 'Consignee (Destination)';
                    removeBtn.style.display = 'none'; // Can't remove destination
                } else {
                    // Middle stops are intermediate
                    stop.setAttribute('data-stop-type', 'intermediate');
                    badge.className = 'stop-role-badge badge bg-warning';
                    badge.textContent = 'Intermediate Stop';
                    removeBtn.style.display = 'inline-block'; // Can remove intermediate stops
                }
                
                // Update placeholder text based on role
                const searchField = stop.querySelector('.stop-address-search');
                if (searchField) {
                    if (index === 0) {
                        searchField.placeholder = 'Search shipper address...';
                    } else if (index === stops.length - 1) {
                        searchField.placeholder = 'Search consignee address...';
                    } else {
                        searchField.placeholder = 'Search intermediate stop address...';
                    }
                }
            });
        }

        // Initialize drag events on existing elements
        document.addEventListener('DOMContentLoaded', function() {
            const existingStops = document.querySelectorAll('.stop-item');
            existingStops.forEach(stop => {
                initializeDragEvents(stop);
            });
        });

        // Update form submission to include stops data
        function updateStopsData() {
            const form = document.getElementById('loadForm');
            
            // Remove existing stop hidden inputs
            const existingStopInputs = form.querySelectorAll('input[name^="stops_data"]');
            existingStopInputs.forEach(input => input.remove());
            
            // Collect all stops data in order
            const container = document.getElementById('sortable_stops_container');
            const stops = [];
            const stopElements = Array.from(container.children);
            
            stopElements.forEach((stopElement, index) => {
                const stopId = stopElement.getAttribute('data-stop-id');
                const stopType = stopElement.getAttribute('data-stop-type');
                
                let keyField, searchField, reasonField, manualDiv;
                
                if (stopType === 'origin') {
                    keyField = document.getElementById('origin_key');
                    searchField = document.getElementById('origin_search');
                    reasonField = document.getElementById('origin_stop_reason');
                    manualDiv = document.getElementById('manual_address_origin');
                } else if (stopType === 'destination') {
                    keyField = document.getElementById('destination_key');
                    searchField = document.getElementById('destination_search');
                    reasonField = document.getElementById('dest_stop_reason');
                    manualDiv = document.getElementById('manual_address_destination');
                } else {
                    const stopNumber = stopId.replace('stop_', '');
                    keyField = document.getElementById(`stop_key_${stopNumber}`);
                    searchField = document.getElementById(`stop_search_${stopNumber}`);
                    reasonField = document.getElementById(`stop_reason_${stopNumber}`);
                    manualDiv = document.getElementById(`manual_address_${stopId}`);
                }
                
                const stop = {
                    sequence: index + 1,
                    type: stopType,
                    key: keyField ? keyField.value : '',
                    reason: reasonField ? reasonField.value : '',
                    search_text: searchField ? searchField.value : ''
                };
                
                // Check for manual address entry
                if (manualDiv && manualDiv.style.display !== 'none') {
                    const manualInputs = manualDiv.querySelectorAll('input');
                    stop.manual = {
                        company_name: manualInputs[0] ? manualInputs[0].value : '',
                        address1: manualInputs[1] ? manualInputs[1].value : '',
                        address2: manualInputs[2] ? manualInputs[2].value : '',
                        city: manualInputs[3] ? manualInputs[3].value : '',
                        state: manualInputs[4] ? manualInputs[4].value : '',
                        zip: manualInputs[5] ? manualInputs[5].value : ''
                    };
                }
                
                stops.push(stop);
            });
            
            // Add stops data as hidden input
            if (stops.length > 0) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'stops_data';
                hiddenInput.value = JSON.stringify(stops);
                form.appendChild(hiddenInput);
            }
            
            // Also set origin_key and destination_key based on current order
            const originStop = stops.find(s => s.type === 'origin');
            const destinationStop = stops.find(s => s.type === 'destination');
            
            if (originStop) {
                document.getElementById('origin_key').value = originStop.key;
            }
            if (destinationStop) {
                document.getElementById('destination_key').value = destinationStop.key;
            }
        }

        // Update the form submission to include stops data
        document.getElementById('loadForm').addEventListener('submit', function(e) {
            updateStopsData();
        });
    </script>
</body>
</html>